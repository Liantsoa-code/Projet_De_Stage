<?php

namespace App\Support;

use App\Models\Demande;
use App\Models\Document;
use App\Models\DocumentRequis;
use App\Models\PeriodeSensible;
use App\Models\TypeDemande;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Opérations sur le dossier d'une demande, partagées entre la création et la modification :
 * analyse des périodes sensibles, enregistrement des pièces justificatives et reprise
 * des pièces réutilisables déjà déposées par le demandeur.
 */
class DemandeDossier
{
    /** Formats acceptés pour les pièces justificatives. */
    public const EXTENSIONS_AUTORISEES = 'pdf,jpg,jpeg,png,doc,docx,odt';

    /**
     * Phase 5.2 : Moteur d'Analyse — chevauchements entre la période demandée et les périodes sensibles du site.
     *
     * @return array{resultats: array<int, array{type: string, message: string}>, bloquant: bool}
     */
    public static function analysePeriodes(int $siteId, string $dateDebut, string $dateFin): array
    {
        // Deux intervalles se chevauchent si chacun commence avant la fin de l'autre.
        $periodes = PeriodeSensible::where('site_id', $siteId)
            ->where('date_debut', '<=', $dateFin)
            ->where('date_fin', '>=', $dateDebut)
            ->orderBy('date_debut')
            ->get();

        $resultats = $periodes->map(fn (PeriodeSensible $periode) => [
            'type' => $periode->niveau_restriction,
            'message' => "Attention : Chevauchement avec la période sensible '{$periode->description}' (du {$periode->date_debut->format('d/m/Y')} au {$periode->date_fin->format('d/m/Y')}).",
        ])->values()->all();

        return [
            'resultats' => $resultats,
            'bloquant' => $periodes->contains('niveau_restriction', 'bloquant'),
        ];
    }

    /**
     * Pièces réutilisables (pièce d'identité, assurance…) que le demandeur a déjà déposées, indexées par id de pièce requise.
     * Pour chaque pièce marquée réutilisable, tous types confondus, on retient le dernier fichier encore présent
     * déposé sous le même libellé (casse ignorée) dans une autre de ses demandes.
     *
     * @return Collection<int, Document>
     */
    public static function piecesReutilisables(int $userId, ?int $saufDemandeId = null): Collection
    {
        $pieces = DocumentRequis::where('reutilisable', true)->get(['id', 'libelle']);
        if ($pieces->isEmpty()) {
            return collect();
        }

        // Libellé actuel de la pièce, ou celui du jour du dépôt si elle a été retirée du type depuis
        $cle = fn (Document $d) => self::cleLibelle($d->documentRequis?->libelle ?? $d->type_document);

        $derniers = Document::with(['documentRequis:id,libelle', 'demande:id,created_at'])
            ->whereHas('demande', fn ($q) => $q->where('user_id', $userId)
                ->when($saufDemandeId, fn ($q) => $q->whereKeyNot($saufDemandeId)))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Document $d) => Storage::exists($d->chemin))
            ->groupBy($cle)
            ->map->first();

        // Collection simple : only() et get() y portent sur l'id de la pièce requise, pas sur celui du fichier
        return $pieces->toBase()
            ->mapWithKeys(fn (DocumentRequis $p) => [$p->id => $derniers->get(self::cleLibelle($p->libelle))])
            ->filter();
    }

    /**
     * Enregistre les fichiers envoyés (doc_{id de la pièce requise}).
     * Un nouveau fichier remplace l'ancien pour la même pièce ; les pièces qui ne
     * correspondent plus au type d'activité (changement de type, pièce retirée) sont supprimées.
     * Une pièce sans fichier envoyé ni déjà jointe est reprise de $reprises (DemandeRequest::piecesReprises).
     *
     * @param  Collection<int, Document>|null  $reprises  fichiers d'autres dossiers du demandeur, indexés par id de pièce requise
     */
    public static function syncDocuments(Demande $demande, Request $request, TypeDemande $type, ?Collection $reprises = null): void
    {
        $docsRequis = $type->documentsRequis;

        $demande->documents()
            ->where(fn ($q) => $q->whereNull('document_requis_id')->orWhereNotIn('document_requis_id', $docsRequis->modelKeys()))
            ->get()
            ->each(fn (Document $document) => self::deleteDocument($document));

        foreach ($docsRequis as $docRequis) {
            $key = 'doc_' . $docRequis->id;
            if (! $request->hasFile($key)) {
                $source = $reprises?->get($docRequis->id);
                if ($source && ! $demande->documents()->where('document_requis_id', $docRequis->id)->exists()) {
                    self::reprendreDocument($demande, $docRequis, $source);
                }
                continue;
            }

            $demande->documents()
                ->where('document_requis_id', $docRequis->id)
                ->get()
                ->each(fn (Document $document) => self::deleteDocument($document));

            $file = $request->file($key);

            Document::create([
                'demande_id' => $demande->id,
                'document_requis_id' => $docRequis->id,
                'nom_fichier' => $file->getClientOriginalName(),
                'chemin' => $file->store('demandes/' . $demande->id), // Stockage local sécurisé
                'type_document' => $docRequis->libelle,
            ]);
        }
    }

    /**
     * Supprime définitivement une demande et ses fichiers (brouillon abandonné).
     */
    public static function deleteDemande(Demande $demande): void
    {
        Storage::deleteDirectory('demandes/' . $demande->id);
        $demande->delete(); // documents, historique et notifications suivent en cascade
    }

    /**
     * Supprime un compte et les fichiers de toutes ses demandes.
     * Les fichiers ne sont effacés qu'une fois la suppression en base réussie.
     */
    public static function deleteUser(User $user): void
    {
        $demandeIds = $user->demandes()->pluck('id');

        $user->delete(); // demandes, documents, notifications suivent en cascade ; ses décisions restent sans auteur

        $demandeIds->each(fn (int $id) => Storage::deleteDirectory('demandes/' . $id));
    }

    /**
     * Copie le fichier d'un autre dossier dans celui-ci : chaque dossier garde ses propres fichiers,
     * la suppression d'un ancien brouillon ne touche donc pas la pièce reprise.
     */
    private static function reprendreDocument(Demande $demande, DocumentRequis $docRequis, Document $source): void
    {
        $extension = pathinfo($source->chemin, PATHINFO_EXTENSION);
        $chemin = 'demandes/' . $demande->id . '/' . Str::random(40) . ($extension ? '.' . $extension : '');

        if (! Storage::copy($source->chemin, $chemin)) {
            return;
        }

        Document::create([
            'demande_id' => $demande->id,
            'document_requis_id' => $docRequis->id,
            'nom_fichier' => $source->nom_fichier,
            'chemin' => $chemin,
            'type_document' => $docRequis->libelle,
        ]);
    }

    private static function cleLibelle(string $libelle): string
    {
        return mb_strtolower(trim($libelle));
    }

    private static function deleteDocument(Document $document): void
    {
        Storage::delete($document->chemin);
        $document->delete();
    }
}

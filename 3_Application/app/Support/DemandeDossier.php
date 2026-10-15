<?php

namespace App\Support;

use App\Models\Demande;
use App\Models\Document;
use App\Models\PeriodeSensible;
use App\Models\TypeDemande;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Opérations sur le dossier d'une demande, partagées entre la création et la modification :
 * analyse des périodes sensibles et enregistrement des pièces justificatives.
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
     * Enregistre les fichiers envoyés (doc_{id de la pièce requise}).
     * Un nouveau fichier remplace l'ancien pour la même pièce ; les pièces qui ne
     * correspondent plus au type d'activité (changement de type, pièce retirée) sont supprimées.
     */
    public static function syncDocuments(Demande $demande, Request $request, TypeDemande $type): void
    {
        $docsRequis = $type->documentsRequis;

        $demande->documents()
            ->where(fn ($q) => $q->whereNull('document_requis_id')->orWhereNotIn('document_requis_id', $docsRequis->modelKeys()))
            ->get()
            ->each(fn (Document $document) => self::deleteDocument($document));

        foreach ($docsRequis as $docRequis) {
            $key = 'doc_' . $docRequis->id;
            if (! $request->hasFile($key)) {
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

    private static function deleteDocument(Document $document): void
    {
        Storage::delete($document->chemin);
        $document->delete();
    }
}

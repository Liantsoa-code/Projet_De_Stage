<?php

namespace App\Support;

use App\Models\Demande;
use App\Models\DocumentOfficiel;
use App\Models\HistoriqueStatut;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * Autorisation et cahier des charges : textes par défaut, génération avant validation,
 * signature du validateur et rendu PDF. Seuls les textes sont modifiables ; les données
 * du dossier (demandeur, aire protégée, dates, équipe, référence) viennent toujours de la demande.
 */
class DocumentsOfficiels
{
    /** Champs modifiables de chaque document, avec leur longueur maximale. */
    public const CHAMPS = [
        'autorisation' => [
            'introduction' => 1000,
            'objet' => 5000,
            'conditions' => 3000,
            'reserve' => 1000,
            'signataire' => 255,
        ],
        'cahier_charges' => [
            'regles' => 5000,
            'engagement' => 3000,
        ],
    ];

    /** Champs qui peuvent rester vides (listes facultatives). */
    private const FACULTATIFS = ['conditions', 'regles'];

    public const VUES = [
        'autorisation' => 'pdf.autorisation',
        'cahier_charges' => 'pdf.cahier_charges',
    ];

    public const FICHIERS = [
        'autorisation' => 'autorisation',
        'cahier_charges' => 'cahier_des_charges',
    ];

    /** Textes fixes proposés à la génération. */
    public const TEXTES = [
        'introduction' => 'Nous soussignés, la Fondation FANAMBY, autorisons par la présente :',
        'reserve' => "Cette autorisation est délivrée sous réserve du strict respect du cahier des charges qui l'accompagne. Toute infraction entraînera une annulation immédiate.",
        'signataire' => 'La Direction FANAMBY',
        'engagement' => "Le demandeur certifie avoir pris connaissance des règlements internes de l'aire protégée et s'engage à respecter la faune, la flore et les communautés locales. Tout non-respect de ce cahier des charges entraînera des poursuites.",
    ];

    /**
     * Texte proposé à la génération, repris du dossier et du type d'activité.
     *
     * @return array<string, string>
     */
    public static function defauts(Demande $demande, string $type): array
    {
        if ($type === 'autorisation') {
            return [
                'introduction' => self::TEXTES['introduction'],
                'objet' => (string) $demande->description,
                'conditions' => '',
                'reserve' => self::TEXTES['reserve'],
                'signataire' => self::TEXTES['signataire'],
            ];
        }

        $demande->loadMissing('typeDemande.reglesSpecifiques');

        return [
            'regles' => $demande->typeDemande->reglesSpecifiques->pluck('libelle')->implode("\n"),
            'engagement' => self::TEXTES['engagement'],
        ];
    }

    /**
     * Texte à imprimer : celui enregistré à la génération, sinon le texte par défaut
     * (dossiers validés avant l'étape de génération).
     *
     * @return array<string, string>
     */
    public static function contenu(Demande $demande, string $type): array
    {
        $enregistre = $demande->documentsOfficiels()->where('type', $type)->first()?->contenu;

        return array_merge(self::defauts($demande, $type), $enregistre ?? []);
    }

    /**
     * Prépare les deux documents s'ils n'existent pas encore. Un document déjà généré
     * garde ses modifications.
     */
    public static function generer(Demande $demande, User $auteur): void
    {
        foreach (array_keys(self::CHAMPS) as $type) {
            DocumentOfficiel::firstOrCreate(
                ['demande_id' => $demande->id, 'type' => $type],
                ['contenu' => self::defauts($demande, $type), 'genere_par' => $auteur->id],
            );
        }
    }

    public static function sontGeneres(Demande $demande): bool
    {
        return $demande->documentsOfficiels()->count() === count(self::CHAMPS);
    }

    /**
     * Documents en cours de relecture pour la page d'instruction, ou null s'ils ne sont pas générés.
     */
    public static function pourEdition(Demande $demande): ?array
    {
        $documents = $demande->documentsOfficiels()->with('auteur:id,name')->get()->keyBy('type');

        if ($documents->count() !== count(self::CHAMPS)) {
            return null;
        }

        $premier = $documents->sortBy('created_at')->first();

        return [
            'contenu' => $documents->map(fn (DocumentOfficiel $d) => array_merge(self::defauts($demande, $d->type), $d->contenu))->all(),
            'genere_par' => $premier->auteur?->name,
            'genere_le' => $premier->created_at,
            'modifie_le' => $documents->max('updated_at'),
        ];
    }

    /**
     * Règles de validation du formulaire de modification des documents.
     *
     * @return array<string, string>
     */
    public static function regles(): array
    {
        $regles = [];

        foreach (self::CHAMPS as $type => $champs) {
            $regles[$type] = 'required|array';
            foreach ($champs as $champ => $max) {
                $presence = in_array($champ, self::FACULTATIFS, true) ? 'nullable' : 'required';
                $regles["{$type}.{$champ}"] = "{$presence}|string|max:{$max}";
            }
        }

        return $regles;
    }

    public static function enregistrer(Demande $demande, array $donnees): void
    {
        foreach (self::CHAMPS as $type => $champs) {
            $contenu = collect($champs)->map(fn ($max, $champ) => trim((string) ($donnees[$type][$champ] ?? '')))->all();
            $demande->documentsOfficiels()->where('type', $type)->first()?->update(['contenu' => $contenu]);
        }
    }

    /**
     * La signature est le nom du validateur retapé : casse, accents et espaces ne comptent pas.
     */
    public static function signatureValide(User $user, ?string $saisie): bool
    {
        $normaliser = fn (?string $nom) => Str::lower(Str::ascii(Str::squish((string) $nom)));

        return $saisie !== null && $normaliser($saisie) !== '' && $normaliser($saisie) === $normaliser($user->name);
    }

    /**
     * Lignes non vides d'un texte saisi « une par ligne » (règles, conditions particulières).
     *
     * @return array<int, string>
     */
    public static function lignes(?string $texte): array
    {
        return collect(preg_split('/\R/u', (string) $texte))
            ->map(fn ($ligne) => trim($ligne))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * PDF du document. En projet (avant validation), il est marqué comme tel et non signé.
     */
    public static function pdf(Demande $demande, string $type, bool $projet = false)
    {
        return Pdf::loadView(self::VUES[$type], self::donneesVue($demande, $type, $projet));
    }

    /**
     * Variables de la vue PDF : la demande, le texte à imprimer et, une fois le dossier validé,
     * la décision signée (nom du validateur et date).
     */
    public static function donneesVue(Demande $demande, string $type, bool $projet = false): array
    {
        $demande->loadMissing('user', 'site', 'typeDemande');

        $validation = $projet ? null : HistoriqueStatut::with('user:id,name')
            ->where('demande_id', $demande->id)
            ->whereHas('nouveauStatut', fn ($q) => $q->where('code', 'validee'))
            ->latest('date_changement')
            ->first();

        return [
            'demande' => $demande,
            'contenu' => self::contenu($demande, $type),
            'projet' => $projet,
            'validation' => $validation,
        ];
    }
}

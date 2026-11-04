<?php

namespace App\Support;

use App\Models\Demande;
use App\Models\DocumentOfficiel;
use App\Models\HistoriqueStatut;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\PhpWord;

/**
 * Autorisation, cahier des charges et convention de collaboration : textes par défaut, génération
 * avant validation, signature du validateur et rendu PDF ou Word. Seuls les textes sont modifiables ;
 * les données du dossier (demandeur, aire protégée, dates, équipe, référence) viennent toujours de la demande.
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
        // Accord entre Fanamby et le bénéficiaire, un champ par article (modèle : convention papier de Fanamby)
        'convention' => [
            'partie_fanamby' => 1500,
            'objet' => 3000,
            'avis' => 1500,
            'modalites' => 3000,
            'redevance_jour' => 15,
            'paiement' => 1000,
            'affectation' => 3000,
            'obligations_beneficiaire' => 3000,
            'obligations_fanamby' => 2000,
            'responsabilite' => 2000,
            'litiges' => 1500,
            'entree_vigueur' => 1500,
            'lieu' => 100,
            'signataire' => 255,
        ],
    ];

    /** Champs qui peuvent rester vides (listes facultatives, redevance pas encore fixée). */
    private const FACULTATIFS = ['conditions', 'regles', 'modalites', 'redevance_jour'];

    /** Champs numériques : la redevance de la convention, en ariary par jour d'activités. */
    private const NUMERIQUES = ['redevance_jour'];

    public const VUES = [
        'autorisation' => 'pdf.autorisation',
        'cahier_charges' => 'pdf.cahier_charges',
        'convention' => 'pdf.convention',
    ];

    public const FICHIERS = [
        'autorisation' => 'autorisation',
        'cahier_charges' => 'cahier_des_charges',
        'convention' => 'convention_de_collaboration',
    ];

    /** Textes fixes proposés à la génération. */
    public const TEXTES = [
        'introduction' => "Nous soussignés, l'Association FANAMBY, autorisons par la présente :",
        'reserve' => "Cette autorisation est délivrée sous réserve du strict respect du cahier des charges qui l'accompagne. Toute infraction entraînera une annulation immédiate.",
        'signataire' => 'La Direction FANAMBY',
        'lieu' => 'Antananarivo',
    ];

    /**
     * Texte proposé à la génération, repris du dossier et du type de projet : l'objet de la mission
     * reprend les objectifs du projet (à défaut le synopsis), les conditions particulières celles proposées
     * par l'analyse de la demande, l'engagement ceux du formulaire de demande.
     *
     * @return array<string, string>
     */
    public static function defauts(Demande $demande, string $type): array
    {
        if ($type === 'autorisation') {
            return [
                'introduction' => self::TEXTES['introduction'],
                'objet' => (string) ($demande->objectifs ?: $demande->description),
                'conditions' => mb_substr(implode("\n", AnalyseDemande::conditions($demande->resultats_analyse)), 0, self::CHAMPS['autorisation']['conditions']),
                'reserve' => self::TEXTES['reserve'],
                'signataire' => self::TEXTES['signataire'],
            ];
        }

        if ($type === 'convention') {
            return self::defautsConvention($demande);
        }

        $demande->loadMissing('typeDemande.reglesSpecifiques');

        return [
            'regles' => $demande->typeDemande->reglesSpecifiques->pluck('libelle')->implode("\n"),
            'engagement' => self::engagement(),
        ];
    }

    /**
     * Textes proposés pour la convention de collaboration, repris de la convention papier de Fanamby
     * et du dossier (aire protégée, projet, objectifs, référence). La redevance reste à fixer par le validateur.
     *
     * @return array<string, string>
     */
    private static function defautsConvention(Demande $demande): array
    {
        $demande->loadMissing('site', 'typeDemande');
        $site = $demande->site->nom;
        $projet = $demande->titre ? "« {$demande->titre} » ({$demande->typeProjet()})" : $demande->typeProjet();
        $objectifs = trim((string) ($demande->objectifs ?: $demande->description));

        return [
            'partie_fanamby' => "Association Fanamby, organisme gestionnaire de l'aire protégée de {$site}, sise au Lot II M 63 AR Bis Antsakaviro, 101 Antananarivo, Madagascar, Tél. +261 20 78 636 61, représentée par son Directeur Exécutif, ci-après désignée « Fanamby »",
            'objet' => "La présente convention a pour objet de définir les conditions de collaboration entre les Parties dans le cadre de la réalisation du projet {$projet} dans l'aire protégée de {$site}, aux dates précisées à l'article 3."
                . ($objectifs !== '' ? "\nObjectifs du projet : {$objectifs}" : ''),
            'avis' => "Par la signature de la présente convention, les Parties reconnaissent que Fanamby a accordé son avis favorable à la réalisation du projet (dossier {$demande->reference}). La présente convention vaut cadre officiel de collaboration entre Fanamby et le Bénéficiaire pour l'exécution des activités autorisées aux dates prévues.",
            'modalites' => implode("\n", [
                "installation et repérages techniques à partir de l'arrivée sur le terrain",
                'activités décrites dans le dossier de demande, selon le planning transmis à Fanamby',
                "nettoyage intégral du site après l'intervention",
            ]),
            'redevance_jour' => '',
            'paiement' => 'Le paiement devra être effectué selon les modalités communiquées par Fanamby avant le démarrage effectif des activités.',
            'affectation' => implode("\n", [
                'Fanamby assure la perception des recettes issues de la présente convention et procède à leur affectation selon la répartition suivante :',
                "- Conservation et gestion écologique (40 %) : activités de conservation, surveillance et patrouilles, lutte contre les feux illicites, restauration de l'aire protégée, gestion administrative et fonctionnement du site ;",
                '- Développement local (40 %) : projets sociaux et communautaires, infrastructures de base, appui aux chaînes de valeur locales, entretien des pistes et circuits, équipements communautaires ;',
                '- Gouvernance territoriale (20 %) : appui aux Fokontany, Communes et Région concernés, coordination et redevabilité territoriale, médiation et gestion des conflits, communication et transparence.',
            ]),
            'obligations_beneficiaire' => implode("\n", [
                'respecter strictement les consignes données par Fanamby et les autorités compétentes',
                'ne causer aucune dégradation du site, de la biodiversité ou des infrastructures',
                'limiter les nuisances sonores et environnementales',
                'respecter les zones autorisées de circulation et de survol',
                "remettre le site dans son état initial après l'intervention",
                'transmettre à Fanamby une copie des images produites, selon des modalités à convenir',
                'disposer de toutes les autorisations techniques nécessaires au matériel utilisé (drone notamment)',
            ]),
            'obligations_fanamby' => implode("\n", [
                'faciliter l\'accès au site selon les règles applicables',
                "assurer l'encadrement local nécessaire",
                "percevoir et gérer les recettes conformément à l'article 5",
                'veiller à la compatibilité des activités avec les objectifs de conservation du site',
            ]),
            'responsabilite' => implode("\n", [
                'de son équipe technique et logistique',
                'du matériel utilisé',
                'des assurances nécessaires',
                'de tout dommage causé aux tiers ou au site du fait de ses activités',
            ]),
            'litiges' => "Tout différend relatif à l'exécution ou à l'interprétation de la présente convention fera d'abord l'objet d'un règlement amiable entre les Parties. À défaut, compétence est attribuée aux juridictions compétentes de Madagascar.",
            'entree_vigueur' => "La présente convention prend effet à compter de sa date de signature par les Parties et demeure valable jusqu'à la clôture complète des obligations réciproques.",
            'lieu' => self::TEXTES['lieu'],
            'signataire' => self::TEXTES['signataire'],
        ];
    }

    /**
     * Redevance de la convention (article 4) : montant par jour saisi par le validateur, nombre de jours
     * d'activités (début → fin, inclus) et total. Montant vide : pas encore fixé ; 0 : aucune redevance.
     *
     * @return array{par_jour: ?int, jours: int, total: ?int}
     */
    public static function redevance(Demande $demande, array $contenu): array
    {
        $saisie = trim((string) ($contenu['redevance_jour'] ?? ''));
        $parJour = ctype_digit($saisie) ? (int) $saisie : null;
        $jours = $demande->date_debut && $demande->date_fin
            ? DelaiDecision::joursEntre($demande->date_debut, $demande->date_fin) + 1
            : 0;

        return ['par_jour' => $parJour, 'jours' => $jours, 'total' => $parJour === null ? null : $parJour * $jours];
    }

    /**
     * Montant en ariary, chiffres groupés par trois (« 8 000 000 »).
     */
    public static function montant(int $valeur): string
    {
        return number_format($valeur, 0, ',', ' ');
    }

    /**
     * Engagement du cahier des charges : les engagements acceptés dans le formulaire de demande (section V).
     */
    public static function engagement(): string
    {
        $lignes = array_map(fn (string $e) => '- ' . lcfirst($e), Demande::ENGAGEMENTS);

        return "Le demandeur s'engage à :\n" . implode(" ;\n", $lignes) . ".\n"
            . 'Tout non-respect de ce cahier des charges entraînera des poursuites.';
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
     * Prépare les documents qui n'existent pas encore. Un document déjà généré garde ses
     * modifications (un dossier généré avant l'arrivée de la convention reçoit seulement celle-ci).
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
                $regles["{$type}.{$champ}"] = in_array($champ, self::NUMERIQUES, true)
                    ? "{$presence}|integer|min:0|max:999999999999"
                    : "{$presence}|string|max:{$max}";
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
     * PDF du document. En projet (avant validation), il n'est pas encore signé.
     */
    public static function pdf(Demande $demande, string $type, bool $projet = false)
    {
        return Pdf::loadView(self::VUES[$type], self::donneesVue($demande, $type, $projet));
    }

    /**
     * Version Word du document, avec le même texte que le PDF.
     */
    public static function word(Demande $demande, string $type, bool $projet = false): PhpWord
    {
        $donnees = self::donneesVue($demande, $type, $projet);

        return match ($type) {
            'autorisation' => DocumentsWord::autorisation($donnees),
            'cahier_charges' => DocumentsWord::cahierDesCharges($donnees),
            'convention' => DocumentsWord::convention($donnees),
        };
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
            'validation' => $validation,
        ];
    }
}

<?php

namespace App\Support;

use Carbon\Carbon;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;
use PhpOffice\PhpWord\Style\Language;

/**
 * Version Word (.docx) des documents produits par la plateforme, à modifier avant impression.
 * Chaque document reprend le texte et la mise en page de sa vue PDF (resources/views/pdf/),
 * avec les mêmes données : modifier une vue PDF demande de modifier la méthode correspondante.
 */
class DocumentsWord
{
    // Couleurs de la charte, comme dans les vues PDF
    private const GRIS = '6E7A72';
    private const OCRE = '9A5F0C';
    private const BLEU = '1D5C8A';

    /** Largeur utile d'une page A4 avec les marges des documents, en twips (1/20 de point). */
    private const LARGEUR = 9412;

    private const GRAS = ['bold' => true];

    /** Cellule de tableau dont le texte passe à la ligne (PhpWord l'interdit par défaut). */
    private const CELLULE = ['noWrap' => false];

    /** Ligne pointillée sous une valeur du formulaire, comme les champs du formulaire papier. */
    private const POINTILLES = ['borderBottomSize' => 6, 'borderBottomColor' => self::GRIS, 'borderBottomStyle' => 'dotted', 'valign' => 'bottom'] + self::CELLULE;

    /** Champ à compléter d'un modèle, surligné comme dans le PDF. */
    private const CHAMP = ['color' => self::BLEU, 'shading' => ['fill' => 'DFEAF3']];

    /** Mise en forme des blocs d'un modèle, comme pdf/modele_reference.blade.php : [police, paragraphe]. */
    private const BLOCS_MODELE = [
        'h1' => [['bold' => true, 'size' => 13.5, 'allCaps' => true], ['alignment' => Jc::CENTER, 'spaceBefore' => 360, 'spaceAfter' => 400]],
        'h3' => [['bold' => true, 'size' => 10.5], ['spaceBefore' => 270, 'spaceAfter' => 90, 'keepNext' => true]],
        'droite' => [[], ['alignment' => Jc::END]],
        'texte' => [[], []],
    ];

    /**
     * Autorisation officielle d'accès, comme pdf/autorisation.blade.php.
     *
     * @param  array  $donnees  variables de la vue (DocumentsOfficiels::donneesVue)
     */
    public static function autorisation(array $donnees): PhpWord
    {
        ['demande' => $demande, 'contenu' => $contenu, 'validation' => $validation] = $donnees;
        [$word, $section] = self::nouveau("Autorisation d'accès", 'Arial');

        self::logo($section);
        self::titre($section, "AUTORISATION OFFICIELLE D'ACCÈS");

        self::texte($section, $contenu['introduction']);
        self::ligne($section, [['Monsieur / Madame :', self::GRAS], " {$demande->user->name} ({$demande->user->email})"]);
        self::ligne($section, ["À accéder à l'aire protégée de : ", [$demande->site->nom, self::GRAS]]);
        self::ligne($section, ['Dans le cadre du projet suivant : ', [$demande->typeProjet(), self::GRAS], $demande->titre ? " — « {$demande->titre} »" : '']);
        self::ligne($section, [['Arrivée sur le terrain :', self::GRAS], ' Le ' . self::date($demande->arrivee())]);
        self::ligne($section, [['Période autorisée :', self::GRAS], ' Du ' . self::date($demande->date_debut) . ' au ' . self::date($demande->date_fin)]);
        self::ligne($section, [["Taille de l'équipe :", self::GRAS], " {$demande->equipe_nombre} personne(s)"]);
        self::ligne($section, [['Objet de la mission :', self::GRAS]]);
        self::texte($section, $contenu['objet']);

        if ($conditions = DocumentsOfficiels::lignes($contenu['conditions'])) {
            self::ligne($section, [['Conditions particulières :', self::GRAS]]);
            self::liste($section, $conditions);
        }

        self::texte($section, $contenu['reserve'], ['italic' => true], ['spaceBefore' => 480]);

        $signature = $section->addTextRun(['alignment' => Jc::END, 'spaceBefore' => 240]);
        $signature->addText('Fait pour valoir ce que de droit,', self::GRAS);
        $signature->addTextBreak();
        $signature->addText($contenu['signataire']);
        if ($validation) {
            $signature->addTextBreak();
            $signature->addText(
                'Validé et signé électroniquement par ' . ($validation->user?->name ?? 'un validateur') . ' le ' . $validation->date_changement->format('d/m/Y à H:i'),
                ['size' => 9, 'color' => self::GRIS],
            );
        }

        self::pied($section, 'Document officiel généré le ' . now()->format('d/m/Y à H:i') . " par la plateforme de gestion Fanamby. Référence : {$demande->reference}");

        return $word;
    }

    /**
     * Cahier des charges, comme pdf/cahier_charges.blade.php.
     *
     * @param  array  $donnees  variables de la vue (DocumentsOfficiels::donneesVue)
     */
    public static function cahierDesCharges(array $donnees): PhpWord
    {
        ['demande' => $demande, 'contenu' => $contenu] = $donnees;
        [$word, $section] = self::nouveau('Cahier des charges', 'Arial');

        self::logo($section);
        self::titre($section, 'CAHIER DES CHARGES À RESPECTER');

        self::ligne($section, [
            "Ce document est une annexe obligatoire à l'autorisation d'accès pour ", [$demande->user->name, self::GRAS],
            " dans l'aire protégée de ", [$demande->site->nom, self::GRAS], '.',
        ]);

        self::partie($section, 'Règles spécifiques liées au type de projet :');
        self::liste($section, DocumentsOfficiels::lignes($contenu['regles']) ?: ['Aucune règle spécifique supplémentaire enregistrée.']);

        if ($alertes = AnalyseDemande::contraintesRelevees($demande->resultats_analyse)) {
            $alerte = self::cadre($section, ['borderSize' => 6, 'borderColor' => 'E0C38F', 'bgColor' => 'F6EAD3']);
            $alerte->addText("Avertissements lors de l'analyse :", ['bold' => true, 'color' => self::OCRE], ['spaceAfter' => 60]);
            self::liste($alerte, array_column($alertes, 'message'), ['color' => self::OCRE]);
            $section->addTextBreak();
        }

        self::partie($section, 'Engagement du demandeur');
        self::texte($section, $contenu['engagement']);

        self::ligne($section, [['Signature du demandeur :', self::GRAS], $demande->date_soumission
            ? " engagements acceptés en ligne par {$demande->user->name} lors de l'envoi du dossier, le {$demande->date_soumission->format('d/m/Y')}."
            : ' ___________________________',
        ], ['spaceBefore' => 480]);

        self::pied($section, 'Document généré le ' . now()->format('d/m/Y à H:i') . " - Réf. : {$demande->reference}");

        return $word;
    }

    /**
     * Convention de collaboration, comme pdf/convention.blade.php.
     *
     * @param  array  $donnees  variables de la vue (DocumentsOfficiels::donneesVue)
     */
    public static function convention(array $donnees): PhpWord
    {
        ['demande' => $demande, 'contenu' => $contenu, 'validation' => $validation] = $donnees;
        [$word, $section] = self::nouveau('Convention de collaboration', 'Arial', 10);
        $beneficiaire = $demande->user;
        $redevance = DocumentsOfficiels::redevance($demande, $contenu);
        $jours = $redevance['jours'] . ' jour' . ($redevance['jours'] > 1 ? 's' : '');
        $modalites = DocumentsOfficiels::lignes($contenu['modalites']);

        $section->addImage(public_path('assets/logo-fanamby-192.png'), ['width' => 54, 'height' => 54, 'alignment' => Jc::CENTER]);
        self::titre($section, 'CONVENTION DE COLLABORATION');

        $section->addText('Entre les soussignés :');
        self::texte($section, $contenu['partie_fanamby'] . ' ;');
        $section->addText('Et');
        self::ligne($section, [[$beneficiaire->name, self::GRAS], implode('', array_filter([
            $beneficiaire->fonction ? ", {$beneficiaire->fonction}" : '',
            $beneficiaire->organisation ? ", {$beneficiaire->organisation}" : '',
            $beneficiaire->adresse ? ", demeurant à {$beneficiaire->adresse}" : '',
            $beneficiaire->telephone ? ", Tél. {$beneficiaire->telephone}" : '',
            ", e-mail : {$beneficiaire->email}",
            $beneficiaire->piece_identite ? ", CIN / passeport : {$beneficiaire->piece_identite}" : '',
            ', ci-après désigné(e) « le Bénéficiaire » ;',
        ]))]);
        $section->addText('Il a été convenu ce qui suit :');

        self::partie($section, 'Article 1 – Objet de la convention');
        self::texte($section, $contenu['objet']);

        self::partie($section, 'Article 2 – Avis favorable');
        self::texte($section, $contenu['avis']);

        self::partie($section, 'Article 3 – Période et modalités des opérations');
        self::ligne($section, [
            'Arrivée sur le terrain le ', [self::date($demande->arrivee()), self::GRAS], ' ; activités du ', [self::date($demande->date_debut), self::GRAS],
            ' au ', [self::date($demande->date_fin), self::GRAS], " ({$jours}) ; équipe de {$demande->equipe_nombre} personne(s).",
            $modalites ? ' Les opérations comprennent notamment :' : '',
        ]);
        self::liste($section, $modalites);
        $section->addText('Toute modification substantielle du calendrier devra être validée préalablement par Fanamby.');

        self::partie($section, 'Article 4 – Redevance financière');
        if ($redevance['par_jour'] === null) {
            $section->addText('Le montant de la redevance due par le Bénéficiaire sera communiqué par Fanamby.');
        } elseif ($redevance['par_jour'] === 0) {
            $section->addText('La présente convention ne donne lieu à aucune redevance.');
        } else {
            self::ligne($section, [
                "En contrepartie de l'autorisation d'exploitation temporaire du site pour les besoins du projet, le Bénéficiaire s'engage à verser à Fanamby la somme de ",
                [DocumentsOfficiels::montant($redevance['par_jour']) . ' Ariary par jour', self::GRAS],
                ", soit pour {$jours} d'activités : ",
                [DocumentsOfficiels::montant($redevance['total']) . ' Ariary', self::GRAS], '.',
            ]);
        }
        self::texte($section, $contenu['paiement']);

        self::partie($section, 'Article 5 – Affectation des recettes');
        self::texte($section, $contenu['affectation']);

        self::partie($section, 'Article 6 – Obligations du Bénéficiaire');
        $section->addText("Le Bénéficiaire s'engage à :");
        self::liste($section, DocumentsOfficiels::lignes($contenu['obligations_beneficiaire']));

        self::partie($section, 'Article 7 – Obligations de Fanamby');
        $section->addText("Fanamby s'engage à :");
        self::liste($section, DocumentsOfficiels::lignes($contenu['obligations_fanamby']));

        self::partie($section, 'Article 8 – Responsabilité');
        $section->addText('Le Bénéficiaire demeure seul responsable :');
        self::liste($section, DocumentsOfficiels::lignes($contenu['responsabilite']));

        self::partie($section, 'Article 9 – Litiges');
        self::texte($section, $contenu['litiges']);

        self::partie($section, 'Article 10 – Entrée en vigueur');
        self::texte($section, $contenu['entree_vigueur']);

        $section->addText(
            "Fait à {$contenu['lieu']}, le " . ($validation ? $validation->date_changement->format('d/m/Y') : '___ / ___ / ______') . ', en deux exemplaires originaux.',
            [], ['spaceBefore' => 240],
        );

        $signatures = $section->addTable(['cellMarginTop' => 120])->addRow(null, ['cantSplit' => true]);
        $fanamby = $signatures->addCell(intdiv(self::LARGEUR, 2), self::CELLULE)->addTextRun();
        $fanamby->addText('Pour Fanamby', self::GRAS);
        $fanamby->addTextBreak();
        $fanamby->addText($contenu['signataire']);
        $fanamby->addTextBreak();
        $fanamby->addText('Signature et cachet :');
        if ($validation) {
            $fanamby->addTextBreak();
            $fanamby->addText(
                'Validé et signé électroniquement par ' . ($validation->user?->name ?? 'un validateur') . ' le ' . $validation->date_changement->format('d/m/Y à H:i'),
                ['size' => 8, 'color' => self::GRIS],
            );
        }
        $partie = $signatures->addCell(intdiv(self::LARGEUR, 2), self::CELLULE)->addTextRun();
        $partie->addText('Le Bénéficiaire', self::GRAS);
        $partie->addTextBreak();
        $partie->addText($beneficiaire->name);
        $partie->addTextBreak();
        $partie->addText('Signature :');

        self::pied($section, 'Document officiel généré le ' . now()->format('d/m/Y à H:i') . " par la plateforme de gestion Fanamby. Référence : {$demande->reference}");

        return $word;
    }

    /**
     * Formulaire de demande rempli, comme pdf/formulaire_demande.blade.php.
     *
     * @param  array  $donnees  variables de la vue (FormulaireDemande::donnees)
     */
    public static function formulaire(array $donnees): PhpWord
    {
        ['demande' => $demande, 'decision' => $decision] = $donnees;
        $user = $demande->user;
        $favorable = $decision?->nouveauStatut?->code === 'validee';
        $defavorable = $decision?->nouveauStatut?->code === 'rejetee';

        [$word, $section] = self::nouveau("Formulaire de demande d'autorisation", 'Times New Roman');
        self::pied($section, "Formulaire rempli sur la plateforme de gestion Fanamby · Référence {$demande->reference} · Édité le " . now()->format('d/m/Y à H:i'));

        self::titre($section, "Formulaire de Demande d'Autorisation pour Projet Photographique / Vidéographique dans les aires protégées gérées par Fanamby", 13);

        self::partie($section, 'I. Informations sur le Demandeur');
        self::champs($section, [
            'Nom et Prénoms :' => $user->name,
            'Institution / Société de production / Organisation :' => $user->organisation,
            'Fonction :' => $user->fonction,
            'Adresse :' => $user->adresse,
            'Téléphone :' => $user->telephone,
            'Email :' => $user->email,
        ], 4300);

        self::partie($section, 'II. Informations sur le Projet');
        self::champs($section, ['Titre du projet :' => $demande->titre], 1900, self::GRAS);

        self::ligne($section, [['Type de projet :', self::GRAS]]);
        self::cases($section, $donnees['types']->map(fn ($type) => [
            $type->libelle . ($type->precision_requise ? ' (précisez) :' : ''),
            $type->id == $demande->type_demande_id,
            $type->precision_requise && $type->id == $demande->type_demande_id ? $demande->type_precision : null,
        ])->all());

        self::ligne($section, [['Résumé du synopsis :', self::GRAS]]);
        self::zone($section, $demande->description);

        self::ligne($section, [['Objectif(s) principal(aux) du projet :', self::GRAS]]);
        self::zone($section, $demande->objectifs);

        self::ligne($section, [['Aire(s) Protégée(s) concernée(s) :', self::GRAS]]);
        self::cases($section, $donnees['sites']->map(fn ($site) => [$site->nom, $site->id == $demande->site_id])->all());

        self::ligne($section, [['Période prévue :', self::GRAS]]);
        self::champs($section, ['- Arrivée sur le terrain :' => self::date($demande->arrivee()), '- Début des activités :' => self::date($demande->date_debut), '- Fin :' => self::date($demande->date_fin)], 1000, [], 4200);

        $section->addPageBreak();

        self::partie($section, 'V. Engagement du Demandeur');
        self::champs($section, ['Je soussigné(e)' => $user->name, 'CIN / Passeport N°' => $user->piece_identite], 2200);
        $section->addText("m'engage à :", [], ['spaceAfter' => 60]);
        $engagements = $donnees['engagements'];
        self::liste($section, array_map(fn ($e, $i) => $e . ($i === array_key_last($engagements) ? '.' : ' ;'), $engagements, array_keys($engagements)));
        self::champs($section, ['Date :' => self::date($demande->date_soumission)], 1000, [], 4200);
        $section->addText('Signature du demandeur :', [], ['spaceAfter' => 60]);
        if ($demande->date_soumission) {
            $section->addText(
                "Engagements acceptés en ligne par {$user->name} lors de l'envoi du dossier, le {$demande->date_soumission->format('d/m/Y à H:i')}.",
                ['italic' => true, 'color' => '3D4A42'],
            );
        }

        self::partie($section, 'VI. Décision de Fanamby (à remplir par le gestionnaire)');
        $section->addText('Avis de Fanamby :', [], ['spaceAfter' => 60]);
        foreach (['Avis favorable' => $favorable, 'Avis défavorable' => $defavorable] as $avis => $coche) {
            $run = $section->addTextRun(['spaceAfter' => 40]);
            self::caseACocher($run, $coche);
            $run->addText($avis);
        }

        $section->addText('Observations :', [], ['spaceBefore' => 160, 'spaceAfter' => 60]);
        self::zone($section, $decision?->commentaire);

        $signature = match (true) {
            $favorable => 'Validé et signé électroniquement le ' . $decision->date_changement->format('d/m/Y à H:i'),
            $defavorable => 'Décision enregistrée sur la plateforme le ' . $decision->date_changement->format('d/m/Y à H:i'),
            default => '',
        };
        self::champs($section, [
            'Date :' => $decision?->date_changement->format('d/m/Y'),
            'Nom et fonction du responsable Fanamby :' => $decision
                ? ($decision->user?->name ?? 'Compte supprimé') . ($decision->user?->fonction ? ", {$decision->user->fonction}" : '')
                : '',
            'Signature et cachet :' => $signature,
        ], 3900);

        return $word;
    }

    /**
     * Modèle de la page Références, comme pdf/modele_reference.blade.php : le contenu est découpé
     * en blocs par ModelesReference::blocs, les champs à compléter sont surlignés.
     */
    public static function modele(string $titre, string $contenu): PhpWord
    {
        [$word, $section] = self::nouveau("Modèle — {$titre}", 'Arial', 10);

        self::logo($section);
        foreach (ModelesReference::blocs($contenu) as $bloc) {
            if ($bloc['type'] === 'pointilles') {
                self::zone($section, '');
            } elseif ($bloc['type'] === 'liste') {
                foreach ($bloc['lignes'] as $ligne) {
                    self::morceaux($section->addListItemRun(0, 'puces', ['spaceAfter' => 60]), $ligne);
                }
            } else {
                [$police, $paragraphe] = self::BLOCS_MODELE[$bloc['type']];
                $run = $section->addTextRun($paragraphe);
                foreach ($bloc['lignes'] as $i => $ligne) {
                    if ($i > 0) {
                        $run->addTextBreak();
                    }
                    self::morceaux($run, $ligne, $police);
                }
            }
        }

        self::pied($section, "Modèle « {$titre} » — plateforme de gestion Fanamby. Les champs entre crochets sont à compléter.");

        return $word;
    }

    /**
     * Document vierge : A4, police de la vue PDF, langue française et puces des listes.
     *
     * @return array{0: PhpWord, 1: Section}
     */
    private static function nouveau(string $titre, string $police, float $taille = 11): array
    {
        // Le texte saisi par les utilisateurs est échappé à l'écriture du fichier (désactivé par défaut dans PhpWord)
        Settings::setOutputEscapingEnabled(true);

        $word = new PhpWord();
        $word->getDocInfo()->setTitle($titre);
        $word->getDocInfo()->setCreator('Plateforme de gestion Fanamby');
        $word->getSettings()->setThemeFontLang(new Language(Language::FR_FR));
        $word->setDefaultFontName($police);
        $word->setDefaultFontSize($taille);
        $word->setDefaultParagraphStyle(['spaceAfter' => 120, 'lineHeight' => 1.3]);
        $word->addNumberingStyle('puces', [
            'type' => 'hybridMultilevel',
            'levels' => [['format' => 'bullet', 'text' => '•', 'left' => 400, 'hanging' => 260, 'tabPos' => 400]],
        ]);

        // A4 en twips entiers : le format « A4 » de PhpWord écrit des décimales, que Word refuse
        $section = $word->addSection(['pageSizeW' => 11906, 'pageSizeH' => 16838, 'marginTop' => 1134, 'marginBottom' => 1134, 'marginLeft' => 1247, 'marginRight' => 1247]);

        return [$word, $section];
    }

    /**
     * Logo de Fanamby en haut à droite, comme dans les vues PDF (72 px, soit 54 pt).
     */
    private static function logo(Section $section): void
    {
        $section->addImage(public_path('assets/logo-fanamby-192.png'), ['width' => 54, 'height' => 54, 'alignment' => Jc::END]);
    }

    /**
     * Cellule unique sur toute la largeur, pour encadrer ou souligner un bloc
     * (les bordures de paragraphe n'existent pas dans PhpWord).
     */
    private static function cadre(AbstractContainer $conteneur, array $style): AbstractContainer
    {
        return $conteneur->addTable(['width' => 100 * 50, 'unit' => TblWidth::PERCENT, 'cellMargin' => 100])
            ->addRow()
            ->addCell(self::LARGEUR, $style + self::CELLULE);
    }

    private static function titre(Section $section, string $texte, float $taille = 15): void
    {
        $section->addText($texte, ['bold' => true, 'size' => $taille], ['alignment' => Jc::CENTER, 'spaceBefore' => 240, 'spaceAfter' => 480]);
    }

    private static function partie(Section $section, string $texte): void
    {
        $section->addText($texte, ['bold' => true, 'size' => 12], ['spaceBefore' => 300, 'spaceAfter' => 120, 'keepNext' => true]);
    }

    /**
     * Paragraphe fait de morceaux : un texte, ou [texte, police] pour le mettre en forme.
     *
     * @param  array<int, string|array{0: string, 1: array}>  $morceaux
     */
    private static function ligne(AbstractContainer $conteneur, array $morceaux, array $paragraphe = []): void
    {
        $run = $conteneur->addTextRun($paragraphe);
        foreach ($morceaux as $morceau) {
            [$texte, $police] = is_array($morceau) ? $morceau : [$morceau, []];
            if ($texte !== '') {
                $run->addText($texte, $police);
            }
        }
    }

    /**
     * Texte saisi, avec ses retours à la ligne (nl2br dans les vues PDF).
     */
    private static function texte(AbstractContainer $conteneur, ?string $texte, array $police = [], array $paragraphe = []): void
    {
        $run = $conteneur->addTextRun($paragraphe);
        foreach (preg_split('/\R/u', (string) $texte) as $i => $ligne) {
            if ($i > 0) {
                $run->addTextBreak();
            }
            $run->addText($ligne, $police);
        }
    }

    /**
     * @param  array<int, string>  $lignes
     */
    private static function liste(AbstractContainer $conteneur, array $lignes, array $police = []): void
    {
        foreach ($lignes as $ligne) {
            $conteneur->addListItem($ligne, 0, $police, 'puces', ['spaceAfter' => 60]);
        }
    }

    /**
     * Ligne d'un modèle : texte, gras et champs à compléter (ModelesReference::blocs).
     *
     * @param  array<int, array{texte: string, gras: bool, champ: bool}>  $morceaux
     */
    private static function morceaux(TextRun $run, array $morceaux, array $police = []): void
    {
        foreach ($morceaux as $morceau) {
            $run->addText($morceau['texte'], $police + ($morceau['gras'] ? self::GRAS : []) + ($morceau['champ'] ? self::CHAMP : []));
        }
    }

    /**
     * Lignes « libellé valeur » du formulaire, la valeur sur une ligne pointillée.
     *
     * @param  array<string, ?string>  $lignes
     * @param  int  $etiquette  largeur des libellés, en twips
     */
    private static function champs(Section $section, array $lignes, int $etiquette, array $policeEtiquette = [], int $largeur = self::LARGEUR): void
    {
        $table = $section->addTable(['cellMarginTop' => 60]);
        foreach ($lignes as $libelle => $valeur) {
            $row = $table->addRow();
            $row->addCell($etiquette, ['valign' => 'bottom'] + self::CELLULE)->addText($libelle, $policeEtiquette, ['spaceAfter' => 0]);
            self::texte($row->addCell($largeur - $etiquette, self::POINTILLES), $valeur, [], ['spaceAfter' => 0]);
        }
        $section->addTextBreak(1, ['size' => 6]);
    }

    /**
     * Texte libre sur une ligne pointillée (synopsis, observations), ou ligne vide à remplir.
     */
    private static function zone(Section $section, ?string $texte): void
    {
        self::texte(self::cadre($section, self::POINTILLES), $texte, [], ['spaceAfter' => 0]);
        $section->addTextBreak(1, ['size' => 6]);
    }

    /**
     * Cases à cocher sur trois colonnes, comme le formulaire papier.
     *
     * @param  array<int, array{0: string, 1: bool, 2?: ?string}>  $cases  libellé, cochée, précision
     */
    private static function cases(Section $section, array $cases): void
    {
        $table = $section->addTable();
        foreach (array_chunk($cases, 3) as $ligne) {
            $row = $table->addRow();
            foreach (array_pad($ligne, 3, null) as $case) {
                $cellule = $row->addCell(intdiv(self::LARGEUR, 3), self::CELLULE);
                if ($case === null) {
                    continue;
                }
                $run = $cellule->addTextRun(['spaceAfter' => 40]);
                self::caseACocher($run, $case[1]);
                $run->addText($case[0]);
                if (! empty($case[2])) {
                    $run->addTextBreak();
                    $run->addText($case[2], ['italic' => true]);
                }
            }
        }
        $section->addTextBreak(1, ['size' => 6]);
    }

    private static function caseACocher(TextRun $run, bool $cochee): void
    {
        $run->addText($cochee ? '☒' : '☐', ['name' => 'Segoe UI Symbol']);
        $run->addText(' ');
    }

    private static function pied(Section $section, string $texte): void
    {
        $section->addFooter()->addText($texte, ['size' => 8, 'color' => self::GRIS], ['alignment' => Jc::CENTER]);
    }

    private static function date($valeur): string
    {
        return $valeur ? Carbon::parse($valeur)->format('d/m/Y') : '';
    }
}

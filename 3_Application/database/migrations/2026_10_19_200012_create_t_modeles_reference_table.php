<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modèles des documents officiels de la page Références, modifiables par les admins.
 * Le contenu est un texte simple mis en forme à l'impression (voir App\Support\ModelesReference) ;
 * les champs à compléter sont les passages entre crochets, ils ne sont donc pas stockés à part.
 * Les cinq modèles d'origine sont repris tels quels.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_modeles_reference', function (Blueprint $table) {
            $table->id();
            $table->string('titre', 150)->unique();
            $table->text('description');
            $table->text('usage')->nullable();
            $table->string('icone', 40);
            $table->boolean('automatique')->default(false); // document produit par la plateforme à la validation
            $table->text('contenu');
            $table->timestamps();
        });

        $maintenant = now();

        DB::table('t_modeles_reference')->insert(array_map(fn (array $modele) => $modele + [
            'created_at' => $maintenant,
            'updated_at' => $maintenant,
        ], [
            [
                'titre' => 'Avis favorable',
                'description' => "Lettre du gestionnaire de l'aire protégée qui appuie une demande d'activité auprès de l'autorité compétente (ministère ou direction régionale de l'environnement).",
                'usage' => "Quand le demandeur doit joindre l'avis du gestionnaire à sa demande de permis de recherche ou de tournage.",
                'icone' => 'seal-check',
                'automatique' => false,
                'contenu' => <<<'TXT'
N° [numéro]/FANAMBY/[année]

> Antananarivo, le [date]
>
> À l'attention de Monsieur / Madame le Directeur Régional
> de l'Environnement et du Développement Durable de [région]

# Avis favorable

**Objet :** avis favorable pour [activité] dans l'aire protégée de [aire protégée]

[Monsieur / Madame],

La Fondation FANAMBY, gestionnaire délégué de l'aire protégée de [aire protégée], a examiné la demande présentée par [nom du bénéficiaire], [fonction] au sein de [organisme].

Cette demande porte sur la réalisation de l'activité suivante : [activité], du [date de début] au [date de fin], avec une équipe de [nombre] personne(s).

**Objet de la mission :** [description du projet]

Après analyse du dossier, la Fondation FANAMBY émet un **AVIS FAVORABLE** à cette demande, sous réserve :
- de l'obtention des autorisations délivrées par l'autorité compétente ;
- du strict respect du cahier des charges de l'aire protégée et des périodes sensibles en vigueur ;
- de la présence d'un agent ou d'un guide désigné par le gestionnaire pendant toute la durée du séjour ;
- de la remise au gestionnaire d'une copie des résultats ou des images produits dans l'aire protégée.

Veuillez agréer, [Monsieur / Madame], l'expression de notre considération distinguée.

> **Pour la Fondation FANAMBY,**
> [nom et fonction du signataire]
>
> Signature et cachet
TXT,
            ],
            [
                'titre' => "Autorisation officielle d'accès",
                'description' => "Autorisation délivrée au demandeur pour accéder à l'aire protégée pendant la période indiquée.",
                'usage' => "Produite par la plateforme à la validation d'un dossier ; ce modèle en montre la structure et les textes par défaut.",
                'icone' => 'stamp',
                'automatique' => true,
                'contenu' => <<<'TXT'
# Autorisation officielle d'accès

Nous soussignés, la Fondation FANAMBY, autorisons par la présente :

**Monsieur / Madame :** [nom du bénéficiaire] ([adresse e-mail])

À accéder à l'aire protégée de : **[aire protégée]**

Dans le cadre de l'activité suivante : **[activité]**

**Période autorisée :** du [date de début] au [date de fin]

**Taille de l'équipe :** [nombre] personne(s)

**Objet de la mission :**
[description du projet]

**Conditions particulières :** (facultatif)
- [condition, une par ligne]

Cette autorisation est délivrée sous réserve du strict respect du cahier des charges qui l'accompagne. Toute infraction entraînera une annulation immédiate.

> **Fait pour valoir ce que de droit,**
> La Direction FANAMBY
> Validé et signé électroniquement par [nom du validateur] le [date de validation]
TXT,
            ],
            [
                'titre' => 'Cahier des charges',
                'description' => "Annexe obligatoire de l'autorisation : règles à respecter sur le site et engagement signé du demandeur.",
                'usage' => "Produit avec l'autorisation à la validation d'un dossier ; les règles viennent du type d'activité.",
                'icone' => 'file-text',
                'automatique' => true,
                'contenu' => <<<'TXT'
# Cahier des charges à respecter

Ce document est une annexe obligatoire à l'autorisation d'accès pour **[nom du bénéficiaire]** dans l'aire protégée de **[aire protégée]**, pour l'activité **[activité]**.

## Règles spécifiques liées à l'activité
- [règle du type d'activité, une par ligne]

## Avertissements lors de l'analyse
[périodes sensibles qui chevauchent le séjour, le cas échéant]

## Engagement du demandeur
Le demandeur certifie avoir pris connaissance des règlements internes de l'aire protégée et s'engage à respecter la faune, la flore et les communautés locales. Tout non-respect de ce cahier des charges entraînera des poursuites.

**Signature du demandeur :** ___________________________
TXT,
            ],
            [
                'titre' => 'Avis défavorable',
                'description' => "Lettre motivée qui informe le demandeur du refus de sa demande d'accès.",
                'usage' => "Quand un dossier est rejeté et qu'un courrier officiel doit accompagner la décision.",
                'icone' => 'prohibit',
                'automatique' => false,
                'contenu' => <<<'TXT'
N° [numéro]/FANAMBY/[année]

> Antananarivo, le [date]
>
> À [nom du demandeur]
> [adresse]

# Avis défavorable

**Objet :** réponse à votre demande d'accès à l'aire protégée de [aire protégée]
**Référence du dossier :** [FAN-AAAA-NNNN]

[Monsieur / Madame],

Par votre demande déposée le [date de dépôt], vous avez sollicité l'accès à l'aire protégée de [aire protégée] pour l'activité suivante : [activité], du [date de début] au [date de fin].

Après examen de votre dossier, nous avons le regret de vous informer que la Fondation FANAMBY émet un **AVIS DÉFAVORABLE** à cette demande, pour le(s) motif(s) suivant(s) :
- [motif du refus]

Vous pouvez déposer une nouvelle demande sur la plateforme lorsque les conditions ci-dessus seront réunies. Nos équipes restent à votre disposition pour toute information complémentaire.

Veuillez agréer, [Monsieur / Madame], l'expression de nos salutations distinguées.

> **Pour la Fondation FANAMBY,**
> [nom et fonction du signataire]
>
> Signature et cachet
TXT,
            ],
            [
                'titre' => 'Demande de complément',
                'description' => 'Courrier qui liste les pièces ou précisions manquantes et fixe un délai de réponse.',
                'usage' => 'Quand un dossier incomplet doit être relancé par courrier, en plus du commentaire laissé sur la plateforme.',
                'icone' => 'envelope-simple',
                'automatique' => false,
                'contenu' => <<<'TXT'
N° [numéro]/FANAMBY/[année]

> Antananarivo, le [date]
>
> À [nom du demandeur]
> [adresse]

# Demande de complément de dossier

**Objet :** pièces et précisions manquantes
**Référence du dossier :** [FAN-AAAA-NNNN] — aire protégée de [aire protégée]

[Monsieur / Madame],

Nous accusons réception de votre demande d'accès pour l'activité [activité], du [date de début] au [date de fin]. Son instruction ne peut se poursuivre sans les éléments suivants :

## Pièces à fournir
- [pièce manquante]

## Précisions demandées
...
...
...

Merci de compléter votre dossier directement sur la plateforme (bouton « Compléter le dossier ») avant le [date limite]. Passé ce délai, la demande pourra être rejetée.

Veuillez agréer, [Monsieur / Madame], l'expression de nos salutations distinguées.

> **Pour la Fondation FANAMBY,**
> [nom et fonction du signataire]
TXT,
            ],
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('t_modeles_reference');
    }
};

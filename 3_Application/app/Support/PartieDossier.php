<?php

namespace App\Support;

/**
 * Parties d'un dossier visées par une demande de complément ou un signalement, et l'étape du
 * formulaire de demande où le demandeur est amené pour y répondre (depuis sa notification,
 * son tableau de bord ou la fiche de sa demande).
 */
class PartieDossier
{
    /** Ce qu'un validateur peut demander de compléter, avec son libellé (CHECK de t_historique_statuts). */
    public const COMPLEMENT = [
        'demandeur' => 'Coordonnées du demandeur',
        'projet' => 'Informations sur la demande',
        // Un champ « Votre clarification » apparaît alors dans « Informations sur la demande »
        'clarification' => 'Point à clarifier',
        'sejour' => 'Aire protégée, dates et équipe',
        'piece_manquante' => 'Pièce jointe manquante',
        'piece_a_remplacer' => 'Pièce jointe à remplacer',
        'autre' => 'Autre',
    ];

    /** Étapes du formulaire de demande où un lien peut amener le demandeur (CHECK de t_notifications.etape). */
    public const ETAPES_FORMULAIRE = ['demandeur', 'projet', 'aire', 'pieces'];

    /** Étape du formulaire (Demandes/Create.vue) correspondant à une partie de complément ou de signalement. */
    private const ETAPES = [
        'demandeur' => 'demandeur',
        'projet' => 'projet',
        'clarification' => 'projet',
        'sejour' => 'aire',
        'pieces' => 'pieces',
        'piece_manquante' => 'pieces',
        'piece_a_remplacer' => 'pieces',
    ];

    /** Partie de complément proposée quand un signalement est transformé en demande de complément. */
    private const COMPLEMENT_PAR_SIGNALEMENT = [
        'demandeur' => 'demandeur',
        'projet' => 'projet',
        'sejour' => 'sejour',
        'pieces' => 'piece_a_remplacer',
    ];

    /**
     * Partie de complément correspondant à la partie d'un signalement (« autre » pour l'analyse,
     * les documents, le suivi…). Le validateur peut la changer avant d'envoyer.
     */
    public static function complementPourSignalement(?string $partie): string
    {
        return self::COMPLEMENT_PAR_SIGNALEMENT[$partie] ?? 'autre';
    }

    /**
     * Étape du formulaire à ouvrir pour une partie, ou null si la partie n'en a pas (analyse, documents, autre…).
     */
    public static function etape(?string $partie): ?string
    {
        return self::ETAPES[$partie] ?? null;
    }

    /**
     * Libellé d'une partie de complément, ou null si elle n'est pas précisée.
     */
    public static function libelleComplement(?string $partie): ?string
    {
        return self::COMPLEMENT[$partie] ?? null;
    }
}

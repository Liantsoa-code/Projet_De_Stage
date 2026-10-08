<?php

namespace App\Support;

use App\Models\Statut;
use Illuminate\Support\Collection;

class DemandeStatutWorkflow
{
    /** @var array<string, int> */
    private const RANK = [
        'brouillon' => 0,
        'soumise' => 1,
        'en_analyse' => 2,
        'a_completer' => 2,
        'validee' => 3,
        'rejetee' => 3,
    ];

    /** Statuts depuis lesquels un validateur peut renvoyer le dossier au demandeur. */
    private const PEUT_DEMANDER_COMPLEMENT = ['soumise', 'en_analyse'];

    public static function isTerminal(?Statut $statut): bool
    {
        if ($statut === null) {
            return false;
        }

        return in_array($statut->code, ['validee', 'rejetee'], true);
    }

    /**
     * Le demandeur peut modifier son dossier : brouillon ou compléments demandés.
     */
    public static function isEditableByDemandeur(?Statut $statut): bool
    {
        return $statut !== null && in_array($statut->code, ['brouillon', 'a_completer'], true);
    }

    public static function rank(string $code): int
    {
        return self::RANK[$code] ?? -1;
    }

    /**
     * Transitions autorisées pour une décision validateur / admin.
     */
    public static function canTransition(?Statut $from, Statut $to): bool
    {
        if ($from === null) {
            return false;
        }

        // Un brouillon n'est pas encore soumis : aucune décision possible.
        if (self::isTerminal($from) || $from->code === 'brouillon') {
            return false;
        }

        if ((int) $from->id === (int) $to->id) {
            return false;
        }

        if ($to->code === 'a_completer') {
            return in_array($from->code, self::PEUT_DEMANDER_COMPLEMENT, true);
        }

        $fromRank = self::rank($from->code);
        $toRank = self::rank($to->code);

        if ($fromRank < 0 || $toRank < 0) {
            return false;
        }

        return $toRank > $fromRank;
    }

    /**
     * Statuts proposés dans le formulaire de décision (statut actuel + transitions autorisées).
     *
     * @param  Collection<int, Statut>  $allStatuts
     * @return Collection<int, Statut>
     */
    public static function allowedTargetStatuts(?Statut $current, Collection $allStatuts): Collection
    {
        if ($current === null || self::isTerminal($current)) {
            return collect();
        }

        return $allStatuts
            ->filter(fn (Statut $statut) => (int) $statut->id === (int) $current->id
                || self::canTransition($current, $statut))
            ->values();
    }
}

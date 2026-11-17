<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Période d'observation des tableaux de bord et du rapport d'activité (paramètres « du » et « au », AAAA-MM-JJ).
 * Elle porte sur la date d'envoi des demandes : « reçues sur la période » = envoyées entre ces deux dates incluses.
 * Sans borne, toutes les demandes sont prises en compte (depuis le début).
 */
final class PeriodeStats
{
    public function __construct(
        public readonly ?Carbon $du = null,
        public readonly ?Carbon $au = null,
    ) {
    }

    /**
     * Période lue dans la requête : une date illisible est ignorée, des bornes inversées sont remises dans l'ordre.
     */
    public static function depuis(Request $request): self
    {
        $du = self::lire($request->query('du'));
        $au = self::lire($request->query('au'));

        if ($du && $au && $du->gt($au)) {
            [$du, $au] = [$au, $du];
        }

        return new self($du, $au);
    }

    private static function lire(mixed $valeur): ?Carbon
    {
        if (! is_string($valeur) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur)) {
            return null;
        }

        $date = Carbon::createFromFormat('!Y-m-d', $valeur);

        // createFromFormat accepte le 31/02 en le reportant au mois suivant : refusé ici
        return $date && $date->format('Y-m-d') === $valeur ? $date : null;
    }

    public function active(): bool
    {
        return $this->du !== null || $this->au !== null;
    }

    /**
     * Limite une requête aux lignes dont la colonne (date ou date-heure) tombe dans la période, bornes incluses.
     */
    public function appliquer(Builder $query, string $colonne): Builder
    {
        return $query
            ->when($this->du, fn ($q) => $q->where($colonne, '>=', $this->du->copy()->startOfDay()))
            ->when($this->au, fn ($q) => $q->where($colonne, '<=', $this->au->copy()->endOfDay()));
    }

    /** Année du graphique mensuel : celle de la fin de la période, à défaut l'année en cours. */
    public function anneeReference(): int
    {
        return ($this->au ?? today())->year;
    }

    /** « du 01/01/2026 au 31/03/2026 », « depuis le 01/01/2026 », « jusqu'au 31/03/2026 » ou null. */
    public function libelle(): ?string
    {
        return match (true) {
            $this->du && $this->au => 'du ' . $this->du->format('d/m/Y') . ' au ' . $this->au->format('d/m/Y'),
            $this->du !== null => 'depuis le ' . $this->du->format('d/m/Y'),
            $this->au !== null => 'jusqu\'au ' . $this->au->format('d/m/Y'),
            default => null,
        };
    }

    public function pourPage(): array
    {
        return [
            'du' => $this->du?->toDateString(),
            'au' => $this->au?->toDateString(),
            'libelle' => $this->libelle(),
        ];
    }
}

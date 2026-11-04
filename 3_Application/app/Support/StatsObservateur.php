<?php

namespace App\Support;

use App\Models\Signalement;
use Illuminate\Support\Collection;

/**
 * Tableau de bord de l'observateur : suivre tous les dossiers en cours et ses signalements, sans décision.
 */
class StatsObservateur extends StatsInstruction
{
    public function donnees(): array
    {
        $enAttente = $this->dossiersEnAttente();

        return $this->carte($enAttente) + [
            'principales' => $this->principales(),
            'compteurs' => $this->compteurs($enAttente),
            'file' => $enAttente->take(8)->values(),
            'signalements' => $this->mesSignalements(),
        ];
    }

    /**
     * Derniers signalements de l'observateur, les ouverts d'abord.
     */
    private function mesSignalements(): Collection
    {
        return Signalement::where('user_id', $this->user->id)
            ->with(['demande:id,site_id,created_at', 'demande.site:id,nom', 'traitePar:id,name,role'])
            ->orderByRaw('traite_le IS NULL DESC')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Signalement $s) => $s->pourPage() + [
                'demande_id' => $s->demande_id,
                'reference' => $s->demande?->reference,
                'site' => $s->demande?->site?->nom,
            ]);
    }
}

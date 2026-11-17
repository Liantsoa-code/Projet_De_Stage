<?php

namespace App\Support;

use App\Models\Demande;
use App\Models\HistoriqueStatut;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tableau de bord de l'admin : piloter le service (délais, organisation, fréquentation des sites).
 * Les indicateurs de flux (reçues, décisions, délais, types, aires) suivent la période choisie ;
 * les indicateurs du jour (dossiers à traiter, présence sur le terrain, charge, carte) n'en dépendent pas.
 * Le rapport d'activité PDF (rapport()) reprend les mêmes calculs que l'écran.
 */
class StatsAdmin extends StatsInstruction
{
    /** Objectif de délai de décision suivi par l'admin. */
    public const OBJECTIF_JOURS = 15;

    public function donnees(): array
    {
        $enAttente = $this->dossiersEnAttente();

        return $this->carte($enAttente) + $this->rapport() + [
            'compteurs' => $this->compteurs($enAttente),
            'urgents' => $enAttente->filter(fn ($d) => $d['imminent'] || $d['retard'] || $d['conflit'])->take(6)->values(),
            'terrain' => $this->surLeTerrain(),
            'sites_sans_validateur' => Site::whereDoesntHave('validateurs', fn ($q) => $q->where('role', 'validateur'))
                ->orderBy('nom')
                ->get(['id', 'nom']),
            'charge_validateurs' => $this->chargeValidateurs($enAttente),
            'total_users' => User::count(),
        ];
    }

    /**
     * Indicateurs de la période : écran du tableau de bord et rapport d'activité PDF.
     */
    public function rapport(): array
    {
        $decisions = DelaiDecision::decisions($this->recues);

        return [
            'principales' => $this->principales(),
            'qualite' => $this->qualite($decisions),
            'par_site' => $this->parSite($decisions),
            'frequentation' => $this->frequentation(),
            'par_mois' => $this->parMois(),
            'par_type' => (clone $this->recues)
                ->join('t_type_demandes', 't_demandes.type_demande_id', '=', 't_type_demandes.id')
                ->selectRaw('t_type_demandes.libelle as nom, COUNT(t_demandes.id) as total')
                ->groupBy('t_type_demandes.id', 't_type_demandes.libelle')
                ->orderByDesc('total')
                ->get()
                ->map(fn ($t) => ['nom' => $t->nom, 'total' => (int) $t->total])
                ->toArray(),
        ];
    }

    /**
     * Qualité du service sur les dossiers reçus pendant la période et décidés (validés ou rejetés).
     */
    private function qualite(Collection $decisions): array
    {
        $nbDecisions = $decisions->count();
        $avecComplement = $nbDecisions === 0 ? 0 : HistoriqueStatut::whereIn('demande_id', $decisions->pluck('id'))
            ->whereHas('nouveauStatut', fn ($q) => $q->where('code', 'a_completer'))
            ->distinct()
            ->count('demande_id');

        return [
            'delai_median' => DelaiDecision::mediane($decisions),
            'objectif_jours' => self::OBJECTIF_JOURS,
            'pct_objectif' => $this->pourcentage($decisions->where('jours', '<=', self::OBJECTIF_JOURS)->count(), $nbDecisions),
            'decisions' => $nbDecisions,
            // Taux calculé sur les seuls dossiers décidés : les dossiers en attente ne le font pas baisser.
            'taux_validation' => $this->pourcentage($decisions->where('decision', 'validee')->count(), $nbDecisions),
            'pct_complement' => $this->pourcentage($avecComplement, $nbDecisions),
        ];
    }

    /**
     * Synthèse par aire protégée des demandes reçues pendant la période : répartition par statut,
     * taux de validation et délai médian de décision. Toutes les aires figurent, même sans demande.
     * La somme des lignes redonne les statistiques principales.
     */
    private function parSite(Collection $decisions): Collection
    {
        $parSite = (clone $this->recues)
            ->join('t_statuts', 't_statuts.id', '=', 't_demandes.statut_id')
            ->selectRaw('t_demandes.site_id as site_id, t_statuts.code as code, COUNT(*) as total')
            ->groupBy('t_demandes.site_id', 't_statuts.code')
            ->get()
            ->groupBy('site_id');
        $delais = $decisions->groupBy('site_id');

        return Site::orderBy('nom')->get(['id', 'nom'])->map(function (Site $site) use ($parSite, $delais) {
            $codes = collect($parSite[$site->id] ?? [])->mapWithKeys(fn ($l) => [$l->code => (int) $l->total]);
            $nb = fn (string ...$c) => collect($c)->sum(fn ($code) => $codes[$code] ?? 0);

            return [
                'id' => $site->id,
                'nom' => $site->nom,
                'recues' => $codes->sum(),
                'en_attente' => $nb('soumise', 'en_analyse', 'a_completer'),
                'validees' => $nb('validee'),
                'rejetees' => $nb('rejetee'),
                'retirees' => $nb('retiree'),
                'taux_validation' => $this->pourcentage($nb('validee'), $nb('validee', 'rejetee')),
                'delai_median' => DelaiDecision::mediane($delais[$site->id] ?? collect()),
            ];
        })->sortByDesc('recues')->values();
    }

    /**
     * Séjours validés en cours aujourd'hui, de l'arrivée sur le terrain à la fin : qui est sur place, et combien.
     */
    private function surLeTerrain(): array
    {
        $jour = today()->toDateString();
        $sejours = Demande::query()
            ->whereHas('statut', fn ($q) => $q->where('code', 'validee'))
            ->whereRaw('COALESCE(date_arrivee, date_debut) <= ?', [$jour])
            ->where('date_fin', '>=', $jour)
            ->with(['user:id,name', 'site:id,nom'])
            ->orderBy('date_fin')
            ->get(['id', 'user_id', 'site_id', 'date_arrivee', 'date_debut', 'date_fin', 'equipe_nombre', 'created_at']);

        return [
            'sejours' => $sejours->count(),
            'personnes' => (int) $sejours->sum('equipe_nombre'),
            'liste' => $sejours->take(6)->map(fn (Demande $d) => [
                'id' => $d->id,
                'reference' => $d->reference,
                'demandeur' => $d->user?->name,
                'site' => $d->site?->nom,
                'equipe' => $d->equipe_nombre,
                'date_fin' => $d->date_fin->toDateString(),
            ])->values(),
        ];
    }

    /**
     * Dossiers en attente sur les sites de chaque validateur.
     */
    private function chargeValidateurs(Collection $enAttente): Collection
    {
        $parSite = $enAttente->countBy('site_id');

        return User::where('role', 'validateur')
            ->with('sites:id')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $v) => [
                'id' => $v->id,
                'nom' => $v->name,
                'sites' => $v->sites->count(),
                'en_attente' => $v->sites->sum(fn ($s) => $parSite[$s->id] ?? 0),
            ])
            ->sortByDesc('en_attente')
            ->values();
    }

    /**
     * Fréquentation autorisée par site : séjours validés, personnes, et personnes × jours de présence,
     * de l'arrivée à la fin (la pression humaine réelle sur le site).
     * Sans période : les 12 prochains mois, séjours comptés en entier. Avec une période : les séjours qui
     * la chevauchent, et seuls les jours de présence compris dans la période.
     */
    private function frequentation(): Collection
    {
        $periode = $this->periode->active();
        $debut = $periode ? ($this->periode->du ?? Carbon::create(2000)) : today();
        $fin = $periode ? ($this->periode->au ?? today()->addYear()) : today()->addYear();

        return Demande::query()
            ->whereHas('statut', fn ($q) => $q->where('code', 'validee'))
            ->where('date_fin', '>=', $debut->toDateString())
            ->whereRaw('COALESCE(date_arrivee, date_debut) <= ?', [$fin->toDateString()])
            ->with('site:id,nom')
            ->get(['id', 'site_id', 'date_arrivee', 'date_debut', 'date_fin', 'equipe_nombre'])
            ->groupBy('site_id')
            ->map(fn ($ds) => [
                'nom' => $ds->first()->site?->nom,
                'sejours' => $ds->count(),
                'personnes' => (int) $ds->sum('equipe_nombre'),
                'personnes_jours' => (int) $ds->sum(function (Demande $d) use ($periode, $debut, $fin) {
                    $arrivee = $periode ? $d->arrivee()->max($debut) : $d->arrivee();
                    $depart = $periode ? $d->date_fin->min($fin) : $d->date_fin;

                    return $d->equipe_nombre * (DelaiDecision::joursEntre($arrivee, $depart) + 1);
                }),
            ])
            ->sortByDesc('personnes_jours')
            ->values();
    }

    /**
     * Demandes reçues (envoyées) par mois : année de la fin de la période (à défaut l'année en cours)
     * et année précédente, pour comparaison.
     */
    private function parMois(): array
    {
        $annee = $this->periode->anneeReference();
        $parMois = ['annee' => $annee, 'courante' => array_fill(0, 12, 0), 'precedente' => array_fill(0, 12, 0)];

        (clone $this->base)
            ->whereNotNull('t_demandes.date_soumission')
            ->whereYear('t_demandes.date_soumission', '>=', $annee - 1)
            ->whereYear('t_demandes.date_soumission', '<=', $annee)
            ->get(['t_demandes.date_soumission'])
            ->each(function (Demande $d) use (&$parMois, $annee) {
                $parMois[$d->date_soumission->year === $annee ? 'courante' : 'precedente'][$d->date_soumission->month - 1]++;
            });

        return $parMois;
    }

    private function pourcentage(int $part, int $total): ?int
    {
        return $total > 0 ? (int) round($part / $total * 100) : null;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use App\Models\HistoriqueStatut;
use App\Models\PeriodeSensible;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Tableau de bord adapté au rôle :
 * - demandeur : suivre ses dossiers et savoir quoi faire ;
 * - validateur : traiter sa file de dossiers par ordre d'urgence ;
 * - admin : piloter le service (délais, organisation, fréquentation des sites).
 *
 * Les durées (délais, attentes, séjours) sont calculées en PHP, en jours calendaires.
 */
class DashboardController extends Controller
{
    /** Un dossier sans décision depuis plus de N jours est en retard. */
    private const JOURS_RETARD = 7;

    /** Un séjour qui commence dans N jours ou moins sans décision est imminent. */
    private const JOURS_IMMINENT = 14;

    /** Objectif de délai de décision suivi par l'admin. */
    private const OBJECTIF_JOURS = 15;

    /** En dessous de ce nombre de décisions, le délai d'un site n'est pas représentatif. */
    private const MIN_DECISIONS_SITE = 3;

    public function __invoke()
    {
        $user = Auth::user();

        $data = match ($user->role) {
            'admin' => $this->admin($user),
            'validateur' => $this->validateur($user),
            default => $this->demandeur($user),
        };

        return Inertia::render('Dashboard', $data);
    }

    private function demandeur(User $user): array
    {
        $mesDemandes = $user->demandes()
            ->with([
                'site:id,nom',
                'typeDemande:id,libelle,documents_requis',
                'statut:id,code,libelle',
                'documents:id,demande_id,type_document',
            ])
            ->latest('updated_at')
            ->get();

        $aCompleter = $mesDemandes->where('statut.code', 'a_completer')->values();
        // Un seul chargement groupé de l'historique commenté (au lieu d'une requête par demande).
        $aCompleter->load(['historiqueStatuts' => fn ($q) => $q->whereNotNull('commentaire')->with('user:id,name')->latest('date_changement')]);
        $aCompleter->each(function ($d) {
            $dernier = $d->historiqueStatuts->first();
            $d->setAttribute('dernier_commentaire', $dernier?->commentaire);
            $d->setAttribute('commentaire_par', $dernier?->user?->name);
            $d->setAttribute('commentaire_le', $dernier?->date_changement);
            $d->unsetRelation('historiqueStatuts');
        });

        // Délai habituel de décision : celui du site s'il est représentatif, sinon celui de la plateforme.
        $enInstruction = $mesDemandes->whereIn('statut.code', ['soumise', 'en_analyse']);
        $delaiPour = fn () => null;
        if ($enInstruction->isNotEmpty()) {
            $decisions = $this->decisions(Demande::query());
            $global = $this->mediane($decisions);
            $parSite = $decisions->groupBy('site_id')
                ->filter(fn ($ds) => $ds->count() >= self::MIN_DECISIONS_SITE)
                ->map(fn ($ds) => $this->mediane($ds));
            $delaiPour = fn (Demande $d) => $parSite[$d->site_id] ?? $global;
        }

        $mesDemandes->each(function (Demande $d) use ($enInstruction, $delaiPour) {
            if ($enInstruction->contains($d)) {
                $d->setAttribute('delai_habituel', $delaiPour($d));
                $d->setAttribute('attente', $d->date_soumission ? $this->joursEntre($d->date_soumission, today()) : null);
            }
            if ($d->statut->code === 'brouillon') {
                $fournis = $d->documents->pluck('type_document')->all();
                $d->setAttribute('pieces_manquantes', count(array_diff($d->typeDemande?->documents_requis ?? [], $fournis)));
            }
            $d->unsetRelation('documents');
        });

        return [
            'a_completer' => $aCompleter,
            'en_cours' => $mesDemandes->whereIn('statut.code', ['brouillon', 'soumise', 'en_analyse', 'a_completer'])->values(),
            'sejours' => $mesDemandes
                ->where('statut.code', 'validee')
                ->filter(fn ($d) => $d->date_fin && $d->date_fin->gte(today()))
                ->sortBy('date_debut')
                ->values(),
        ];
    }

    private function validateur(User $user): array
    {
        $base = $this->base($user);
        $enAttente = $this->dossiersEnAttente($base);

        return $this->carte($user, $enAttente) + [
            'compteurs' => $this->compteurs($base, $enAttente),
            'file' => $enAttente->take(8)->values(),
            'attente_demandeur' => $this->attenteDemandeur($base),
            'activite' => $this->activite($user),
            'delai_median' => $this->mediane($this->decisions($base)),
        ];
    }

    private function admin(User $user): array
    {
        $base = $this->base($user);
        $enAttente = $this->dossiersEnAttente($base);
        $decisions = $this->decisions($base);

        $nbDecisions = $decisions->count();
        $validees = $decisions->where('decision', 'validee')->count();
        $avecComplement = $nbDecisions === 0 ? 0 : HistoriqueStatut::whereIn('demande_id', $decisions->pluck('id'))
            ->whereHas('nouveauStatut', fn ($q) => $q->where('code', 'a_completer'))
            ->distinct()
            ->count('demande_id');

        return $this->carte($user, $enAttente) + [
            'compteurs' => $this->compteurs($base, $enAttente),
            'urgents' => $enAttente->filter(fn ($d) => $d['imminent'] || $d['retard'] || $d['conflit'])->take(6)->values(),
            'qualite' => [
                'delai_median' => $this->mediane($decisions),
                'objectif_jours' => self::OBJECTIF_JOURS,
                'pct_objectif' => $this->pourcentage($decisions->where('jours', '<=', self::OBJECTIF_JOURS)->count(), $nbDecisions),
                'decisions' => $nbDecisions,
                // Taux calculé sur les seuls dossiers décidés : les dossiers en attente ne le font pas baisser.
                'taux_validation' => $this->pourcentage($validees, $nbDecisions),
                'pct_complement' => $this->pourcentage($avecComplement, $nbDecisions),
            ],
            'terrain' => $this->surLeTerrain(),
            'sites_sans_validateur' => Site::whereDoesntHave('validateurs', fn ($q) => $q->where('role', 'validateur'))
                ->orderBy('nom')
                ->get(['id', 'nom']),
            'charge_validateurs' => $this->chargeValidateurs($enAttente),
            'frequentation' => $this->frequentation(),
            'par_mois' => $this->parMois($base),
            'par_type' => (clone $base)
                ->join('type_demandes', 'demandes.type_demande_id', '=', 'type_demandes.id')
                ->selectRaw('type_demandes.libelle as nom, COUNT(demandes.id) as total')
                ->groupBy('type_demandes.id', 'type_demandes.libelle')
                ->orderByDesc('total')
                ->get()
                ->toArray(),
            'total_users' => User::count(),
        ];
    }

    /**
     * Demandes prises en compte : hors brouillons, limitées aux sites du validateur.
     */
    private function base(User $user): Builder
    {
        return Demande::query()->horsBrouillon()->forStatsViewer($user);
    }

    /**
     * Dossiers soumis ou en analyse, triés par urgence :
     * séjours imminents (date d'arrivée la plus proche d'abord), puis retards (plus ancien d'abord), puis le reste.
     */
    private function dossiersEnAttente(Builder $base): Collection
    {
        $aujourdhui = today();

        return (clone $base)
            ->whereHas('statut', fn ($q) => $q->whereIn('code', ['soumise', 'en_analyse']))
            ->with(['user:id,name', 'site:id,nom', 'statut:id,code,libelle'])
            ->get(['id', 'user_id', 'site_id', 'statut_id', 'date_debut', 'date_fin', 'date_soumission', 'created_at', 'resultats_analyse'])
            ->map(function (Demande $d) use ($aujourdhui) {
                $avant = $d->date_debut ? $this->joursEntre($aujourdhui, $d->date_debut) : null;
                $attente = $d->date_soumission ? $this->joursEntre($d->date_soumission, $aujourdhui) : 0;

                return [
                    'id' => $d->id,
                    'created_at' => $d->created_at,
                    'demandeur' => $d->user?->name,
                    'site_id' => $d->site_id,
                    'site' => $d->site?->nom,
                    'statut' => $d->statut?->only(['code', 'libelle']),
                    'date_debut' => $d->date_debut?->toDateString(),
                    'date_fin' => $d->date_fin?->toDateString(),
                    'jours_avant' => $avant,
                    'attente' => $attente,
                    'imminent' => $avant !== null && $avant <= self::JOURS_IMMINENT,
                    'retard' => $attente > self::JOURS_RETARD,
                    'conflit' => ! empty($d->resultats_analyse),
                ];
            })
            ->sortBy(fn ($d) => [
                $d['imminent'] ? 0 : ($d['retard'] ? 1 : 2),
                $d['imminent'] ? $d['jours_avant'] : -$d['attente'],
            ])
            ->values();
    }

    private function compteurs(Builder $base, Collection $enAttente): array
    {
        return [
            'en_attente' => $enAttente->count(),
            'non_pris' => $enAttente->where('statut.code', 'soumise')->count(),
            'imminents' => $enAttente->where('imminent', true)->count(),
            'retards' => $enAttente->where('retard', true)->count(),
            'conflits' => $enAttente->where('conflit', true)->count(),
            'attente_demandeur' => (clone $base)->whereHas('statut', fn ($q) => $q->where('code', 'a_completer'))->count(),
        ];
    }

    /**
     * Carte : sites affichés, charge de chacun et périodes sensibles pour le curseur de date.
     */
    private function carte(User $user, Collection $enAttente): array
    {
        $colonnes = ['sites.id', 'sites.nom', 'sites.description', 'sites.polygone_coordonnees'];
        $sites = $user->role === 'admin' ? Site::all($colonnes) : $user->sites()->get($colonnes);

        return [
            'sites' => $sites,
            'charge_sites' => $enAttente->groupBy('site_id')->map(fn ($ds) => [
                'en_attente' => $ds->count(),
                'retards' => $ds->where('retard', true)->count(),
                'conflits' => $ds->where('conflit', true)->count(),
            ]),
            'periodes' => PeriodeSensible::whereIn('site_id', $sites->pluck('id'))
                ->where('date_fin', '>=', today()->toDateString())
                ->orderBy('date_debut')
                ->get()
                ->map(fn (PeriodeSensible $p) => [
                    'id' => $p->id,
                    'site_id' => $p->site_id,
                    'date_debut' => $p->date_debut->toDateString(),
                    'date_fin' => $p->date_fin->toDateString(),
                    'niveau_restriction' => $p->niveau_restriction,
                    'description' => $p->description,
                ]),
        ];
    }

    /**
     * Dossiers renvoyés au demandeur pour complément, du plus ancien au plus récent.
     */
    private function attenteDemandeur(Builder $base): Collection
    {
        return (clone $base)
            ->whereHas('statut', fn ($q) => $q->where('code', 'a_completer'))
            ->with(['user:id,name', 'site:id,nom'])
            ->select('demandes.id', 'demandes.user_id', 'demandes.site_id', 'demandes.created_at')
            ->addSelect(['complement_le' => HistoriqueStatut::select('date_changement')
                ->whereColumn('historique_statuts.demande_id', 'demandes.id')
                ->whereHas('nouveauStatut', fn ($q) => $q->where('code', 'a_completer'))
                ->latest('date_changement')
                ->limit(1)])
            ->orderBy('complement_le')
            ->limit(5)
            ->get()
            ->map(fn (Demande $d) => [
                'id' => $d->id,
                'created_at' => $d->created_at,
                'demandeur' => $d->user?->name,
                'site' => $d->site?->nom,
                'depuis' => $d->complement_le ? $this->joursEntre(Carbon::parse($d->complement_le), today()) : null,
            ]);
    }

    /**
     * Décisions prises par le validateur ce mois-ci, par type (hors ses propres demandes).
     */
    private function activite(User $user): array
    {
        $parCode = HistoriqueStatut::query()
            ->join('statuts', 'statuts.id', '=', 'historique_statuts.nouveau_statut_id')
            ->join('demandes', 'demandes.id', '=', 'historique_statuts.demande_id')
            ->where('historique_statuts.user_id', $user->id)
            ->where('demandes.user_id', '!=', $user->id)
            ->where('historique_statuts.date_changement', '>=', now()->startOfMonth())
            ->whereIn('statuts.code', ['en_analyse', 'a_completer', 'validee', 'rejetee'])
            ->selectRaw('statuts.code as code, COUNT(*) as total')
            ->groupBy('statuts.code')
            ->pluck('total', 'code');

        return collect(['en_analyse', 'a_completer', 'validee', 'rejetee'])
            ->mapWithKeys(fn ($code) => [$code => (int) ($parCode[$code] ?? 0)])
            ->all();
    }

    /**
     * Décisions finales (validée / rejetée) avec leur délai en jours depuis la soumission.
     *
     * @return Collection<int, array{id: int, site_id: int, decision: string, jours: int}>
     */
    private function decisions(Builder $base): Collection
    {
        return (clone $base)
            ->join('historique_statuts as h', 'h.demande_id', '=', 'demandes.id')
            ->join('statuts as decision', 'decision.id', '=', 'h.nouveau_statut_id')
            ->whereIn('decision.code', ['validee', 'rejetee'])
            ->whereNotNull('demandes.date_soumission')
            ->get(['demandes.id', 'demandes.site_id', 'demandes.date_soumission', 'h.date_changement as date_decision', 'decision.code as decision'])
            ->map(fn (Demande $d) => [
                'id' => $d->id,
                'site_id' => $d->site_id,
                'decision' => $d->getAttribute('decision'),
                'jours' => $this->joursEntre($d->date_soumission, Carbon::parse($d->getAttribute('date_decision'))),
            ]);
    }

    /**
     * Séjours validés en cours aujourd'hui : qui est sur le terrain, et combien.
     */
    private function surLeTerrain(): array
    {
        $jour = today()->toDateString();
        $sejours = Demande::query()
            ->whereHas('statut', fn ($q) => $q->where('code', 'validee'))
            ->where('date_debut', '<=', $jour)
            ->where('date_fin', '>=', $jour)
            ->with(['user:id,name', 'site:id,nom'])
            ->orderBy('date_fin')
            ->get(['id', 'user_id', 'site_id', 'date_debut', 'date_fin', 'equipe_nombre', 'created_at']);

        return [
            'sejours' => $sejours->count(),
            'personnes' => (int) $sejours->sum('equipe_nombre'),
            'liste' => $sejours->take(6)->map(fn (Demande $d) => [
                'id' => $d->id,
                'created_at' => $d->created_at,
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
     * Fréquentation autorisée sur les 12 prochains mois, par site :
     * séjours, personnes, et personnes × jours (la pression humaine réelle sur le site).
     */
    private function frequentation(): Collection
    {
        return Demande::query()
            ->whereHas('statut', fn ($q) => $q->where('code', 'validee'))
            ->where('date_fin', '>=', today()->toDateString())
            ->where('date_debut', '<=', today()->addYear()->toDateString())
            ->with('site:id,nom')
            ->get(['id', 'site_id', 'date_debut', 'date_fin', 'equipe_nombre'])
            ->groupBy('site_id')
            ->map(fn ($ds) => [
                'nom' => $ds->first()->site?->nom,
                'sejours' => $ds->count(),
                'personnes' => (int) $ds->sum('equipe_nombre'),
                'personnes_jours' => (int) $ds->sum(fn ($d) => $d->equipe_nombre * ($this->joursEntre($d->date_debut, $d->date_fin) + 1)),
            ])
            ->sortByDesc('personnes_jours')
            ->values();
    }

    /**
     * Demandes reçues par mois : année en cours et année précédente, pour comparaison.
     */
    private function parMois(Builder $base): array
    {
        $annee = (int) date('Y');
        $parMois = ['courante' => array_fill(0, 12, 0), 'precedente' => array_fill(0, 12, 0)];

        (clone $base)
            ->whereYear('demandes.created_at', '>=', $annee - 1)
            ->get(['demandes.created_at'])
            ->each(function (Demande $d) use (&$parMois, $annee) {
                $parMois[$d->created_at->year === $annee ? 'courante' : 'precedente'][$d->created_at->month - 1]++;
            });

        return $parMois;
    }

    /** Nombre de jours calendaires entre deux dates (signé). */
    private function joursEntre($debut, $fin): int
    {
        return (int) round($debut->copy()->startOfDay()->diffInDays($fin->copy()->startOfDay()));
    }

    private function mediane(Collection $decisions): ?float
    {
        return $decisions->isEmpty() ? null : round($decisions->median('jours'), 1);
    }

    private function pourcentage(int $part, int $total): ?int
    {
        return $total > 0 ? (int) round($part / $total * 100) : null;
    }
}

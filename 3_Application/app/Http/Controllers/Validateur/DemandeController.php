<?php

namespace App\Http\Controllers\Validateur;

use App\Http\Controllers\Controller;
use App\Http\Requests\DecisionRequest;
use App\Http\Requests\DocumentsOfficielsRequest;
use App\Models\Demande;
use App\Models\Site;
use App\Models\Statut;
use App\Models\HistoriqueStatut;
use App\Support\DelaiDecision;
use App\Support\DemandeNotifications;
use App\Support\DemandeStatutWorkflow;
use App\Support\DocumentsOfficiels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Instruction des demandes (validateur et admin). Les droits d'accès sont dans DemandePolicy.
 */
class DemandeController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $demandes = $this->filteredQuery($request)
            ->with(['site:id,nom', 'typeDemande:id,libelle', 'statut:id,code,libelle', 'user:id,name'])
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Validateur/Demandes/Index', [
            'demandes' => $demandes,
            'viewAllDemandes' => $user->role === 'admin',
            'filters' => $request->only(['q', 'statut', 'site_id', 'du', 'au']),
            'sites' => $user->role === 'admin' ? Site::orderBy('nom')->get(['id', 'nom']) : $user->sites()->orderBy('nom')->get(['t_sites.id', 't_sites.nom']),
            'statuts' => Statut::where('code', '!=', 'brouillon')->get(['id', 'code', 'libelle']),
        ]);
    }

    /**
     * Export CSV (séparateur « ; » et BOM UTF-8 pour Excel) des demandes correspondant aux filtres affichés.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filteredQuery($request)
            ->with(['site:id,nom', 'typeDemande:id,libelle', 'statut:id,code,libelle', 'user:id,name,email',
                'historiqueStatuts' => fn ($q) => $q->whereHas('nouveauStatut', fn ($s) => $s->whereIn('code', ['validee', 'rejetee'])),
            ])
            ->orderBy('created_at', 'desc');

        $fichier = 'demandes_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM : Excel détecte l'UTF-8 (accents)
            fputcsv($out, [
                'N°', 'Demandeur', 'Email', 'Aire protégée', "Type d'activité", 'Début', 'Fin', 'Équipe',
                'Statut', 'Soumise le', 'Décision le', 'Délai (jours)', 'Chevauchements',
            ], ';');

            $query->chunk(500, function ($demandes) use ($out) {
                foreach ($demandes as $d) {
                    $decision = $d->historiqueStatuts->sortByDesc('date_changement')->first()?->date_changement;
                    fputcsv($out, [
                        $d->id,
                        $d->user?->name,
                        $d->user?->email,
                        $d->site?->nom,
                        $d->typeDemande?->libelle,
                        $d->date_debut?->format('d/m/Y'),
                        $d->date_fin?->format('d/m/Y'),
                        $d->equipe_nombre,
                        $d->statut?->libelle,
                        $d->date_soumission?->format('d/m/Y H:i'),
                        $decision?->format('d/m/Y H:i'),
                        $decision && $d->date_soumission ? $d->date_soumission->copy()->startOfDay()->diffInDays($decision->copy()->startOfDay()) : '',
                        count($d->resultats_analyse ?? []),
                    ], ';');
                }
            });

            fclose($out);
        }, $fichier, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Demandes visibles (hors brouillons, sites assignés pour un validateur) filtrées selon la requête.
     */
    private function filteredQuery(Request $request): Builder
    {
        $user = Auth::user();

        $query = Demande::query()->horsBrouillon();

        if ($user->role !== 'admin') {
            $siteIds = $user->sites()->pluck('t_sites.id');
            $query->whereIn('site_id', $siteIds);
        }

        if ($q = trim((string) $request->input('q'))) {
            $query->where(function (Builder $w) use ($q) {
                $w->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"))
                    ->orWhereHas('site', fn ($s) => $s->where('nom', 'like', "%{$q}%"));
                if (ctype_digit(ltrim($q, '#'))) {
                    $w->orWhere('t_demandes.id', (int) ltrim($q, '#'));
                }
            });
        }

        if ($statut = $request->input('statut')) {
            $query->whereHas('statut', fn ($s) => $s->where('code', $statut));
        }

        if ($siteId = $request->input('site_id')) {
            $query->where('site_id', $siteId);
        }

        // Période : demandes dont le séjour chevauche l'intervalle choisi
        if ($du = $request->date('du')) {
            $query->where('date_fin', '>=', $du->toDateString());
        }
        if ($au = $request->date('au')) {
            $query->where('date_debut', '<=', $au->toDateString());
        }

        return $query;
    }

    public function show(Demande $demande)
    {
        Gate::authorize('instruire', $demande);

        $demande->load([
            'site:id,nom', 'typeDemande.documentsRequis', 'statut', 'user:id,name,email', 'documents',
            'historiqueStatuts' => fn ($q) => $q->orderBy('date_changement')->orderBy('id'),
            'historiqueStatuts.user:id,name,role',
            'historiqueStatuts.nouveauStatut',
        ]);
        $allStatuts = Statut::all();

        $user = Auth::user();
        $statutLocked = DemandeStatutWorkflow::isTerminal($demande->statut);

        return Inertia::render('Validateur/Demandes/Show', [
            'demande' => $demande,
            'statuts' => DemandeStatutWorkflow::allowedTargetStatuts($demande->statut, $allStatuts),
            'viewAllDemandes' => $user->role === 'admin',
            'canDecideOnDemande' => Gate::allows('decider', $demande) && ! $statutLocked,
            'statutLocked' => $statutLocked,
            // Autorisation et cahier des charges générés, en relecture avant la validation signée
            'documentsGeneres' => $statutLocked ? null : DocumentsOfficiels::pourEdition($demande),
            // Estimation de la décision sur la timeline, tant que le dossier est en instruction
            'delaiHabituel' => in_array($demande->statut->code, ['soumise', 'en_analyse'], true)
                ? DelaiDecision::habituelParSite()($demande->site_id)
                : null,
        ]);
    }

    public function updateStatut(DecisionRequest $request, Demande $demande)
    {
        $user = Auth::user();
        $validated = $request->validated();

        $nouveauStatut = Statut::findOrFail($validated['statut_id']);

        // Verrou sur la demande : de deux décisions simultanées, seule la première s'applique,
        // la seconde est contrôlée sur le statut à jour. Statut, historique et notification
        // sont enregistrés ensemble ou pas du tout.
        $erreur = DB::transaction(function () use ($demande, $nouveauStatut, $user, $validated) {
            $demande = Demande::whereKey($demande->id)->lockForUpdate()->firstOrFail();
            $demande->load('statut', 'site');

            if (! DemandeStatutWorkflow::canTransition($demande->statut, $nouveauStatut)) {
                return DemandeStatutWorkflow::isTerminal($demande->statut)
                    ? 'Cette demande est clôturée et ne peut plus être modifiée.'
                    : 'Vous ne pouvez pas rétrograder le statut ni le laisser inchangé.';
            }

            // La validation fige les documents relus : ils doivent avoir été générés.
            if ($nouveauStatut->code === 'validee' && ! DocumentsOfficiels::sontGeneres($demande)) {
                return 'Générez et relisez l\'autorisation et le cahier des charges avant de valider le dossier.';
            }

            // Dossier renvoyé ou refusé : les documents préparés ne servent plus (ils seront regénérés
            // à partir du dossier complété).
            if (in_array($nouveauStatut->code, ['a_completer', 'rejetee'], true)) {
                $demande->documentsOfficiels()->delete();
            }

            $ancienStatutId = $demande->statut_id;

            // Mise à jour de la demande principale
            $demande->update(['statut_id' => $nouveauStatut->id]);

            // Historisation (Phase 6.2)
            HistoriqueStatut::create([
                'demande_id' => $demande->id,
                'user_id' => $user->id,
                'ancien_statut_id' => $ancienStatutId,
                'nouveau_statut_id' => $nouveauStatut->id,
                'commentaire' => $validated['commentaire'],
                'date_changement' => now(),
            ]);

            // Notification du demandeur (Phase 6.3)
            DemandeNotifications::decision($demande, $nouveauStatut, $user, $validated['commentaire']);

            return null;
        });

        if ($erreur !== null) {
            return back()->withErrors(['statut_id' => $erreur]);
        }

        $successMessage = match (true) {
            $nouveauStatut->code === 'validee' => 'Dossier validé et signé. Les documents officiels sont disponibles pour le demandeur.',
            $user->role === 'admin' => 'Décision administrateur enregistrée et historisée.',
            default => 'Le statut a été mis à jour et la décision a été historisée.',
        };

        return redirect()->route('validateur.demandes.show', $demande->id)
                         ->with('message', $successMessage);
    }

    /**
     * Première étape de la validation : prépare l'autorisation et le cahier des charges à relire.
     * Le statut ne change pas ; la validation viendra avec la signature (updateStatut).
     */
    public function genererDocuments(Demande $demande)
    {
        Gate::authorize('decider', $demande);
        $this->verifierValidable($demande);

        DocumentsOfficiels::generer($demande, Auth::user());

        return redirect()->route('validateur.demandes.show', $demande->id)
            ->with('message', 'Documents générés : relisez-les, modifiez-les si besoin, puis signez pour valider le dossier.');
    }

    public function modifierDocuments(DocumentsOfficielsRequest $request, Demande $demande)
    {
        $this->verifierValidable($demande);
        abort_unless(DocumentsOfficiels::sontGeneres($demande), 404);

        DocumentsOfficiels::enregistrer($demande, $request->validated());

        return back()->with('message', 'Modifications des documents enregistrées.');
    }

    /**
     * Abandon de la génération : les textes modifiés sont perdus.
     */
    public function annulerDocuments(Demande $demande)
    {
        Gate::authorize('decider', $demande);
        $this->verifierValidable($demande);

        $demande->documentsOfficiels()->delete();

        return back()->with('message', 'Documents supprimés. Vous pouvez choisir une autre décision.');
    }

    /**
     * Aperçu PDF d'un document en relecture, marqué « projet ».
     */
    public function apercuDocument(Demande $demande, string $type)
    {
        Gate::authorize('decider', $demande);
        abort_unless(isset(DocumentsOfficiels::CHAMPS[$type]), 404);
        abort_unless($demande->documentsOfficiels()->where('type', $type)->exists(), 404);

        return DocumentsOfficiels::pdf($demande, $type, projet: true)
            ->stream('projet_' . DocumentsOfficiels::FICHIERS[$type] . '_' . $demande->id . '.pdf');
    }

    /**
     * Les documents ne se préparent que pour un dossier qui peut encore être validé.
     */
    private function verifierValidable(Demande $demande): void
    {
        $validee = Statut::where('code', 'validee')->first();

        if (! $validee || ! DemandeStatutWorkflow::canTransition($demande->statut, $validee)) {
            abort(403, 'Ce dossier ne peut pas être validé dans son état actuel.');
        }
    }
}

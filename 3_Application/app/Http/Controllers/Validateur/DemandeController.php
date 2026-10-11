<?php

namespace App\Http\Controllers\Validateur;

use App\Http\Controllers\Controller;
use App\Models\Demande;
use App\Models\Site;
use App\Models\Statut;
use App\Models\HistoriqueStatut;
use App\Support\DemandeDecisionRules;
use App\Support\DemandeNotificationMessage;
use App\Support\DemandeStatutWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'sites' => $user->role === 'admin' ? Site::orderBy('nom')->get(['id', 'nom']) : $user->sites()->orderBy('nom')->get(['sites.id', 'sites.nom']),
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
                'N°', 'Demandeur', 'Email', 'Site', "Type d'activité", 'Début', 'Fin', 'Équipe',
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
            $siteIds = $user->sites()->pluck('sites.id');
            $query->whereIn('site_id', $siteIds);
        }

        if ($q = trim((string) $request->input('q'))) {
            $query->where(function (Builder $w) use ($q) {
                $w->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"))
                    ->orWhereHas('site', fn ($s) => $s->where('nom', 'like', "%{$q}%"));
                if (ctype_digit(ltrim($q, '#'))) {
                    $w->orWhere('demandes.id', (int) ltrim($q, '#'));
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
        $this->assertCanAccessDemande(Auth::user(), $demande);

        $demande->load(['site:id,nom', 'typeDemande', 'statut', 'user:id,name,email', 'documents', 'historiqueStatuts.user:id,name,role', 'historiqueStatuts.nouveauStatut']);
        $allStatuts = Statut::all();

        $user = Auth::user();
        $statutLocked = DemandeStatutWorkflow::isTerminal($demande->statut);

        return Inertia::render('Validateur/Demandes/Show', [
            'demande' => $demande,
            'statuts' => DemandeStatutWorkflow::allowedTargetStatuts($demande->statut, $allStatuts),
            'viewAllDemandes' => $user->role === 'admin',
            'canDecideOnDemande' => DemandeDecisionRules::canUserDecide($user, $demande) && ! $statutLocked,
            'statutLocked' => $statutLocked,
        ]);
    }

    public function updateStatut(Request $request, Demande $demande)
    {
        $user = Auth::user();
        $this->assertCanAccessDemande($user, $demande);

        if (! DemandeDecisionRules::canUserDecide($user, $demande)) {
            abort(403, 'Vous ne pouvez pas valider ou modifier le statut de votre propre demande.');
        }

        $validated = $request->validate([
            'statut_id' => 'required|exists:statuts,id',
            'commentaire' => 'required|string|max:1000',
        ]);

        $demande->loadMissing('statut');
        $nouveauStatut = Statut::findOrFail($validated['statut_id']);

        if (! DemandeStatutWorkflow::canTransition($demande->statut, $nouveauStatut)) {
            return back()->withErrors([
                'statut_id' => DemandeStatutWorkflow::isTerminal($demande->statut)
                    ? 'Cette demande est clôturée et ne peut plus être modifiée.'
                    : 'Vous ne pouvez pas rétrograder le statut ni le laisser inchangé.',
            ]);
        }

        $ancienStatutId = $demande->statut_id;
        $nouveauStatutId = $nouveauStatut->id;
        $isAdminDecision = $user->role === 'admin';

        // Mise à jour de la demande principale
        $demande->update(['statut_id' => $nouveauStatutId]);

        // Historisation (Phase 6.2)
        HistoriqueStatut::create([
            'demande_id' => $demande->id,
            'user_id' => $user->id,
            'ancien_statut_id' => $ancienStatutId,
            'nouveau_statut_id' => $nouveauStatutId,
            'commentaire' => $validated['commentaire'],
            'date_changement' => now(),
        ]);

        // Création de la Notification (Phase 6.3)
        $demande->loadMissing('site');

        \App\Models\Notification::create([
            'user_id' => $demande->user_id,
            'demande_id' => $demande->id,
            'contenu' => DemandeNotificationMessage::forStatutChange(
                $demande,
                $nouveauStatut,
                $user,
                $validated['commentaire']
            ),
            'lu' => false,
            'date_envoi' => now(),
        ]);

        $successMessage = $isAdminDecision
            ? 'Décision administrateur enregistrée et historisée.'
            : 'Le statut a été mis à jour et la décision a été historisée.';

        return redirect()->route('validateur.demandes.show', $demande->id)
                         ->with('message', $successMessage);
    }

    private function assertCanAccessDemande($user, Demande $demande): void
    {
        // Un brouillon n'est pas encore soumis : invisible côté validation.
        if ($demande->statut?->code === 'brouillon') {
            abort(404);
        }

        if ($user->role === 'admin') {
            return;
        }

        if (! $user->sites()->whereKey($demande->site_id)->exists()) {
            abort(403, 'Accès non autorisé à cette demande.');
        }
    }
}

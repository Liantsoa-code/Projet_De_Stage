<?php

namespace App\Http\Controllers\Validateur;

use App\Http\Controllers\Controller;
use App\Http\Requests\DecisionRequest;
use App\Http\Requests\DocumentsOfficielsRequest;
use App\Models\Demande;
use App\Models\Signalement;
use App\Models\Site;
use App\Models\Statut;
use App\Models\TypeDemande;
use App\Models\HistoriqueStatut;
use App\Support\AnalyseDemande;
use App\Support\DelaiDecision;
use App\Support\DemandeNotifications;
use App\Support\DemandeStatutWorkflow;
use App\Support\DocumentsOfficiels;
use App\Support\FormatDocument;
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
            // Admin et observateur voient toutes les aires protégées ; l'observateur ne fait que consulter
            'viewAllDemandes' => in_array($user->role, ['admin', 'observateur'], true),
            'lectureSeule' => $user->role === 'observateur',
            'filters' => $request->only(['q', 'du', 'au']) + [
                'statut' => $this->choix($request, 'statut'),
                'site_id' => $this->choix($request, 'site_id'),
                'type_demande_id' => $this->choix($request, 'type_demande_id'),
            ],
            'sites' => $user->role !== 'validateur' ? Site::orderBy('nom')->get(['id', 'nom']) : $user->sites()->orderBy('nom')->get(['t_sites.id', 't_sites.nom']),
            'statuts' => Statut::where('code', '!=', 'brouillon')->get(['id', 'code', 'libelle']),
            'types' => TypeDemande::orderBy('libelle')->get(['id', 'libelle']),
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
                'N°', 'Demandeur', 'Email', 'Aire protégée', 'Titre du projet', 'Type de projet', 'Arrivée sur le terrain', 'Début des activités', 'Fin', 'Équipe',
                'Statut', 'Soumise le', 'Décision le', 'Délai (jours)', 'Analyse', 'Contraintes',
            ], ';');
            $verdicts = ['compatible' => 'Compatible', 'conditions' => 'Compatible sous conditions', 'conflit' => 'Conflit détecté'];

            $query->chunk(500, function ($demandes) use ($out, $verdicts) {
                foreach ($demandes as $d) {
                    $decision = $d->historiqueStatuts->sortByDesc('date_changement')->first()?->date_changement;
                    fputcsv($out, [
                        $d->id,
                        $d->user?->name,
                        $d->user?->email,
                        $d->site?->nom,
                        $d->titre,
                        $d->typeProjet(),
                        $d->arrivee()?->format('d/m/Y'),
                        $d->date_debut?->format('d/m/Y'),
                        $d->date_fin?->format('d/m/Y'),
                        $d->equipe_nombre,
                        $d->statut?->libelle,
                        $d->date_soumission?->format('d/m/Y H:i'),
                        $decision?->format('d/m/Y H:i'),
                        $decision && $d->date_soumission ? $d->date_soumission->copy()->startOfDay()->diffInDays($decision->copy()->startOfDay()) : '',
                        $verdicts[AnalyseDemande::verdict($d->resultats_analyse)],
                        count(AnalyseDemande::contraintesRelevees($d->resultats_analyse)),
                    ], ';');
                }
            });

            fclose($out);
        }, $fichier, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Demandes visibles (hors brouillons ; sites assignés pour un validateur, tout pour un admin ou un observateur)
     * filtrées selon la requête.
     */
    private function filteredQuery(Request $request): Builder
    {
        $user = Auth::user();

        $query = Demande::query()->horsBrouillon();

        if ($user->role === 'validateur') {
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

        // Filtres à choix multiples : une demande correspond si elle a l'une des valeurs cochées
        if ($statuts = $this->choix($request, 'statut')) {
            $query->whereHas('statut', fn ($s) => $s->whereIn('code', $statuts));
        }

        if ($siteIds = $this->choix($request, 'site_id')) {
            $query->whereIn('site_id', $siteIds);
        }

        if ($typeIds = $this->choix($request, 'type_demande_id')) {
            $query->whereIn('type_demande_id', $typeIds);
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

    /**
     * Valeurs cochées d'un filtre multiple (« statut[]=soumise&statut[]=validee »).
     * Une valeur seule (« statut=soumise », liens du tableau de bord) est acceptée aussi.
     */
    private function choix(Request $request, string $cle): array
    {
        return $request->collect($cle)->filter(fn ($v) => is_scalar($v) && $v !== '')->map(fn ($v) => (string) $v)->values()->all();
    }

    public function show(Demande $demande)
    {
        Gate::authorize('suivre', $demande);

        $demande->load([
            'site:id,nom', 'typeDemande.documentsRequis', 'statut', 'documents',
            'user:id,name,email,organisation,fonction,adresse,telephone,piece_identite',
            'historiqueStatuts' => fn ($q) => $q->orderBy('date_changement')->orderBy('id'),
            'historiqueStatuts.user:id,name,role',
            'historiqueStatuts.nouveauStatut',
            'signalements' => fn ($q) => $q->latest()->latest('id'),
            'signalements.auteur:id,name,role',
            'signalements.traitePar:id,name,role',
        ]);
        $allStatuts = Statut::all();

        $user = Auth::user();
        $statutLocked = DemandeStatutWorkflow::isTerminal($demande->statut);

        return Inertia::render('Validateur/Demandes/Show', [
            'demande' => $demande,
            'statuts' => DemandeStatutWorkflow::allowedTargetStatuts($demande->statut, $allStatuts),
            'viewAllDemandes' => in_array($user->role, ['admin', 'observateur'], true),
            // Observateur : tout le dossier en lecture, sans décision ; il peut signaler un problème
            'lectureSeule' => $user->role === 'observateur',
            'signalements' => $demande->signalements->map->pourPage()->values(),
            'partiesSignalement' => Signalement::PARTIES,
            'peutSignaler' => Gate::allows('signaler', $demande),
            'peutTraiterSignalements' => Gate::allows('traiterSignalement', $demande),
            'canDecideOnDemande' => Gate::allows('decider', $demande) && ! $statutLocked,
            'statutLocked' => $statutLocked,
            // Version Word des documents : pas pour un validateur auteur de la demande
            'peutTelechargerWord' => Gate::allows('telechargerWord', $demande),
            // Autorisation, cahier des charges et convention générés, en relecture avant la validation signée
            'documentsGeneres' => $statutLocked || Gate::denies('consulterProjets', $demande) ? null : DocumentsOfficiels::pourEdition($demande),
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
                return 'Générez et relisez l\'autorisation, le cahier des charges et la convention avant de valider le dossier.';
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

            // Notification (cloche + email) du demandeur et de l'équipe du site (Phase 6.3)
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
     * Première étape de la validation : prépare l'autorisation, le cahier des charges et la convention à relire.
     * Le statut ne change pas ; la validation viendra avec la signature (updateStatut).
     */
    public function genererDocuments(Demande $demande)
    {
        Gate::authorize('decider', $demande);
        $this->verifierValidable($demande);

        DocumentsOfficiels::generer($demande, Auth::user());

        return redirect()->route('validateur.demandes.show', $demande->id)
            ->with('message', 'Documents prêts : relisez-les et modifiez-les si besoin. Rien n\'est envoyé au demandeur avant votre signature.');
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
     * Document en relecture (PDF ou Word), marqué « projet ».
     */
    public function apercuDocument(Request $request, Demande $demande, string $type)
    {
        // Lecture du projet : l'équipe qui suit le dossier (observateur compris) ; le Word, modifiable, aux décideurs
        Gate::authorize('consulterProjets', $demande);
        if (FormatDocument::demande($request) === 'docx') {
            Gate::authorize('telechargerWord', $demande);
        }
        abort_unless(isset(DocumentsOfficiels::CHAMPS[$type]), 404);
        abort_unless($demande->documentsOfficiels()->where('type', $type)->exists(), 404);

        return FormatDocument::reponse(
            $request,
            'projet_' . DocumentsOfficiels::FICHIERS[$type] . '_' . $demande->id,
            fn () => DocumentsOfficiels::pdf($demande, $type, projet: true),
            fn () => DocumentsOfficiels::word($demande, $type, projet: true),
        );
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

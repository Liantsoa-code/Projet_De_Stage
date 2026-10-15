<?php

namespace App\Http\Controllers;

use App\Http\Requests\DemandeRequest;
use App\Models\Demande;
use App\Models\HistoriqueStatut;
use App\Models\PeriodeSensible;
use App\Models\Site;
use App\Models\Statut;
use App\Models\TypeDemande;
use App\Support\DelaiDecision;
use App\Support\DemandeDossier;
use App\Support\DemandeNotifications;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Espace demandeur. Les droits d'accès sont dans DemandePolicy, la validation dans DemandeRequest.
 */
class DemandeController extends Controller
{
    public function index(Request $request)
    {
        $statut = $request->string('statut')->toString();

        // Récupère uniquement les demandes de l'utilisateur connecté
        $demandes = Demande::where('user_id', Auth::id())
            ->with(['site:id,nom', 'typeDemande:id,libelle', 'statut:id,code,libelle'])
            ->when($statut !== '', fn ($q) => $q->whereHas('statut', fn ($s) => $s->where('code', $statut)))
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        // Nombre de demandes par statut, pour les onglets de filtre
        $compteurs = Demande::where('demandes.user_id', Auth::id())
            ->join('statuts', 'statuts.id', '=', 'demandes.statut_id')
            ->selectRaw('statuts.code as code, COUNT(*) as total')
            ->groupBy('statuts.code')
            ->pluck('total', 'code');

        return Inertia::render('Demandes/Index', [
            'demandes' => $demandes,
            'compteurs' => $compteurs,
            'filters' => ['statut' => $statut],
        ]);
    }

    public function show(Demande $demande)
    {
        Gate::authorize('view', $demande);

        $demande->load([
            'site:id,nom', 'typeDemande.documentsRequis', 'statut', 'documents',
            'historiqueStatuts' => fn ($q) => $q->orderBy('date_changement')->orderBy('id'),
            'historiqueStatuts.user:id,name,role',
            'historiqueStatuts.nouveauStatut',
        ]);

        // Dernière demande de complément, mise en avant tant que le dossier n'est pas renvoyé
        $complement = $demande->statut->code === 'a_completer'
            ? $demande->historiqueStatuts->last(fn ($h) => $h->nouveauStatut?->code === 'a_completer')
            : null;

        return Inertia::render('Demandes/Show', [
            'demande' => $demande,
            'complement' => $complement,
            // Estimation de la décision sur la timeline, tant que le dossier est en instruction
            'delaiHabituel' => in_array($demande->statut->code, ['soumise', 'en_analyse'], true)
                ? DelaiDecision::habituelParSite()($demande->site_id)
                : null,
            'canEdit' => Gate::allows('update', $demande),
            'canDelete' => Gate::allows('delete', $demande),
        ]);
    }

    public function create()
    {
        return Inertia::render('Demandes/Create', $this->formProps());
    }

    public function store(DemandeRequest $request)
    {
        $soumission = $request->estSoumission();
        $validated = $request->validated();
        $typeDemande = $request->typeDemande();

        $analyse = $this->analyse($validated, $soumission);

        // Si une période bloquante est détectée, on refuse catégoriquement la demande.
        if ($analyse['bloquant']) {
            return back()->withErrors(['date_debut' => 'Impossible : vos dates chevauchent une période d\'interdiction stricte (bloquante) sur ce site.']);
        }

        $demande = DB::transaction(function () use ($request, $validated, $typeDemande, $soumission, $analyse) {
            $statutInitial = $this->statutId($soumission ? 'soumise' : 'brouillon');

            $demande = Demande::create([
                'user_id' => Auth::id(),
                'site_id' => $validated['site_id'],
                'type_demande_id' => $validated['type_demande_id'],
                'description' => $validated['description'] ?? null,
                'date_debut' => $validated['date_debut'] ?? null,
                'date_fin' => $validated['date_fin'] ?? null,
                'equipe_nombre' => $validated['equipe_nombre'] ?? 1,
                'statut_id' => $statutInitial,
                'date_soumission' => $soumission ? now() : null,
                'resultats_analyse' => $analyse['resultats'], // Fige les résultats dans un JSON
            ]);

            DemandeDossier::syncDocuments($demande, $request, $typeDemande);

            // Un brouillon n'entre dans l'historique qu'au moment de sa soumission.
            if ($soumission) {
                $this->historiser($demande, null, $statutInitial);
            }

            return $demande;
        });

        if (! $soumission) {
            return redirect()->route('demandes.show', $demande)->with('message', 'Brouillon enregistré. Vous pourrez le compléter et le soumettre plus tard.');
        }

        DemandeNotifications::soumission($demande);

        return redirect()->route('demandes.show', $demande)->with('message', 'Votre demande a été soumise avec succès.');
    }

    public function edit(Demande $demande)
    {
        Gate::authorize('update', $demande);

        $demande->load(['documents', 'statut']);

        return Inertia::render('Demandes/Create', $this->formProps() + [
            'demande' => $demande,
            'complement' => $demande->statut->code === 'a_completer'
                ? $demande->historiqueStatuts()
                    ->whereHas('nouveauStatut', fn ($q) => $q->where('code', 'a_completer'))
                    ->latest('date_changement')
                    ->value('commentaire')
                : null,
        ]);
    }

    public function update(DemandeRequest $request, Demande $demande)
    {
        $ancienStatut = $demande->statut;
        $renvoiComplement = $ancienStatut->code === 'a_completer';
        $soumission = $request->estSoumission();
        $validated = $request->validated();
        $typeDemande = $request->typeDemande();

        $analyse = $this->analyse($validated, $soumission);

        if ($analyse['bloquant']) {
            return back()->withErrors(['date_debut' => 'Impossible : vos dates chevauchent une période d\'interdiction stricte (bloquante) sur ce site.']);
        }

        $statutChange = DB::transaction(function () use ($request, $demande, $validated, $typeDemande, $soumission, $renvoiComplement, $ancienStatut, $analyse) {
            // Verrou : une décision du validateur prise pendant la saisie ne doit pas être écrasée.
            $statutActuel = Demande::whereKey($demande->id)->lockForUpdate()->value('statut_id');
            if ((int) $statutActuel !== (int) $ancienStatut->id) {
                return true;
            }

            $champs = [
                'site_id' => $validated['site_id'],
                'type_demande_id' => $validated['type_demande_id'],
                'description' => $validated['description'] ?? null,
                'date_debut' => $validated['date_debut'] ?? null,
                'date_fin' => $validated['date_fin'] ?? null,
                'equipe_nombre' => $validated['equipe_nombre'] ?? 1,
                'resultats_analyse' => $analyse['resultats'],
            ];

            if ($renvoiComplement) {
                // Le dossier retourne directement en analyse chez le validateur.
                $champs['statut_id'] = $this->statutId('en_analyse');

                $this->historiser($demande, $ancienStatut->id, $champs['statut_id'],
                    trim($validated['message_complement'] ?? '') ?: 'Compléments fournis par le demandeur.');
            } elseif ($soumission) {
                $champs['statut_id'] = $this->statutId('soumise');
                $champs['date_soumission'] = now();

                $this->historiser($demande, $ancienStatut->id, $champs['statut_id']);
            }

            $demande->update($champs);
            DemandeDossier::syncDocuments($demande, $request, $typeDemande);

            return false;
        });

        if ($statutChange) {
            return redirect()->route('demandes.show', $demande)
                ->with('error', "Le statut de cette demande a changé entre-temps : vos modifications n'ont pas été enregistrées.");
        }

        $demande->refresh();

        if ($renvoiComplement) {
            DemandeNotifications::complementRenvoye($demande);

            return redirect()->route('demandes.show', $demande)->with('message', 'Votre dossier complété a été renvoyé pour examen.');
        }

        if ($soumission) {
            DemandeNotifications::soumission($demande);

            return redirect()->route('demandes.show', $demande)->with('message', 'Votre demande a été soumise avec succès.');
        }

        return redirect()->route('demandes.show', $demande)->with('message', 'Brouillon mis à jour.');
    }

    public function destroy(Demande $demande)
    {
        Gate::authorize('delete', $demande);

        DemandeDossier::deleteDemande($demande);

        return redirect()->route('demandes.index')->with('message', 'Brouillon supprimé.');
    }

    /**
     * Données communes aux formulaires de création et de modification.
     */
    private function formProps(): array
    {
        return [
            'sites' => Site::all(),
            'types' => TypeDemande::with('documentsRequis:id,type_demande_id,libelle')->get(),
            // Périodes à venir, pour alerter le demandeur avant l'envoi
            'periodes' => PeriodeSensible::where('date_fin', '>=', now()->toDateString())
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
     * Analyse des périodes sensibles, uniquement pour une soumission (le brouillon peut être incomplet).
     */
    private function analyse(array $validated, bool $soumission): array
    {
        if (! $soumission) {
            return ['resultats' => [], 'bloquant' => false];
        }

        return DemandeDossier::analysePeriodes(
            (int) $validated['site_id'],
            $validated['date_debut'],
            $validated['date_fin'],
        );
    }

    /**
     * RG09 : tout changement de statut est conservé, y compris la soumission par le demandeur.
     */
    private function historiser(Demande $demande, ?int $ancienStatutId, int $nouveauStatutId, ?string $commentaire = null): void
    {
        HistoriqueStatut::create([
            'demande_id' => $demande->id,
            'user_id' => Auth::id(),
            'ancien_statut_id' => $ancienStatutId,
            'nouveau_statut_id' => $nouveauStatutId,
            'commentaire' => $commentaire,
            'date_changement' => now(),
        ]);
    }

    private function statutId(string $code): int
    {
        return Statut::where('code', $code)->value('id');
    }
}

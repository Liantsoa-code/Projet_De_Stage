<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use App\Models\HistoriqueStatut;
use App\Models\Site;
use App\Models\TypeDemande;
use App\Models\Statut;
use App\Models\User;
use App\Models\PeriodeSensible;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Support\DemandeDecisionRules;
use App\Support\DemandeDossier;
use App\Support\DemandeNotificationMessage;
use App\Support\DemandeStatutWorkflow;

class DemandeController extends Controller
{
    public function index(Request $request)
    {
        $statut = $request->string('statut')->toString();

        // Récupère uniquement les demandes de l'utilisateur connecté
        $demandes = Demande::where('user_id', Auth::id())
            ->with(['site', 'typeDemande', 'statut'])
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
        $this->assertOwner($demande);

        $demande->load([
            'site', 'typeDemande', 'statut', 'documents',
            'historiqueStatuts' => fn ($q) => $q->orderBy('date_changement'),
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
            'canEdit' => DemandeStatutWorkflow::isEditableByDemandeur($demande->statut),
            'canDelete' => $demande->statut->code === 'brouillon',
        ]);
    }

    public function create()
    {
        return Inertia::render('Demandes/Create', $this->formProps());
    }

    public function store(Request $request)
    {
        $soumission = $request->input('action') !== 'brouillon';
        [$validated, $typeDemande] = $this->validateDemande($request, null, $soumission);

        $analyse = $this->analyse($validated, $soumission);

        // Si une période bloquante est détectée, on refuse catégoriquement la demande.
        if ($analyse['bloquant']) {
            return back()->withErrors(['date_debut' => 'Impossible : vos dates chevauchent une période d\'interdiction stricte (bloquante) sur ce site.']);
        }

        $demande = DB::transaction(function () use ($request, $validated, $typeDemande, $soumission, $analyse) {
            $demande = Demande::create([
                'user_id' => Auth::id(),
                'site_id' => $validated['site_id'],
                'type_demande_id' => $validated['type_demande_id'],
                'description' => $validated['description'] ?? null,
                'date_debut' => $validated['date_debut'] ?? null,
                'date_fin' => $validated['date_fin'] ?? null,
                'equipe_nombre' => $validated['equipe_nombre'] ?? 1,
                'statut_id' => $this->statutId($soumission ? 'soumise' : 'brouillon'),
                'date_soumission' => $soumission ? now() : null,
                'resultats_analyse' => $analyse['resultats'], // Fige les résultats dans un JSON
            ]);

            DemandeDossier::syncDocuments($demande, $request, $typeDemande);

            return $demande;
        });

        if (! $soumission) {
            return redirect()->route('demandes.show', $demande)->with('message', 'Brouillon enregistré. Vous pourrez le compléter et le soumettre plus tard.');
        }

        $this->notifySubmission($demande);

        return redirect()->route('demandes.show', $demande)->with('message', 'Votre demande a été soumise avec succès.');
    }

    public function edit(Demande $demande)
    {
        $this->assertEditable($demande);

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

    public function update(Request $request, Demande $demande)
    {
        $this->assertEditable($demande);

        $ancienStatut = $demande->statut;
        $renvoiComplement = $ancienStatut->code === 'a_completer';
        // Un dossier à compléter ne peut pas repasser en brouillon : il est forcément renvoyé.
        $soumission = $renvoiComplement || $request->input('action') !== 'brouillon';

        [$validated, $typeDemande] = $this->validateDemande($request, $demande, $soumission);

        $analyse = $this->analyse($validated, $soumission);

        if ($analyse['bloquant']) {
            return back()->withErrors(['date_debut' => 'Impossible : vos dates chevauchent une période d\'interdiction stricte (bloquante) sur ce site.']);
        }

        DB::transaction(function () use ($request, $demande, $validated, $typeDemande, $soumission, $renvoiComplement, $ancienStatut, $analyse) {
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

                HistoriqueStatut::create([
                    'demande_id' => $demande->id,
                    'user_id' => Auth::id(),
                    'ancien_statut_id' => $ancienStatut->id,
                    'nouveau_statut_id' => $champs['statut_id'],
                    'commentaire' => trim($validated['message_complement'] ?? '') ?: 'Compléments fournis par le demandeur.',
                    'date_changement' => now(),
                ]);
            } elseif ($soumission) {
                $champs['statut_id'] = $this->statutId('soumise');
                $champs['date_soumission'] = now();
            }

            $demande->update($champs);
            DemandeDossier::syncDocuments($demande, $request, $typeDemande);
        });

        $demande->refresh();

        if ($renvoiComplement) {
            $this->notifyComplement($demande);

            return redirect()->route('demandes.show', $demande)->with('message', 'Votre dossier complété a été renvoyé pour examen.');
        }

        if ($soumission) {
            $this->notifySubmission($demande);

            return redirect()->route('demandes.show', $demande)->with('message', 'Votre demande a été soumise avec succès.');
        }

        return redirect()->route('demandes.show', $demande)->with('message', 'Brouillon mis à jour.');
    }

    public function destroy(Demande $demande)
    {
        $this->assertOwner($demande);

        if ($demande->statut?->code !== 'brouillon') {
            abort(403, 'Seuls les brouillons peuvent être supprimés.');
        }

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
            'types' => TypeDemande::all(),
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
     * Brouillon : seuls le site et le type sont obligatoires.
     * Soumission : tous les champs et toutes les pièces (sauf celles déjà fournies).
     *
     * @return array{0: array<string, mixed>, 1: TypeDemande}
     */
    private function validateDemande(Request $request, ?Demande $demande, bool $soumission): array
    {
        $requis = $soumission ? 'required' : 'nullable';

        $rules = [
            'site_id' => 'required|exists:sites,id',
            'type_demande_id' => 'required|exists:type_demandes,id',
            'date_debut' => "$requis|date|after_or_equal:today",
            'date_fin' => "$requis|date" . ($request->filled('date_debut') ? '|after_or_equal:date_debut' : ''),
            'equipe_nombre' => "$requis|integer|min:1",
            'description' => "$requis|string",
            'message_complement' => 'nullable|string|max:1000',
        ];
        $messages = [
            'date_debut.after_or_equal' => "La date de début ne peut pas être antérieure à aujourd'hui.",
            'date_fin.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ];

        // Vérification automatique et dynamique des documents (Phase 5 - Point 6)
        $typeDemande = TypeDemande::find($request->input('type_demande_id'));
        $dejaFournis = $demande ? $demande->documents()->pluck('type_document')->all() : [];

        foreach ($typeDemande?->documents_requis ?? [] as $index => $docName) {
            $key = 'doc_' . $index;
            $obligatoire = $soumission && ! in_array($docName, $dejaFournis, true);
            $rules[$key] = ($obligatoire ? 'required' : 'nullable') . '|file|mimes:' . DemandeDossier::EXTENSIONS_AUTORISEES . '|max:20480'; // Limité à 20 Mo par fichier
            $messages[$key.'.required'] = "Le document '$docName' est obligatoire.";
            $messages[$key.'.file'] = "Le document '$docName' doit être un fichier valide.";
            $messages[$key.'.mimes'] = "Le document '$docName' doit être au format PDF, image (JPG, PNG) ou Word/OpenDocument.";
            $messages[$key.'.max'] = "Le fichier '$docName' ne doit pas dépasser 20 Mo.";
        }

        $validated = $request->validate($rules, $messages);

        return [$validated, $typeDemande];
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

    private function notifySubmission(Demande $demande): void
    {
        $demande->load(['site', 'user', 'typeDemande']);

        \App\Models\Notification::create([
            'user_id' => $demande->user_id,
            'demande_id' => $demande->id,
            'contenu' => DemandeNotificationMessage::forSubmission($demande),
            'lu' => false,
            'date_envoi' => now(),
        ]);

        $this->notifyDeciders(
            DemandeDecisionRules::notifiableDecidersForNewDemande($demande),
            $demande,
            DemandeNotificationMessage::forNewDemandeToValidateur($demande),
        );
    }

    /**
     * Prévient les décideurs du site ainsi que la personne qui a demandé les compléments.
     */
    private function notifyComplement(Demande $demande): void
    {
        $demandeurDuComplement = $demande->historiqueStatuts()
            ->whereHas('nouveauStatut', fn ($q) => $q->where('code', 'a_completer'))
            ->latest('date_changement')
            ->first()?->user;

        $decideurs = DemandeDecisionRules::notifiableDecidersForNewDemande($demande)
            ->when($demandeurDuComplement, fn ($c) => $c->push($demandeurDuComplement))
            ->unique('id')
            ->reject(fn (User $u) => (int) $u->id === (int) $demande->user_id);

        $this->notifyDeciders($decideurs, $demande, DemandeNotificationMessage::forComplementToValidateur($demande));
    }

    private function notifyDeciders($decideurs, Demande $demande, string $message): void
    {
        $envoi = now();

        $decideurs->each(function (User $decideur) use ($demande, $message, $envoi) {
            \App\Models\Notification::create([
                'user_id' => $decideur->id,
                'demande_id' => $demande->id,
                'contenu' => $message,
                'lu' => false,
                'date_envoi' => $envoi,
            ]);
        });
    }

    private function statutId(string $code): int
    {
        return Statut::where('code', $code)->value('id');
    }

    private function assertOwner(Demande $demande): void
    {
        if ((int) $demande->user_id !== (int) Auth::id()) {
            abort(403, 'Accès non autorisé à cette demande.');
        }
    }

    private function assertEditable(Demande $demande): void
    {
        $this->assertOwner($demande);

        if (! DemandeStatutWorkflow::isEditableByDemandeur($demande->statut)) {
            abort(403, 'Cette demande ne peut plus être modifiée.');
        }
    }
}

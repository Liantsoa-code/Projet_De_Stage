<?php

namespace Tests\Feature;

use App\Models\Demande;
use App\Models\HistoriqueStatut;
use App\Models\Site;
use App\Models\Statut;
use App\Models\TypeDemande;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Données de la timeline de suivi : historique ordonné et estimation de la décision.
 */
class SuiviDemandeTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;
    private Site $autreSite;
    private TypeDemande $type;
    private User $demandeur;
    private User $validateur;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['soumise' => 'Soumise', 'en_analyse' => 'En analyse', 'a_completer' => 'À compléter', 'validee' => 'Validée', 'rejetee' => 'Rejetée'] as $code => $libelle) {
            Statut::firstOrCreate(['code' => $code], ['libelle' => $libelle, 'description' => '']);
        }

        $this->site = Site::create(['nom' => 'Andasibe', 'description' => '', 'polygone_coordonnees' => []]);
        $this->autreSite = Site::create(['nom' => 'Isalo', 'description' => '', 'polygone_coordonnees' => []]);
        $this->type = TypeDemande::create(['libelle' => 'Recherche']);
        $this->demandeur = User::factory()->create(['role' => 'demandeur']);
        $this->validateur = User::factory()->create(['role' => 'validateur']);
        $this->validateur->sites()->attach([$this->site->id, $this->autreSite->id]);
    }

    private function statut(string $code): int
    {
        return Statut::where('code', $code)->value('id');
    }

    private function makeDemande(string $statut, array $attrs = []): Demande
    {
        return Demande::create(array_merge([
            'user_id' => $this->demandeur->id,
            'site_id' => $this->site->id,
            'type_demande_id' => $this->type->id,
            'description' => 'Test',
            'date_debut' => now()->addDays(40)->toDateString(),
            'date_fin' => now()->addDays(42)->toDateString(),
            'equipe_nombre' => 2,
            'statut_id' => $this->statut($statut),
            'date_soumission' => now(),
            'resultats_analyse' => [],
        ], $attrs));
    }

    /** Dossier validé aujourd'hui, N jours après sa soumission. */
    private function decision(int $jours, ?Site $site = null): void
    {
        $demande = $this->makeDemande('validee', [
            'site_id' => ($site ?? $this->site)->id,
            'date_soumission' => now()->subDays($jours),
        ]);
        HistoriqueStatut::create([
            'demande_id' => $demande->id, 'user_id' => $this->validateur->id,
            'ancien_statut_id' => $this->statut('en_analyse'), 'nouveau_statut_id' => $this->statut('validee'),
            'commentaire' => 'Décision', 'date_changement' => now(),
        ]);
    }

    public function test_demandeur_voit_le_delai_habituel_du_site_pour_un_dossier_en_instruction(): void
    {
        foreach ([4, 6, 10] as $jours) {
            $this->decision($jours);
        }
        $this->decision(30, $this->autreSite);
        $demande = $this->makeDemande('en_analyse');

        $this->actingAs($this->demandeur)
            ->get(route('demandes.show', $demande))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Demandes/Show')
                ->where('delaiHabituel', 6)); // médiane du site (3 décisions), pas celle de la plateforme
    }

    public function test_le_delai_de_la_plateforme_sert_quand_le_site_compte_trop_peu_de_decisions(): void
    {
        $this->decision(4, $this->autreSite);
        $this->decision(8, $this->autreSite);
        $demande = $this->makeDemande('soumise');

        $this->actingAs($this->validateur)
            ->get(route('validateur.demandes.show', $demande))
            ->assertInertia(fn (Assert $page) => $page->where('delaiHabituel', 6));
    }

    public function test_aucune_estimation_pour_un_dossier_decide(): void
    {
        $this->decision(5);
        $demande = $this->makeDemande('validee');

        $this->actingAs($this->demandeur)
            ->get(route('demandes.show', $demande))
            ->assertInertia(fn (Assert $page) => $page->where('delaiHabituel', null));
    }

    public function test_l_historique_est_transmis_dans_l_ordre_chronologique(): void
    {
        $demande = $this->makeDemande('en_analyse');
        // Enregistrées dans le désordre : la timeline doit les recevoir triées par date
        foreach ([[2, 'en_analyse', 'a_completer'], [0, null, 'soumise'], [1, 'soumise', 'a_completer'], [3, 'a_completer', 'en_analyse']] as [$jour, $ancien, $nouveau]) {
            HistoriqueStatut::create([
                'demande_id' => $demande->id, 'user_id' => $this->validateur->id,
                'ancien_statut_id' => $ancien ? $this->statut($ancien) : null, 'nouveau_statut_id' => $this->statut($nouveau),
                'commentaire' => "Jour $jour", 'date_changement' => now()->subDays(10 - $jour),
            ]);
        }

        $attendu = fn (Assert $page) => $page
            ->where('demande.historique_statuts.0.commentaire', 'Jour 0')
            ->where('demande.historique_statuts.1.commentaire', 'Jour 1')
            ->where('demande.historique_statuts.2.commentaire', 'Jour 2')
            ->where('demande.historique_statuts.3.commentaire', 'Jour 3');

        $this->actingAs($this->demandeur)->get(route('demandes.show', $demande))->assertInertia($attendu);
        $this->actingAs($this->validateur)->get(route('validateur.demandes.show', $demande))->assertInertia($attendu);
    }
}

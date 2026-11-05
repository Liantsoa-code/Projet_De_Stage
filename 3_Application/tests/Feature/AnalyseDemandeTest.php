<?php

namespace Tests\Feature;

use App\Models\Demande;
use App\Models\HistoriqueStatut;
use App\Models\PeriodeSensible;
use App\Models\Site;
use App\Models\Statut;
use App\Models\TypeDemande;
use App\Models\User;
use App\Support\AnalyseDemande;
use App\Support\DocumentsOfficiels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Moteur d'analyse des demandes : règles, verdict, analyse en direct du formulaire,
 * enregistrement à l'envoi et conditions proposées dans l'autorisation.
 */
class AnalyseDemandeTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;
    private TypeDemande $type;
    private User $demandeur;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        foreach (['soumise' => 'Soumise', 'en_analyse' => 'En analyse', 'validee' => 'Validée', 'rejetee' => 'Rejetée'] as $code => $libelle) {
            Statut::firstOrCreate(['code' => $code], ['libelle' => $libelle, 'description' => '']);
        }

        $this->site = Site::create(['nom' => 'Lokobe', 'description' => '', 'polygone_coordonnees' => []]);
        $this->type = TypeDemande::create(['libelle' => 'Documentaire']);
        $this->demandeur = User::factory()->create(['role' => 'demandeur']);
    }

    /** Analyse d'un séjour de $duree jours commençant dans $dans jours. */
    private function analyser(int $dans = 20, int $duree = 3, int $equipe = 2, ?int $demandeId = null): array
    {
        return AnalyseDemande::analyser([
            'site_id' => $this->site->id,
            'date_debut' => now()->addDays($dans)->toDateString(),
            'date_fin' => now()->addDays($dans + $duree - 1)->toDateString(),
            'equipe_nombre' => $equipe,
        ], $this->demandeur->id, $demandeId);
    }

    private function regles(array $analyse): array
    {
        return array_column($analyse['contraintes'], 'regle');
    }

    private function periode(int $du, int $au, string $niveau = 'avertissement', ?string $condition = null): PeriodeSensible
    {
        return PeriodeSensible::create([
            'site_id' => $this->site->id,
            'date_debut' => now()->addDays($du)->toDateString(),
            'date_fin' => now()->addDays($au)->toDateString(),
            'description' => 'Reproduction',
            'niveau_restriction' => $niveau,
            'condition_proposee' => $condition,
        ]);
    }

    private function demande(string $statut, array $attrs = []): Demande
    {
        return Demande::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'demandeur'])->id,
            'site_id' => $this->site->id,
            'type_demande_id' => $this->type->id,
            'date_debut' => now()->addDays(20)->toDateString(),
            'date_fin' => now()->addDays(22)->toDateString(),
            'equipe_nombre' => 4,
            'statut_id' => Statut::where('code', $statut)->value('id'),
            'date_soumission' => $statut === 'brouillon' ? null : now(),
            'resultats_analyse' => [],
        ], $attrs));
    }

    // ── Verdict ──

    public function test_stay_without_constraint_is_compatible(): void
    {
        $analyse = $this->analyser();

        $this->assertSame('compatible', $analyse['verdict']);
        $this->assertFalse($analyse['bloquant']);
        $this->assertSame([], $analyse['contraintes']);
    }

    public function test_verdict_reads_analyses_recorded_before_the_engine(): void
    {
        $this->assertSame('conditions', AnalyseDemande::verdict([['type' => 'avertissement', 'message' => 'Chevauchement']]));
        $this->assertSame('compatible', AnalyseDemande::verdict(null));
        $this->assertSame('compatible', AnalyseDemande::verdict([['niveau' => 'info', 'message' => 'À savoir']]));
    }

    // ── Règles 1 et 2 : périodes sensibles ──

    public function test_warning_period_proposes_its_condition_on_the_shared_days(): void
    {
        $this->periode(21, 40, condition: 'Aucune approche à moins de 30 m');

        $analyse = $this->analyser(dans: 20, duree: 3);

        $this->assertSame('conditions', $analyse['verdict']);
        $contrainte = $analyse['contraintes'][0];
        $this->assertSame(['periode', 'attention'], [$contrainte['regle'], $contrainte['niveau']]);
        $this->assertStringContainsString('pendant 2 jours', $contrainte['message']);
        $this->assertSame('Aucune approche à moins de 30 m', $contrainte['condition']);
    }

    public function test_warning_period_without_condition_gets_a_general_one(): void
    {
        $this->periode(15, 40);

        $condition = $this->analyser()['contraintes'][0]['condition'];

        $this->assertStringContainsString('consignes du gestionnaire', $condition);
        $this->assertStringContainsString(now()->addDays(20)->format('d/m/Y'), $condition);
    }

    public function test_blocking_period_blocks_and_gives_a_conflict(): void
    {
        $this->periode(10, 25, 'bloquant');

        $analyse = $this->analyser();

        $this->assertTrue($analyse['bloquant']);
        $this->assertSame('conflit', $analyse['verdict']);
        $this->assertNull($analyse['contraintes'][0]['condition']);
    }

    public function test_period_starting_soon_after_departure_forbids_extension(): void
    {
        $this->periode(25, 40);

        $analyse = $this->analyser(dans: 20, duree: 3); // départ à J+22, période à J+25

        $this->assertSame('compatible', $analyse['verdict']);
        $this->assertSame(['periode_proche'], $this->regles($analyse));
        $this->assertStringContainsString(now()->addDays(24)->format('d/m/Y'), $analyse['contraintes'][0]['condition']);
        $this->assertSame([], $this->regles($this->analyser(dans: 5, duree: 3)));
    }

    // ── Règles 3 et 4 : capacité et fréquentation ──

    public function test_team_larger_than_capacity_takes_turns(): void
    {
        $this->site->update(['capacite_accueil' => 4]);

        $analyse = $this->analyser(equipe: 5);

        $this->assertSame(['capacite'], $this->regles($analyse));
        $this->assertSame('conditions', $analyse['verdict']);
        $this->assertStringContainsString('Pas plus de 4', $analyse['contraintes'][0]['condition']);
    }

    public function test_attendance_is_measured_on_the_busiest_day(): void
    {
        $this->site->update(['capacite_accueil' => 8]);
        $this->demande('validee', ['date_debut' => now()->addDays(18)->toDateString(), 'date_fin' => now()->addDays(20)->toDateString(), 'equipe_nombre' => 3]);
        $this->demande('validee', ['date_debut' => now()->addDays(21)->toDateString(), 'date_fin' => now()->addDays(25)->toDateString(), 'equipe_nombre' => 5]);
        $this->demande('soumise', ['equipe_nombre' => 9]); // pas encore autorisée : ne compte pas

        $analyse = $this->analyser(dans: 20, duree: 3, equipe: 4);

        $contrainte = $analyse['contraintes'][0];
        $this->assertSame(['frequentation', 'attention'], [$contrainte['regle'], $contrainte['niveau']]);
        $this->assertStringContainsString('5 personnes déjà autorisées', $contrainte['message']);
        $this->assertStringContainsString(now()->addDays(21)->format('d/m/Y'), $contrainte['message']);
        // 5 déjà présents le jour le plus chargé + 4 dépassent 8 : il reste 3 places
        $this->assertStringContainsString('pas plus de 3 membres', $contrainte['condition']);
    }

    public function test_full_protected_area_is_a_conflict_left_to_the_validator(): void
    {
        $this->site->update(['capacite_accueil' => 6]);
        $this->demande('validee', ['equipe_nombre' => 6]);

        $analyse = $this->analyser(equipe: 1);

        $this->assertSame('conflit', $analyse['verdict']);
        $this->assertFalse($analyse['bloquant']);
    }

    public function test_attendance_without_capacity_is_only_information(): void
    {
        $this->demande('validee', ['equipe_nombre' => 30]);

        $analyse = $this->analyser();

        $this->assertSame('compatible', $analyse['verdict']);
        $this->assertSame(['frequentation'], $this->regles($analyse));
    }

    // ── Règles 5 et 6 : équipe nombreuse et séjour long ──

    public function test_large_team_and_long_stay_bring_their_conditions(): void
    {
        $this->assertSame([], $this->regles($this->analyser(duree: AnalyseDemande::DUREE_LONGUE, equipe: AnalyseDemande::EFFECTIF_ACCOMPAGNATEUR - 1)));

        $analyse = $this->analyser(duree: AnalyseDemande::DUREE_LONGUE + 1, equipe: AnalyseDemande::EFFECTIF_ACCOMPAGNATEUR);

        $this->assertSame(['accompagnateur', 'duree'], $this->regles($analyse));
        $this->assertCount(2, AnalyseDemande::conditions($analyse['contraintes']));
    }

    // ── Règle 7 : arrivée proche ──

    public function test_arrival_before_the_usual_decision_time_is_reported(): void
    {
        $decidee = $this->demande('validee', ['date_soumission' => now()->subDays(20)]);
        HistoriqueStatut::create([
            'demande_id' => $decidee->id,
            'user_id' => null,
            'ancien_statut_id' => Statut::where('code', 'en_analyse')->value('id'),
            'nouveau_statut_id' => $decidee->statut_id,
            'date_changement' => now()->subDays(10),
        ]);

        $this->assertContains('delai', $this->regles($this->analyser(dans: 5)));
        $this->assertNotContains('delai', $this->regles($this->analyser(dans: 15)));
        $this->assertSame('compatible', AnalyseDemande::verdict($this->analyser(dans: 5)['contraintes']));
    }

    // ── Règles 8 et 9 : autre demande et règles du site ──

    public function test_other_request_of_the_same_applicant_is_reported_except_itself(): void
    {
        $autre = $this->demande('soumise', ['user_id' => $this->demandeur->id]);
        $this->demande('rejetee', ['user_id' => $this->demandeur->id]);

        $analyse = $this->analyser();

        $this->assertSame(['doublon'], $this->regles($analyse));
        $this->assertStringContainsString($autre->reference, $analyse['contraintes'][0]['message']);
        $this->assertSame([], $this->regles($this->analyser(demandeId: $autre->id)));
    }

    public function test_permanent_rules_of_the_site_are_proposed_without_changing_the_verdict(): void
    {
        $this->site->conditions()->create(['libelle' => 'Guide local obligatoire']);

        $analyse = $this->analyser();

        $this->assertSame('compatible', $analyse['verdict']);
        $this->assertSame(['Guide local obligatoire'], AnalyseDemande::conditions($analyse['contraintes']));
    }

    public function test_constraints_are_sorted_from_the_most_serious(): void
    {
        $this->site->conditions()->create(['libelle' => 'Guide local obligatoire']);
        $this->site->update(['capacite_accueil' => 3]);
        $this->demande('validee', ['equipe_nombre' => 3]);

        $niveaux = array_column($this->analyser(equipe: 4)['contraintes'], 'niveau');

        $this->assertSame(['conflit', 'attention', 'info'], $niveaux);
    }

    // ── Formulaire, envoi et documents ──

    public function test_form_gets_the_analysis_live(): void
    {
        $this->site->update(['capacite_accueil' => 2]);

        $this->actingAs($this->demandeur)
            ->postJson(route('demandes.analyse'), [
                'site_id' => $this->site->id,
                'date_debut' => now()->addDays(20)->toDateString(),
                'date_fin' => now()->addDays(22)->toDateString(),
                'equipe_nombre' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('verdict', 'conditions')
            ->assertJsonPath('contraintes.0.regle', 'capacite');

        $this->actingAs($this->demandeur)
            ->postJson(route('demandes.analyse'), ['site_id' => $this->site->id, 'date_debut' => now()->addDays(5)->toDateString(), 'date_fin' => now()->toDateString()])
            ->assertUnprocessable();
    }

    public function test_live_analysis_requires_an_account(): void
    {
        $this->postJson(route('demandes.analyse'), ['site_id' => $this->site->id])->assertUnauthorized();
    }

    public function test_submission_records_the_analysis_and_documents_propose_its_conditions(): void
    {
        $piece = $this->type->documentsRequis()->create(['libelle' => 'Synopsis']);
        $this->site->conditions()->create(['libelle' => 'Guide local obligatoire']);
        $this->periode(4, 30, condition: 'Prises de vue sans flash');

        $this->actingAs($this->demandeur)
            ->post(route('demandes.store'), array_merge($this->champsFormulaire($this->demandeur), [
                'site_id' => $this->site->id,
                'type_demande_id' => $this->type->id,
                'date_debut' => now()->addDays(5)->toDateString(),
                'date_fin' => now()->addDays(7)->toDateString(),
                'equipe_nombre' => 3,
                'description' => 'Tournage',
                'doc_' . $piece->id => UploadedFile::fake()->create('synopsis.pdf', 100, 'application/pdf'),
            ]))
            ->assertSessionHasNoErrors();

        $demande = Demande::firstOrFail();
        $this->assertSame('conditions', AnalyseDemande::verdict($demande->resultats_analyse));
        $this->assertSame(['periode', 'regle_site'], array_column($demande->resultats_analyse, 'regle'));
        // Le cahier des charges ne reprend que les contraintes, l'autorisation toutes les conditions
        $this->assertCount(1, AnalyseDemande::contraintesRelevees($demande->resultats_analyse));
        $this->assertSame("Prises de vue sans flash\nGuide local obligatoire", DocumentsOfficiels::defauts($demande, 'autorisation')['conditions']);
    }

    // ── Administration des règles ──

    public function test_admin_sets_capacity_and_permanent_rules_of_a_site(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $gardee = $this->site->conditions()->create(['libelle' => 'Ancien texte']);
        $this->site->conditions()->create(['libelle' => 'À retirer']);

        $this->actingAs($admin)
            ->put(route('admin.sites.update', $this->site), [
                'nom' => 'Lokobe',
                'capacite_accueil' => 12,
                'conditions' => [['id' => $gardee->id, 'libelle' => 'Nouveau texte'], ['libelle' => 'Règle ajoutée']],
            ])
            ->assertSessionHasNoErrors();

        $this->site->refresh();
        $this->assertSame(12, $this->site->capacite_accueil);
        $this->assertSame(['Nouveau texte', 'Règle ajoutée'], $this->site->conditions()->pluck('libelle')->all());
        $this->assertSame('Nouveau texte', $gardee->fresh()->libelle);
    }

    public function test_condition_of_a_blocking_period_is_not_kept(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $champs = [
            'site_id' => $this->site->id,
            'date_debut' => now()->addDays(10)->toDateString(),
            'date_fin' => now()->addDays(20)->toDateString(),
            'description' => 'Fermeture',
            'condition_proposee' => 'Sans objet',
        ];

        $this->actingAs($admin)->post(route('admin.periode-sensibles.store'), $champs + ['niveau_restriction' => 'bloquant'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.periode-sensibles.store'), $champs + ['niveau_restriction' => 'avertissement'])->assertSessionHasNoErrors();

        $this->assertSame([null, 'Sans objet'], PeriodeSensible::orderBy('id')->pluck('condition_proposee')->all());
    }
}

<?php

namespace Tests\Unit;

use App\Models\Demande;
use App\Models\Site;
use App\Models\Statut;
use App\Models\TypeDemande;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class DemandePolicyTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        // « brouillon » et « a_completer » sont déjà créés par une migration
        foreach (['brouillon', 'soumise', 'a_completer', 'validee'] as $code) {
            Statut::firstOrCreate(['code' => $code], ['libelle' => $code, 'description' => '']);
        }

        $this->site = Site::create(['nom' => 'Site A', 'description' => '', 'polygone_coordonnees' => []]);
    }

    private function makeDemande(User $creator, string $statut = 'soumise', ?Site $site = null): Demande
    {
        return Demande::create([
            'user_id' => $creator->id,
            'site_id' => ($site ?? $this->site)->id,
            'type_demande_id' => TypeDemande::firstOrCreate(['libelle' => 'Test'])->id,
            'description' => 'Test',
            'date_debut' => now()->toDateString(),
            'date_fin' => now()->addDay()->toDateString(),
            'equipe_nombre' => 1,
            'statut_id' => Statut::where('code', $statut)->value('id'),
            'date_soumission' => now(),
        ]);
    }

    private function validateurDuSite(?Site $site = null): User
    {
        $validateur = User::factory()->create(['role' => 'validateur']);
        $validateur->sites()->attach($site ?? $this->site);

        return $validateur;
    }

    // ── Décision : jamais sur sa propre demande ──

    public function test_admin_cannot_decide_on_own_demande(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $demande = $this->makeDemande($admin);

        $this->assertFalse($admin->can('decider', $demande));
    }

    public function test_other_admin_can_decide_on_admin_demande(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $demande = $this->makeDemande($admin);

        $this->assertTrue($otherAdmin->can('decider', $demande));
    }

    public function test_site_validateur_can_decide_on_admin_demande(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $demande = $this->makeDemande($admin);

        $this->assertTrue($this->validateurDuSite()->can('decider', $demande));
    }

    public function test_validateur_cannot_decide_on_own_demande(): void
    {
        $validateur = $this->validateurDuSite();
        $demande = $this->makeDemande($validateur);

        $this->assertFalse($validateur->can('decider', $demande));
    }

    // ── Instruction : sites assignés, brouillons invisibles ──

    public function test_validateur_of_another_site_cannot_open_the_file(): void
    {
        $autreSite = Site::create(['nom' => 'Site B', 'description' => '', 'polygone_coordonnees' => []]);
        $demande = $this->makeDemande(User::factory()->create(['role' => 'demandeur']));

        $this->assertFalse($this->validateurDuSite($autreSite)->can('instruire', $demande));
        $this->assertFalse($this->validateurDuSite($autreSite)->can('consulterFichiers', $demande));
    }

    public function test_draft_does_not_exist_for_deciders_but_stays_readable_by_its_author(): void
    {
        $demandeur = User::factory()->create(['role' => 'demandeur']);
        $admin = User::factory()->create(['role' => 'admin']);
        $brouillon = $this->makeDemande($demandeur, 'brouillon');

        $reponse = Gate::forUser($admin)->inspect('instruire', $brouillon);
        $this->assertTrue($reponse->denied());
        $this->assertSame(404, $reponse->status());
        $this->assertFalse($admin->can('consulterFichiers', $brouillon));
        $this->assertTrue($demandeur->can('consulterFichiers', $brouillon));
    }

    // ── Espace demandeur : modification et suppression selon le statut ──

    public function test_author_edits_only_drafts_and_files_to_complete_and_deletes_only_drafts(): void
    {
        $demandeur = User::factory()->create(['role' => 'demandeur']);

        $this->assertTrue($demandeur->can('update', $this->makeDemande($demandeur, 'brouillon')));
        $this->assertTrue($demandeur->can('update', $this->makeDemande($demandeur, 'a_completer')));
        $this->assertFalse($demandeur->can('update', $this->makeDemande($demandeur, 'soumise')));

        $this->assertTrue($demandeur->can('delete', $this->makeDemande($demandeur, 'brouillon')));
        $this->assertFalse($demandeur->can('delete', $this->makeDemande($demandeur, 'a_completer')));
    }

    public function test_nobody_but_the_author_uses_the_requester_space(): void
    {
        $demande = $this->makeDemande(User::factory()->create(['role' => 'demandeur']), 'brouillon');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertFalse($admin->can('view', $demande));
        $this->assertFalse($admin->can('update', $demande));
        $this->assertFalse($admin->can('delete', $demande));
    }

    // ── PDF officiels ──

    public function test_official_pdf_only_for_a_validated_demande(): void
    {
        $demandeur = User::factory()->create(['role' => 'demandeur']);

        $this->assertFalse($demandeur->can('telechargerPdf', $this->makeDemande($demandeur, 'soumise')));
        $this->assertTrue($demandeur->can('telechargerPdf', $this->makeDemande($demandeur, 'validee')));
        $this->assertTrue($this->validateurDuSite()->can('telechargerPdf', $this->makeDemande($demandeur, 'validee')));
    }
}

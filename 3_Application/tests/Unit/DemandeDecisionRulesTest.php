<?php

namespace Tests\Unit;

use App\Models\Demande;
use App\Models\Site;
use App\Models\Statut;
use App\Models\TypeDemande;
use App\Models\User;
use App\Support\DemandeDecisionRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemandeDecisionRulesTest extends TestCase
{
    use RefreshDatabase;

    private function makeDemande(User $creator, Site $site): Demande
    {
        $statut = Statut::firstOrCreate(['code' => 'soumise'], ['libelle' => 'Soumise', 'description' => '']);
        $type = TypeDemande::create(['libelle' => 'Test']);

        return Demande::create([
            'user_id' => $creator->id,
            'site_id' => $site->id,
            'type_demande_id' => $type->id,
            'description' => 'Test',
            'date_debut' => now()->toDateString(),
            'date_fin' => now()->addDay()->toDateString(),
            'equipe_nombre' => 1,
            'statut_id' => $statut->id,
            'date_soumission' => now(),
        ]);
    }

    public function test_notifiable_deciders_exclude_creator_and_include_other_admin_for_admin_demande(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $validateur = User::factory()->create(['role' => 'validateur']);
        $site = Site::create(['nom' => 'Site A', 'description' => '', 'polygone_coordonnees' => []]);
        $validateur->sites()->attach($site);
        $demande = $this->makeDemande($admin, $site);

        $ids = DemandeDecisionRules::notifiableDecidersForNewDemande($demande)->pluck('id')->all();

        $this->assertContains($otherAdmin->id, $ids);
        $this->assertContains($validateur->id, $ids);
        $this->assertNotContains($admin->id, $ids);
    }

    public function test_notifiable_deciders_for_demande_classique_only_site_validateurs(): void
    {
        $demandeur = User::factory()->create(['role' => 'demandeur']);
        $admin = User::factory()->create(['role' => 'admin']);
        $validateur = User::factory()->create(['role' => 'validateur']);
        $site = Site::create(['nom' => 'Site A', 'description' => '', 'polygone_coordonnees' => []]);
        $validateur->sites()->attach($site);
        $demande = $this->makeDemande($demandeur, $site);

        $ids = DemandeDecisionRules::notifiableDecidersForNewDemande($demande)->pluck('id')->all();

        $this->assertSame([$validateur->id], $ids);
        $this->assertNotContains($admin->id, $ids);
    }
}

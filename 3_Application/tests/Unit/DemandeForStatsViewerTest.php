<?php

namespace Tests\Unit;

use App\Models\Demande;
use App\Models\Site;
use App\Models\Statut;
use App\Models\TypeDemande;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemandeForStatsViewerTest extends TestCase
{
    use RefreshDatabase;

    private function seedDemande(Site $site): void
    {
        $statut = Statut::firstOrCreate(
            ['code' => 'soumise'],
            ['libelle' => 'Soumise', 'description' => '']
        );
        $type = TypeDemande::firstOrCreate(['libelle' => 'Test stats']);
        $demandeur = User::factory()->create(['role' => 'demandeur']);

        Demande::create([
            'user_id' => $demandeur->id,
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

    public function test_admin_sees_all_demandes_in_stats_scope(): void
    {
        $siteA = Site::create(['nom' => 'A', 'description' => '', 'polygone_coordonnees' => []]);
        $siteB = Site::create(['nom' => 'B', 'description' => '', 'polygone_coordonnees' => []]);
        $this->seedDemande($siteA);
        $this->seedDemande($siteB);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertSame(2, Demande::query()->forStatsViewer($admin)->count());
    }

    public function test_validateur_sees_only_demandes_on_assigned_sites(): void
    {
        $siteA = Site::create(['nom' => 'A', 'description' => '', 'polygone_coordonnees' => []]);
        $siteB = Site::create(['nom' => 'B', 'description' => '', 'polygone_coordonnees' => []]);
        $this->seedDemande($siteA);
        $this->seedDemande($siteB);

        $validateur = User::factory()->create(['role' => 'validateur']);
        $validateur->sites()->attach($siteA);

        $this->assertSame(1, Demande::query()->forStatsViewer($validateur)->count());
    }
}

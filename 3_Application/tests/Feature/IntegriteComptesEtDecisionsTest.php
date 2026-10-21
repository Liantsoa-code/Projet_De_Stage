<?php

namespace Tests\Feature;

use App\Models\Demande;
use App\Models\HistoriqueStatut;
use App\Models\Site;
use App\Models\Statut;
use App\Models\TypeDemande;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IntegriteComptesEtDecisionsTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;
    private TypeDemande $type;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['soumise' => 'Soumise', 'en_analyse' => 'En analyse', 'validee' => 'Validée', 'rejetee' => 'Rejetée'] as $code => $libelle) {
            Statut::firstOrCreate(['code' => $code], ['libelle' => $libelle, 'description' => '']);
        }

        $this->site = Site::create(['nom' => 'Site A', 'description' => '', 'polygone_coordonnees' => []]);
        $this->type = TypeDemande::create(['libelle' => 'Recherche']);
    }

    private function makeDemande(User $demandeur, string $statut = 'soumise'): Demande
    {
        return Demande::create([
            'user_id' => $demandeur->id,
            'site_id' => $this->site->id,
            'type_demande_id' => $this->type->id,
            'description' => 'Test',
            'date_debut' => now()->addDays(10)->toDateString(),
            'date_fin' => now()->addDays(12)->toDateString(),
            'equipe_nombre' => 2,
            'statut_id' => Statut::where('code', $statut)->value('id'),
            'date_soumission' => now(),
            'resultats_analyse' => [],
        ]);
    }

    private function validateurDuSite(): User
    {
        $validateur = User::factory()->create(['role' => 'validateur']);
        $validateur->sites()->attach($this->site);

        return $validateur;
    }

    // ── Suppression de compte ──

    public function test_validateur_with_past_decisions_can_delete_account_and_history_is_kept(): void
    {
        $validateur = $this->validateurDuSite();
        $demande = $this->makeDemande(User::factory()->create());

        $this->actingAs($validateur)->post(route('validateur.demandes.documents.generer', $demande))->assertSessionHasNoErrors();
        $this->actingAs($validateur)->post(route('validateur.demandes.update-statut', $demande), [
            'statut_id' => Statut::where('code', 'validee')->value('id'),
            'commentaire' => 'Dossier complet.',
            'signature' => $validateur->name,
        ])->assertSessionHasNoErrors();

        $this->actingAs($validateur)
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertNull($validateur->fresh());
        $historique = HistoriqueStatut::where('demande_id', $demande->id)->sole();
        $this->assertNull($historique->user_id);
        $this->assertSame('Dossier complet.', $historique->commentaire);
    }

    public function test_account_deletion_removes_files_of_its_demandes(): void
    {
        Storage::fake();
        $demandeur = User::factory()->create();
        $demande = $this->makeDemande($demandeur);
        Storage::put("demandes/{$demande->id}/protocole.pdf", 'contenu');

        $this->actingAs($demandeur)
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertNull($demande->fresh());
        Storage::assertMissing("demandes/{$demande->id}");
    }

    public function test_last_admin_cannot_delete_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->from('/profile')
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($admin->fresh());

        User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->delete('/profile', ['password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertNull($admin->fresh());
    }

    // ── Décisions ──

    public function test_second_decision_on_a_demande_closed_meanwhile_is_refused(): void
    {
        $demande = $this->makeDemande(User::factory()->create());
        $premier = $this->validateurDuSite();
        $second = $this->validateurDuSite();

        $this->actingAs($premier)->post(route('validateur.demandes.documents.generer', $demande))->assertSessionHasNoErrors();
        $this->actingAs($premier)->post(route('validateur.demandes.update-statut', $demande), [
            'statut_id' => Statut::where('code', 'validee')->value('id'),
            'commentaire' => 'Accepté.',
            'signature' => $premier->name,
        ])->assertSessionHasNoErrors();

        // Le second validateur avait ouvert le dossier avant la décision du premier.
        $this->actingAs($second)->post(route('validateur.demandes.update-statut', $demande), [
            'statut_id' => Statut::where('code', 'rejetee')->value('id'),
            'commentaire' => 'Refusé.',
        ])->assertSessionHasErrors('statut_id');

        $this->assertSame('validee', $demande->fresh()->statut->code);
        $this->assertSame(1, HistoriqueStatut::where('demande_id', $demande->id)->count());
    }

    // ── Rôles ──

    public function test_admin_cannot_remove_own_admin_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), ['role' => 'validateur', 'sites' => [$this->site->id]])
            ->assertSessionHasErrors('role');

        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertCount(0, $admin->sites);
    }

    public function test_admin_can_change_role_of_another_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), ['role' => 'validateur', 'sites' => [$this->site->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame('validateur', $user->fresh()->role);
        $this->assertEquals([$this->site->id], $user->sites()->pluck('t_sites.id')->all());
    }

    public function test_user_update_rejects_unknown_site(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $user), ['role' => 'validateur', 'sites' => [$this->site->id, 999999]])
            ->assertSessionHasErrors('sites.1');

        $this->assertSame('demandeur', $user->fresh()->role);
        $this->assertCount(0, $user->sites);
    }
}

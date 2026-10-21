<?php

namespace Tests\Feature;

use App\Models\Demande;
use App\Models\Document;
use App\Models\Site;
use App\Models\Statut;
use App\Models\TypeDemande;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemandeSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;
    private Site $autreSite;
    private TypeDemande $type;
    private int $protocoleId;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['soumise' => 'Soumise', 'en_analyse' => 'En analyse', 'validee' => 'Validée', 'rejetee' => 'Rejetée'] as $code => $libelle) {
            Statut::firstOrCreate(['code' => $code], ['libelle' => $libelle, 'description' => '']);
        }

        $this->site = Site::create(['nom' => 'Site A', 'description' => '', 'polygone_coordonnees' => []]);
        $this->autreSite = Site::create(['nom' => 'Site B', 'description' => '', 'polygone_coordonnees' => []]);
        $this->type = TypeDemande::create(['libelle' => 'Recherche']);
        $this->protocoleId = $this->type->documentsRequis()->create(['libelle' => 'Protocole'])->id;
    }

    private function makeDemande(User $demandeur, string $statut = 'soumise', ?Site $site = null): Demande
    {
        return Demande::create([
            'user_id' => $demandeur->id,
            'site_id' => ($site ?? $this->site)->id,
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

    private function validateurDuSite(Site $site): User
    {
        $validateur = User::factory()->create(['role' => 'validateur']);
        $validateur->sites()->attach($site);

        return $validateur;
    }

    private function storeDocument(Demande $demande): Document
    {
        Storage::fake();
        Storage::put("demandes/{$demande->id}/protocole.pdf", 'contenu');

        return Document::create([
            'demande_id' => $demande->id,
            'nom_fichier' => 'protocole.pdf',
            'chemin' => "demandes/{$demande->id}/protocole.pdf",
            'document_requis_id' => $this->protocoleId,
            'type_document' => 'Protocole',
        ]);
    }

    // ── Pièces jointes ──

    public function test_validateur_of_another_site_cannot_download_document(): void
    {
        $demande = $this->makeDemande(User::factory()->create(['role' => 'demandeur']));
        $document = $this->storeDocument($demande);

        $this->actingAs($this->validateurDuSite($this->autreSite))
            ->get(route('documents.show', $document))
            ->assertForbidden();
    }

    public function test_validateur_of_the_site_and_owner_can_download_document(): void
    {
        $demandeur = User::factory()->create(['role' => 'demandeur']);
        $demande = $this->makeDemande($demandeur);
        $document = $this->storeDocument($demande);

        $this->actingAs($this->validateurDuSite($this->site))
            ->get(route('documents.show', $document))
            ->assertOk();

        $this->actingAs($demandeur)
            ->get(route('documents.show', $document))
            ->assertOk();
    }

    public function test_other_demandeur_cannot_download_document(): void
    {
        $demande = $this->makeDemande(User::factory()->create(['role' => 'demandeur']));
        $document = $this->storeDocument($demande);

        $this->actingAs(User::factory()->create(['role' => 'demandeur']))
            ->get(route('documents.show', $document))
            ->assertForbidden();
    }

    // ── PDF officiels ──

    public function test_cahier_des_charges_requires_validated_demande(): void
    {
        $demandeur = User::factory()->create(['role' => 'demandeur']);
        $demande = $this->makeDemande($demandeur, 'en_analyse');

        $this->actingAs($demandeur)
            ->get(route('demandes.pdf.cahier-charges', $demande))
            ->assertForbidden();
    }

    public function test_validateur_of_another_site_cannot_generate_pdf(): void
    {
        $demande = $this->makeDemande(User::factory()->create(['role' => 'demandeur']), 'validee');

        $this->actingAs($this->validateurDuSite($this->autreSite))
            ->get(route('demandes.pdf.autorisation', $demande))
            ->assertForbidden();
    }

    public function test_owner_can_generate_pdf_of_validated_demande(): void
    {
        $demandeur = User::factory()->create(['role' => 'demandeur']);
        $demande = $this->makeDemande($demandeur, 'validee');

        $this->actingAs($demandeur)
            ->get(route('demandes.pdf.cahier-charges', $demande))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    // ── Soumission ──

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'site_id' => $this->site->id,
            'type_demande_id' => $this->type->id,
            'date_debut' => now()->addDays(5)->toDateString(),
            'date_fin' => now()->addDays(7)->toDateString(),
            'equipe_nombre' => 3,
            'description' => 'Inventaire de la faune',
            'doc_' . $this->protocoleId => UploadedFile::fake()->create('protocole.pdf', 100, 'application/pdf'),
        ], $overrides);
    }

    public function test_submission_rejects_past_start_date(): void
    {
        Storage::fake();

        $this->actingAs(User::factory()->create(['role' => 'demandeur']))
            ->post(route('demandes.store'), $this->payload([
                'date_debut' => now()->subDay()->toDateString(),
            ]))
            ->assertSessionHasErrors('date_debut');

        $this->assertSame(0, Demande::count());
    }

    public function test_submission_rejects_disallowed_file_type(): void
    {
        Storage::fake();

        $this->actingAs(User::factory()->create(['role' => 'demandeur']))
            ->post(route('demandes.store'), $this->payload([
                'doc_' . $this->protocoleId => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
            ]))
            ->assertSessionHasErrors('doc_' . $this->protocoleId);

        $this->assertSame(0, Demande::count());
    }

    public function test_submission_accepts_pdf_document(): void
    {
        Storage::fake();

        $this->actingAs(User::factory()->create(['role' => 'demandeur']))
            ->post(route('demandes.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('demandes.show', Demande::first()));

        $this->assertSame(1, Demande::count());
        $this->assertSame(1, Document::count());
    }

    // ── Fiche demande : réservée au demandeur ──

    public function test_demande_detail_is_restricted_to_its_owner(): void
    {
        $demandeur = User::factory()->create(['role' => 'demandeur']);
        $demande = $this->makeDemande($demandeur);

        $this->actingAs($demandeur)->get(route('demandes.show', $demande))->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'demandeur']))
            ->get(route('demandes.show', $demande))
            ->assertForbidden();
    }

    // ── Suppressions admin ──

    public function test_site_with_demandes_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $demande = $this->makeDemande(User::factory()->create(['role' => 'demandeur']));

        $this->actingAs($admin)
            ->delete(route('admin.sites.destroy', $this->site))
            ->assertRedirect(route('admin.sites.index'))
            ->assertSessionHas('error');

        $this->assertModelExists($this->site);
        $this->assertModelExists($demande);
    }

    public function test_site_without_demandes_can_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->delete(route('admin.sites.destroy', $this->autreSite))
            ->assertSessionHas('message');

        $this->assertModelMissing($this->autreSite);
    }

    public function test_type_with_demandes_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $demande = $this->makeDemande(User::factory()->create(['role' => 'demandeur']));

        $this->actingAs($admin)
            ->delete(route('admin.type-demandes.destroy', $this->type))
            ->assertSessionHas('error');

        $this->assertModelExists($this->type);
        $this->assertModelExists($demande);
    }
}

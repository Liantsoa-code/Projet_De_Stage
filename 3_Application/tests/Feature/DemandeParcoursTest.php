<?php

namespace Tests\Feature;

use App\Models\Demande;
use App\Models\Document;
use App\Models\HistoriqueStatut;
use App\Models\Notification;
use App\Models\PeriodeSensible;
use App\Models\Site;
use App\Models\Statut;
use App\Models\TypeDemande;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Brouillons, compléments, analyse des périodes, filtres et export.
 */
class DemandeParcoursTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;
    private TypeDemande $type;
    private User $demandeur;
    private User $validateur;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        // « brouillon » et « a_completer » sont déjà insérés par la migration
        foreach (['soumise' => 'Soumise', 'en_analyse' => 'En analyse', 'validee' => 'Validée', 'rejetee' => 'Rejetée'] as $code => $libelle) {
            Statut::firstOrCreate(['code' => $code], ['libelle' => $libelle, 'description' => '']);
        }

        $this->site = Site::create(['nom' => 'Andasibe', 'description' => '', 'polygone_coordonnees' => []]);
        $this->type = TypeDemande::create([
            'libelle' => 'Recherche',
            'documents_requis' => ['Protocole'],
            'regles_specifiques' => [],
        ]);
        $this->demandeur = User::factory()->create(['role' => 'demandeur', 'name' => 'Rova Demandeur']);
        $this->validateur = User::factory()->create(['role' => 'validateur']);
        $this->validateur->sites()->attach($this->site);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'site_id' => $this->site->id,
            'type_demande_id' => $this->type->id,
            'date_debut' => now()->addDays(5)->toDateString(),
            'date_fin' => now()->addDays(7)->toDateString(),
            'equipe_nombre' => 3,
            'description' => 'Inventaire de la faune',
            'doc_0' => UploadedFile::fake()->create('protocole.pdf', 100, 'application/pdf'),
        ], $overrides);
    }

    private function makeDemande(string $statut, array $attrs = []): Demande
    {
        return Demande::create(array_merge([
            'user_id' => $this->demandeur->id,
            'site_id' => $this->site->id,
            'type_demande_id' => $this->type->id,
            'description' => 'Test',
            'date_debut' => now()->addDays(10)->toDateString(),
            'date_fin' => now()->addDays(12)->toDateString(),
            'equipe_nombre' => 2,
            'statut_id' => Statut::where('code', $statut)->value('id'),
            'date_soumission' => $statut === 'brouillon' ? null : now(),
            'resultats_analyse' => [],
        ], $attrs));
    }

    // ── Brouillons ──

    public function test_draft_only_requires_site_and_type(): void
    {
        $this->actingAs($this->demandeur)
            ->post(route('demandes.store'), [
                'site_id' => $this->site->id,
                'type_demande_id' => $this->type->id,
                'action' => 'brouillon',
            ])
            ->assertSessionHasNoErrors();

        $demande = Demande::firstOrFail();
        $this->assertSame('brouillon', $demande->statut->code);
        $this->assertNull($demande->date_soumission);
        $this->assertNull($demande->date_debut);
        // Personne n'est prévenu tant que le brouillon n'est pas soumis
        $this->assertSame(0, Notification::count());
    }

    public function test_draft_is_invisible_to_validateur(): void
    {
        $brouillon = $this->makeDemande('brouillon');
        Storage::put("demandes/{$brouillon->id}/p.pdf", 'x');
        $document = Document::create(['demande_id' => $brouillon->id, 'nom_fichier' => 'p.pdf', 'chemin' => "demandes/{$brouillon->id}/p.pdf", 'type_document' => 'Protocole']);

        $this->actingAs($this->validateur)
            ->get(route('validateur.demandes.index'))
            ->assertInertia(fn (Assert $page) => $page->has('demandes.data', 0));

        $this->actingAs($this->validateur)->get(route('validateur.demandes.show', $brouillon))->assertNotFound();
        $this->actingAs($this->validateur)->get(route('documents.show', $document))->assertForbidden();
    }

    public function test_draft_can_be_submitted_later_reusing_uploaded_document(): void
    {
        $this->actingAs($this->demandeur)
            ->post(route('demandes.store'), $this->payload(['action' => 'brouillon']))
            ->assertSessionHasNoErrors();
        $demande = Demande::firstOrFail();
        $this->assertSame(1, $demande->documents()->count());

        // Soumission sans renvoyer la pièce déjà fournie
        $payload = $this->payload(['action' => 'soumettre', '_method' => 'put']);
        unset($payload['doc_0']);

        $this->actingAs($this->demandeur)
            ->post(route('demandes.update', $demande), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('demandes.show', $demande));

        $demande->refresh();
        $this->assertSame('soumise', $demande->statut->code);
        $this->assertNotNull($demande->date_soumission);
        $this->assertTrue(Notification::where('user_id', $this->validateur->id)->where('demande_id', $demande->id)->exists());
    }

    public function test_only_drafts_can_be_deleted(): void
    {
        $brouillon = $this->makeDemande('brouillon');
        $soumise = $this->makeDemande('soumise');

        $this->actingAs($this->demandeur)->delete(route('demandes.destroy', $soumise))->assertForbidden();
        $this->actingAs($this->demandeur)->delete(route('demandes.destroy', $brouillon))->assertRedirect(route('demandes.index'));

        $this->assertModelMissing($brouillon);
        $this->assertModelExists($soumise);
    }

    public function test_submitted_demande_cannot_be_edited(): void
    {
        $soumise = $this->makeDemande('soumise');

        $this->actingAs($this->demandeur)->get(route('demandes.edit', $soumise))->assertForbidden();
    }

    // ── Compléments ──

    public function test_complement_round_trip(): void
    {
        $demande = $this->makeDemande('en_analyse');
        $Acompleter = Statut::where('code', 'a_completer')->firstOrFail();

        // 1. Le validateur demande un complément
        $this->actingAs($this->validateur)
            ->post(route('validateur.demandes.update-statut', $demande), [
                'statut_id' => $Acompleter->id,
                'commentaire' => 'Merci de joindre le protocole signé.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('a_completer', $demande->fresh()->statut->code);
        $this->assertTrue(Notification::where('user_id', $this->demandeur->id)->where('contenu', 'like', '%complété%')->exists());

        // 2. Le demandeur voit la demande de complément et renvoie son dossier
        $this->actingAs($this->demandeur)
            ->get(route('demandes.show', $demande))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canEdit', true)
                ->where('complement.commentaire', 'Merci de joindre le protocole signé.'));

        $this->actingAs($this->demandeur)
            ->post(route('demandes.update', $demande), $this->payload([
                '_method' => 'put',
                'message_complement' => 'Protocole signé ajouté.',
            ]))
            ->assertSessionHasNoErrors();

        // 3. Retour en analyse, historisé, validateur prévenu
        $this->assertSame('en_analyse', $demande->fresh()->statut->code);
        $this->assertTrue(HistoriqueStatut::where('demande_id', $demande->id)
            ->where('user_id', $this->demandeur->id)
            ->where('commentaire', 'Protocole signé ajouté.')
            ->exists());
        $this->assertTrue(Notification::where('user_id', $this->validateur->id)->where('contenu', 'like', '%a complété son dossier%')->exists());
    }

    // ── Analyse des périodes sensibles ──

    public function test_submission_records_warning_overlap_and_refuses_blocking_one(): void
    {
        PeriodeSensible::create([
            'site_id' => $this->site->id,
            'date_debut' => now()->addDays(6)->toDateString(),
            'date_fin' => now()->addDays(20)->toDateString(),
            'description' => 'Nidification',
            'niveau_restriction' => 'avertissement',
        ]);

        $this->actingAs($this->demandeur)->post(route('demandes.store'), $this->payload())->assertSessionHasNoErrors();
        $this->assertCount(1, Demande::firstOrFail()->resultats_analyse);

        PeriodeSensible::create([
            'site_id' => $this->site->id,
            'date_debut' => now()->addDays(30)->toDateString(),
            'date_fin' => now()->addDays(40)->toDateString(),
            'description' => 'Fermeture',
            'niveau_restriction' => 'bloquant',
        ]);

        $this->actingAs($this->demandeur)
            ->post(route('demandes.store'), $this->payload([
                'date_debut' => now()->addDays(35)->toDateString(),
                'date_fin' => now()->addDays(45)->toDateString(),
            ]))
            ->assertSessionHasErrors('date_debut');
        $this->assertSame(1, Demande::count());
    }

    public function test_form_receives_upcoming_sensitive_periods(): void
    {
        PeriodeSensible::create(['site_id' => $this->site->id, 'date_debut' => now()->subDays(20)->toDateString(), 'date_fin' => now()->subDays(10)->toDateString(), 'description' => 'Passée', 'niveau_restriction' => 'bloquant']);
        PeriodeSensible::create(['site_id' => $this->site->id, 'date_debut' => now()->addDays(10)->toDateString(), 'date_fin' => now()->addDays(20)->toDateString(), 'description' => 'À venir', 'niveau_restriction' => 'bloquant']);

        $this->actingAs($this->demandeur)
            ->get(route('demandes.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('periodes', 1)
                ->where('periodes.0.description', 'À venir'));
    }

    // ── Filtres, pagination, export ──

    public function test_validateur_list_is_filtered_and_paginated(): void
    {
        $this->makeDemande('soumise');
        $this->makeDemande('validee');
        $autre = User::factory()->create(['role' => 'demandeur', 'name' => 'Hery Autre']);
        $this->makeDemande('soumise', ['user_id' => $autre->id]);

        $this->actingAs($this->validateur)
            ->get(route('validateur.demandes.index', ['statut' => 'soumise', 'q' => 'Hery']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('demandes.data', 1)
                ->where('demandes.data.0.user.name', 'Hery Autre')
                ->where('demandes.total', 1));
    }

    public function test_export_csv_contains_filtered_rows(): void
    {
        $this->makeDemande('validee');
        $this->makeDemande('rejetee');
        $this->makeDemande('brouillon');

        $response = $this->actingAs($this->validateur)
            ->get(route('validateur.demandes.export', ['statut' => 'validee']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));

        $lignes = array_values(array_filter(explode("\n", $response->streamedContent())));
        $this->assertCount(2, $lignes); // en-tête + 1 demande validée
        $this->assertStringContainsString('Demandeur', $lignes[0]);
        $this->assertStringContainsString('Validée', $lignes[1]);
    }

    public function test_demandeur_cannot_export(): void
    {
        $this->actingAs($this->demandeur)->get(route('validateur.demandes.export'))->assertForbidden();
    }

    public function test_admin_site_search(): void
    {
        Site::create(['nom' => 'Ranomafana', 'description' => '', 'polygone_coordonnees' => []]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.sites.index', ['q' => 'ranoma']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('sites.data', 1)
                ->where('sites.data.0.nom', 'Ranomafana'));
    }
}

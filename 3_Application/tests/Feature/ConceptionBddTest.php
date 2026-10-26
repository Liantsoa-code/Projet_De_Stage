<?php

namespace Tests\Feature;

use App\Models\Demande;
use App\Models\Document;
use App\Models\HistoriqueStatut;
use App\Models\PeriodeSensible;
use App\Models\Site;
use App\Models\Statut;
use App\Models\TypeDemande;
use App\Models\User;
use App\Support\DocumentsOfficiels;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Garanties issues de la démarche de conception : 1NF des listes d'un type, historique complet
 * des statuts (RG09) et contraintes du modèle physique (CHECK, RESTRICT).
 */
class ConceptionBddTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;
    private TypeDemande $type;
    private int $protocoleId;
    private User $demandeur;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        foreach (['soumise' => 'Soumise', 'en_analyse' => 'En analyse', 'validee' => 'Validée', 'rejetee' => 'Rejetée'] as $code => $libelle) {
            Statut::firstOrCreate(['code' => $code], ['libelle' => $libelle, 'description' => '']);
        }

        $this->site = Site::create(['nom' => 'Andasibe', 'description' => '', 'polygone_coordonnees' => []]);
        $this->type = TypeDemande::create(['libelle' => 'Recherche']);
        $this->protocoleId = $this->type->documentsRequis()->create(['libelle' => 'Protocole'])->id;
        $this->type->reglesSpecifiques()->create(['libelle' => 'Équipe limitée à 10 personnes']);
        $this->demandeur = User::factory()->create(['role' => 'demandeur']);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge($this->champsFormulaire(), [
            'site_id' => $this->site->id,
            'type_demande_id' => $this->type->id,
            'date_debut' => now()->addDays(5)->toDateString(),
            'date_fin' => now()->addDays(7)->toDateString(),
            'equipe_nombre' => 3,
            'description' => 'Inventaire de la faune',
            'doc_' . $this->protocoleId => UploadedFile::fake()->create('protocole.pdf', 100, 'application/pdf'),
        ], $overrides);
    }

    private function makeDemande(array $attrs = []): Demande
    {
        return Demande::create(array_merge([
            'user_id' => $this->demandeur->id,
            'site_id' => $this->site->id,
            'type_demande_id' => $this->type->id,
            'date_debut' => now()->addDays(10)->toDateString(),
            'date_fin' => now()->addDays(12)->toDateString(),
            'equipe_nombre' => 2,
            'statut_id' => Statut::where('code', 'soumise')->value('id'),
            'date_soumission' => now(),
        ], $attrs));
    }

    // ── RG09 : la soumission est historisée ──

    public function test_direct_submission_is_recorded_in_history(): void
    {
        $this->actingAs($this->demandeur)->post(route('demandes.store'), $this->payload())->assertSessionHasNoErrors();

        $demande = Demande::firstOrFail();
        $entree = HistoriqueStatut::where('demande_id', $demande->id)->sole();

        $this->assertNull($entree->ancien_statut_id);
        $this->assertSame($demande->statut_id, $entree->nouveau_statut_id);
        $this->assertSame($this->demandeur->id, $entree->user_id);
    }

    public function test_draft_has_no_history_until_it_is_submitted(): void
    {
        $this->actingAs($this->demandeur)->post(route('demandes.store'), $this->payload(['action' => 'brouillon']));
        $demande = Demande::firstOrFail();
        $this->assertSame(0, $demande->historiqueStatuts()->count());

        $payload = $this->payload(['action' => 'soumettre', '_method' => 'put']);
        unset($payload['doc_' . $this->protocoleId]);
        $this->actingAs($this->demandeur)->post(route('demandes.update', $demande), $payload)->assertSessionHasNoErrors();

        $entree = $demande->historiqueStatuts()->sole();
        $this->assertSame(Statut::where('code', 'brouillon')->value('id'), $entree->ancien_statut_id);
        // Le statut courant est toujours le dernier statut historisé.
        $this->assertSame($demande->fresh()->statut_id, $entree->nouveau_statut_id);
    }

    // ── 1NF : pièces requises et règles en tables filles ──

    public function test_renaming_a_required_document_keeps_uploaded_files_attached(): void
    {
        $this->actingAs($this->demandeur)->post(route('demandes.store'), $this->payload(['action' => 'brouillon']));
        $document = Document::firstOrFail();

        $this->actingAs($this->admin)->put(route('admin.type-demandes.update', $this->type), [
            'libelle' => 'Recherche',
            'documents_requis' => [['id' => $this->protocoleId, 'libelle' => 'Protocole de recherche']],
            'regles_specifiques' => [],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Protocole de recherche', $this->type->documentsRequis()->sole()->libelle);
        $this->assertSame($this->protocoleId, $document->fresh()->document_requis_id);
        // Le libellé de la pièce déposée reste celui du jour du dépôt.
        $this->assertSame('Protocole', $document->fresh()->type_document);

        // Le brouillon peut être soumis sans renvoyer le fichier : la pièce est toujours reconnue.
        $payload = $this->payload(['action' => 'soumettre', '_method' => 'put']);
        unset($payload['doc_' . $this->protocoleId]);
        $this->actingAs($this->demandeur)->post(route('demandes.update', Demande::firstOrFail()), $payload)->assertSessionHasNoErrors();
        $this->assertTrue(Storage::exists($document->chemin));
    }

    public function test_removing_a_required_document_keeps_files_of_submitted_requests(): void
    {
        $demande = $this->makeDemande();
        $document = Document::create([
            'demande_id' => $demande->id, 'document_requis_id' => $this->protocoleId,
            'nom_fichier' => 'p.pdf', 'chemin' => 'x', 'type_document' => 'Protocole',
        ]);

        $this->actingAs($this->admin)->put(route('admin.type-demandes.update', $this->type), [
            'libelle' => 'Recherche',
            'documents_requis' => [['id' => null, 'libelle' => 'Assurance']],
            'regles_specifiques' => [['id' => null, 'libelle' => 'Guide obligatoire']],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['Assurance'], $this->type->documentsRequis()->pluck('libelle')->all());
        $this->assertSame(['Guide obligatoire'], $this->type->reglesSpecifiques()->pluck('libelle')->all());
        $this->assertNull($document->fresh()->document_requis_id);
        $this->assertSame('Protocole', $document->fresh()->type_document);
    }

    public function test_type_cannot_list_the_same_document_twice(): void
    {
        $this->actingAs($this->admin)->post(route('admin.type-demandes.store'), [
            'libelle' => 'Tournage',
            'documents_requis' => [['libelle' => 'Scénario'], ['libelle' => 'scénario']],
        ])->assertSessionHasErrors('documents_requis.1.libelle');

        $this->assertDatabaseMissing('t_type_demandes', ['libelle' => 'Tournage']);
    }

    public function test_specific_rules_appear_in_the_terms_of_reference(): void
    {
        $html = view('pdf.cahier_charges', DocumentsOfficiels::donneesVue($this->makeDemande(), 'cahier_charges'))->render();

        $this->assertStringContainsString('Équipe limitée à 10 personnes', $html);
    }

    // ── Modèle physique : CHECK et RESTRICT appliqués par la base ──

    public function test_database_rejects_request_ending_before_it_starts(): void
    {
        $this->expectException(QueryException::class);

        $this->makeDemande(['date_debut' => now()->addDays(10)->toDateString(), 'date_fin' => now()->addDays(5)->toDateString()]);
    }

    public function test_database_rejects_empty_team(): void
    {
        $this->expectException(QueryException::class);

        $this->makeDemande(['equipe_nombre' => 0]);
    }

    public function test_database_rejects_sensitive_period_ending_before_it_starts(): void
    {
        $this->expectException(QueryException::class);

        PeriodeSensible::create([
            'site_id' => $this->site->id, 'date_debut' => '2026-12-10', 'date_fin' => '2026-12-01',
            'description' => 'Nidification', 'niveau_restriction' => 'bloquant',
        ]);
    }

    public function test_database_refuses_to_delete_a_site_or_type_still_in_use(): void
    {
        $this->makeDemande();

        foreach ([$this->site, $this->type] as $parent) {
            try {
                $parent->delete();
                $this->fail(class_basename($parent) . ' supprimé alors que des demandes y sont rattachées.');
            } catch (QueryException) {
                $this->assertModelExists($parent);
            }
        }

        $this->assertSame(1, Demande::count());
    }
}

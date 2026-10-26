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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Pièces réutilisables : une pièce d'identité déjà déposée est reprise dans les demandes suivantes.
 */
class RepriseDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;
    private TypeDemande $recherche;
    private TypeDemande $tournage;
    private int $protocoleId;
    private int $cinRechercheId;
    private int $cinTournageId;
    private User $demandeur;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        Statut::firstOrCreate(['code' => 'soumise'], ['libelle' => 'Soumise', 'description' => '']);

        $this->site = Site::create(['nom' => 'Andasibe', 'description' => '', 'polygone_coordonnees' => []]);
        $this->recherche = TypeDemande::create(['libelle' => 'Recherche']);
        $this->tournage = TypeDemande::create(['libelle' => 'Tournage']);
        $this->protocoleId = $this->recherche->documentsRequis()->create(['libelle' => 'Protocole'])->id;
        $this->cinRechercheId = $this->recherche->documentsRequis()->create(['libelle' => 'Carte d\'identité', 'reutilisable' => true])->id;
        // Même pièce sous un autre type, libellé à la casse près
        $this->cinTournageId = $this->tournage->documentsRequis()->create(['libelle' => 'carte d\'identité ', 'reutilisable' => true])->id;
        $this->demandeur = User::factory()->create(['role' => 'demandeur']);
    }

    /** Demande soumise avec un fichier par pièce requise, comme après un vrai dépôt. */
    private function demandeDeposee(User $user, array $pieces): Demande
    {
        $demande = Demande::create([
            'user_id' => $user->id,
            'site_id' => $this->site->id,
            'type_demande_id' => $this->recherche->id,
            'description' => 'Ancienne mission',
            'date_debut' => now()->addDays(10)->toDateString(),
            'date_fin' => now()->addDays(12)->toDateString(),
            'equipe_nombre' => 2,
            'statut_id' => Statut::where('code', 'soumise')->value('id'),
            'date_soumission' => now(),
            'resultats_analyse' => [],
        ]);

        foreach ($pieces as $docRequisId => $nom) {
            $chemin = "demandes/{$demande->id}/{$nom}";
            Storage::put($chemin, 'contenu de ' . $nom);
            Document::create([
                'demande_id' => $demande->id,
                'document_requis_id' => $docRequisId,
                'nom_fichier' => $nom,
                'chemin' => $chemin,
                'type_document' => 'Pièce',
            ]);
        }

        return $demande;
    }

    private function payload(TypeDemande $type, array $overrides = []): array
    {
        return array_merge($this->champsFormulaire(), [
            'site_id' => $this->site->id,
            'type_demande_id' => $type->id,
            'date_debut' => now()->addDays(5)->toDateString(),
            'date_fin' => now()->addDays(7)->toDateString(),
            'equipe_nombre' => 3,
            'description' => 'Nouvelle mission',
        ], $overrides);
    }

    public function test_seules_les_pieces_reutilisables_sont_proposees_au_formulaire(): void
    {
        $ancienne = $this->demandeDeposee($this->demandeur, [$this->protocoleId => 'protocole.pdf', $this->cinRechercheId => 'cin.pdf']);

        $this->actingAs($this->demandeur)->get(route('demandes.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where("reutilisables.{$this->cinRechercheId}.nom_fichier", 'cin.pdf')
                ->where("reutilisables.{$this->cinRechercheId}.reference", $ancienne->reference)
                // Même libellé sous un autre type : la carte d'identité est aussi proposée
                ->where("reutilisables.{$this->cinTournageId}.nom_fichier", 'cin.pdf')
                ->missing("reutilisables.{$this->protocoleId}"));
    }

    public function test_la_piece_reprise_est_copiee_dans_le_nouveau_dossier(): void
    {
        $ancienne = $this->demandeDeposee($this->demandeur, [$this->cinRechercheId => 'cin.pdf']);

        $this->actingAs($this->demandeur)->post(route('demandes.store'), $this->payload($this->recherche, [
            'doc_' . $this->protocoleId => UploadedFile::fake()->create('protocole.pdf', 100, 'application/pdf'),
            'reprendre' => [$this->cinRechercheId],
        ]))->assertSessionHasNoErrors();

        $nouvelle = Demande::latest('id')->first();
        $reprise = $nouvelle->documents()->where('document_requis_id', $this->cinRechercheId)->sole();

        $this->assertSame('cin.pdf', $reprise->nom_fichier);
        $this->assertStringStartsWith("demandes/{$nouvelle->id}/", $reprise->chemin);
        $this->assertSame('contenu de cin.pdf', Storage::get($reprise->chemin));

        // Le fichier d'origine reste en place : chaque dossier garde le sien
        Storage::assertExists("demandes/{$ancienne->id}/cin.pdf");
    }

    public function test_reprise_d_un_type_a_l_autre(): void
    {
        $this->demandeDeposee($this->demandeur, [$this->cinRechercheId => 'cin.pdf']);

        $this->actingAs($this->demandeur)->post(route('demandes.store'), $this->payload($this->tournage, [
            'reprendre' => [$this->cinTournageId],
        ]))->assertSessionHasNoErrors();

        $this->assertSame('cin.pdf', Demande::latest('id')->first()->documents()->sole()->nom_fichier);
    }

    public function test_une_piece_ecartee_reste_obligatoire(): void
    {
        $this->demandeDeposee($this->demandeur, [$this->cinRechercheId => 'cin.pdf']);

        $this->actingAs($this->demandeur)->post(route('demandes.store'), $this->payload($this->recherche, [
            'doc_' . $this->protocoleId => UploadedFile::fake()->create('protocole.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasErrors('doc_' . $this->cinRechercheId);
    }

    public function test_impossible_de_reprendre_la_piece_d_un_autre_demandeur(): void
    {
        $autre = User::factory()->create(['role' => 'demandeur']);
        $this->demandeDeposee($autre, [$this->cinRechercheId => 'cin_autre.pdf']);

        $this->actingAs($this->demandeur)->post(route('demandes.store'), $this->payload($this->recherche, [
            'doc_' . $this->protocoleId => UploadedFile::fake()->create('protocole.pdf', 100, 'application/pdf'),
            'reprendre' => [$this->cinRechercheId],
        ]))->assertSessionHasErrors('doc_' . $this->cinRechercheId);

        // Seul le fichier d'origine existe : rien n'a été copié
        $this->assertSame(1, Document::where('nom_fichier', 'cin_autre.pdf')->count());
    }

    public function test_un_nouveau_fichier_l_emporte_sur_la_reprise(): void
    {
        $this->demandeDeposee($this->demandeur, [$this->cinRechercheId => 'cin.pdf']);

        $this->actingAs($this->demandeur)->post(route('demandes.store'), $this->payload($this->recherche, [
            'doc_' . $this->protocoleId => UploadedFile::fake()->create('protocole.pdf', 100, 'application/pdf'),
            'doc_' . $this->cinRechercheId => UploadedFile::fake()->create('cin_2026.pdf', 100, 'application/pdf'),
            'reprendre' => [$this->cinRechercheId],
        ]))->assertSessionHasNoErrors();

        $this->assertSame('cin_2026.pdf', Demande::latest('id')->first()->documents()
            ->where('document_requis_id', $this->cinRechercheId)->sole()->nom_fichier);
    }

    public function test_l_admin_marque_une_piece_reutilisable(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.type-demandes.update', $this->recherche), [
            'libelle' => 'Recherche',
            'documents_requis' => [
                ['id' => $this->protocoleId, 'libelle' => 'Protocole', 'reutilisable' => true],
                ['id' => $this->cinRechercheId, 'libelle' => 'Carte d\'identité', 'reutilisable' => false],
            ],
            'regles_specifiques' => [],
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            [$this->protocoleId => true, $this->cinRechercheId => false],
            $this->recherche->documentsRequis()->pluck('reutilisable', 'id')->map(fn ($v) => (bool) $v)->all(),
        );
    }
}

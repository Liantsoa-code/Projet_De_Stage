<?php

namespace Tests\Feature;

use App\Models\Demande;
use App\Models\HistoriqueStatut;
use App\Models\Site;
use App\Models\Statut;
use App\Models\TypeDemande;
use App\Models\User;
use App\Support\DocumentsOfficiels;
use App\Support\FormulaireDemande;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Formulaire de demande au format du formulaire papier de Fanamby : coordonnées du demandeur (I),
 * projet (II), engagement (V), décision (VI) et PDF du formulaire rempli.
 */
class FormulaireDemandeTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;
    private TypeDemande $documentaire;
    private TypeDemande $autre;
    private User $demandeur;
    private User $validateur;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['soumise' => 'Soumise', 'en_analyse' => 'En analyse', 'validee' => 'Validée', 'rejetee' => 'Rejetée'] as $code => $libelle) {
            Statut::firstOrCreate(['code' => $code], ['libelle' => $libelle, 'description' => '']);
        }

        $this->site = Site::create(['nom' => 'Allée des Baobabs', 'description' => '', 'polygone_coordonnees' => []]);
        $this->documentaire = TypeDemande::create(['libelle' => 'Documentaire']);
        $this->autre = TypeDemande::create(['libelle' => 'Autre', 'precision_requise' => true]);
        $this->demandeur = User::factory()->create(['role' => 'demandeur', 'name' => 'Rova Demandeur']);
        $this->validateur = User::factory()->create(['role' => 'validateur', 'name' => 'Jean Validateur', 'fonction' => 'Responsable des autorisations']);
        $this->validateur->sites()->attach($this->site);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge($this->champsFormulaire(), [
            'site_id' => $this->site->id,
            'type_demande_id' => $this->documentaire->id,
            'date_debut' => now()->addDays(5)->toDateString(),
            'date_fin' => now()->addDays(7)->toDateString(),
            'equipe_nombre' => 3,
            'description' => 'Les baobabs au lever du soleil.',
        ], $overrides);
    }

    private function makeDemande(string $statut, array $attrs = []): Demande
    {
        return Demande::create(array_merge([
            'user_id' => $this->demandeur->id,
            'site_id' => $this->site->id,
            'type_demande_id' => $this->documentaire->id,
            'titre' => 'Les baobabs',
            'description' => 'Synopsis',
            'objectifs' => 'Réaliser un documentaire.',
            'date_debut' => now()->addDays(10)->toDateString(),
            'date_fin' => now()->addDays(12)->toDateString(),
            'equipe_nombre' => 2,
            'statut_id' => Statut::where('code', $statut)->value('id'),
            'date_soumission' => $statut === 'brouillon' ? null : now(),
            'resultats_analyse' => [],
        ], $attrs));
    }

    private function decision(Demande $demande, string $code, string $commentaire): void
    {
        HistoriqueStatut::create([
            'demande_id' => $demande->id,
            'user_id' => $this->validateur->id,
            'ancien_statut_id' => Statut::where('code', 'en_analyse')->value('id'),
            'nouveau_statut_id' => Statut::where('code', $code)->value('id'),
            'commentaire' => $commentaire,
            'date_changement' => now(),
        ]);
    }

    // ── Envoi du formulaire ──

    public function test_la_soumission_exige_les_sections_du_formulaire_et_l_engagement(): void
    {
        $this->actingAs($this->demandeur)
            ->post(route('demandes.store'), $this->payload([
                'organisation' => '', 'fonction' => '', 'adresse' => '', 'telephone' => '',
                'titre' => '', 'objectifs' => '', 'piece_identite' => '', 'engagement' => '0',
            ]))
            ->assertSessionHasErrors(['organisation', 'fonction', 'adresse', 'telephone', 'titre', 'objectifs', 'piece_identite', 'engagement']);

        $this->assertSame(0, Demande::count());
    }

    public function test_le_brouillon_n_exige_ni_coordonnees_ni_engagement(): void
    {
        $this->actingAs($this->demandeur)
            ->post(route('demandes.store'), [
                'site_id' => $this->site->id,
                'type_demande_id' => $this->documentaire->id,
                'titre' => 'Titre provisoire',
                'action' => 'brouillon',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Titre provisoire', Demande::sole()->titre);
    }

    public function test_le_telephone_ne_contient_que_des_chiffres_et_separateurs(): void
    {
        $this->actingAs($this->demandeur)
            ->post(route('demandes.store'), $this->payload(['telephone' => '034 abc']))
            ->assertSessionHasErrors('telephone');
    }

    public function test_les_champs_du_projet_sont_enregistres_sur_la_demande(): void
    {
        $this->actingAs($this->demandeur)->post(route('demandes.store'), $this->payload())->assertSessionHasNoErrors();

        $demande = Demande::sole();
        $this->assertSame('La faune de la forêt', $demande->titre);
        $this->assertSame('Les baobabs au lever du soleil.', $demande->description);
        $this->assertSame('Réaliser un documentaire de 26 minutes.', $demande->objectifs);
        $this->assertSame('soumise', $demande->statut->code);
    }

    public function test_les_coordonnees_sont_enregistrees_sur_le_compte_et_reprises_ensuite(): void
    {
        $this->actingAs($this->demandeur)->post(route('demandes.store'), $this->payload())->assertSessionHasNoErrors();

        $compte = $this->demandeur->fresh();
        $this->assertSame('Wild Lens Productions', $compte->organisation);
        $this->assertSame('+261 34 12 345 67', $compte->telephone);
        $this->assertSame('101 211 045 678', $compte->piece_identite);

        $this->actingAs($this->demandeur)
            ->get(route('demandes.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('demandeur.organisation', 'Wild Lens Productions')
                ->where('demandeur.fonction', 'Réalisatrice')
                ->has('engagements', count(Demande::ENGAGEMENTS)));
    }

    public function test_le_type_autre_exige_une_precision(): void
    {
        $this->actingAs($this->demandeur)
            ->post(route('demandes.store'), $this->payload(['type_demande_id' => $this->autre->id, 'type_precision' => '']))
            ->assertSessionHasErrors('type_precision');

        $this->actingAs($this->demandeur)
            ->post(route('demandes.store'), $this->payload(['type_demande_id' => $this->autre->id, 'type_precision' => 'Photographie commerciale']))
            ->assertSessionHasNoErrors();

        $demande = Demande::sole();
        $this->assertSame('Photographie commerciale', $demande->type_precision);
        $this->assertSame('Autre (Photographie commerciale)', $demande->typeProjet());
    }

    public function test_la_precision_n_est_pas_gardee_pour_un_type_qui_ne_la_demande_pas(): void
    {
        $this->actingAs($this->demandeur)
            ->post(route('demandes.store'), $this->payload(['type_precision' => 'Sans objet']))
            ->assertSessionHasNoErrors();

        $this->assertNull(Demande::sole()->type_precision);
    }

    public function test_l_admin_choisit_les_types_qui_demandent_une_precision(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.type-demandes.store'), [
            'libelle' => 'Projet hors liste',
            'precision_requise' => true,
            'documents_requis' => [],
            'regles_specifiques' => [],
        ])->assertSessionHasNoErrors();

        $this->assertTrue(TypeDemande::where('libelle', 'Projet hors liste')->sole()->precision_requise);
    }

    // ── Formulaire rempli (PDF) ──

    public function test_le_formulaire_pdf_est_disponible_des_l_envoi_pour_le_demandeur_et_le_validateur(): void
    {
        $demande = $this->makeDemande('soumise');

        $this->actingAs($this->demandeur)
            ->get(route('demandes.pdf.formulaire', $demande))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->validateur)
            ->get(route('demandes.pdf.formulaire', $demande))
            ->assertOk();
    }

    public function test_le_formulaire_pdf_est_refuse_pour_un_brouillon_ou_un_tiers(): void
    {
        $brouillon = $this->makeDemande('brouillon');
        $soumise = $this->makeDemande('soumise');
        $autreValidateur = User::factory()->create(['role' => 'validateur']);

        $this->actingAs($this->demandeur)->get(route('demandes.pdf.formulaire', $brouillon))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'demandeur']))->get(route('demandes.pdf.formulaire', $soumise))->assertForbidden();
        $this->actingAs($autreValidateur)->get(route('demandes.pdf.formulaire', $soumise))->assertForbidden();
    }

    public function test_la_section_vi_reprend_l_avis_favorable_ou_defavorable(): void
    {
        $enCours = $this->makeDemande('en_analyse');
        $this->assertNull(FormulaireDemande::decision($enCours));

        $validee = $this->makeDemande('validee');
        $this->decision($validee, 'validee', 'Respectez le balisage.');
        $avis = FormulaireDemande::decision($validee);
        $this->assertSame('validee', $avis->nouveauStatut->code);
        $this->assertSame('Respectez le balisage.', $avis->commentaire);
        $this->assertSame('Responsable des autorisations', $avis->user->fonction);

        $rejetee = $this->makeDemande('rejetee');
        $this->decision($rejetee, 'rejetee', 'Équipe trop nombreuse.');
        $this->assertSame('rejetee', FormulaireDemande::decision($rejetee)->nouveauStatut->code);

        $this->actingAs($this->demandeur)->get(route('demandes.pdf.formulaire', $rejetee))->assertOk();
    }

    // ── Documents officiels ──

    public function test_les_documents_officiels_reprennent_les_objectifs_et_les_engagements(): void
    {
        $demande = $this->makeDemande('en_analyse');

        $this->assertSame('Réaliser un documentaire.', DocumentsOfficiels::defauts($demande, 'autorisation')['objet']);

        $engagement = DocumentsOfficiels::defauts($demande, 'cahier_charges')['engagement'];
        foreach (Demande::ENGAGEMENTS as $ligne) {
            $this->assertStringContainsString(lcfirst($ligne), $engagement);
        }
    }
}

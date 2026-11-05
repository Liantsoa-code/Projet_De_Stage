<?php

namespace Tests\Feature;

use App\Models\Demande;
use App\Models\Document;
use App\Models\Notification;
use App\Models\Signalement;
use App\Models\Site;
use App\Models\Statut;
use App\Models\TypeDemande;
use App\Models\User;
use App\Support\DocumentsOfficiels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Rôle observateur : suit tous les dossiers en lecture seule, sans jamais décider, et signale les problèmes.
 * Signalements internes : traités par un validateur du site ou un admin, invisibles pour le demandeur.
 */
class ObservateurTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;
    private Site $autreSite;
    private TypeDemande $type;
    private User $demandeur;
    private User $validateur;
    private User $observateur;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        Mail::fake();

        foreach (['soumise' => 'Soumise', 'en_analyse' => 'En analyse', 'validee' => 'Validée', 'rejetee' => 'Rejetée'] as $code => $libelle) {
            Statut::firstOrCreate(['code' => $code], ['libelle' => $libelle, 'description' => '']);
        }

        $this->site = Site::create(['nom' => 'Andasibe', 'description' => '', 'polygone_coordonnees' => []]);
        $this->autreSite = Site::create(['nom' => 'Isalo', 'description' => '', 'polygone_coordonnees' => []]);
        $this->type = TypeDemande::create(['libelle' => 'Recherche']);
        $this->demandeur = User::factory()->create(['role' => 'demandeur']);
        $this->validateur = User::factory()->create(['role' => 'validateur', 'name' => 'Hery Rakoto']);
        $this->validateur->sites()->attach($this->site);
        $this->observateur = User::factory()->create(['role' => 'observateur', 'name' => 'Nirina Suivi']);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function makeDemande(string $statut = 'en_analyse', ?Site $site = null): Demande
    {
        return Demande::create([
            'user_id' => $this->demandeur->id,
            'site_id' => ($site ?? $this->site)->id,
            'type_demande_id' => $this->type->id,
            'description' => 'Inventaire',
            'date_debut' => now()->addDays(10)->toDateString(),
            'date_fin' => now()->addDays(12)->toDateString(),
            'equipe_nombre' => 2,
            'statut_id' => Statut::where('code', $statut)->value('id'),
            'date_soumission' => now(),
            'resultats_analyse' => [],
        ]);
    }

    private function signaler(User $user, Demande $demande, array $donnees = [])
    {
        return $this->actingAs($user)->post(route('validateur.demandes.signalements.store', $demande), $donnees + [
            'partie' => 'pieces',
            'message' => 'La copie du passeport est illisible.',
        ]);
    }

    // ── Lecture seule ──

    public function test_observer_sees_every_site_in_read_only(): void
    {
        $ici = $this->makeDemande();
        $ailleurs = $this->makeDemande('soumise', $this->autreSite);

        $this->actingAs($this->observateur)
            ->get(route('validateur.demandes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('demandes.total', 2)
                ->where('lectureSeule', true)
                ->where('viewAllDemandes', true));

        $this->actingAs($this->observateur)
            ->get(route('validateur.demandes.show', $ailleurs))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lectureSeule', true)
                ->where('canDecideOnDemande', false)
                ->where('peutTelechargerWord', false)
                ->where('peutSignaler', true)
                ->where('peutTraiterSignalements', false));

        $this->actingAs($this->observateur)->get(route('validateur.demandes.export'))->assertOk();
        $this->actingAs($this->observateur)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('file', 2)->has('signalements'));
        $this->assertNotNull($ici);
    }

    public function test_observer_can_never_decide_nor_edit_documents(): void
    {
        $demande = $this->makeDemande();

        $this->actingAs($this->observateur)->post(route('validateur.demandes.update-statut', $demande), [
            'statut_id' => Statut::where('code', 'rejetee')->value('id'),
            'commentaire' => 'Non',
        ])->assertForbidden();
        $this->actingAs($this->observateur)->post(route('validateur.demandes.documents.generer', $demande))->assertForbidden();
        $this->assertSame('en_analyse', $demande->fresh()->statut->code);

        // Documents préparés par le validateur : l'observateur les lit en PDF, jamais en Word
        $this->actingAs($this->validateur)->post(route('validateur.demandes.documents.generer', $demande));
        $this->actingAs($this->observateur)->put(route('validateur.demandes.documents.modifier', $demande), [])->assertForbidden();
        $this->actingAs($this->observateur)->delete(route('validateur.demandes.documents.annuler', $demande))->assertForbidden();
        $this->actingAs($this->observateur)->get(route('validateur.demandes.documents.apercu', [$demande, 'autorisation']))->assertOk();
        $this->actingAs($this->observateur)->get(route('validateur.demandes.documents.apercu', [$demande, 'autorisation', 'format' => 'docx']))->assertForbidden();
        $this->actingAs($this->observateur)
            ->get(route('validateur.demandes.show', $demande))
            ->assertInertia(fn (Assert $page) => $page->where('documentsGeneres.contenu.autorisation.signataire', DocumentsOfficiels::TEXTES['signataire']));
        $this->assertSame(3, $demande->documentsOfficiels()->count());
    }

    public function test_observer_downloads_attachments_and_official_pdfs_but_not_word(): void
    {
        $demande = $this->makeDemande('validee');
        Storage::put("demandes/{$demande->id}/protocole.pdf", 'contenu');
        $document = Document::create(['demande_id' => $demande->id, 'nom_fichier' => 'protocole.pdf', 'chemin' => "demandes/{$demande->id}/protocole.pdf", 'type_document' => 'Protocole']);

        $this->actingAs($this->observateur)->get(route('documents.show', $document))->assertOk();
        $this->actingAs($this->observateur)->get(route('demandes.pieces.zip', $demande))->assertOk();
        $this->actingAs($this->observateur)->get(route('demandes.pdf.convention', $demande))->assertOk();
        $this->actingAs($this->observateur)->get(route('demandes.pdf.autorisation', [$demande, 'format' => 'docx']))->assertForbidden();
    }

    public function test_observer_does_not_reach_references_or_administration(): void
    {
        $this->actingAs($this->observateur)->get(route('references.index'))->assertForbidden();
        $this->actingAs($this->observateur)->get(route('admin.sites.index'))->assertForbidden();
        $this->actingAs($this->observateur)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_a_draft_does_not_exist_for_the_observer(): void
    {
        $brouillon = Demande::create([
            'user_id' => $this->demandeur->id, 'site_id' => $this->site->id, 'type_demande_id' => $this->type->id,
            'statut_id' => Statut::where('code', 'brouillon')->value('id'), 'equipe_nombre' => 1,
        ]);

        $this->actingAs($this->observateur)->get(route('validateur.demandes.show', $brouillon))->assertNotFound();
    }

    public function test_observers_are_notified_of_new_demandes(): void
    {
        $this->actingAs($this->demandeur)->post(route('demandes.store'), array_merge($this->champsFormulaire($this->demandeur), [
            'site_id' => $this->autreSite->id,
            'type_demande_id' => $this->type->id,
            'date_debut' => now()->addDays(5)->toDateString(),
            'date_fin' => now()->addDays(7)->toDateString(),
            'equipe_nombre' => 2,
            'description' => 'Inventaire',
        ]))->assertSessionHasNoErrors();

        $this->assertTrue(Notification::where('user_id', $this->observateur->id)->where('contenu', 'like', 'Nouvelle demande pour « Isalo »%suivre%')->exists());
    }

    public function test_an_admin_can_make_someone_an_observer(): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.users.update', $this->demandeur), ['role' => 'observateur', 'sites' => []])
            ->assertSessionHasNoErrors();

        $this->assertSame('observateur', $this->demandeur->fresh()->role);
    }

    // ── Signalements ──

    public function test_a_report_notifies_the_site_deciders_only(): void
    {
        $demande = $this->makeDemande();
        $validateurAilleurs = User::factory()->create(['role' => 'validateur']);
        $validateurAilleurs->sites()->attach($this->autreSite);

        $this->signaler($this->observateur, $demande)->assertSessionHasNoErrors();

        $signalement = Signalement::sole();
        $this->assertSame('pieces', $signalement->partie);
        $this->assertTrue($signalement->auteur->is($this->observateur));
        foreach ([$this->validateur, $this->admin] as $decideur) {
            $this->assertTrue(Notification::where('user_id', $decideur->id)->where('contenu', 'like', 'Nirina Suivi signale un problème%Pièces jointes%illisible%')->exists());
        }
        $this->assertFalse(Notification::where('user_id', $validateurAilleurs->id)->exists());
        $this->assertFalse(Notification::where('user_id', $this->demandeur->id)->where('contenu', 'like', '%signale%')->exists());
    }

    public function test_validators_can_report_but_not_the_demandeur_nor_another_site(): void
    {
        $demande = $this->makeDemande();

        $this->signaler($this->validateur, $demande, ['partie' => 'sejour', 'message' => 'Dates à vérifier.'])->assertSessionHasNoErrors();
        $this->assertTrue(Notification::where('user_id', $this->admin->id)->where('contenu', 'like', 'Hery Rakoto signale%')->exists());
        $this->assertFalse(Notification::where('user_id', $this->validateur->id)->where('contenu', 'like', '%signale%')->exists());

        $validateurAilleurs = User::factory()->create(['role' => 'validateur']);
        $validateurAilleurs->sites()->attach($this->autreSite);
        $this->signaler($validateurAilleurs, $demande)->assertForbidden();
        $this->signaler($this->demandeur, $demande)->assertForbidden();
        $this->signaler($this->observateur, $demande, ['partie' => 'inconnue'])->assertSessionHasErrors('partie');
        $this->signaler($this->observateur, $demande, ['message' => ''])->assertSessionHasErrors('message');

        $this->assertSame(1, Signalement::count());
    }

    public function test_a_site_validator_handles_a_report_and_the_observer_is_told(): void
    {
        $demande = $this->makeDemande();
        $this->signaler($this->observateur, $demande);
        $signalement = Signalement::sole();

        // L'observateur ne traite pas les signalements
        $this->actingAs($this->observateur)
            ->put(route('validateur.demandes.signalements.traiter', [$demande, $signalement]), ['reponse' => 'Vu'])
            ->assertForbidden();

        $this->actingAs($this->validateur)
            ->put(route('validateur.demandes.signalements.traiter', [$demande, $signalement]), ['reponse' => 'Complément demandé au demandeur.'])
            ->assertSessionHasNoErrors();

        $signalement->refresh();
        $this->assertNotNull($signalement->traite_le);
        $this->assertTrue($signalement->traitePar->is($this->validateur));
        $this->assertTrue(Notification::where('user_id', $this->observateur->id)->where('contenu', 'like', '%a été traité par Hery Rakoto%Complément demandé%')->exists());

        // Déjà traité : pas de seconde réponse
        $this->actingAs($this->admin)
            ->put(route('validateur.demandes.signalements.traiter', [$demande, $signalement]), ['reponse' => 'Autre'])
            ->assertSessionHas('error');
        $this->assertSame('Complément demandé au demandeur.', $signalement->fresh()->reponse);
    }

    public function test_reports_appear_on_the_dossier_and_the_dashboards_but_never_for_the_demandeur(): void
    {
        $demande = $this->makeDemande('soumise');
        $this->signaler($this->observateur, $demande);

        $this->actingAs($this->validateur)
            ->get(route('validateur.demandes.show', $demande))
            ->assertInertia(fn (Assert $page) => $page
                ->has('signalements', 1)
                ->where('signalements.0.ouvert', true)
                ->where('signalements.0.partie_libelle', 'Pièces jointes')
                ->where('peutTraiterSignalements', true));

        $this->actingAs($this->validateur)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('file.0.signalements', 1)->where('compteurs.signales', 1));

        $this->actingAs($this->observateur)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('signalements.0.reference', $demande->reference)->where('signalements.0.ouvert', true));

        $this->actingAs($this->demandeur)
            ->get(route('demandes.show', $demande))
            ->assertInertia(fn (Assert $page) => $page->missing('signalements'));
    }

    public function test_a_report_of_another_dossier_cannot_be_handled_through_this_one(): void
    {
        $demande = $this->makeDemande();
        $autre = $this->makeDemande();
        $this->signaler($this->observateur, $autre);

        $this->actingAs($this->validateur)
            ->put(route('validateur.demandes.signalements.traiter', [$demande, Signalement::sole()]), ['reponse' => 'Vu'])
            ->assertNotFound();
    }

    public function test_reports_survive_the_deletion_of_their_author(): void
    {
        $demande = $this->makeDemande();
        $this->signaler($this->observateur, $demande);

        $this->observateur->delete();

        $this->assertNull(Signalement::sole()->user_id);
    }
}

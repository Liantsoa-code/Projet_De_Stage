<?php

namespace Tests\Feature;

use App\Models\Demande;
use App\Models\DocumentOfficiel;
use App\Models\Site;
use App\Models\Statut;
use App\Models\TypeDemande;
use App\Models\User;
use App\Support\DocumentsOfficiels;
use App\Support\FormatDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Validation en deux temps : « Générer » prépare l'autorisation, le cahier des charges et la convention,
 * le validateur les modifie, puis retape son nom pour valider le dossier.
 */
class ValidationDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;
    private TypeDemande $type;
    private User $demandeur;
    private User $validateur;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['soumise' => 'Soumise', 'en_analyse' => 'En analyse', 'a_completer' => 'À compléter', 'validee' => 'Validée', 'rejetee' => 'Rejetée'] as $code => $libelle) {
            Statut::firstOrCreate(['code' => $code], ['libelle' => $libelle, 'description' => '']);
        }

        $this->site = Site::create(['nom' => 'Andasibe', 'description' => '', 'polygone_coordonnees' => []]);
        $this->type = TypeDemande::create(['libelle' => 'Recherche']);
        $this->type->reglesSpecifiques()->create(['libelle' => 'Équipe limitée à 10 personnes']);
        $this->demandeur = User::factory()->create(['role' => 'demandeur']);
        $this->validateur = User::factory()->create(['role' => 'validateur', 'name' => 'Hery Rakoto']);
        $this->validateur->sites()->attach($this->site);
    }

    private function makeDemande(string $statut = 'en_analyse'): Demande
    {
        return Demande::create([
            'user_id' => $this->demandeur->id,
            'site_id' => $this->site->id,
            'type_demande_id' => $this->type->id,
            'description' => 'Inventaire des lémuriens',
            'date_debut' => now()->addDays(10)->toDateString(),
            'date_fin' => now()->addDays(12)->toDateString(),
            'equipe_nombre' => 2,
            'statut_id' => Statut::where('code', $statut)->value('id'),
            'date_soumission' => now(),
            'resultats_analyse' => [],
        ]);
    }

    private function valider(Demande $demande, ?string $signature)
    {
        return $this->actingAs($this->validateur)->post(route('validateur.demandes.update-statut', $demande), [
            'statut_id' => Statut::where('code', 'validee')->value('id'),
            'commentaire' => 'Dossier complet.',
            'signature' => $signature,
        ]);
    }

    private function contenuModifie(): array
    {
        return [
            'autorisation' => [
                'introduction' => "L'Association FANAMBY autorise :",
                'objet' => 'Inventaire nocturne des lémuriens',
                'conditions' => "Guide local obligatoire\nPas de feu",
                'reserve' => 'Sous réserve du cahier des charges.',
                'signataire' => 'Le Directeur des opérations',
            ],
            'cahier_charges' => [
                'regles' => "Équipe limitée à 10 personnes\nAucun prélèvement",
                'engagement' => 'Le demandeur respecte la faune et la flore.',
            ],
            'convention' => array_merge(DocumentsOfficiels::defauts(Demande::firstOrFail(), 'convention'), [
                'redevance_jour' => '4000000',
                'modalites' => "Prises de vues au lever du soleil\nNettoyage du site",
                'signataire' => 'La Directrice exécutive',
            ]),
        ];
    }

    public function test_generate_prepares_the_three_documents_without_changing_status(): void
    {
        $demande = $this->makeDemande();

        $this->actingAs($this->validateur)
            ->post(route('validateur.demandes.documents.generer', $demande))
            ->assertSessionHasNoErrors();

        $this->assertSame('en_analyse', $demande->fresh()->statut->code);
        $this->assertSame(3, $demande->documentsOfficiels()->count());
        $this->assertSame('Inventaire des lémuriens', DocumentsOfficiels::contenu($demande, 'autorisation')['objet']);
        $this->assertSame('Équipe limitée à 10 personnes', DocumentsOfficiels::contenu($demande, 'cahier_charges')['regles']);

        $this->actingAs($this->validateur)
            ->get(route('validateur.demandes.show', $demande))
            ->assertInertia(fn (Assert $page) => $page
                ->where('documentsGeneres.contenu.autorisation.signataire', 'La Direction FANAMBY')
                ->where('documentsGeneres.genere_par', 'Hery Rakoto'));
    }

    public function test_validation_requires_generated_documents(): void
    {
        $demande = $this->makeDemande();

        $this->valider($demande, 'Hery Rakoto')->assertSessionHasErrors('statut_id');

        $this->assertSame('en_analyse', $demande->fresh()->statut->code);
    }

    public function test_validation_requires_the_validator_to_retype_their_name(): void
    {
        $demande = $this->makeDemande();
        $this->actingAs($this->validateur)->post(route('validateur.demandes.documents.generer', $demande));

        $this->valider($demande, null)->assertSessionHasErrors('signature');
        $this->valider($demande, 'Jean Dupont')->assertSessionHasErrors('signature');
        $this->assertSame('en_analyse', $demande->fresh()->statut->code);

        // Casse, accents et espaces ne comptent pas.
        $this->valider($demande, '  héry   RAKOTO ')->assertSessionHasNoErrors();
        $this->assertSame('validee', $demande->fresh()->statut->code);
    }

    public function test_edited_texts_are_used_in_the_official_pdf(): void
    {
        $demande = $this->makeDemande();
        $this->actingAs($this->validateur)->post(route('validateur.demandes.documents.generer', $demande));

        $this->actingAs($this->validateur)
            ->put(route('validateur.demandes.documents.modifier', $demande), $this->contenuModifie())
            ->assertSessionHasNoErrors();
        $this->valider($demande, 'Hery Rakoto')->assertSessionHasNoErrors();

        $autorisation = view('pdf.autorisation', DocumentsOfficiels::donneesVue($demande->fresh(), 'autorisation'))->render();
        $this->assertStringContainsString('Inventaire nocturne des lémuriens', $autorisation);
        $this->assertStringContainsString('<li>Guide local obligatoire</li>', $autorisation);
        $this->assertStringContainsString('Le Directeur des opérations', $autorisation);
        $this->assertStringContainsString('signé électroniquement par Hery Rakoto', $autorisation);
        $this->assertStringNotContainsString('Projet', $autorisation);

        $cahier = view('pdf.cahier_charges', DocumentsOfficiels::donneesVue($demande->fresh(), 'cahier_charges'))->render();
        $this->assertStringContainsString('<li>Aucun prélèvement</li>', $cahier);

        $this->actingAs($this->demandeur)->get(route('demandes.pdf.autorisation', $demande))->assertOk();
    }

    public function test_official_documents_can_be_downloaded_in_word(): void
    {
        $demande = $this->makeDemande();
        $demande->update(['titre' => 'Lémuriens & <makis>']);
        $this->actingAs($this->validateur)->post(route('validateur.demandes.documents.generer', $demande));
        $this->actingAs($this->validateur)->put(route('validateur.demandes.documents.modifier', $demande), $this->contenuModifie());

        // En relecture, le projet n'est pas encore signé
        $projet = $this->actingAs($this->validateur)
            ->get(route('validateur.demandes.documents.apercu', [$demande, 'autorisation', 'format' => 'docx']))
            ->assertOk()
            ->assertDownload("projet_autorisation_{$demande->id}.docx");
        $this->assertStringNotContainsString('signé électroniquement', $this->texteWord($projet));

        $this->valider($demande, 'Hery Rakoto')->assertSessionHasNoErrors();

        $autorisation = $this->actingAs($this->validateur)
            ->get(route('demandes.pdf.autorisation', [$demande, 'format' => 'docx']))
            ->assertOk()
            ->assertHeader('Content-Type', FormatDocument::FORMATS['docx'])
            ->assertDownload("autorisation_{$demande->id}.docx");
        $texte = $this->texteWord($autorisation);
        $this->assertStringContainsString('Inventaire nocturne des lémuriens', $texte);
        $this->assertStringContainsString('Guide local obligatoire', $texte);
        $this->assertStringContainsString('« Lémuriens & <makis> »', $texte);
        $this->assertStringContainsString('signé électroniquement par Hery Rakoto', $texte);
        $this->assertStringNotContainsString('Projet', $texte);

        $cahier = $this->actingAs($this->validateur)
            ->get(route('demandes.pdf.cahier-charges', [$demande, 'format' => 'docx']))
            ->assertOk()
            ->assertDownload("cahier_des_charges_{$demande->id}.docx");
        $this->assertStringContainsString('Aucun prélèvement', $this->texteWord($cahier));
    }

    public function test_convention_uses_the_dossier_and_computes_the_fee_over_activity_days(): void
    {
        $demande = $this->makeDemande();
        $demande->update(['titre' => 'Les lémuriens de nuit', 'date_arrivee' => now()->addDays(9)->toDateString()]);
        $this->actingAs($this->validateur)->post(route('validateur.demandes.documents.generer', $demande));

        // Textes proposés : aire protégée, projet et référence du dossier ; redevance à fixer
        $defauts = DocumentsOfficiels::contenu($demande, 'convention');
        $this->assertStringContainsString("l'aire protégée de Andasibe", $defauts['partie_fanamby']);
        $this->assertStringContainsString('« Les lémuriens de nuit » (Recherche)', $defauts['objet']);
        $this->assertStringContainsString($demande->reference, $defauts['avis']);
        $this->assertSame('', $defauts['redevance_jour']);
        $projet = view('pdf.convention', DocumentsOfficiels::donneesVue($demande->fresh(), 'convention', projet: true))->render();
        $this->assertStringContainsString('sera communiqué par Fanamby', $projet);

        // 4 000 000 Ar par jour, activités du J+10 au J+12 : 3 jours, 12 000 000 Ar
        $this->actingAs($this->validateur)
            ->put(route('validateur.demandes.documents.modifier', $demande), $this->contenuModifie())
            ->assertSessionHasNoErrors();
        $this->valider($demande, 'Hery Rakoto')->assertSessionHasNoErrors();

        $html = view('pdf.convention', DocumentsOfficiels::donneesVue($demande->fresh(), 'convention'))->render();
        $this->assertStringContainsString('<strong>4 000 000 Ariary par jour</strong>', $html);
        $this->assertStringContainsString('soit pour 3 jours d\'activités', $html);
        $this->assertStringContainsString('<strong>12 000 000 Ariary</strong>', $html);
        $this->assertStringContainsString('<li>Prises de vues au lever du soleil</li>', $html);
        $this->assertStringContainsString('Arrivée sur le terrain le <strong>' . now()->addDays(9)->format('d/m/Y') . '</strong>', $html);
        $this->assertStringContainsString('La Directrice exécutive', $html);
        $this->assertStringContainsString('signé électroniquement par Hery Rakoto', $html);

        $this->actingAs($this->demandeur)
            ->get(route('demandes.pdf.convention', $demande))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $word = $this->actingAs($this->validateur)
            ->get(route('demandes.pdf.convention', [$demande, 'format' => 'docx']))
            ->assertOk()
            ->assertDownload("convention_de_collaboration_{$demande->id}.docx");
        $this->assertStringContainsString('12 000 000 Ariary', $this->texteWord($word));
    }

    public function test_convention_fee_must_be_a_whole_amount_and_zero_means_no_fee(): void
    {
        $demande = $this->makeDemande();
        $this->actingAs($this->validateur)->post(route('validateur.demandes.documents.generer', $demande));

        $contenu = $this->contenuModifie();
        $contenu['convention']['redevance_jour'] = '4 000 Ar';
        $this->actingAs($this->validateur)
            ->put(route('validateur.demandes.documents.modifier', $demande), $contenu)
            ->assertSessionHasErrors('convention.redevance_jour');

        $contenu['convention']['redevance_jour'] = '0';
        $this->actingAs($this->validateur)
            ->put(route('validateur.demandes.documents.modifier', $demande), $contenu)
            ->assertSessionHasNoErrors();
        $html = view('pdf.convention', DocumentsOfficiels::donneesVue($demande->fresh(), 'convention', projet: true))->render();
        $this->assertStringContainsString('ne donne lieu à aucune redevance', $html);
    }

    public function test_a_dossier_generated_before_the_convention_only_receives_it(): void
    {
        $demande = $this->makeDemande();
        $this->actingAs($this->validateur)->post(route('validateur.demandes.documents.generer', $demande));
        $demande->documentsOfficiels()->where('type', 'autorisation')->first()->update(['contenu' => ['objet' => 'Texte relu'] + DocumentsOfficiels::defauts($demande, 'autorisation')]);
        $demande->documentsOfficiels()->where('type', 'convention')->delete();

        // Sans convention, le dossier n'est pas prêt à être validé
        $this->valider($demande, 'Hery Rakoto')->assertSessionHasErrors('statut_id');

        $this->actingAs($this->validateur)->post(route('validateur.demandes.documents.generer', $demande));
        $this->assertSame(3, $demande->documentsOfficiels()->count());
        $this->assertSame('Texte relu', DocumentsOfficiels::contenu($demande, 'autorisation')['objet']);
    }

    public function test_the_demandeur_only_gets_documents_in_pdf(): void
    {
        $demande = $this->makeDemande();
        $this->actingAs($this->validateur)->post(route('validateur.demandes.documents.generer', $demande));
        $this->valider($demande, 'Hery Rakoto')->assertSessionHasNoErrors();

        foreach (['demandes.pdf.autorisation', 'demandes.pdf.cahier-charges', 'demandes.pdf.convention', 'demandes.pdf.formulaire'] as $route) {
            $this->actingAs($this->demandeur)->get(route($route, [$demande, 'format' => 'docx']))->assertForbidden();
            $this->actingAs($this->demandeur)->get(route($route, $demande))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }

        $this->actingAs($this->validateur)
            ->get(route('validateur.demandes.show', $demande))
            ->assertInertia(fn (Assert $page) => $page->where('peutTelechargerWord', true));

        // Un validateur du site, auteur de la demande, n'a lui aussi que le PDF.
        $demande->update(['user_id' => $this->validateur->id]);
        $this->actingAs($this->validateur)->get(route('demandes.pdf.autorisation', [$demande, 'format' => 'docx']))->assertForbidden();
        $this->actingAs($this->validateur)
            ->get(route('validateur.demandes.show', $demande))
            ->assertInertia(fn (Assert $page) => $page->where('peutTelechargerWord', false));
    }

    public function test_an_unknown_format_is_refused(): void
    {
        $demande = $this->makeDemande();
        $this->actingAs($this->validateur)->post(route('validateur.demandes.documents.generer', $demande));
        $this->valider($demande, 'Hery Rakoto');

        $this->actingAs($this->demandeur)->get(route('demandes.pdf.autorisation', [$demande, 'format' => 'odt']))->assertNotFound();
        $this->actingAs($this->demandeur)
            ->get(route('demandes.pdf.autorisation', [$demande, 'format' => 'pdf']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_documents_cannot_be_edited_once_the_demande_is_validated(): void
    {
        $demande = $this->makeDemande();
        $this->actingAs($this->validateur)->post(route('validateur.demandes.documents.generer', $demande));
        $this->valider($demande, 'Hery Rakoto');

        $this->actingAs($this->validateur)
            ->put(route('validateur.demandes.documents.modifier', $demande), $this->contenuModifie())
            ->assertForbidden();
        $this->actingAs($this->validateur)
            ->delete(route('validateur.demandes.documents.annuler', $demande))
            ->assertForbidden();

        $this->assertSame('Inventaire des lémuriens', DocumentsOfficiels::contenu($demande, 'autorisation')['objet']);
    }

    public function test_required_texts_cannot_be_emptied(): void
    {
        $demande = $this->makeDemande();
        $this->actingAs($this->validateur)->post(route('validateur.demandes.documents.generer', $demande));

        $contenu = $this->contenuModifie();
        $contenu['autorisation']['signataire'] = '';

        $this->actingAs($this->validateur)
            ->put(route('validateur.demandes.documents.modifier', $demande), $contenu)
            ->assertSessionHasErrors('autorisation.signataire');
    }

    public function test_draft_preview_is_reserved_to_deciders(): void
    {
        $demande = $this->makeDemande();
        $this->actingAs($this->validateur)->post(route('validateur.demandes.documents.generer', $demande));

        $this->actingAs($this->validateur)
            ->get(route('validateur.demandes.documents.apercu', [$demande, 'autorisation']))
            ->assertOk();
        $this->actingAs($this->demandeur)
            ->get(route('validateur.demandes.documents.apercu', [$demande, 'autorisation']))
            ->assertForbidden();
        // Le demandeur n'a pas accès aux documents avant la validation.
        $this->actingAs($this->demandeur)->get(route('demandes.pdf.autorisation', $demande))->assertForbidden();
        $this->actingAs($this->demandeur)->get(route('demandes.pdf.convention', $demande))->assertForbidden();
        $this->actingAs($this->validateur)
            ->get(route('validateur.demandes.documents.apercu', [$demande, 'convention', 'format' => 'docx']))
            ->assertOk()
            ->assertDownload("projet_convention_de_collaboration_{$demande->id}.docx");
    }

    public function test_other_validators_and_the_author_cannot_generate(): void
    {
        $demande = $this->makeDemande();
        $autre = User::factory()->create(['role' => 'validateur']);

        $this->actingAs($autre)->post(route('validateur.demandes.documents.generer', $demande))->assertForbidden();
        $this->assertSame(0, DocumentOfficiel::count());
    }

    public function test_complement_request_discards_generated_documents(): void
    {
        $demande = $this->makeDemande();
        $this->actingAs($this->validateur)->post(route('validateur.demandes.documents.generer', $demande));

        $this->actingAs($this->validateur)->post(route('validateur.demandes.update-statut', $demande), [
            'statut_id' => Statut::where('code', 'a_completer')->value('id'),
            'commentaire' => 'Joindre le protocole.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, $demande->documentsOfficiels()->count());
    }
}

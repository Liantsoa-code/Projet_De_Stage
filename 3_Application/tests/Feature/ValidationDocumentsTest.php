<?php

namespace Tests\Feature;

use App\Models\Demande;
use App\Models\DocumentOfficiel;
use App\Models\Site;
use App\Models\Statut;
use App\Models\TypeDemande;
use App\Models\User;
use App\Support\DocumentsOfficiels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Validation en deux temps : « Générer » prépare l'autorisation et le cahier des charges,
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
                'introduction' => 'La Fondation FANAMBY autorise :',
                'objet' => 'Inventaire nocturne des lémuriens',
                'conditions' => "Guide local obligatoire\nPas de feu",
                'reserve' => 'Sous réserve du cahier des charges.',
                'signataire' => 'Le Directeur des opérations',
            ],
            'cahier_charges' => [
                'regles' => "Équipe limitée à 10 personnes\nAucun prélèvement",
                'engagement' => 'Le demandeur respecte la faune et la flore.',
            ],
        ];
    }

    public function test_generate_prepares_both_documents_without_changing_status(): void
    {
        $demande = $this->makeDemande();

        $this->actingAs($this->validateur)
            ->post(route('validateur.demandes.documents.generer', $demande))
            ->assertSessionHasNoErrors();

        $this->assertSame('en_analyse', $demande->fresh()->statut->code);
        $this->assertSame(2, $demande->documentsOfficiels()->count());
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

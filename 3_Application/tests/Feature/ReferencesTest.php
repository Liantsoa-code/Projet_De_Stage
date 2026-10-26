<?php

namespace Tests\Feature;

use App\Models\DocumentReference;
use App\Models\ModeleReference;
use App\Models\User;
use App\Support\ModelesReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Page Références, réservée aux validateurs et aux admins : modèles vierges des documents
 * officiels et documents déposés par l'équipe.
 */
class ReferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_demandeur_n_a_pas_acces_aux_references(): void
    {
        $demandeur = User::factory()->create(['role' => 'demandeur']);

        $this->actingAs($demandeur)->get(route('references.index'))->assertForbidden();
        $this->actingAs($demandeur)->get(route('references.modeles.show', ModeleReference::first()))->assertForbidden();
    }

    public function test_un_invite_est_redirige_vers_la_connexion(): void
    {
        $this->get(route('references.index'))->assertRedirect(route('login'));
    }

    public function test_les_cinq_modeles_d_origine_sont_repris_avec_leurs_champs(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'validateur']))
            ->get(route('references.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('References/Index')
                ->has('modeles', 5)
                ->where('modeles.0.titre', 'Avis favorable')
                ->where('modeles.0.champs', fn ($champs) => collect($champs)->contains('nom du bénéficiaire')
                    && collect($champs)->duplicates()->isEmpty())
                ->where('peutGererModeles', false));

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('references.index'))
            ->assertInertia(fn (Assert $page) => $page->where('peutGererModeles', true));
    }

    public function test_chaque_modele_s_ouvre_en_pdf(): void
    {
        $validateur = User::factory()->create(['role' => 'validateur']);

        foreach (ModeleReference::all() as $modele) {
            $this->actingAs($validateur)
                ->get(route('references.modeles.show', $modele))
                ->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');
        }
    }

    public function test_un_modele_inconnu_renvoie_404(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/references/modeles/999')->assertNotFound();
        $this->actingAs($admin)->get('/references/inconnu')->assertNotFound();
    }

    private function donneesModele(array $donnees = []): array
    {
        return $donnees + [
            'titre' => 'Convention de tournage',
            'description' => 'Engagements de l\'équipe de tournage.',
            'usage' => null,
            'icone' => 'film-slate',
            'automatique' => false,
            'contenu' => "# Convention de tournage\n\nEntre l'Association FANAMBY et [société de production].",
        ];
    }

    public function test_un_admin_cree_modifie_et_supprime_un_modele(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('references.modeles.create'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('References/ModeleForm')->where('modele', null));
        $this->actingAs($admin)->post(route('references.modeles.store'), $this->donneesModele())
            ->assertRedirect(route('references.index'));

        $modele = ModeleReference::where('titre', 'Convention de tournage')->sole();
        $this->assertSame(['société de production'], ModelesReference::champs($modele->contenu));

        $this->actingAs($admin)->get(route('references.modeles.edit', $modele))->assertOk();
        $this->actingAs($admin)->put(route('references.modeles.update', $modele), $this->donneesModele([
            'titre' => 'Convention de tournage 2027',
            'contenu' => 'Signé à [lieu].',
        ]))->assertRedirect(route('references.index'));

        $modele->refresh();
        $this->assertSame('Convention de tournage 2027', $modele->titre);
        $this->assertSame('Signé à [lieu].', $modele->contenu);

        $this->actingAs($admin)->delete(route('references.modeles.destroy', $modele))->assertRedirect(route('references.index'));
        $this->assertModelMissing($modele);
        $this->assertSame(5, ModeleReference::count());
    }

    public function test_un_modele_exige_un_titre_unique_une_icone_connue_et_un_contenu(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('references.modeles.store'), $this->donneesModele([
            'titre' => 'Avis favorable',
            'icone' => 'inconnue',
            'contenu' => '',
        ]))->assertSessionHasErrors(['titre', 'icone', 'contenu']);

        // Garder son propre titre en modification est permis
        $modele = ModeleReference::where('titre', 'Avis favorable')->sole();
        $this->actingAs($admin)->put(route('references.modeles.update', $modele), $this->donneesModele(['titre' => 'Avis favorable']))
            ->assertSessionHasNoErrors();
    }

    public function test_un_validateur_ne_peut_pas_gerer_les_modeles(): void
    {
        $validateur = User::factory()->create(['role' => 'validateur']);
        $modele = ModeleReference::first();

        $this->actingAs($validateur)->get(route('references.modeles.create'))->assertForbidden();
        $this->actingAs($validateur)->post(route('references.modeles.store'), $this->donneesModele())->assertForbidden();
        $this->actingAs($validateur)->get(route('references.modeles.edit', $modele))->assertForbidden();
        $this->actingAs($validateur)->put(route('references.modeles.update', $modele), $this->donneesModele())->assertForbidden();
        $this->actingAs($validateur)->delete(route('references.modeles.destroy', $modele))->assertForbidden();
        $this->actingAs($validateur)->post(route('references.modeles.apercu'), ['contenu' => 'Test'])->assertForbidden();

        $this->assertSame(5, ModeleReference::count());
    }

    public function test_l_admin_obtient_un_apercu_pdf_avant_d_enregistrer(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('references.modeles.apercu'), ['titre' => 'Brouillon', 'contenu' => "# Essai\n\n[champ]"])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_la_mise_en_forme_echappe_le_html_saisi(): void
    {
        $html = ModelesReference::html("# Titre\n\n**Objet :** [date] <script>alert(1)</script>\n- un\n- deux\n\n> Signé\n>\n> [nom]\n\n...");

        $this->assertStringContainsString('<h1>Titre</h1>', $html);
        $this->assertStringContainsString('<strong>Objet :</strong> <span class="champ">[date]</span> &lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('<ul><li>un</li><li>deux</li></ul>', $html);
        $this->assertStringContainsString('<p class="droite">Signé<br><br><span class="champ">[nom]</span></p>', $html);
        $this->assertStringContainsString('<div class="ligne"></div>', $html);
    }

    private function deposer(User $user, array $donnees = [])
    {
        return $this->actingAs($user)->post(route('references.documents.store'), $donnees + [
            'titre' => 'Avis favorable signé',
            'description' => 'Exemple pour Menabe',
            'fichier' => UploadedFile::fake()->create('avis.pdf', 120, 'application/pdf'),
        ]);
    }

    public function test_un_validateur_depose_un_document_visible_par_l_equipe(): void
    {
        Storage::fake('local');
        $validateur = User::factory()->create(['role' => 'validateur', 'name' => 'Hery Rakoto']);

        $this->deposer($validateur)->assertRedirect()->assertSessionHas('message');

        $document = DocumentReference::sole();
        $this->assertSame('avis.pdf', $document->nom_fichier);
        $this->assertSame('application/pdf', $document->type_mime);
        Storage::disk('local')->assertExists($document->chemin);

        // Un autre membre de l'équipe le voit, sans pouvoir le supprimer
        $this->actingAs(User::factory()->create(['role' => 'validateur']))
            ->get(route('references.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('documents', 1)
                ->where('documents.0.titre', 'Avis favorable signé')
                ->where('documents.0.auteur', 'Hery Rakoto')
                ->where('documents.0.peut_supprimer', false));
    }

    public function test_le_depot_exige_un_titre_et_un_format_autorise(): void
    {
        Storage::fake('local');
        $validateur = User::factory()->create(['role' => 'validateur']);

        $this->deposer($validateur, ['titre' => ''])->assertSessionHasErrors('titre');
        $this->deposer($validateur, ['fichier' => UploadedFile::fake()->create('page.html', 5, 'text/html')])
            ->assertSessionHasErrors('fichier');

        $this->assertSame(0, DocumentReference::count());
    }

    public function test_un_demandeur_ne_peut_ni_deposer_ni_consulter(): void
    {
        Storage::fake('local');
        $this->deposer(User::factory()->create(['role' => 'validateur']));
        $demandeur = User::factory()->create(['role' => 'demandeur']);

        $this->deposer($demandeur)->assertForbidden();
        $this->actingAs($demandeur)->get(route('references.documents.show', DocumentReference::first()))->assertForbidden();
        $this->assertSame(1, DocumentReference::count());
    }

    public function test_le_document_s_affiche_dans_le_navigateur_ou_se_telecharge(): void
    {
        Storage::fake('local');
        $validateur = User::factory()->create(['role' => 'validateur']);
        $this->deposer($validateur);
        $document = DocumentReference::sole();

        $this->actingAs($validateur)->get(route('references.documents.show', $document))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'inline; filename=avis.pdf');

        $this->actingAs($validateur)->get(route('references.documents.show', ['document' => $document, 'telecharger' => 1]))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=avis.pdf');
    }

    public function test_seuls_l_auteur_et_un_admin_peuvent_supprimer(): void
    {
        Storage::fake('local');
        $auteur = User::factory()->create(['role' => 'validateur']);
        $this->deposer($auteur);
        $this->deposer($auteur);
        [$premier, $second] = DocumentReference::orderBy('id')->get()->all();

        $this->actingAs(User::factory()->create(['role' => 'validateur']))
            ->delete(route('references.documents.destroy', $premier))
            ->assertForbidden();

        $this->actingAs($auteur)->delete(route('references.documents.destroy', $premier))->assertRedirect();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->delete(route('references.documents.destroy', $second))->assertRedirect();

        $this->assertSame(0, DocumentReference::count());
        Storage::disk('local')->assertMissing($premier->chemin);
        Storage::disk('local')->assertMissing($second->chemin);
    }
}

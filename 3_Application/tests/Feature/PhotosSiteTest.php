<?php

namespace Tests\Feature;

use App\Models\PhotoSite;
use App\Models\Site;
use App\Models\User;
use App\Support\PhotosSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Galerie « Voir les photos » : photos réduites à l'envoi, gérées par les admins, visibles par tous ;
 * lien facultatif vers la banque d'images complète.
 */
class PhotosSiteTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        $this->site = Site::create(['nom' => 'Andasibe', 'description' => '', 'polygone_coordonnees' => []]);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function ajouter(int $largeur = 4000, int $hauteur = 3000, array $champs = [])
    {
        return $this->actingAs($this->admin)->post(route('admin.sites.photos.store', $this->site), [
            'photo' => UploadedFile::fake()->image('foret.jpg', $largeur, $hauteur),
        ] + $champs);
    }

    public function test_an_uploaded_photo_is_reduced_to_a_large_image_and_a_thumbnail(): void
    {
        $this->ajouter(4000, 3000, ['legende' => 'Forêt humide', 'credit' => 'Fanamby'])->assertSessionHasNoErrors();

        $photo = PhotoSite::sole();
        $this->assertSame([1600, 1200], [$photo->largeur, $photo->hauteur]);
        $this->assertSame([1600, 1200], array_slice(getimagesizefromstring(Storage::get($photo->chemin)), 0, 2));
        $this->assertSame([480, 360], array_slice(getimagesizefromstring(Storage::get($photo->chemin_vignette)), 0, 2));
        $this->assertSame('Forêt humide', $photo->legende);
        // L'original n'est pas gardé : seulement les deux tailles réduites
        $this->assertCount(2, Storage::allFiles("sites/{$this->site->id}"));
    }

    public function test_a_small_photo_is_not_enlarged(): void
    {
        $this->ajouter(800, 600)->assertSessionHasNoErrors();

        $this->assertSame([800, 600], [PhotoSite::sole()->largeur, PhotoSite::sole()->hauteur]);
    }

    public function test_only_images_are_accepted_and_the_gallery_is_limited(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.sites.photos.store', $this->site), ['photo' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors('photo');

        for ($i = 0; $i < PhotosSite::MAX; $i++) {
            $this->ajouter(600, 400)->assertSessionHasNoErrors();
        }
        $this->ajouter(600, 400)->assertSessionHasErrors('photo');
        $this->assertSame(PhotosSite::MAX, $this->site->photos()->count());
    }

    public function test_only_admins_manage_photos(): void
    {
        $this->ajouter()->assertSessionHasNoErrors();
        $photo = PhotoSite::sole();

        foreach (['validateur', 'demandeur'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->post(route('admin.sites.photos.store', $this->site), ['photo' => UploadedFile::fake()->image('a.jpg')])->assertForbidden();
            $this->actingAs($user)->delete(route('admin.sites.photos.destroy', [$this->site, $photo]))->assertForbidden();
        }
        $this->assertSame(1, PhotoSite::count());
    }

    public function test_caption_order_and_deletion(): void
    {
        $this->ajouter()->assertSessionHasNoErrors();
        $this->ajouter()->assertSessionHasNoErrors();
        [$premiere, $seconde] = PhotoSite::orderBy('ordre')->get()->all();

        $this->actingAs($this->admin)
            ->put(route('admin.sites.photos.update', [$this->site, $premiere]), ['legende' => 'Lac', 'credit' => 'R. Rakoto'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Lac', $premiere->fresh()->legende);

        $this->actingAs($this->admin)
            ->put(route('admin.sites.photos.ordre', $this->site), ['ordre' => [$seconde->id, $premiere->id]])
            ->assertSessionHasNoErrors();
        $this->assertSame([$seconde->id, $premiere->id], $this->site->photos()->orderBy('ordre')->pluck('id')->all());

        $this->actingAs($this->admin)->delete(route('admin.sites.photos.destroy', [$this->site, $premiere]))->assertSessionHasNoErrors();
        $this->assertModelMissing($premiere);
        $this->assertFalse(Storage::exists($premiere->chemin));
        $this->assertFalse(Storage::exists($premiere->chemin_vignette));
    }

    public function test_a_photo_of_another_site_cannot_be_edited_through_this_one(): void
    {
        $this->ajouter()->assertSessionHasNoErrors();
        $autre = Site::create(['nom' => 'Isalo', 'description' => '', 'polygone_coordonnees' => []]);

        $this->actingAs($this->admin)
            ->delete(route('admin.sites.photos.destroy', [$autre, PhotoSite::sole()]))
            ->assertNotFound();
        $this->actingAs($this->admin)
            ->put(route('admin.sites.photos.ordre', $autre), ['ordre' => [PhotoSite::sole()->id]])
            ->assertSessionHasErrors('ordre.0');
    }

    public function test_the_gallery_and_its_files_are_public(): void
    {
        $this->site->update(['lien_photos' => 'https://photos.example.org/andasibe']);
        $this->ajouter(2000, 1000, ['legende' => 'Indri'])->assertSessionHasNoErrors();
        $photo = PhotoSite::sole();
        auth()->logout();

        $this->getJson(route('sites.photos', $this->site))
            ->assertOk()
            ->assertJsonPath('lien_photos', 'https://photos.example.org/andasibe')
            ->assertJsonPath('photos.0.legende', 'Indri')
            ->assertJsonPath('photos.0.url_vignette', route('sites.photos.fichier', [$photo->id, 'vignette']))
            ->assertJsonMissingPath('photos.0.chemin');

        $this->get(route('sites.photos.fichier', [$photo->id, 'vignette']))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=2592000, public');
        $this->get(route('sites.photos.fichier', [$photo->id, 'originale']))->assertNotFound();
    }

    public function test_maps_know_which_sites_have_photos(): void
    {
        $this->ajouter()->assertSessionHasNoErrors();

        $this->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->where('sites.0.nb_photos', 1)
                ->where('sites.0.lien_photos', null));

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('sites.0.nb_photos', 1));
    }

    public function test_the_image_bank_link_must_be_a_web_address(): void
    {
        $donnees = ['nom' => 'Andasibe', 'conditions' => [], 'polygone_coordonnees' => []];

        $this->actingAs($this->admin)
            ->put(route('admin.sites.update', $this->site), $donnees + ['lien_photos' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('lien_photos');

        $this->actingAs($this->admin)
            ->put(route('admin.sites.update', $this->site), $donnees + ['lien_photos' => 'https://photos.example.org/andasibe'])
            ->assertSessionHasNoErrors();
        $this->assertSame('https://photos.example.org/andasibe', $this->site->fresh()->lien_photos);
    }

    public function test_deleting_a_site_removes_its_photo_files(): void
    {
        $this->ajouter()->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->delete(route('admin.sites.destroy', $this->site));

        $this->assertSame(0, PhotoSite::count());
        $this->assertSame([], Storage::allFiles("sites/{$this->site->id}"));
    }
}

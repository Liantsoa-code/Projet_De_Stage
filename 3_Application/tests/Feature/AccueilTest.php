<?php

namespace Tests\Feature;

use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Page d'accueil publique : la liste des sites est mise en cache et vidée à chaque modification d'un site.
 */
class AccueilTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_shows_site_changes_despite_the_cache(): void
    {
        $andasibe = Site::create(['nom' => 'Andasibe', 'description' => '', 'polygone_coordonnees' => []]);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->has('sites', 1));

        $menabe = Site::create(['nom' => 'Menabe', 'description' => '', 'polygone_coordonnees' => []]);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('sites', 2)->where('sites.1.nom', 'Menabe'));

        $andasibe->update(['nom' => 'Andasibe-Mantadia']);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('sites.0.nom', 'Andasibe-Mantadia'));

        $menabe->delete();
        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('sites', 1));
    }
}

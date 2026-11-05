<?php

namespace Tests\Feature;

use App\Models\Demande;
use App\Models\Document;
use App\Models\Site;
use App\Models\Statut;
use App\Models\TypeDemande;
use App\Models\User;
use App\Support\DonneesTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Jeu de données de test (php artisan donnees:test) : découpage du script, pièces jointes de démonstration
 * et analyse refaite. Le chargement du script lui-même n'est pas testé ici : ses TRUNCATE valident la
 * transaction qui isole chaque test.
 */
class JeuDeDonneesTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;
    private TypeDemande $type;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        Statut::firstOrCreate(['code' => 'soumise'], ['libelle' => 'Soumise', 'description' => '']);
        $this->site = Site::create(['nom' => 'Lokobe', 'description' => '', 'polygone_coordonnees' => []]);
        $this->type = TypeDemande::create(['libelle' => 'Documentaire']);
    }

    private function demande(string $statut, array $attrs = []): Demande
    {
        return Demande::create(array_merge([
            'user_id' => User::factory()->create(['role' => 'demandeur'])->id,
            'site_id' => $this->site->id,
            'type_demande_id' => $this->type->id,
            'date_debut' => now()->addDays(20)->toDateString(),
            'date_fin' => now()->addDays(22)->toDateString(),
            'equipe_nombre' => 2,
            'statut_id' => Statut::where('code', $statut)->value('id'),
            'date_soumission' => $statut === 'brouillon' ? null : now(),
            'resultats_analyse' => [],
        ], $attrs));
    }

    public function test_script_splits_into_complete_statements(): void
    {
        $instructions = DonneesTest::instructions(file_get_contents(base_path(DonneesTest::FICHIER)));

        $this->assertGreaterThan(60, count($instructions));
        foreach ($instructions as $sql) {
            $this->assertMatchesRegularExpression('/^(SET|TRUNCATE|INSERT|UPDATE|DELETE|ALTER)\b/', ltrim($sql));
            $this->assertStringEndsWith(';', rtrim($sql));
        }
    }

    public function test_missing_attachments_get_a_demo_pdf_and_existing_ones_are_kept(): void
    {
        $demande = $this->demande('soumise');
        foreach (['present', 'absent'] as $nom) {
            Document::create(['demande_id' => $demande->id, 'nom_fichier' => "{$nom}.pdf", 'chemin' => "demandes/{$demande->id}/{$nom}.pdf", 'type_document' => 'Synopsis']);
        }
        Storage::put("demandes/{$demande->id}/present.pdf", 'fichier du demandeur');

        $this->assertSame(1, DonneesTest::piecesFactices());

        $this->assertStringStartsWith('%PDF', Storage::get("demandes/{$demande->id}/absent.pdf"));
        $this->assertSame('fichier du demandeur', Storage::get("demandes/{$demande->id}/present.pdf"));
        $this->assertSame(0, DonneesTest::piecesFactices());
    }

    public function test_submitted_requests_are_analysed_again_drafts_are_not(): void
    {
        $this->site->conditions()->create(['libelle' => 'Guide local obligatoire']);
        $envoyee = $this->demande('soumise', ['resultats_analyse' => [['type' => 'avertissement', 'message' => 'Ancien format']]]);
        $brouillon = $this->demande('brouillon');

        $this->assertSame(1, DonneesTest::analyser());

        $this->assertSame(['regle_site'], array_column($envoyee->fresh()->resultats_analyse, 'regle'));
        $this->assertSame([], $brouillon->fresh()->resultats_analyse);
    }

    public function test_command_is_refused_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('donnees:test', ['--force' => true])->assertFailed();
    }
}

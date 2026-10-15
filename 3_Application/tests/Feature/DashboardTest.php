<?php

namespace Tests\Feature;

use App\Models\Demande;
use App\Models\Document;
use App\Models\HistoriqueStatut;
use App\Models\Site;
use App\Models\Statut;
use App\Models\TypeDemande;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Tableau de bord adapté à chaque rôle (DashboardController).
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Site $site;
    private Site $autreSite;
    private TypeDemande $type;
    private User $demandeur;
    private User $validateur;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['soumise' => 'Soumise', 'en_analyse' => 'En analyse', 'validee' => 'Validée', 'rejetee' => 'Rejetée'] as $code => $libelle) {
            Statut::firstOrCreate(['code' => $code], ['libelle' => $libelle, 'description' => '']);
        }

        $this->site = Site::create(['nom' => 'Andasibe', 'description' => '', 'polygone_coordonnees' => []]);
        $this->autreSite = Site::create(['nom' => 'Isalo', 'description' => '', 'polygone_coordonnees' => []]);
        $this->type = TypeDemande::create(['libelle' => 'Recherche']);
        $this->type->documentsRequis()->createMany([['libelle' => 'Protocole'], ['libelle' => 'Assurance']]);
        $this->demandeur = User::factory()->create(['role' => 'demandeur', 'name' => 'Rova Demandeur']);
        $this->validateur = User::factory()->create(['role' => 'validateur']);
        $this->validateur->sites()->attach($this->site);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function makeDemande(string $statut, array $attrs = []): Demande
    {
        return Demande::create(array_merge([
            'user_id' => $this->demandeur->id,
            'site_id' => $this->site->id,
            'type_demande_id' => $this->type->id,
            'description' => 'Test',
            'date_debut' => now()->addDays(40)->toDateString(),
            'date_fin' => now()->addDays(42)->toDateString(),
            'equipe_nombre' => 2,
            'statut_id' => Statut::where('code', $statut)->value('id'),
            'date_soumission' => $statut === 'brouillon' ? null : now(),
            'resultats_analyse' => [],
        ], $attrs));
    }

    /** Dossier décidé aujourd'hui, N jours après sa soumission. */
    private function decision(string $code, int $jours, ?string $passeParComplement = null): Demande
    {
        $demande = $this->makeDemande($code, [
            'date_soumission' => now()->subDays($jours),
            'date_debut' => now()->subDays(20)->toDateString(),
            'date_fin' => now()->subDays(18)->toDateString(),
        ]);
        if ($passeParComplement) {
            HistoriqueStatut::create([
                'demande_id' => $demande->id, 'user_id' => $this->validateur->id,
                'ancien_statut_id' => Statut::where('code', 'soumise')->value('id'),
                'nouveau_statut_id' => Statut::where('code', 'a_completer')->value('id'),
                'commentaire' => $passeParComplement, 'date_changement' => now(),
            ]);
        }
        HistoriqueStatut::create([
            'demande_id' => $demande->id, 'user_id' => $this->validateur->id,
            'ancien_statut_id' => Statut::where('code', 'en_analyse')->value('id'),
            'nouveau_statut_id' => $demande->statut_id,
            'commentaire' => 'Décision', 'date_changement' => now(),
        ]);

        return $demande;
    }

    public function test_validateur_file_is_sorted_by_urgency_and_limited_to_his_sites(): void
    {
        $normal = $this->makeDemande('soumise');
        $retard = $this->makeDemande('en_analyse', ['date_soumission' => now()->subDays(10)]);
        $imminent = $this->makeDemande('soumise', ['date_debut' => now()->addDays(3)->toDateString(), 'date_fin' => now()->addDays(5)->toDateString()]);
        $this->makeDemande('soumise', ['site_id' => $this->autreSite->id]);
        $this->makeDemande('a_completer');

        $this->actingAs($this->validateur)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('file', 3)
                ->where('file.0.id', $imminent->id)
                ->where('file.0.jours_avant', 3)
                ->where('file.1.id', $retard->id)
                ->where('file.1.retard', true)
                ->where('file.2.id', $normal->id)
                ->where('compteurs.en_attente', 3)
                ->where('compteurs.non_pris', 2)
                ->where('compteurs.imminents', 1)
                ->where('compteurs.retards', 1)
                ->where('compteurs.attente_demandeur', 1)
                ->has('attente_demandeur', 1)
                ->missing('qualite')
            );
    }

    public function test_validateur_sees_his_activity_of_the_month(): void
    {
        $this->decision('validee', 2);
        $this->decision('rejetee', 4, 'Pièce manquante');

        $this->actingAs($this->validateur)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('activite.validee', 1)
                ->where('activite.rejetee', 1)
                ->where('activite.a_completer', 1)
                ->where('delai_median', 3)
            );
    }

    public function test_admin_sees_service_quality_with_validation_rate_on_decided_files_only(): void
    {
        $this->decision('validee', 6);
        $this->decision('validee', 2, 'Préciser le protocole');
        $this->decision('rejetee', 20);
        // Un dossier en attente ne doit pas faire baisser le taux de validation
        $this->makeDemande('soumise');

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('qualite.decisions', 3)
                ->where('qualite.delai_median', 6)
                ->where('qualite.taux_validation', 67)
                ->where('qualite.pct_objectif', 67)
                ->where('qualite.pct_complement', 33)
                ->has('par_mois.courante', 12)
                ->missing('file')
            );
    }

    public function test_admin_sees_organisation_and_field_presence(): void
    {
        // Séjour en cours : 4 personnes pendant 4 jours
        $this->makeDemande('validee', [
            'equipe_nombre' => 4,
            'date_debut' => now()->subDay()->toDateString(),
            'date_fin' => now()->addDays(2)->toDateString(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('terrain.sejours', 1)
                ->where('terrain.personnes', 4)
                ->has('sites_sans_validateur', 1)
                ->where('sites_sans_validateur.0.nom', 'Isalo')
                ->where('frequentation.0.nom', 'Andasibe')
                ->where('frequentation.0.personnes_jours', 16)
                ->where('charge_validateurs.0.sites', 1)
            );
    }

    public function test_demandeur_sees_missing_documents_and_usual_delay(): void
    {
        $brouillon = $this->makeDemande('brouillon');
        Document::create(['demande_id' => $brouillon->id, 'nom_fichier' => 'p.pdf', 'chemin' => 'x', 'document_requis_id' => $this->type->documentsRequis->first()->id, 'type_document' => 'Protocole']);
        $this->makeDemande('soumise', ['date_soumission' => now()->subDays(2)]);
        // Le brouillon est le plus récemment modifié : il arrive en tête de liste
        $brouillon->updated_at = now()->addMinute();
        $brouillon->save();
        foreach ([4, 6, 8] as $jours) {
            $this->decision('validee', $jours);
        }

        $this->actingAs($this->demandeur)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('en_cours', 2)
                ->where('en_cours.0.pieces_manquantes', 1)
                ->where('en_cours.1.attente', 2)
                ->where('en_cours.1.delai_habituel', 6)
                ->missing('en_cours.0.documents')
            );
    }
}

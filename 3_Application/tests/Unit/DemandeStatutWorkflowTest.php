<?php

namespace Tests\Unit;

use App\Models\Statut;
use App\Support\DemandeStatutWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemandeStatutWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function statut(string $code, string $libelle): Statut
    {
        return Statut::firstOrCreate(
            ['code' => $code],
            ['libelle' => $libelle, 'description' => '']
        );
    }

    public function test_terminal_statuts_cannot_transition(): void
    {
        $rejetee = $this->statut('rejetee', 'Rejetée');
        $soumise = $this->statut('soumise', 'Soumise');

        $this->assertTrue(DemandeStatutWorkflow::isTerminal($rejetee));
        $this->assertFalse(DemandeStatutWorkflow::canTransition($rejetee, $soumise));
    }

    public function test_cannot_downgrade_or_keep_same_statut(): void
    {
        $soumise = $this->statut('soumise', 'Soumise');
        $enAnalyse = $this->statut('en_analyse', 'En analyse');

        $this->assertFalse(DemandeStatutWorkflow::canTransition($enAnalyse, $soumise));
        $this->assertFalse(DemandeStatutWorkflow::canTransition($enAnalyse, $enAnalyse));
    }

    public function test_can_advance_statut(): void
    {
        $soumise = $this->statut('soumise', 'Soumise');
        $enAnalyse = $this->statut('en_analyse', 'En analyse');
        $validee = $this->statut('validee', 'Validée');

        $this->assertTrue(DemandeStatutWorkflow::canTransition($soumise, $enAnalyse));
        $this->assertTrue(DemandeStatutWorkflow::canTransition($soumise, $validee));
        $this->assertTrue(DemandeStatutWorkflow::canTransition($enAnalyse, $validee));
    }
    public function test_validateur_can_request_complement_from_soumise_or_en_analyse(): void
    {
        $soumise = $this->statut('soumise', 'Soumise');
        $enAnalyse = $this->statut('en_analyse', 'En analyse');
        $aCompleter = $this->statut('a_completer', 'À compléter');
        $validee = $this->statut('validee', 'Validée');

        $this->assertTrue(DemandeStatutWorkflow::canTransition($soumise, $aCompleter));
        $this->assertTrue(DemandeStatutWorkflow::canTransition($enAnalyse, $aCompleter));
        $this->assertFalse(DemandeStatutWorkflow::canTransition($validee, $aCompleter));
        // En attente du demandeur : le validateur peut encore trancher, mais pas « reprendre » l'analyse lui-même
        $this->assertTrue(DemandeStatutWorkflow::canTransition($aCompleter, $validee));
        $this->assertFalse(DemandeStatutWorkflow::canTransition($aCompleter, $enAnalyse));
    }

    public function test_no_decision_on_brouillon(): void
    {
        $brouillon = $this->statut('brouillon', 'Brouillon');
        $soumise = $this->statut('soumise', 'Soumise');

        $this->assertFalse(DemandeStatutWorkflow::canTransition($brouillon, $soumise));
        $this->assertTrue(DemandeStatutWorkflow::isEditableByDemandeur($brouillon));
        $this->assertFalse(DemandeStatutWorkflow::isEditableByDemandeur($soumise));
    }
}

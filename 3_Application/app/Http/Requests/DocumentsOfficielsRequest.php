<?php

namespace App\Http\Requests;

use App\Support\DocumentsOfficiels;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Modification des textes de l'autorisation, du cahier des charges et de la convention avant la validation.
 */
class DocumentsOfficielsRequest extends FormRequest
{
    /**
     * Mêmes droits qu'une décision : dossier d'un de ses sites, et jamais sa propre demande.
     */
    public function authorize(): Response
    {
        return Gate::inspect('decider', $this->route('demande'));
    }

    public function rules(): array
    {
        return DocumentsOfficiels::regles();
    }

    public function attributes(): array
    {
        return [
            'autorisation.introduction' => 'introduction',
            'autorisation.objet' => 'objet de la mission',
            'autorisation.conditions' => 'conditions particulières',
            'autorisation.reserve' => 'mention de réserve',
            'autorisation.signataire' => 'signataire',
            'cahier_charges.regles' => 'règles à respecter',
            'cahier_charges.engagement' => 'engagement du demandeur',
            'convention.partie_fanamby' => 'présentation de Fanamby',
            'convention.objet' => 'objet de la convention',
            'convention.avis' => 'avis favorable',
            'convention.modalites' => 'modalités des opérations',
            'convention.redevance_jour' => 'redevance par jour',
            'convention.paiement' => 'modalités de paiement',
            'convention.affectation' => 'affectation des recettes',
            'convention.obligations_beneficiaire' => 'obligations du bénéficiaire',
            'convention.obligations_fanamby' => 'obligations de Fanamby',
            'convention.responsabilite' => 'responsabilité',
            'convention.litiges' => 'litiges',
            'convention.entree_vigueur' => 'entrée en vigueur',
            'convention.lieu' => 'lieu de signature',
            'convention.signataire' => 'signataire',
        ];
    }
}

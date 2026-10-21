<?php

namespace App\Http\Requests;

use App\Support\DocumentsOfficiels;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Modification des textes de l'autorisation et du cahier des charges avant la validation.
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
        ];
    }
}

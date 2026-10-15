<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Décision d'un validateur ou d'un admin sur une demande (changement de statut commenté).
 */
class DecisionRequest extends FormRequest
{
    /**
     * Vérifié avant la validation : dossier d'un de ses sites, et jamais sa propre demande.
     */
    public function authorize(): Response
    {
        return Gate::inspect('decider', $this->route('demande'));
    }

    public function rules(): array
    {
        return [
            'statut_id' => 'required|exists:statuts,id',
            'commentaire' => 'required|string|max:1000',
        ];
    }
}

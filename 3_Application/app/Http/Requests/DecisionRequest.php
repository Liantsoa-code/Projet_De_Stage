<?php

namespace App\Http\Requests;

use App\Models\Statut;
use App\Support\DocumentsOfficiels;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * Décision d'un validateur ou d'un admin sur une demande (changement de statut commenté,
 * signé par le nom du décideur pour une validation).
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
            'statut_id' => 'required|exists:t_statuts,id',
            'commentaire' => 'required|string|max:1000',
            'signature' => 'nullable|string|max:255',
        ];
    }

    /**
     * La validation définitive se signe : le décideur retape son nom.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (Statut::whereKey($this->input('statut_id'))->value('code') !== 'validee') {
                    return;
                }

                if (! DocumentsOfficiels::signatureValide($this->user(), $this->input('signature'))) {
                    $validator->errors()->add('signature', 'Pour signer, retapez exactement votre nom : ' . $this->user()->name . '.');
                }
            },
        ];
    }
}

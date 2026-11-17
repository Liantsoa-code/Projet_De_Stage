<?php

namespace App\Http\Requests;

use App\Models\Statut;
use App\Support\DocumentsOfficiels;
use App\Support\PartieDossier;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
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
            // Demande de complément : partie à compléter, où la notification amène le demandeur
            'partie' => [
                Rule::requiredIf(fn () => $this->estComplement()),
                'nullable',
                Rule::in(array_keys(PartieDossier::COMPLEMENT)),
            ],
            'signature' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'partie.required' => 'Indiquez la partie du dossier à compléter.',
        ];
    }

    public function estComplement(): bool
    {
        return Statut::whereKey($this->input('statut_id'))->value('code') === 'a_completer';
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

<?php

namespace App\Http\Requests;

use App\Support\ModelesReference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Création et modification d'un modèle de la page Références (admins uniquement).
 */
class ModeleReferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('is-admin');
    }

    public function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:150', Rule::unique('t_modeles_reference', 'titre')->ignore($this->route('modele'))],
            'description' => 'required|string|max:1000',
            'usage' => 'nullable|string|max:1000',
            'icone' => ['required', Rule::in(ModelesReference::ICONES)],
            'automatique' => 'boolean',
            'contenu' => 'required|string|max:' . ModelesReference::CONTENU_MAX,
        ];
    }

    public function messages(): array
    {
        return [
            'titre.required' => 'Donnez un titre au modèle.',
            'titre.max' => 'Le titre ne doit pas dépasser 150 caractères.',
            'titre.unique' => 'Un modèle porte déjà ce titre.',
            'description.required' => 'Décrivez en une phrase à quoi sert ce document.',
            'description.max' => 'La description ne doit pas dépasser 1000 caractères.',
            'usage.max' => 'Ce texte ne doit pas dépasser 1000 caractères.',
            'icone.required' => 'Choisissez une icône.',
            'icone.in' => 'Choisissez une icône de la liste.',
            'contenu.required' => 'Le contenu du modèle est obligatoire.',
            'contenu.max' => 'Le contenu ne doit pas dépasser ' . ModelesReference::CONTENU_MAX . ' caractères.',
        ];
    }
}

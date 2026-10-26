<?php

namespace App\Http\Requests;

use App\Models\Demande;
use App\Models\TypeDemande;
use App\Support\DemandeDossier;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Création et modification d'une demande : brouillon, soumission ou renvoi d'un complément.
 * Les champs suivent le formulaire papier de Fanamby : I. demandeur, II. projet, V. engagement.
 *
 * Brouillon : seuls l'aire protégée et le type de projet sont obligatoires.
 * Soumission : tous les champs, l'acceptation des engagements et toutes les pièces
 * (sauf celles déjà fournies ou reprises d'un autre dossier).
 */
class DemandeRequest extends FormRequest
{
    private ?TypeDemande $typeDemande = null;

    private ?Collection $piecesReprises = null;

    /**
     * Vérifié avant la validation : seul l'auteur modifie sa demande, et seulement si elle est modifiable.
     */
    public function authorize(): Response
    {
        return $this->demande() ? Gate::inspect('update', $this->demande()) : Response::allow();
    }

    public function rules(): array
    {
        $requis = $this->estSoumission() ? 'required' : 'nullable';

        $rules = [
            'site_id' => 'required|exists:t_sites,id',
            'type_demande_id' => 'required|exists:t_type_demandes,id',
            // I. Informations sur le demandeur (enregistrées sur son compte)
            'organisation' => "$requis|string|max:150",
            'fonction' => "$requis|string|max:100",
            'adresse' => "$requis|string|max:255",
            'telephone' => "$requis|string|max:30|regex:/^[0-9 +().-]+$/",
            // II. Informations sur le projet
            'titre' => "$requis|string|max:200",
            'type_precision' => ($this->estSoumission() && $this->precisionRequise() ? 'required' : 'nullable') . '|string|max:150',
            'description' => "$requis|string",
            'objectifs' => "$requis|string",
            'date_debut' => "$requis|date|after_or_equal:today",
            'date_fin' => "$requis|date" . ($this->filled('date_debut') ? '|after_or_equal:date_debut' : ''),
            'equipe_nombre' => "$requis|integer|min:1",
            // V. Engagement du demandeur, renouvelé à chaque envoi
            'piece_identite' => "$requis|string|max:50",
            'engagement' => $this->estSoumission() ? 'accepted' : 'nullable',
            'message_complement' => 'nullable|string|max:1000',
            'reprendre' => 'nullable|array',
            'reprendre.*' => 'integer',
        ];

        // Pièces exigées par le type de projet choisi, hors celles déjà jointes ou reprises d'un autre dossier
        $dejaFournies = [
            ...$this->demande()?->documents()->pluck('document_requis_id')->all() ?? [],
            ...$this->piecesReprises()->keys()->all(),
        ];

        foreach ($this->typeDemande()?->documentsRequis ?? [] as $docRequis) {
            $obligatoire = $this->estSoumission() && ! in_array($docRequis->id, $dejaFournies);
            $rules['doc_' . $docRequis->id] = ($obligatoire ? 'required' : 'nullable') . '|file|mimes:' . DemandeDossier::EXTENSIONS_AUTORISEES . '|max:20480'; // Limité à 20 Mo par fichier
        }

        return $rules;
    }

    public function messages(): array
    {
        $messages = [
            'organisation.required' => 'Indiquez votre institution, société de production ou organisation.',
            'fonction.required' => 'Indiquez votre fonction.',
            'adresse.required' => 'Indiquez votre adresse.',
            'telephone.required' => 'Indiquez votre numéro de téléphone.',
            'telephone.regex' => 'Le numéro de téléphone ne peut contenir que des chiffres, des espaces et les signes + ( ) - .',
            'titre.required' => 'Donnez un titre au projet.',
            'type_precision.required' => 'Précisez le type de projet.',
            'description.required' => 'Résumez le synopsis du projet.',
            'objectifs.required' => 'Indiquez le ou les objectifs principaux du projet.',
            'date_debut.after_or_equal' => "La date de début ne peut pas être antérieure à aujourd'hui.",
            'date_fin.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
            'piece_identite.required' => 'Indiquez le numéro de votre CIN ou de votre passeport.',
            'engagement.accepted' => 'Acceptez les engagements du demandeur pour envoyer la demande.',
        ];

        foreach ($this->typeDemande()?->documentsRequis ?? [] as $docRequis) {
            $key = 'doc_' . $docRequis->id;
            $docName = $docRequis->libelle;
            $messages[$key . '.required'] = "Le document '$docName' est obligatoire.";
            $messages[$key . '.file'] = "Le document '$docName' doit être un fichier valide.";
            $messages[$key . '.mimes'] = "Le document '$docName' doit être au format PDF, image (JPG, PNG) ou Word/OpenDocument.";
            $messages[$key . '.max'] = "Le fichier '$docName' ne doit pas dépasser 20 Mo.";
        }

        return $messages;
    }

    /**
     * Le dossier est-il envoyé (soumission ou renvoi d'un complément) plutôt qu'enregistré en brouillon ?
     * Un dossier à compléter ne peut pas repasser en brouillon : il est forcément renvoyé.
     */
    public function estSoumission(): bool
    {
        return $this->demande()?->statut?->code === 'a_completer' || $this->input('action') !== 'brouillon';
    }

    /**
     * Type de projet choisi, avec ses pièces requises.
     */
    public function typeDemande(): ?TypeDemande
    {
        return $this->typeDemande ??= TypeDemande::with('documentsRequis')->find($this->input('type_demande_id'));
    }

    /**
     * Le type choisi demande-t-il une précision (« Autre (précisez) ») ?
     */
    public function precisionRequise(): bool
    {
        return (bool) $this->typeDemande()?->precision_requise;
    }

    /**
     * Pièces réutilisables que le demandeur choisit de reprendre (reprendre[] = ids de pièces requises),
     * limitées à celles qu'il a réellement déposées dans un autre de ses dossiers.
     *
     * @return Collection<int, \App\Models\Document>
     */
    public function piecesReprises(): Collection
    {
        if ($this->piecesReprises !== null) {
            return $this->piecesReprises;
        }

        $ids = array_map('intval', array_filter((array) $this->input('reprendre', []), 'is_numeric'));

        return $this->piecesReprises = $ids
            ? DemandeDossier::piecesReutilisables($this->user()->id, $this->demande()?->id)->only($ids)
            : collect();
    }

    /**
     * Demande modifiée (null à la création).
     */
    private function demande(): ?Demande
    {
        return $this->route('demande');
    }
}

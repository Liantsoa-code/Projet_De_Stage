<?php

namespace App\Http\Requests;

use App\Models\Demande;
use App\Models\TypeDemande;
use App\Support\DemandeDossier;
use App\Support\DocumentsOfficiels;
use Carbon\Carbon;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * Création et modification d'une demande : brouillon, soumission ou renvoi d'un complément.
 * Les champs suivent le formulaire papier de Fanamby : I. demandeur, II. projet, V. engagement.
 *
 * Brouillon : seuls l'aire protégée et le type de projet sont obligatoires.
 * Soumission : tous les champs, l'acceptation des engagements signée par le nom du demandeur
 * et toutes les pièces (sauf celles déjà fournies ou reprises d'un autre dossier).
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
            // II. Informations sur la demande
            'titre' => "$requis|string|max:200",
            'type_precision' => ($this->estSoumission() && $this->precisionRequise() ? 'required' : 'nullable') . '|string|max:150',
            'description' => "$requis|string",
            'objectifs' => "$requis|string",
            // Réponse à un point à clarifier, exigée pour renvoyer ce complément
            'clarification' => ($this->demande()?->clarificationDemandee() ? 'required' : 'nullable') . '|string|max:2000',
            // Arrivée sur le terrain, au plus tard le jour du début des activités
            'date_arrivee' => "$requis|date|after_or_equal:today" . ($this->filled('date_debut') ? '|before_or_equal:date_debut' : ''),
            'date_debut' => "$requis|date|after_or_equal:today",
            'date_fin' => "$requis|date" . ($this->filled('date_debut') ? '|after_or_equal:date_debut' : ''),
            'equipe_nombre' => "$requis|integer|min:1",
            // Demande de dérogation (dates sur une période bloquante) : exigée par le contrôleur après l'analyse
            'derogation_motif' => 'nullable|string|max:2000',
            // V. Engagement du demandeur, renouvelé à chaque envoi
            'piece_identite' => "$requis|string|max:50",
            'engagement' => $this->estSoumission() ? 'accepted' : 'nullable',
            'signature' => 'nullable|string|max:255',
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
            'description.required' => 'Résumez le projet.',
            'objectifs.required' => 'Indiquez le ou les objectifs principaux du projet.',
            'clarification.required' => 'Répondez au point à clarifier demandé par le validateur.',
            'date_arrivee.required' => "Indiquez la date d'arrivée sur le terrain.",
            'date_arrivee.after_or_equal' => "La date d'arrivée ne peut pas être antérieure à aujourd'hui.",
            'date_arrivee.before_or_equal' => "L'arrivée sur le terrain doit avoir lieu au plus tard le jour du début des activités.",
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
     * Section V : l'engagement se signe, le demandeur retape son nom complet (celui du profil),
     * comme le validateur pour valider un dossier.
     * Une demande ne dépasse pas 6 mois, de l'arrivée sur le terrain (à défaut du début) à la fin.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->estSoumission() && ! DocumentsOfficiels::signatureValide($this->user(), $this->input('signature'))) {
                    $validator->errors()->add('signature', 'Pour signer, retapez exactement votre nom complet : ' . $this->user()->name . '.');
                }
            },
            function (Validator $validator) {
                $limite = $this->finAuPlusTard();
                if ($limite && ! $validator->errors()->has('date_fin') && Carbon::parse($this->input('date_fin'))->gt($limite)) {
                    $validator->errors()->add('date_fin', 'Une demande ne peut pas dépasser ' . Demande::DUREE_MAX_MOIS
                        . ' mois, de l\'arrivée sur le terrain à la fin des activités : fin au plus tard le ' . $limite->format('d/m/Y') . '.');
                }
            },
        ];
    }

    /**
     * Fin des activités au plus tard : 6 mois après l'arrivée sur le terrain (à défaut le début des
     * activités), sans déborder sur le mois suivant (31 août → 28 février). Null si les dates manquent.
     */
    private function finAuPlusTard(): ?Carbon
    {
        $depart = $this->dateValide('date_arrivee') ?? $this->dateValide('date_debut');

        return $depart && $this->dateValide('date_fin') ? $depart->addMonthsNoOverflow(Demande::DUREE_MAX_MOIS) : null;
    }

    private function dateValide(string $champ): ?Carbon
    {
        $valeur = $this->input($champ);

        return is_string($valeur) && strtotime($valeur) !== false ? Carbon::parse($valeur)->startOfDay() : null;
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

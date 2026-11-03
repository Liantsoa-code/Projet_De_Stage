<script setup>
import FeuilleA4 from './FeuilleA4.vue';
import { horodatage } from '@/utils/documents.js';
import { formatDate } from '@/utils/statut.js';
import { computed } from 'vue';

// Aperçu du formulaire de demande rempli, tel qu'il sera généré à l'envoi : même mise en page que
// resources/views/pdf/formulaire_demande.blade.php, à garder en phase. La section VI (décision de Fanamby)
// reste vierge : aucun avis n'est rendu sur un dossier en cours de saisie.
const props = defineProps({
    // Champs de la demande, avec user (coordonnées des sections I et V) ; reference absente avant le premier envoi
    demande: { type: Object, required: true },
    types: { type: Array, required: true },
    sites: { type: Array, required: true },
    engagements: { type: Array, required: true },
});

// Cases par lignes de trois, dans l'ordre de la vue PDF (types par id, aires protégées par nom)
const parTrois = (liste) => Array.from({ length: Math.ceil(liste.length / 3) }, (_, i) => liste.slice(i * 3, i * 3 + 3));
const lignesTypes = computed(() => parTrois([...props.types].sort((a, b) => a.id - b.id)));
const lignesSites = computed(() => parTrois([...props.sites].sort((a, b) => a.nom.localeCompare(b.nom, 'fr'))));

const date = (valeur) => (valeur ? formatDate(valeur) : '');
const reference = computed(() => props.demande.reference ?? 'attribuée à l\'envoi');
</script>

<template>
    <FeuilleA4 class="doc" marges="60px 70px 30px">
        <h1>Formulaire de Demande d'Autorisation pour Projet Photographique /<br>Vidéographique dans les aires protégées gérées par Fanamby</h1>

        <h2>I. Informations sur le Demandeur</h2>
        <table class="champs">
            <tr><th>Nom et Prénoms :</th><td>{{ demande.user.name }}</td></tr>
            <tr><th>Institution / Société de production / Organisation :</th><td>{{ demande.user.organisation }}</td></tr>
            <tr><th>Fonction :</th><td>{{ demande.user.fonction }}</td></tr>
            <tr><th>Adresse :</th><td>{{ demande.user.adresse }}</td></tr>
            <tr><th>Téléphone :</th><td>{{ demande.user.telephone }}</td></tr>
            <tr><th>Email :</th><td>{{ demande.user.email }}</td></tr>
        </table>

        <h2>II. Informations sur le Projet</h2>
        <table class="champs">
            <tr><th><strong>Titre du projet :</strong></th><td>{{ demande.titre }}</td></tr>
        </table>

        <p class="titre"><strong>Type de projet :</strong></p>
        <table class="cases">
            <tr v-for="(ligne, i) in lignesTypes" :key="i">
                <td v-for="type in ligne" :key="type.id">
                    <span class="case">{{ type.id === demande.type_demande_id ? 'X' : '' }}</span>{{ type.libelle }}<template v-if="type.precision_requise"> (précisez) :</template>
                    <span v-if="type.precision_requise && type.id === demande.type_demande_id && demande.type_precision" class="precision">{{ demande.type_precision }}</span>
                </td>
                <td v-for="n in 3 - ligne.length" :key="`vide-${n}`"></td>
            </tr>
        </table>

        <p class="titre"><strong>Résumé du synopsis :</strong></p>
        <div class="texte">{{ demande.description }}</div>

        <p class="titre"><strong>Objectif(s) principal(aux) du projet :</strong></p>
        <div class="texte">{{ demande.objectifs }}</div>

        <p class="titre"><strong>Aire(s) Protégée(s) concernée(s) :</strong></p>
        <table class="cases">
            <tr v-for="(ligne, i) in lignesSites" :key="i">
                <td v-for="site in ligne" :key="site.id"><span class="case">{{ site.id === demande.site_id ? 'X' : '' }}</span>{{ site.nom }}</td>
                <td v-for="n in 3 - ligne.length" :key="`vide-${n}`"></td>
            </tr>
        </table>

        <p class="titre"><strong>Période prévue :</strong></p>
        <table class="champs champs--court">
            <tr><th>- Début :</th><td>{{ date(demande.date_debut) }}</td></tr>
            <tr><th>- Fin :</th><td>{{ date(demande.date_fin) }}</td></tr>
        </table>

        <template #pied>
            <span class="pied">Formulaire rempli sur la plateforme de gestion Fanamby · Référence {{ reference }} · Édité le {{ horodatage() }}</span>
        </template>
    </FeuilleA4>

    <FeuilleA4 class="doc" marges="60px 70px 30px">
        <h2 class="premier">V. Engagement du Demandeur</h2>
        <table class="champs">
            <tr><th>Je soussigné(e)</th><td>{{ demande.user.name }}</td></tr>
            <tr><th>CIN / Passeport N°</th><td>{{ demande.user.piece_identite }}</td></tr>
        </table>
        <p class="engage">m'engage à :</p>
        <ul>
            <li v-for="(engagement, i) in engagements" :key="i">{{ engagement }}{{ i === engagements.length - 1 ? '.' : ' ;' }}</li>
        </ul>
        <table class="champs champs--court">
            <tr><th>Date :</th><td>{{ date(demande.date_soumission) }}</td></tr>
        </table>
        <p class="titre">Signature du demandeur :</p>
        <p v-if="demande.date_soumission" class="signature">
            Engagements acceptés en ligne par {{ demande.user.name }} lors de l'envoi du dossier, le {{ horodatage(demande.date_soumission) }}.
        </p>

        <h2>VI. Décision de Fanamby (à remplir par le gestionnaire)</h2>
        <p class="avis">Avis de Fanamby :</p>
        <p class="avis"><span class="case"></span>Avis favorable</p>
        <p class="avis avis--dernier"><span class="case"></span>Avis défavorable</p>

        <p class="titre">Observations :</p>
        <div class="texte"></div>

        <table class="champs">
            <tr><th>Date :</th><td></td></tr>
            <tr><th>Nom et fonction du responsable Fanamby :</th><td></td></tr>
            <tr><th>Signature et cachet :</th><td></td></tr>
        </table>

        <template #pied>
            <span class="pied">Formulaire rempli sur la plateforme de gestion Fanamby · Référence {{ reference }} · Édité le {{ horodatage() }}</span>
        </template>
    </FeuilleA4>
</template>

<style scoped>
.doc { font: 12.5px/1.45 'Times New Roman', 'Times', serif; }
h1 { font-size: 16px; text-align: center; margin: 0 0 30px; line-height: 1.35; }
h2 { font-size: 13.5px; margin: 26px 0 10px; }
h2.premier { margin-top: 0; }
.champs { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
.champs--court { width: 45%; }
.champs th { width: 1%; white-space: nowrap; padding: 5px 6px 2px 0; font-weight: normal; text-align: left; vertical-align: bottom; }
.champs td { padding: 5px 0 2px; border-bottom: 1px dotted #6E7A72; vertical-align: bottom; }
.titre { margin: 12px 0 6px; }
.texte { margin: 0 0 6px; padding: 4px 0; border-bottom: 1px dotted #6E7A72; min-height: 25px; white-space: pre-line; }
.cases { width: 100%; border-collapse: collapse; }
.cases td { width: 33%; padding: 2px 8px 2px 0; vertical-align: top; }
.case { display: inline-block; width: 10px; height: 10px; border: 1px solid #15201A; margin-right: 6px; text-align: center; font: bold 9px/10px 'Helvetica', 'Arial', sans-serif; vertical-align: -1px; }
.precision { display: block; margin-left: 18px; font-style: italic; }
ul { margin: 4px 0 10px; padding-left: 18px; }
.engage { margin: 8px 0 0; }
.signature { margin: 10px 0 0; color: #3D4A42; font-style: italic; }
.avis { margin: 0 0 2px; }
.avis--dernier { margin-bottom: 10px; }
.pied { font: 10px 'Helvetica', 'Arial', sans-serif; }
</style>

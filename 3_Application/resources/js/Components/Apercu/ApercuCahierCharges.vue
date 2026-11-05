<script setup>
import FeuilleA4 from './FeuilleA4.vue';
import { contraintesRelevees } from '@/utils/analyse.js';
import { horodatage, lignes } from '@/utils/documents.js';
import { formatDate } from '@/utils/statut.js';
import { computed } from 'vue';

// Aperçu du cahier des charges, en projet : même mise en page que resources/views/pdf/cahier_charges.blade.php,
// à garder en phase. Les règles et l'engagement viennent du formulaire en cours de saisie.
const props = defineProps({
    demande: { type: Object, required: true },
    contenu: { type: Object, required: true },
});

const regles = computed(() => lignes(props.contenu.regles));
const alertes = computed(() => contraintesRelevees(props.demande.resultats_analyse));
</script>

<template>
    <FeuilleA4 class="doc">
        <div class="logo"><img src="/assets/logo-fanamby-192.png" alt="Fanamby"></div>

        <h1>CAHIER DES CHARGES À RESPECTER</h1>

        <div class="contenu">
            <p>
                Ce document est une annexe obligatoire à l'autorisation d'accès pour <strong>{{ demande.user.name }}</strong>
                dans l'aire protégée de <strong>{{ demande.site.nom }}</strong>.
            </p>

            <h3>Règles spécifiques liées au type de projet :</h3>
            <ul>
                <li v-for="(regle, i) in regles" :key="i">{{ regle }}</li>
                <li v-if="!regles.length">Aucune règle spécifique supplémentaire enregistrée.</li>
            </ul>

            <div v-if="alertes.length" class="alerte">
                <strong>Avertissements lors de l'analyse :</strong>
                <ul>
                    <li v-for="(alerte, i) in alertes" :key="i">{{ alerte.message }}</li>
                </ul>
            </div>

            <h3>Engagement du demandeur</h3>
            <p class="texte">{{ contenu.engagement }}</p>

            <p v-if="demande.date_soumission" class="signature">
                <strong>Signature du demandeur :</strong> engagements acceptés en ligne par {{ demande.user.name }}
                lors de l'envoi du dossier, le {{ formatDate(demande.date_soumission) }}.
            </p>
            <p v-else class="signature"><strong>Signature du demandeur :</strong> ___________________________</p>
        </div>

        <template #pied>
            <span class="pied">Document généré le {{ horodatage() }} - Réf. : {{ demande.reference }}</span>
        </template>
    </FeuilleA4>
</template>

<style scoped>
.doc { font: 16px/1.15 'Helvetica', 'Arial', sans-serif; }
/* Tailwind affiche les images en bloc : text-align: right de la vue PDF ne suffit pas ici */
.logo img { display: block; width: 72px; height: 72px; margin-left: auto; }
h1 { font-size: 20px; text-transform: uppercase; text-align: center; margin: 13px 0 40px; }
.contenu { margin: 20px; line-height: 1.6; }
.alerte { background-color: #F6EAD3; color: #9A5F0C; padding: 10px; border: 1px solid #E0C38F; margin-top: 20px; }
.alerte ul { margin-bottom: 0; }
/* nl2br de la vue PDF : les retours à la ligne saisis sont gardés */
.texte { white-space: pre-line; }
/* Les <br><br> de la vue PDF avant la signature */
.signature { margin-top: 3.2em; }
.pied { font-size: 12px; }
</style>

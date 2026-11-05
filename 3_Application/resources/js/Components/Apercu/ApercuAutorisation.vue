<script setup>
import FeuilleA4 from './FeuilleA4.vue';
import { horodatage, lignes, typeProjet } from '@/utils/documents.js';
import { formatDate } from '@/utils/statut.js';
import { computed } from 'vue';

// Aperçu de l'autorisation officielle d'accès, en projet (avant la signature) : même mise en page
// que resources/views/pdf/autorisation.blade.php, à garder en phase. Les données viennent de la demande,
// les textes (introduction, objet, conditions, réserve, signataire) du formulaire en cours de saisie.
const props = defineProps({
    demande: { type: Object, required: true },
    contenu: { type: Object, required: true },
});

const conditions = computed(() => lignes(props.contenu.conditions));
</script>

<template>
    <FeuilleA4 class="doc">
        <div class="logo"><img src="/assets/logo-fanamby-192.png" alt="Fanamby"></div>

        <h1>AUTORISATION OFFICIELLE D'ACCÈS</h1>

        <div class="contenu">
            <p class="texte">{{ contenu.introduction }}</p>
            <p><strong>Monsieur / Madame :</strong> {{ demande.user.name }} ({{ demande.user.email }})</p>
            <p>À accéder à l'aire protégée de : <strong>{{ demande.site.nom }}</strong></p>
            <p>Dans le cadre du projet suivant : <strong>{{ typeProjet(demande) }}</strong><template v-if="demande.titre"> — « {{ demande.titre }} »</template></p>
            <p><strong>Arrivée sur le terrain :</strong> Le {{ formatDate(demande.date_arrivee || demande.date_debut) }}</p>
            <p><strong>Période autorisée :</strong> Du {{ formatDate(demande.date_debut) }} au {{ formatDate(demande.date_fin) }}</p>
            <p><strong>Taille de l'équipe :</strong> {{ demande.equipe_nombre }} personne(s)</p>
            <p><strong>Objet de la mission :</strong></p>
            <p class="texte">{{ contenu.objet }}</p>

            <template v-if="conditions.length">
                <p><strong>Conditions particulières :</strong></p>
                <ul>
                    <li v-for="(condition, i) in conditions" :key="i">{{ condition }}</li>
                </ul>
            </template>

            <p class="texte reserve"><em>{{ contenu.reserve }}</em></p>
            <p class="signature"><strong>Fait pour valoir ce que de droit,</strong><br>{{ contenu.signataire }}</p>
        </div>

        <template #pied>
            <span class="pied">Document officiel généré le {{ horodatage() }} par la plateforme de gestion Fanamby. Référence : {{ demande.reference }}</span>
        </template>
    </FeuilleA4>
</template>

<style scoped>
.doc { font: 16px/1.15 'Helvetica', 'Arial', sans-serif; }
/* Tailwind affiche les images en bloc : text-align: right de la vue PDF ne suffit pas ici */
.logo img { display: block; width: 72px; height: 72px; margin-left: auto; }
h1 { font-size: 20px; text-transform: uppercase; text-align: center; margin: 13px 0 40px; }
.contenu { margin: 20px; line-height: 1.6; }
/* nl2br de la vue PDF : les retours à la ligne saisis sont gardés */
.texte { white-space: pre-line; }
/* Les <br><br> de la vue PDF autour de la mention de réserve */
.reserve { margin-top: 3.2em; }
.signature { margin-top: 1.6em; text-align: right; }
.pied { font-size: 12px; }
</style>

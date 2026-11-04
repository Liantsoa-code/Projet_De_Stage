<script setup>
import FeuilleA4 from './FeuilleA4.vue';
import { horodatage, lignes, montant, redevance } from '@/utils/documents.js';
import { formatDate } from '@/utils/statut.js';
import { computed } from 'vue';

// Aperçu de la convention de collaboration, en projet (avant la signature) : même mise en page que
// resources/views/pdf/convention.blade.php, à garder en phase. Le bénéficiaire et les dates viennent
// de la demande, les textes de chaque article du formulaire en cours de saisie.
const props = defineProps({
    demande: { type: Object, required: true },
    contenu: { type: Object, required: true },
});

const beneficiaire = computed(() => props.demande.user);
const identite = computed(() => {
    const b = beneficiaire.value;
    return [
        b.fonction,
        b.organisation,
        b.adresse && `demeurant à ${b.adresse}`,
        b.telephone && `Tél. ${b.telephone}`,
        `e-mail : ${b.email}`,
        b.piece_identite && `CIN / passeport : ${b.piece_identite}`,
    ].filter(Boolean).join(', ');
});
const modalites = computed(() => lignes(props.contenu.modalites));
const r = computed(() => redevance(props.demande, props.contenu));
const pluriel = (n) => (n > 1 ? 's' : '');
</script>

<template>
    <FeuilleA4 class="doc" marges="50px 60px 75px">
        <div class="logo"><img src="/assets/logo-fanamby-192.png" alt="Fanamby"></div>

        <h1>Convention de collaboration</h1>

        <p>Entre les soussignés :</p>
        <p class="texte">{{ contenu.partie_fanamby }} ;</p>
        <p>Et</p>
        <p><strong>{{ beneficiaire.name }}</strong>, {{ identite }}, ci-après désigné(e) « le Bénéficiaire » ;</p>
        <p>Il a été convenu ce qui suit :</p>

        <h3>Article 1 – Objet de la convention</h3>
        <p class="texte">{{ contenu.objet }}</p>

        <h3>Article 2 – Avis favorable</h3>
        <p class="texte">{{ contenu.avis }}</p>

        <h3>Article 3 – Période et modalités des opérations</h3>
        <p>
            Arrivée sur le terrain le <strong>{{ formatDate(demande.date_arrivee || demande.date_debut) }}</strong> ; activités du <strong>{{ formatDate(demande.date_debut) }}</strong>
            au <strong>{{ formatDate(demande.date_fin) }}</strong> ({{ r.jours }} jour{{ pluriel(r.jours) }}) ;
            équipe de {{ demande.equipe_nombre }} personne(s).<template v-if="modalites.length"> Les opérations comprennent notamment :</template>
        </p>
        <ul v-if="modalites.length">
            <li v-for="(m, i) in modalites" :key="i">{{ m }}</li>
        </ul>
        <p>Toute modification substantielle du calendrier devra être validée préalablement par Fanamby.</p>

        <h3>Article 4 – Redevance financière</h3>
        <p v-if="r.parJour === null">Le montant de la redevance due par le Bénéficiaire sera communiqué par Fanamby.</p>
        <p v-else-if="r.parJour === 0">La présente convention ne donne lieu à aucune redevance.</p>
        <p v-else>
            En contrepartie de l'autorisation d'exploitation temporaire du site pour les besoins du projet, le Bénéficiaire s'engage à verser à Fanamby la somme de
            <strong>{{ montant(r.parJour) }} Ariary par jour</strong>, soit pour {{ r.jours }} jour{{ pluriel(r.jours) }} d'activités :
            <strong>{{ montant(r.total) }} Ariary</strong>.
        </p>
        <p class="texte">{{ contenu.paiement }}</p>

        <h3>Article 5 – Affectation des recettes</h3>
        <p class="texte">{{ contenu.affectation }}</p>

        <h3>Article 6 – Obligations du Bénéficiaire</h3>
        <p>Le Bénéficiaire s'engage à :</p>
        <ul><li v-for="(l, i) in lignes(contenu.obligations_beneficiaire)" :key="i">{{ l }}</li></ul>

        <h3>Article 7 – Obligations de Fanamby</h3>
        <p>Fanamby s'engage à :</p>
        <ul><li v-for="(l, i) in lignes(contenu.obligations_fanamby)" :key="i">{{ l }}</li></ul>

        <h3>Article 8 – Responsabilité</h3>
        <p>Le Bénéficiaire demeure seul responsable :</p>
        <ul><li v-for="(l, i) in lignes(contenu.responsabilite)" :key="i">{{ l }}</li></ul>

        <h3>Article 9 – Litiges</h3>
        <p class="texte">{{ contenu.litiges }}</p>

        <h3>Article 10 – Entrée en vigueur</h3>
        <p class="texte">{{ contenu.entree_vigueur }}</p>

        <p class="fait">Fait à {{ contenu.lieu }}, le ___ / ___ / ______, en deux exemplaires originaux.</p>

        <table class="signatures">
            <tr>
                <td><strong>Pour Fanamby</strong><br>{{ contenu.signataire }}<br>Signature et cachet :</td>
                <td><strong>Le Bénéficiaire</strong><br>{{ beneficiaire.name }}<br>Signature :</td>
            </tr>
        </table>

        <template #pied>
            <span class="pied">Document officiel généré le {{ horodatage() }} par la plateforme de gestion Fanamby. Référence : {{ demande.reference }}</span>
        </template>
    </FeuilleA4>
</template>

<style scoped>
.doc { font: 13px/1.5 'Helvetica', 'Arial', sans-serif; }
/* Tailwind affiche les images en bloc : on centre le logo comme la vue PDF */
.logo img { display: block; width: 72px; height: 72px; margin: 0 auto; }
h1 { font-size: 20px; font-weight: 700; text-transform: uppercase; text-align: center; margin: 10px 0 26px; }
h3 { font-size: 13.5px; font-weight: 700; margin: 18px 0 6px; }
p { margin: 0 0 8px; }
ul { margin: 2px 0 8px; padding-left: 20px; list-style: disc; }
/* nl2br de la vue PDF : les retours à la ligne saisis sont gardés */
.texte { white-space: pre-line; }
.fait { margin-top: 18px; }
.signatures { width: 100%; margin-top: 24px; border-collapse: collapse; }
.signatures td { width: 50%; vertical-align: top; padding-right: 20px; }
.pied { font-size: 11px; }
</style>

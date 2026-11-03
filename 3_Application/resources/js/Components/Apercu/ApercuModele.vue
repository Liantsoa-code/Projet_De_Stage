<script setup>
import FeuilleA4 from './FeuilleA4.vue';
import { blocsModele } from '@/utils/documents.js';
import { computed } from 'vue';

// Aperçu d'un modèle de la page Références : même mise en page que resources/views/pdf/modele_reference.blade.php,
// à garder en phase. Le contenu est mis en forme par blocsModele (règles de ModelesReference::html).
const props = defineProps({
    titre: { type: String, required: true },
    contenu: { type: String, required: true },
});

const blocs = computed(() => blocsModele(props.contenu));
</script>

<template>
    <FeuilleA4 class="doc">
        <div class="logo"><img src="/assets/logo-fanamby-192.png" alt="Fanamby"></div>

        <div class="contenu">
            <template v-for="(bloc, i) in blocs" :key="i">
                <div v-if="bloc.type === 'pointilles'" class="pointilles"></div>
                <ul v-else-if="bloc.type === 'liste'">
                    <li v-for="(ligne, j) in bloc.lignes" :key="j"><span v-for="(m, k) in ligne" :key="k" :class="{ gras: m.gras, champ: m.champ }">{{ m.texte }}</span></li>
                </ul>
                <component :is="bloc.type === 'h1' || bloc.type === 'h3' ? bloc.type : 'p'" v-else :class="{ droite: bloc.type === 'droite' }">
                    <template v-for="(ligne, j) in bloc.lignes" :key="j"><br v-if="j"><span v-for="(m, k) in ligne" :key="k" :class="{ gras: m.gras, champ: m.champ }">{{ m.texte }}</span></template>
                </component>
            </template>
        </div>

        <template #pied>
            <span class="pied">Modèle « {{ titre }} » — plateforme de gestion Fanamby. Les champs entre crochets sont à compléter.</span>
        </template>
    </FeuilleA4>
</template>

<style scoped>
.doc { font: 13px/1.15 'Helvetica', 'Arial', sans-serif; }
/* Tailwind affiche les images en bloc : text-align: right de la vue PDF ne suffit pas ici */
.logo img { display: block; width: 72px; height: 72px; margin-left: auto; }
.contenu { margin: 0 20px; line-height: 1.6; }
h1 { font-size: 18px; text-transform: uppercase; text-align: center; margin: 24px 0 26px; }
h3 { font-size: 14px; margin: 18px 0 6px; }
p, ul { margin: 0 0 12px; }
.gras { font-weight: bold; }
.champ { color: #1D5C8A; background: #DFEAF3; padding: 0 3px; }
.droite { text-align: right; }
.pointilles { border-bottom: 1px dotted #6E7A72; height: 22px; margin-bottom: 4px; }
.pied { font-size: 11px; }
</style>

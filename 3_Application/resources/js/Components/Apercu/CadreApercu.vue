<script setup>
import Icon from '@/Components/Icon.vue';
import EchelleA4 from './EchelleA4.vue';
import FenetreApercu from './FenetreApercu.vue';
import { ref } from 'vue';

// Colonne « Aperçu » à côté d'un formulaire : le document tel qu'il sera généré, mis à jour à chaque
// frappe. Le slot par défaut reçoit les feuilles (Apercu*.vue), le slot « actions » des boutons
// d'en-tête (PDF…) ; « Agrandir » montre le même document à taille réelle.
defineProps({
    titre: { type: String, default: 'Aperçu' },
    sousTitre: { type: String, default: '' },
});

const agrandi = ref(false);
</script>

<template>
    <section class="cadre" :aria-label="titre">
        <header class="cadre__head">
            <div class="cadre__titre">
                <strong>{{ titre }}</strong>
                <small v-if="sousTitre">{{ sousTitre }}</small>
            </div>
            <div class="cadre__actions">
                <slot name="actions" />
                <button type="button" class="btn-sm-edit" @click="agrandi = true">
                    <Icon name="arrows-out-simple" />Agrandir
                </button>
            </div>
        </header>
        <div class="cadre__fond">
            <EchelleA4><slot /></EchelleA4>
        </div>

        <FenetreApercu v-if="agrandi" :titre="titre" :sous-titre="sousTitre" @close="agrandi = false">
            <slot />
            <template #actions><slot name="actions" /></template>
        </FenetreApercu>
    </section>
</template>

<style scoped>
.cadre { display: grid; gap: 10px; padding: 14px; background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r); min-width: 0; }
.cadre__head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px 12px; }
.cadre__titre { display: grid; gap: 1px; min-width: 0; }
.cadre__titre strong { font-size: 14.5px; font-weight: 700; color: var(--ink); }
.cadre__titre small { font-size: 12px; color: var(--ink-3); }
.cadre__actions { display: flex; flex-wrap: wrap; gap: 6px; }
.cadre__fond { padding: 16px; background: var(--sunk); border-radius: var(--r-sm); }
</style>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

// Feuilles A4 réduites à la largeur disponible (jamais agrandies). Les feuilles gardent leur taille
// réelle (794 px, comme DomPDF à 96 dpi) et sont mises à l'échelle : le rendu reste celui du PDF.
// On mesure une ligne vide (largeur) et les feuilles (hauteur), jamais la zone dont on fixe la hauteur.
const LARGEUR_A4 = 794;

const mesure = ref(null);
const feuilles = ref(null);
const echelle = ref(1);
const hauteur = ref(0);

let observateur;
const mesurer = () => {
    echelle.value = Math.min(1, mesure.value.clientWidth / LARGEUR_A4);
    hauteur.value = feuilles.value.offsetHeight * echelle.value;
};

onMounted(() => {
    observateur = new ResizeObserver(mesurer);
    observateur.observe(mesure.value);
    observateur.observe(feuilles.value);
});

onBeforeUnmount(() => observateur?.disconnect());
</script>

<template>
    <div class="echelle" :style="{ height: `${hauteur}px` }">
        <div ref="mesure" class="echelle__mesure"></div>
        <div ref="feuilles" class="echelle__feuilles" :style="{ transform: `scale(${echelle})` }">
            <slot />
        </div>
    </div>
</template>

<style scoped>
.echelle { position: relative; overflow: hidden; }
.echelle__mesure { height: 0; }
/* Centrées : moitié de 794 px. minmax(0, 1fr) : un mot très long ne peut pas élargir les feuilles */
.echelle__feuilles { position: absolute; top: 0; left: 50%; width: 794px; margin-left: -397px; transform-origin: 50% 0; display: grid; grid-template-columns: minmax(0, 1fr); gap: 24px; }
</style>

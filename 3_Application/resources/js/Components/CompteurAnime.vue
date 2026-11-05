<script setup>
import { onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    valeur: { type: [Number, String], default: 0 },
    duree: { type: Number, default: 2000 },
    chiffres: { type: Number, default: 1 }, // nombre minimal de chiffres : 2 → « 03 »
});

const cible = () => Number(props.valeur) || 0;
const decimales = () => (String(props.valeur).split('.')[1] || '').length;
const affiche = ref(0);
let frame = null;

function animer(depart, arrivee) {
    cancelAnimationFrame(frame);
    //Tsisy animation raha mangataka le olona
    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches || depart === arrivee) {
        affiche.value = arrivee;
        return;
    }
    const debut = performance.now();
    const pas = (t) => {
        const p = Math.min((t - debut) / props.duree, 1);
        const ease = 1 - Math.pow(1 - p, 3); // miena rehefa mifarana
        affiche.value = depart + (arrivee - depart) * ease;
        if (p < 1) frame = requestAnimationFrame(pas);
    };
    frame = requestAnimationFrame(pas);
}

const format = (n) => n.toLocaleString('fr-FR', { minimumIntegerDigits: props.chiffres, minimumFractionDigits: decimales(), maximumFractionDigits: decimales() });

onMounted(() => animer(0, cible()));
watch(() => props.valeur, () => animer(affiche.value, cible()));
onUnmounted(() => cancelAnimationFrame(frame));
</script>

<template>
    <span><span aria-hidden="true">{{ format(affiche) }}</span><span class="sr-only">{{ format(cible()) }}</span></span>
</template>

<script setup>
import Icon from '@/Components/Icon.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { statutBadgeClass } from '@/utils/statut.js';

// Dossier en attente, tel que calculé par StatsInstruction::dossiersEnAttente
const props = defineProps({
    d: { type: Object, required: true },
});

const arrivee = computed(() => {
    const n = props.d.jours_avant;
    if (n === null) return null;
    if (n < 0) return `Arrivée dépassée de ${-n} j`;
    if (n === 0) return "Arrivée aujourd'hui";
    return `Arrivée dans ${n} j`;
});
</script>

<template>
    <Link :href="route('validateur.demandes.show', d.id)" class="row ligne">
        <span class="row__main">
            <span class="mono ref">{{ d.reference }}</span>
            <span :class="statutBadgeClass(d.statut?.code)">{{ d.statut?.libelle }}</span>
        </span>
        <span class="row__main">
            <strong>{{ d.demandeur ?? 'Demandeur' }}</strong>
            <span class="site-label">{{ d.site ?? '—' }}</span>
        </span>
        <span class="ligne__tags">
            <span v-if="d.imminent" class="tag tag--alert"><Icon name="calendar-x" />{{ arrivee }}</span>
            <span v-else-if="arrivee" class="tag">{{ arrivee }}</span>
            <span class="tag" :class="{ 'tag--alert': d.retard }"><Icon name="clock" />{{ d.attente }} j d'attente</span>
            <span v-if="d.conflit" class="tag tag--warn"><span class="hatch"></span>Période sensible</span>
        </span>
    </Link>
</template>

<style scoped>
.ligne { grid-template-columns: 128px minmax(0, 1fr) auto; }
.ligne .row__main { justify-items: start; }
.ligne__tags { display: grid; justify-items: end; gap: 4px; }
@media (max-width: 720px) {
    .ligne { grid-template-columns: minmax(0, 1fr); gap: 8px; }
    .ligne__tags { justify-items: start; }
}
</style>

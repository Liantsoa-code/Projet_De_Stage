<script setup>
import Icon from '@/Components/Icon.vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

// Liste déroulante à cases à cocher pour les barres de filtres : aucune case cochée = pas de filtre
const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, required: true }, // [{ value, label }]
    tous: { type: String, required: true }, // « Tous les statuts »
    pluriel: { type: String, required: true }, // « statuts » : « 3 statuts »
});
const emit = defineEmits(['update:modelValue']);

const ouvert = ref(false);
const racine = ref(null);

const choisis = computed(() => props.options.filter((o) => props.modelValue.includes(o.value)));
const resume = computed(() => {
    if (!choisis.value.length) return props.tous;
    if (choisis.value.length <= 2) return choisis.value.map((o) => o.label).join(', ');
    return `${choisis.value.length} ${props.pluriel}`;
});

// Nouveau tableau à chaque fois : useListFilters ne voit pas les modifications en place
const basculer = (value) => emit('update:modelValue', props.modelValue.includes(value)
    ? props.modelValue.filter((v) => v !== value)
    : [...props.modelValue, value]);

const fermerDehors = (e) => { if (ouvert.value && !racine.value?.contains(e.target)) ouvert.value = false; };
onMounted(() => document.addEventListener('click', fermerDehors));
onBeforeUnmount(() => document.removeEventListener('click', fermerDehors));
</script>

<template>
    <div ref="racine" class="fm" @keydown.esc="ouvert = false">
        <button type="button" class="gf-select fm__btn" :class="{ 'fm__btn--actif': choisis.length }" :aria-expanded="ouvert" :aria-label="`${tous} : ${resume}`" @click="ouvert = !ouvert">
            <span class="fm__resume">{{ resume }}</span>
            <Icon name="caret-down" class="fm__caret" />
        </button>
        <div v-if="ouvert" class="fm__menu">
            <label v-for="o in options" :key="o.value" class="fm__option">
                <input type="checkbox" :checked="modelValue.includes(o.value)" @change="basculer(o.value)" />
                <span>{{ o.label }}</span>
            </label>
            <button v-if="choisis.length" type="button" class="fm__vider" @click="emit('update:modelValue', [])">{{ tous }}</button>
        </div>
    </div>
</template>

<style scoped>
.fm { position: relative; }
.fm__btn { display: inline-flex; align-items: center; gap: 8px; max-width: 260px; text-align: left; cursor: pointer; }
.fm__btn--actif { border-color: var(--forest); color: var(--forest); font-weight: 600; }
.fm__resume { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.fm__caret { width: 12px; height: 12px; flex-shrink: 0; color: var(--ink-3); }
.fm__menu {
    position: absolute; z-index: 30; top: calc(100% + 4px); left: 0;
    display: grid; min-width: 220px; max-height: 320px; overflow-y: auto; padding: 6px;
    background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r-sm);
    box-shadow: 0 8px 24px rgb(0 0 0 / 0.12);
}
.fm__option { display: flex; align-items: center; gap: 9px; padding: 7px 8px; border-radius: 6px; font-size: 14px; cursor: pointer; }
.fm__option:hover { background: var(--sunk); }
.fm__option input { accent-color: var(--forest); width: 15px; height: 15px; }
.fm__vider { margin-top: 4px; padding: 7px 8px; border: 0; border-top: 1px solid var(--rule-soft); background: none; text-align: left; font-size: 12.5px; font-weight: 600; color: var(--ink-3); cursor: pointer; }
.fm__vider:hover { color: var(--ink); }
</style>

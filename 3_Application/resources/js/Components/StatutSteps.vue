<script setup>
import { computed } from 'vue';

const props = defineProps({
    code: { type: String, required: true },
    large: { type: Boolean, default: false },
});

const steps = computed(() => {
    const c = props.code;
    return [
        { label: c === 'brouillon' ? 'Brouillon' : 'Déposée', state: c === 'brouillon' ? 'draft' : 'done' },
        {
            label: c === 'a_completer' ? 'Complément' : c === 'soumise' ? 'En file' : 'Analyse',
            state: c === 'brouillon' ? '' : c === 'soumise' ? 'wait' : c === 'en_analyse' ? 'cur' : c === 'a_completer' ? 'att' : 'done',
        },
        {
            label: c === 'validee' ? 'Validée' : c === 'rejetee' ? 'Rejetée' : 'Décision',
            state: c === 'validee' ? 'done' : c === 'rejetee' ? 'no' : '',
        },
    ];
});
</script>

<template>
    <div class="steps" :class="{ 'steps--lg': large }" role="img" :aria-label="`Avancement : ${steps.map(s => s.label).join(', ')}`">
        <div v-for="s in steps" :key="s.label" class="step" :class="s.state && `step--${s.state}`">
            <i></i>
            <span>{{ s.label }}</span>
        </div>
    </div>
</template>

<style scoped>
.steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 4px; min-width: 0; }
.step { display: grid; gap: 5px; min-width: 0; font-size: 11.5px; color: var(--ink-3); }
.step span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.step i { display: block; height: 4px; border-radius: 2px; background: var(--rule); }
.step--done i { background: var(--forest); }
.step--cur i { background: var(--water); }
.step--wait i { background: var(--water); opacity: 0.4; }
.step--att i { background: var(--ochre); }
.step--no i { background: var(--signal); }
.step--draft i { background: repeating-linear-gradient(90deg, var(--ink-3) 0 4px, transparent 4px 7px); }
.step--cur span, .step--att span, .step--no span, .step--wait span { color: var(--ink); font-weight: 600; }
.steps--lg { max-width: 560px; }
.steps--lg .step { font-size: 12.5px; }
.steps--lg .step i { height: 6px; border-radius: 3px; }
</style>

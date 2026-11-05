<script setup>
import Icon from '@/Components/Icon.vue';
import { VERDICTS, conditionsAnalyse, contraintesRelevees, niveau, verdictAnalyse } from '@/utils/analyse.js';
import { computed } from 'vue';

// Résultat de l'analyse automatique d'une demande (AnalyseDemande) : verdict, contraintes détectées avec leur
// motif, informations utiles et conditions proposées. Partagé par le formulaire (en direct, avant l'envoi)
// et les fiches du demandeur et du validateur (analyse figée à l'envoi).
const props = defineProps({
    contraintes: { type: Array, required: true },
    // Colonne étroite du formulaire
    compact: { type: Boolean, default: false },
    // Formulaire : explique ce que le verdict change pour l'envoi
    avantEnvoi: { type: Boolean, default: false },
    // Nouvelle analyse en cours : l'ancien résultat reste affiché, atténué
    chargement: { type: Boolean, default: false },
});

const ICONES = {
    periode: 'calendar-x',
    periode_proche: 'calendar-blank',
    capacite: 'users-three',
    frequentation: 'users',
    accompagnateur: 'shield-check',
    duree: 'clock-countdown',
    delai: 'hourglass-medium',
    doublon: 'files',
    regle_site: 'tree-evergreen',
};

const verdict = computed(() => verdictAnalyse(props.contraintes));
const relevees = computed(() => contraintesRelevees(props.contraintes));
// Les règles permanentes de l'aire protégée ne figurent que parmi les conditions proposées.
const infos = computed(() => props.contraintes.filter((c) => niveau(c) === 'info' && c.regle !== 'regle_site'));
const conditions = computed(() => conditionsAnalyse(props.contraintes));
const bloquant = computed(() => props.contraintes.some((c) => niveau(c) === 'bloquant'));

const pluriel = (n, mot) => `${n} ${mot}${n > 1 ? 's' : ''}`;
const resume = computed(() => [
    relevees.value.length ? pluriel(relevees.value.length, 'contrainte') + ` détectée${relevees.value.length > 1 ? 's' : ''}` : 'Aucune contrainte détectée',
    conditions.value.length && pluriel(conditions.value.length, 'condition') + ` proposée${conditions.value.length > 1 ? 's' : ''}`,
].filter(Boolean).join(' · '));

const consequence = computed(() => {
    if (!props.avantEnvoi) return null;
    if (bloquant.value) return 'Envoi impossible sur ces dates : choisissez d\'autres dates.';
    if (verdict.value === 'conflit') return 'La demande peut être envoyée : le validateur arbitrera ce conflit.';
    if (conditions.value.length) return 'Le validateur pourra reprendre ces conditions dans votre autorisation.';
    return null;
});
</script>

<template>
    <div class="an" :class="[`an--${verdict}`, { 'an--compact': compact, 'an--chargement': chargement }]" :aria-busy="chargement">
        <div class="an__verdict" :role="avantEnvoi ? 'status' : null">
            <Icon :name="VERDICTS[verdict].icone" />
            <div>
                <strong>{{ VERDICTS[verdict].label }}</strong>
                <span>{{ resume }}</span>
            </div>
        </div>
        <p v-if="consequence" class="an__consequence">{{ consequence }}</p>

        <ul v-if="relevees.length" class="an__liste">
            <li v-for="(c, i) in relevees" :key="`r-${i}`" class="an__item" :class="`an__item--${niveau(c)}`">
                <Icon :name="ICONES[c.regle] ?? 'calendar-x'" />
                <div>
                    <strong>{{ c.titre ?? 'Période sensible' }}</strong>
                    <p>{{ c.message }}</p>
                </div>
            </li>
        </ul>

        <div v-if="infos.length" class="an__bloc">
            <p class="an__label">À savoir</p>
            <ul class="an__liste">
                <li v-for="(c, i) in infos" :key="`i-${i}`" class="an__item an__item--info">
                    <Icon :name="ICONES[c.regle] ?? 'warning-circle'" />
                    <div>
                        <strong>{{ c.titre }}</strong>
                        <p>{{ c.message }}</p>
                    </div>
                </li>
            </ul>
        </div>

        <div v-if="conditions.length" class="an__bloc">
            <p class="an__label">Conditions proposées</p>
            <ol class="an__conditions">
                <li v-for="(condition, i) in conditions" :key="`c-${i}`">{{ condition }}</li>
            </ol>
        </div>
    </div>
</template>

<style scoped>
.an { display: grid; gap: 12px; padding: 4px 24px 18px; transition: opacity 0.2s; }
.an--compact { gap: 10px; padding: 0; }
.an--chargement { opacity: 0.55; }

.an__verdict { display: flex; gap: 10px; align-items: flex-start; padding: 12px 14px; border-radius: var(--r-sm); font-size: 13.5px; color: var(--ink-2); }
.an__verdict strong { display: block; font-size: 15px; color: var(--ink); }
.an__verdict svg { width: 22px; height: 22px; flex-shrink: 0; margin-top: 1px; }
.an--compatible .an__verdict { background: var(--forest-soft); }
.an--compatible .an__verdict svg { color: var(--forest); }
.an--conditions .an__verdict { background: var(--ochre-soft); }
.an--conditions .an__verdict svg { color: var(--ochre); }
.an--conflit .an__verdict { background: var(--signal-soft); }
.an--conflit .an__verdict svg { color: var(--signal); }
.an--compact .an__verdict strong { font-size: 14px; }
.an--compact .an__verdict svg { width: 20px; height: 20px; }
.an__consequence { font-size: 13px; color: var(--ink-2); }

.an__liste { display: grid; gap: 6px; margin: 0; padding: 0; list-style: none; }
.an__item { display: grid; grid-template-columns: 18px minmax(0, 1fr); gap: 10px; align-items: start; padding: 10px 12px; border: 1px solid var(--rule-soft); border-left-width: 3px; border-radius: var(--r-sm); background: var(--surface); }
.an__item svg { width: 18px; height: 18px; margin-top: 1px; color: var(--ink-3); }
.an__item strong { display: block; font-size: 13.5px; font-weight: 600; color: var(--ink); }
.an__item p { font-size: 13px; color: var(--ink-2); line-height: 1.5; overflow-wrap: anywhere; }
.an__item--bloquant, .an__item--conflit { border-left-color: var(--signal); }
.an__item--bloquant svg, .an__item--conflit svg { color: var(--signal); }
.an__item--attention { border-left-color: var(--ochre); }
.an__item--attention svg { color: var(--ochre); }
.an__item--info { border-left-color: var(--water-line); }
.an__item--info svg { color: var(--water); }
.an--compact .an__item { padding: 8px 10px; }
.an--compact .an__item p { font-size: 12.5px; }

.an__bloc { display: grid; gap: 6px; }
.an__label { font: 600 10.5px/1.2 var(--f-display); text-transform: uppercase; letter-spacing: 0.04em; color: var(--ink-3); }
.an__conditions { display: grid; gap: 6px; margin: 0; padding-left: 20px; font-size: 13.5px; color: var(--ink-2); line-height: 1.5; }
.an__conditions li::marker { font: 600 12px var(--f-display); color: var(--forest); }
.an--compact .an__conditions { font-size: 12.5px; }
</style>

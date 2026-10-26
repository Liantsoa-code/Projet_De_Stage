<script setup>
import InputError from '@/Components/InputError.vue';

// Étape « Aire protégée et période » (section II du formulaire). L'aire protégée peut aussi
// être choisie sur la carte de VerificationCalendrier ; l'équipe sert aux statistiques de fréquentation.
defineProps({
    form: { type: Object, required: true },
    sites: { type: Array, required: true },
    // Date du jour (AAAA-MM-JJ) : les dates passées ne sont pas proposées
    today: { type: String, required: true },
});
</script>

<template>
    <fieldset class="choix">
        <legend class="gf-label">Aire protégée concernée</legend>
        <div class="choix__grille">
            <label v-for="(s, i) in sites" :key="s.id" class="choix__case">
                <input :id="i === 0 ? 'site_id' : undefined" v-model="form.site_id" type="radio" name="site_id" :value="s.id" />
                <span>{{ s.nom }}</span>
            </label>
        </div>
        <p class="gf-hint">Une aire protégée par demande. Vous pouvez aussi la choisir sur la carte.</p>
        <InputError class="gf-error" :message="form.errors.site_id" />
    </fieldset>
    <fieldset class="choix">
        <legend class="gf-label">Période prévue</legend>
        <div class="gf-row gf-row-3">
            <div>
                <label class="gf-label gf-label--sub" for="date_debut">Début</label>
                <input id="date_debut" v-model="form.date_debut" type="date" class="gf-input" :min="today" required />
                <InputError class="gf-error" :message="form.errors.date_debut" />
            </div>
            <div>
                <label class="gf-label gf-label--sub" for="date_fin">Fin</label>
                <input id="date_fin" v-model="form.date_fin" type="date" class="gf-input" :min="form.date_debut || today" required />
                <InputError class="gf-error" :message="form.errors.date_fin" />
            </div>
            <div>
                <label class="gf-label gf-label--sub" for="equipe_nombre">Personnes sur le terrain</label>
                <input id="equipe_nombre" v-model="form.equipe_nombre" type="number" min="1" class="gf-input" required />
                <InputError class="gf-error" :message="form.errors.equipe_nombre" />
            </div>
        </div>
    </fieldset>
</template>

<style scoped>
.choix { margin: 0; padding: 0; border: 0; min-width: 0; }
.choix__grille { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px; }
@media (max-width: 420px) { .choix__grille { grid-template-columns: minmax(0, 1fr); } }
.choix__case { display: flex; align-items: flex-start; gap: 8px; padding: 9px 10px; border: 1px solid var(--rule); border-radius: var(--r-sm); font-size: 13.5px; cursor: pointer; }
.choix__case:hover { border-color: var(--ink-3); }
.choix__case:has(input:checked) { border-color: var(--forest); background: var(--forest-soft); font-weight: 600; }
.choix__case:has(input:focus-visible) { outline: 2px solid var(--water); outline-offset: 2px; }
.choix__case input { margin-top: 2px; accent-color: var(--forest); flex-shrink: 0; }
.gf-label--sub { font-weight: 500; color: var(--ink-3); }
</style>

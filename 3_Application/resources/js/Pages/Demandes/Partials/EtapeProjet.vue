<script setup>
import InputError from '@/Components/InputError.vue';

// Étape « II. Informations sur le projet » : titre, type de projet (cases du formulaire papier,
// avec une précision pour « Autre »), résumé du synopsis et objectifs
defineProps({
    form: { type: Object, required: true },
    types: { type: Array, required: true },
    // Type choisi, pour afficher le champ de précision
    typeProjet: { type: Object, default: null },
});

defineEmits(['type-change']);
</script>

<template>
    <div>
        <label class="gf-label" for="titre">Titre du projet</label>
        <input id="titre" v-model="form.titre" type="text" class="gf-input" maxlength="200" required />
        <InputError class="gf-error" :message="form.errors.titre" />
    </div>
    <fieldset class="choix">
        <legend class="gf-label">Type de projet</legend>
        <div class="choix__grille">
            <label v-for="(t, i) in types" :key="t.id" class="choix__case">
                <input
                    :id="i === 0 ? 'type_demande_id' : undefined"
                    v-model="form.type_demande_id"
                    type="radio"
                    name="type_demande_id"
                    :value="t.id"
                    @change="$emit('type-change')"
                />
                <span>{{ t.libelle }}<template v-if="t.precision_requise"> (précisez)</template></span>
            </label>
        </div>
        <InputError class="gf-error" :message="form.errors.type_demande_id" />
    </fieldset>
    <div v-if="typeProjet?.precision_requise">
        <label class="gf-label" for="type_precision">Précisez le type de projet</label>
        <input id="type_precision" v-model="form.type_precision" type="text" class="gf-input" maxlength="150" required placeholder="Ex : Photographie commerciale" />
        <InputError class="gf-error" :message="form.errors.type_precision" />
    </div>
    <div>
        <label class="gf-label" for="description">Résumé du synopsis</label>
        <textarea id="description" v-model="form.description" class="gf-textarea" rows="4" required placeholder="Ce que le projet raconte ou montre, les lieux et le déroulé des prises de vue"></textarea>
        <InputError class="gf-error" :message="form.errors.description" />
    </div>
    <div>
        <label class="gf-label" for="objectifs">Objectif(s) principal(aux) du projet</label>
        <textarea id="objectifs" v-model="form.objectifs" class="gf-textarea" rows="3" required placeholder="Ex : réaliser une série de photographies pour une campagne de communication"></textarea>
        <InputError class="gf-error" :message="form.errors.objectifs" />
    </div>
</template>

<style scoped>
.choix { margin: 0; padding: 0; border: 0; min-width: 0; }
.choix__grille { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px; }
@media (max-width: 720px) { .choix__grille { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 420px) { .choix__grille { grid-template-columns: minmax(0, 1fr); } }
.choix__case { display: flex; align-items: flex-start; gap: 8px; padding: 9px 10px; border: 1px solid var(--rule); border-radius: var(--r-sm); font-size: 13.5px; cursor: pointer; }
.choix__case:hover { border-color: var(--ink-3); }
.choix__case:has(input:checked) { border-color: var(--forest); background: var(--forest-soft); font-weight: 600; }
.choix__case:has(input:focus-visible) { outline: 2px solid var(--water); outline-offset: 2px; }
.choix__case input { margin-top: 2px; accent-color: var(--forest); flex-shrink: 0; }
</style>

<script setup>
import Icon from '@/Components/Icon.vue';

// Liste de lignes { id, libelle } : l'id d'une ligne existante est conservé quand on la renomme.
const lignes = defineModel({ type: Array, required: true });

defineProps({
    placeholder: { type: String, default: '' },
    ajouterLabel: { type: String, default: 'Ajouter' },
    vide: { type: String, default: 'Aucun élément.' },
    // Erreurs de validation Inertia, ex. { 'documents_requis.0.libelle': '…' }
    errors: { type: Object, default: () => ({}) },
    champ: { type: String, required: true },
});

const ajouter = () => lignes.value.push({ id: null, libelle: '' });
const retirer = (i) => lignes.value.splice(i, 1);
</script>

<template>
    <div class="liste">
        <p v-if="!lignes.length" class="liste__vide">{{ vide }}</p>
        <div v-for="(ligne, i) in lignes" :key="ligne.id ?? `n-${i}`" class="liste__ligne">
            <div class="liste__champ">
                <input type="text" class="field-input" v-model="ligne.libelle" :placeholder="placeholder" />
                <p v-if="errors[`${champ}.${i}.libelle`]" class="field-error">{{ errors[`${champ}.${i}.libelle`] }}</p>
            </div>
            <button type="button" class="liste__retirer" :aria-label="`Retirer ${ligne.libelle || 'la ligne'}`" @click="retirer(i)">
                <Icon name="x" />
            </button>
        </div>
        <button type="button" class="liste__ajouter" @click="ajouter">
            <Icon name="plus" />
            {{ ajouterLabel }}
        </button>
    </div>
</template>

<style scoped>
.liste { display: flex; flex-direction: column; gap: 8px; }
.liste__vide { margin: 0; font-size: 12.5px; font-style: italic; color: var(--c-muted); }
.liste__ligne { display: flex; align-items: flex-start; gap: 8px; }
.liste__champ { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
.field-input {
    width: 100%; padding: 10px 14px; border: 1px solid var(--rule);
    border-radius: var(--radius-sm); font-size: 13px; color: var(--c-ink); background: var(--c-surface);
    outline: none; font-family: var(--f-body); transition: border-color 0.2s, box-shadow 0.2s;
}
.field-input:focus { border-color: var(--c-brand); box-shadow: 0 0 0 4px var(--c-brand-ring); }
.field-error { margin: 0; font-size: 11px; color: var(--signal); }
.liste__retirer {
    flex: none; display: inline-flex; align-items: center; justify-content: center;
    width: 38px; height: 38px; border: 1px solid var(--rule); border-radius: var(--radius-sm);
    background: var(--c-surface); color: var(--c-muted); cursor: pointer; transition: color 0.2s, border-color 0.2s;
}
.liste__retirer:hover { color: var(--signal); border-color: var(--signal); }
.liste__retirer svg { width: 14px; height: 14px; }
.liste__ajouter {
    align-self: flex-start; display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 12px; border: 1px dashed var(--rule); border-radius: var(--radius-sm);
    background: transparent; color: var(--c-text); font-size: 12.5px; font-weight: 500; cursor: pointer;
    font-family: var(--f-body); transition: border-color 0.2s, background 0.2s;
}
.liste__ajouter:hover { border-color: var(--c-brand); background: var(--c-surface-2); }
.liste__ajouter svg { width: 14px; height: 14px; }
</style>

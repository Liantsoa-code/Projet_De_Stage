<script setup>
import Icon from '@/Components/Icon.vue';
import InputError from '@/Components/InputError.vue';

// Étape « Pièces à joindre » : un nouveau fichier remplace celui déjà fourni pour la même pièce
defineProps({
    form: { type: Object, required: true },
    typeActivite: { type: Object, default: null },
    // Pièces exigées par le type, avec le fichier choisi (fichier) ou déjà envoyé (existant)
    pieces: { type: Array, required: true },
});

defineEmits(['fichier']);
</script>

<template>
    <p v-if="!typeActivite" class="hint">Choisissez d'abord un type d'activité : la liste des pièces obligatoires en dépend.</p>
    <p v-else-if="!pieces.length" class="hint">Aucune pièce n'est exigée pour ce type d'activité.</p>
    <div v-else class="docs">
        <div v-for="p in pieces" :key="p.id" class="doc" :class="{ 'doc--ok': p.fourni }">
            <Icon :name="p.fourni ? 'file-pdf' : 'file-dashed'" class="doc__icon" />
            <div class="doc__body">
                <strong>{{ p.libelle }}</strong>
                <small v-if="p.fichier">{{ p.fichier.name }}</small>
                <small v-else-if="p.existant">Déjà fourni : <a :href="route('documents.show', p.existant.id)">{{ p.existant.nom_fichier }}</a></small>
                <small v-else>PDF, JPG, PNG, Word ou OpenDocument, 20 Mo au maximum</small>
                <InputError class="gf-error" :message="form.errors['doc_' + p.id]" />
            </div>
            <label class="btn-ghost btn--sm doc__btn">
                <Icon :name="p.fourni ? 'arrows-clockwise' : 'upload-simple'" />
                {{ p.fourni ? 'Remplacer' : 'Choisir' }}
                <input :id="`doc_${p.id}`" type="file" class="sr-only" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.odt" @change="$emit('fichier', p.id, $event.target.files[0])" />
            </label>
        </div>
    </div>
</template>

<style scoped>
.hint { font-size: 13.5px; color: var(--ink-3); }

.docs { display: grid; gap: 8px; }
.doc { display: grid; grid-template-columns: 30px minmax(0, 1fr) auto; gap: 12px; align-items: center; padding: 10px 12px; border: 1px dashed var(--rule); border-radius: var(--r-sm); }
.doc--ok { border-style: solid; }
.doc__icon { width: 26px; height: 26px; color: var(--ink-3); }
.doc--ok .doc__icon { color: var(--forest); }
.doc__body { display: grid; gap: 2px; min-width: 0; }
.doc__body strong { font-weight: 600; font-size: 14px; }
.doc__body small { font-size: 12.5px; color: var(--ink-3); overflow-wrap: anywhere; }
.doc__body a { color: var(--water); font-weight: 600; }
.doc__btn { cursor: pointer; }
.doc__btn:focus-within { outline: 2px solid var(--water); outline-offset: 2px; }
.btn--sm { padding: 7px 10px; font-size: 13px; }
</style>

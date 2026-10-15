<script setup>
import InputError from '@/Components/InputError.vue';

// Étape « Objet de la mission » ; lors d'un complément, le demandeur peut aussi écrire au validateur
defineProps({
    form: { type: Object, required: true },
    aCompleter: { type: Boolean, default: false },
});
</script>

<template>
    <div>
        <label class="gf-label" for="description">Description du projet</label>
        <textarea id="description" v-model="form.description" class="gf-textarea" rows="5" required placeholder="Objectif, protocole, zones visitées, matériel utilisé"></textarea>
        <InputError class="gf-error" :message="form.errors.description" />
    </div>
    <div v-if="aCompleter">
        <label class="gf-label" for="message_complement">Message au validateur (facultatif)</label>
        <textarea id="message_complement" v-model="form.message_complement" class="gf-textarea" rows="3" placeholder="Précisez ce que vous avez modifié ou ajouté"></textarea>
        <InputError class="gf-error" :message="form.errors.message_complement" />
    </div>
</template>

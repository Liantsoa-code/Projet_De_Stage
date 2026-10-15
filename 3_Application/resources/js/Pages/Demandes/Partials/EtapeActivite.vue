<script setup>
import InputError from '@/Components/InputError.vue';

// Étape « Activité et site » (le site peut aussi être choisi sur la carte de VerificationCalendrier)
defineProps({
    form: { type: Object, required: true },
    types: { type: Array, required: true },
    sites: { type: Array, required: true },
});

defineEmits(['type-change']);
</script>

<template>
    <div class="gf-row gf-row-2">
        <div>
            <label class="gf-label" for="type_demande_id">Type d'activité</label>
            <select id="type_demande_id" v-model="form.type_demande_id" class="gf-select" required @change="$emit('type-change')">
                <option disabled value="">Choisissez un type</option>
                <option v-for="t in types" :key="t.id" :value="t.id">{{ t.libelle }}</option>
            </select>
            <InputError class="gf-error" :message="form.errors.type_demande_id" />
        </div>
        <div>
            <label class="gf-label" for="site_id">Aire protégée</label>
            <select id="site_id" v-model="form.site_id" class="gf-select" required>
                <option disabled value="">Choisissez un site</option>
                <option v-for="s in sites" :key="s.id" :value="s.id">{{ s.nom }}</option>
            </select>
            <InputError class="gf-error" :message="form.errors.site_id" />
        </div>
    </div>
</template>

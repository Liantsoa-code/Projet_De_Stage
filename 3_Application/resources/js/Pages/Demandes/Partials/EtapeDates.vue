<script setup>
import InputError from '@/Components/InputError.vue';

// Étape « Dates et équipe »
defineProps({
    form: { type: Object, required: true },
    // Date du jour (AAAA-MM-JJ) : les dates passées ne sont pas proposées
    today: { type: String, required: true },
});
</script>

<template>
    <div class="gf-row gf-row-3">
        <div>
            <label class="gf-label" for="date_debut">Arrivée</label>
            <input id="date_debut" v-model="form.date_debut" type="date" class="gf-input" :min="today" required />
            <InputError class="gf-error" :message="form.errors.date_debut" />
        </div>
        <div>
            <label class="gf-label" for="date_fin">Départ</label>
            <input id="date_fin" v-model="form.date_fin" type="date" class="gf-input" :min="form.date_debut || today" required />
            <InputError class="gf-error" :message="form.errors.date_fin" />
        </div>
        <div>
            <label class="gf-label" for="equipe_nombre">Personnes sur le terrain</label>
            <input id="equipe_nombre" v-model="form.equipe_nombre" type="number" min="1" class="gf-input" required />
            <InputError class="gf-error" :message="form.errors.equipe_nombre" />
        </div>
    </div>
</template>

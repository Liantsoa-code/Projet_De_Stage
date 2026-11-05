<script setup>
import InputError from '@/Components/InputError.vue';
import ListeEditable from '@/Components/ListeEditable.vue';

// Données de l'aire protégée utilisées par l'analyse automatique des demandes (AnalyseDemande),
// partagées par les formulaires de création et de modification.
defineProps({ form: { type: Object, required: true } });
</script>

<template>
    <div class="regles">
        <div>
            <label class="gf-label" for="capacite_accueil">Capacité d'accueil</label>
            <input id="capacite_accueil" type="number" min="1" class="gf-input regles__capacite" v-model="form.capacite_accueil" placeholder="Sans limite" />
            <p class="gf-hint">Nombre maximal de personnes présentes en même temps. L'analyse compare l'équipe et les séjours déjà autorisés à cette capacité.</p>
            <InputError class="gf-error" :message="form.errors.capacite_accueil" />
        </div>
        <div>
            <label class="gf-label">Règles permanentes</label>
            <ListeEditable
                v-model="form.conditions"
                champ="conditions"
                :errors="form.errors"
                placeholder="Ex : Présence d'un guide local agréé pendant tout le séjour"
                ajouter-label="Ajouter une règle"
                vide="Aucune règle permanente."
            />
            <p class="gf-hint">Proposées dans les conditions particulières de chaque autorisation sur cette aire protégée.</p>
        </div>
    </div>
</template>

<style scoped>
.regles { display: grid; gap: 16px; }
.regles__capacite { max-width: 180px; }
</style>

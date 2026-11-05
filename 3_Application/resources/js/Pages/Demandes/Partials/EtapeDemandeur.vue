<script setup>
import InputError from '@/Components/InputError.vue';

// Étape « I. Informations sur le demandeur » : le nom et l'e-mail viennent du compte ;
// les autres coordonnées sont enregistrées sur le compte et reprises dans les demandes suivantes.
defineProps({
    form: { type: Object, required: true },
    // Compte connecté : name, email
    demandeur: { type: Object, required: true },
});
</script>

<template>
    <div class="gf-row gf-row-2">
        <div>
            <span class="gf-label">Nom et prénoms</span>
            <p class="compte">{{ demandeur.name }}</p>
        </div>
        <div>
            <span class="gf-label">E-mail</span>
            <p class="compte">{{ demandeur.email }}</p>
        </div>
    </div>
    <div>
        <label class="gf-label" for="organisation">Institution / Société de production / Organisation</label>
        <input id="organisation" v-model="form.organisation" type="text" class="gf-input" maxlength="150" autocomplete="organization" required placeholder="Ex : Entreprise individuelle, nom de la société ou de l'université" />
        <InputError class="gf-error" :message="form.errors.organisation" />
    </div>
    <div class="gf-row gf-row-2">
        <div>
            <label class="gf-label" for="fonction">Fonction</label>
            <input id="fonction" v-model="form.fonction" type="text" class="gf-input" maxlength="100" autocomplete="organization-title" required placeholder="Ex : Photographe, réalisateur" />
            <InputError class="gf-error" :message="form.errors.fonction" />
        </div>
        <div>
            <label class="gf-label" for="telephone">Téléphone</label>
            <input id="telephone" v-model="form.telephone" type="tel" class="gf-input" maxlength="30" autocomplete="tel" required placeholder="Ex : +261 34 00 000 00" />
            <InputError class="gf-error" :message="form.errors.telephone" />
        </div>
    </div>
    <div>
        <label class="gf-label" for="adresse">Adresse</label>
        <input id="adresse" v-model="form.adresse" type="text" class="gf-input" maxlength="255" autocomplete="street-address" required />
        <InputError class="gf-error" :message="form.errors.adresse" />
    </div>
    <p class="hint">Le nom et l'e-mail sont ceux de votre compte. Les autres coordonnées y sont enregistrées et reprises dans vos prochaines demandes.</p>
</template>

<style scoped>
.compte { padding: 10px 0 2px; font-size: 14px; font-weight: 500; color: var(--ink); overflow-wrap: anywhere; }
.hint { font-size: 13px; color: var(--ink-3); }
</style>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Mon profil" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <div class="page-title">Mon profil</div>
                <div class="page-sub">Informations du compte, mot de passe et suppression. Cliquez sur « Modifier » pour changer une carte.</div>
            </div>
        </template>

        <div class="profile">
            <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" />
            <UpdatePasswordForm />
            <DeleteUserForm />
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Cartes communes aux sous-formulaires : en-tête avec bouton à droite, champs sur deux colonnes */
.profile { display: grid; gap: 16px; max-width: 760px; }
.profile :deep(.profile-card) { padding: 22px 24px; background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r); }
.profile :deep(.profile-card--danger) { border-color: var(--signal-line); }
.profile :deep(.profile-card__head) { display: flex; align-items: flex-start; gap: 12px; }
.profile :deep(.profile-card__head > div) { flex: 1; min-width: 0; display: grid; gap: 4px; }
.profile :deep(.profile-card__head .section-ok) { align-self: center; }
.profile :deep(.profile-card__note) { margin-top: 16px; display: grid; gap: 6px; }
.profile :deep(.profile-fields) { display: grid; grid-template-columns: 1fr 1fr; gap: 18px 24px; margin-top: 20px; }
.profile :deep(.profile-fields__large) { grid-column: 1 / -1; }
.profile :deep(.profile-fields dt) { font-size: 12.5px; color: var(--ink-3); }
.profile :deep(.profile-fields dd) { margin-top: 3px; font-size: 14.5px; font-weight: 500; color: var(--ink); overflow-wrap: anywhere; }
.profile :deep(.profile-fields__vide) { font-style: italic; font-weight: 400; color: var(--ink-4); }
.profile :deep(.profile-actions) { display: flex; justify-content: flex-end; gap: 10px; }
@media (max-width: 640px) {
    .profile :deep(.profile-card) { padding: 18px; }
    .profile :deep(.profile-fields) { grid-template-columns: 1fr; }
}
</style>

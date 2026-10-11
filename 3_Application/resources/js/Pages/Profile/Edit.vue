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
                <div class="page-sub">Informations du compte, mot de passe et suppression.</div>
            </div>
        </template>

        <div class="profile">
            <section class="profile__card">
                <UpdateProfileInformationForm :must-verify-email="mustVerifyEmail" :status="status" />
            </section>
            <section class="profile__card">
                <UpdatePasswordForm />
            </section>
            <section class="profile__card profile__card--danger">
                <DeleteUserForm />
            </section>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.profile { display: grid; gap: 16px; max-width: 720px; }
.profile__card { padding: 22px 24px; background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r); }
.profile__card--danger { border-color: var(--signal-line); }
.profile__card :deep(form) { max-width: 520px; }
</style>

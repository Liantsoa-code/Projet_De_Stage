<script setup>
import Icon from '@/Components/Icon.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Page en préparation" />

    <AuthenticatedLayout>
        <template #header>
            <div class="page-title">Page en préparation</div>
        </template>

        <section class="en-preparation">
            <Icon name="hourglass-medium" />
            <h2 class="section-title">Cette page est en cours de réalisation</h2>
            <p class="section-text">Elle sera disponible dans une prochaine version de la plateforme.</p>
            <Link :href="route('dashboard')" class="btn-ghost"><Icon name="arrow-left" />Retour au tableau de bord</Link>
        </section>
    </AuthenticatedLayout>
</template>

<style scoped>
.en-preparation { display: grid; justify-items: center; gap: 12px; padding: 56px 24px; text-align: center; background: var(--surface); border: 1px solid var(--rule); border-radius: var(--r); color: var(--ink-3); }
.en-preparation > svg { width: 36px; height: 36px; }
</style>

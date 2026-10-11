<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Vérifier l'adresse e-mail" />

        <div class="auth-head">
            <h1>Vérifiez votre adresse e-mail</h1>
            <p>Un lien de confirmation vient de vous être envoyé. Cliquez dessus pour activer votre compte et déposer votre première demande.</p>
        </div>

        <div v-if="verificationLinkSent" class="auth-status">
            Un nouveau lien a été envoyé à l'adresse indiquée lors de l'inscription.
        </div>

        <form class="auth-form" @submit.prevent="submit">
            <button class="btn-accent" :disabled="form.processing">Renvoyer l'e-mail</button>
        </form>

        <p class="auth-alt">
            <Link :href="route('logout')" method="post" as="button" class="text-link">Se déconnecter</Link>
        </p>
    </GuestLayout>
</template>

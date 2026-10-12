<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Mot de passe oublié" />

        <div class="auth-head">
            <h1>Mot de passe oublié</h1>
            <p>Indiquez l'adresse e-mail du compte : vous recevrez un lien pour choisir un nouveau mot de passe.</p>
        </div>

        <div v-if="status" class="auth-status">{{ status }}</div>

        <form class="auth-form" @submit.prevent="submit">
            <div>
                <label class="gf-label" for="email">Adresse e-mail</label>
                <input id="email" v-model="form.email" type="email" class="gf-input" required autofocus autocomplete="username" />
                <InputError class="gf-error" :message="form.errors.email" />
            </div>

            <button class="btn-accent" :disabled="form.processing">Envoyer le lien</button>
        </form>

        <p class="auth-alt">
            <Link :href="route('login')" class="text-link">Retour à la connexion</Link>
        </p>
    </GuestLayout>
</template>

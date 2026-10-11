<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Nouveau mot de passe" />

        <div class="auth-head">
            <h1>Nouveau mot de passe</h1>
            <p>Choisissez un mot de passe long et différent de ceux que vous utilisez ailleurs.</p>
        </div>

        <form class="auth-form" @submit.prevent="submit">
            <div>
                <label class="gf-label" for="email">Adresse e-mail</label>
                <input id="email" v-model="form.email" type="email" class="gf-input" required autofocus autocomplete="username" />
                <InputError class="gf-error" :message="form.errors.email" />
            </div>

            <div>
                <label class="gf-label" for="password">Nouveau mot de passe</label>
                <input id="password" v-model="form.password" type="password" class="gf-input" required autocomplete="new-password" />
                <InputError class="gf-error" :message="form.errors.password" />
            </div>

            <div>
                <label class="gf-label" for="password_confirmation">Confirmer le mot de passe</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="gf-input" required autocomplete="new-password" />
                <InputError class="gf-error" :message="form.errors.password_confirmation" />
            </div>

            <button class="btn-accent" :disabled="form.processing">Enregistrer le mot de passe</button>
        </form>
    </GuestLayout>
</template>

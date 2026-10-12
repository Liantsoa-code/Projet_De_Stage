<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Confirmer le mot de passe" />

        <div class="auth-head">
            <h1>Confirmation requise</h1>
            <p>Cette action est protégée. Saisissez à nouveau votre mot de passe pour continuer.</p>
        </div>

        <form class="auth-form" @submit.prevent="submit">
            <div>
                <label class="gf-label" for="password">Mot de passe</label>
                <input id="password" v-model="form.password" type="password" class="gf-input" required autocomplete="current-password" autofocus />
                <InputError class="gf-error" :message="form.errors.password" />
            </div>

            <button class="btn-accent" :disabled="form.processing">Confirmer</button>
        </form>
    </GuestLayout>
</template>

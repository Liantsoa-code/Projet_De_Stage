<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import Icon from '@/Components/Icon.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: { type: Boolean },
    status: { type: String },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout onglet="connexion">
        <Head title="Connexion" />

        <div class="auth-head">
            <span class="eyebrow">Demandeurs et validateurs</span>
            <h1>Connexion à votre espace</h1>
            <p>Retrouvez vos demandes d'accès, ou les dossiers à instruire sur vos sites.</p>
        </div>

        <div v-if="status" class="auth-status" role="status">
            <Icon name="check-circle" />
            {{ status }}
        </div>

        <form class="auth-form" novalidate @submit.prevent="submit">
            <div>
                <label class="gf-label" for="email">Adresse e-mail</label>
                <div class="auth-field">
                    <Icon name="envelope-simple" class="auth-field__icon" />
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="gf-input auth-field__input"
                        :class="{ 'auth-field__input--invalid': form.errors.email }"
                        required
                        autofocus
                        autocomplete="username"
                        placeholder="nom@exemple.mg"
                    />
                </div>
                <InputError class="gf-error" :message="form.errors.email" />
            </div>

            <div>
                <div class="auth-label-row">
                    <label class="gf-label" for="password">Mot de passe</label>
                    <Link v-if="canResetPassword" :href="route('password.request')" class="text-link auth-small">Mot de passe oublié ?</Link>
                </div>
                <PasswordInput id="password" v-model="form.password" autocomplete="current-password" :invalid="!!form.errors.password" />
                <InputError class="gf-error" :message="form.errors.password" />
            </div>

            <label class="auth-check">
                <Checkbox v-model:checked="form.remember" name="remember" />
                Rester connecté sur cet appareil
            </label>

            <button class="btn-accent auth-submit" :disabled="form.processing">
                <Icon :name="form.processing ? 'circle-notch' : 'sign-in'" :class="{ spin: form.processing }" />
                {{ form.processing ? 'Connexion…' : 'Se connecter' }}
            </button>
        </form>
    </GuestLayout>
</template>

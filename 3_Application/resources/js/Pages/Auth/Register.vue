<script setup>
import Icon from '@/Components/Icon.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const LONGUEUR_MIN = 8;

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const assezLong = computed(() => form.password.length >= LONGUEUR_MIN);
const confirme = computed(() => form.password_confirmation.length > 0 && form.password === form.password_confirmation);
const confirmationFausse = computed(() => form.password_confirmation.length > 0 && !confirme.value);

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout onglet="inscription">
        <Head title="Créer un compte" />

        <template #caption>
            <span class="eyebrow">Avec votre compte</span>
            <ul class="caption-list">
                <li><Icon name="check-circle" />Déposer des demandes de recherche ou de tournage</li>
                <li><Icon name="check-circle" />Suivre l'instruction et répondre aux compléments</li>
                <li><Icon name="check-circle" />Télécharger l'autorisation et le cahier des charges</li>
            </ul>
        </template>

        <div class="auth-head">
            <span class="eyebrow">Compte demandeur</span>
            <h1>Créer un compte</h1>
            <p>Pour les chercheurs, équipes de tournage et partenaires qui souhaitent accéder à une aire protégée.</p>
        </div>

        <form class="auth-form" novalidate @submit.prevent="submit">
            <div>
                <label class="gf-label" for="name">Nom complet ou structure</label>
                <div class="auth-field">
                    <Icon name="user" class="auth-field__icon" />
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        class="gf-input auth-field__input"
                        :class="{ 'auth-field__input--invalid': form.errors.name }"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="Ex. Université d'Antananarivo"
                    />
                </div>
                <InputError class="gf-error" :message="form.errors.name" />
            </div>

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
                        autocomplete="username"
                        placeholder="nom@exemple.mg"
                    />
                </div>
                <p class="gf-hint">Les notifications sur vos demandes y seront envoyées.</p>
                <InputError class="gf-error" :message="form.errors.email" />
            </div>

            <div>
                <label class="gf-label" for="password">Mot de passe</label>
                <PasswordInput id="password" v-model="form.password" autocomplete="new-password" :invalid="!!form.errors.password" />
                <p class="auth-rule" :class="{ 'auth-rule--ok': assezLong }">
                    <Icon :name="assezLong ? 'check-circle' : 'warning-circle'" />
                    {{ LONGUEUR_MIN }} caractères minimum
                </p>
                <InputError class="gf-error" :message="form.errors.password" />
            </div>

            <div>
                <label class="gf-label" for="password_confirmation">Confirmer le mot de passe</label>
                <PasswordInput id="password_confirmation" v-model="form.password_confirmation" autocomplete="new-password" :invalid="confirmationFausse || !!form.errors.password_confirmation" />
                <p v-if="form.password_confirmation" class="auth-rule" :class="confirme ? 'auth-rule--ok' : 'auth-rule--ko'">
                    <Icon :name="confirme ? 'check-circle' : 'x-circle'" />
                    {{ confirme ? 'Les mots de passe correspondent' : 'Les mots de passe ne correspondent pas' }}
                </p>
                <InputError class="gf-error" :message="form.errors.password_confirmation" />
            </div>

            <button class="btn-accent auth-submit" :disabled="form.processing">
                <Icon :name="form.processing ? 'circle-notch' : 'arrow-right'" :class="{ spin: form.processing }" />
                {{ form.processing ? 'Création…' : 'Créer mon compte' }}
            </button>
        </form>
    </GuestLayout>
</template>

<style scoped>
.caption-list { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
.caption-list li { display: flex; gap: 9px; align-items: flex-start; color: var(--ink); }
.caption-list :deep(svg) { width: 17px; height: 17px; margin-top: 2px; flex-shrink: 0; color: var(--forest); }
</style>

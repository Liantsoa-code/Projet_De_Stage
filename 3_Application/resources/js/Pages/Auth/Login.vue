<script setup>
import Checkbox from '@/Components/Checkbox.vue';
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
    <GuestLayout>
        <Head title="Connexion" />

        <div class="login-header">
            <h2 class="login-title">Connexion à Fanamby</h2>
            <p class="login-sub">Entrez votre adresse email et votre mot de passe pour accéder à votre espace.</p>
        </div>

        <div v-if="status" class="login-status">{{ status }}</div>

        <form @submit.prevent="submit" class="login-form">
            <div class="lf-group">
                <label class="lf-label" for="email">Adresse email</label>
                <input
                    id="email"
                    type="email"
                    class="lf-input"
                    v-model="form.email"
                    required autofocus
                    autocomplete="username"
                    placeholder="nom@exemple.com"
                />
                <InputError class="gf-error" :message="form.errors.email" />
            </div>

            <div class="lf-group">
                <label class="lf-label" for="password">Mot de passe</label>
                <input
                    id="password"
                    type="password"
                    class="lf-input"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    placeholder="Votre mot de passe"
                />
                <InputError class="gf-error" :message="form.errors.password" />
            </div>

            <div class="lf-remember">
                <label class="lf-check-label">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span>Rester connecté</span>
                </label>
            </div>

            <button class="lf-btn" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                Se connecter
            </button>

            <div class="lf-divider-wrap">
                <div class="lf-divider"></div>
                <span class="lf-divider-text">Ou</span>
                <div class="lf-divider"></div>
            </div>

            <Link v-if="canResetPassword" :href="route('password.request')" class="lf-btn-outline">
                Mot de passe oublié ?
            </Link>

            <div class="lf-signup-text">
                Pas encore de compte ? 
                <Link :href="route('register')" class="lf-link">Créer un compte</Link>
            </div>
        </form>
    </GuestLayout>
</template>

<style scoped>
.login-header {
    text-align: center;
    margin-bottom: 32px;
}

.login-title {
    font-size: 26px;
    font-weight: 800;
    color: #111827;
    margin-bottom: 8px;
    letter-spacing: -0.02em;
}

.login-sub {
    font-size: 14px;
    color: #6b7280;
    line-height: 1.5;
}

.login-status {
    margin-bottom: 24px;
    padding: 12px 16px;
    background: #ecfdf5;
    color: #065f46;
    border: 1px solid #a7f3d0;
    border-left: 4px solid #10b981;
    border-radius: 12px;
    font-size: 14px;
    text-align: center;
}

.login-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.lf-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.lf-label {
    font-size: 13.5px;
    font-weight: 600;
    color: #374151;
}

.lf-input {
    width: 100%;
    padding: 12px 16px;
    background: #ffffff;
    border: 1px solid #d9dedc;
    border-radius: 12px;
    color: #111827;
    font-size: 15px;
    outline: none;
    box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
    transition: border-color 0.2s, box-shadow 0.2s;
}

.lf-input:hover {
    border-color: #bfc7c4;
}

.lf-input:focus {
    border-color: #10b981;
    box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.18);
}

.lf-input::placeholder {
    color: #9ca3af;
}

.gf-error {
    color: #ef4444;
    font-size: 13px;
    margin-top: 4px;
}

.lf-remember {
    display: flex;
    align-items: center;
}

.lf-check-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13.5px;
    color: #4b5563;
    cursor: pointer;
}

.lf-btn {
    width: 100%;
    padding: 14px;
    background: linear-gradient(180deg, #059669 0%, #047857 100%);
    color: #ffffff;
    font-weight: 700;
    font-size: 15px;
    border: 1px solid #036c4e;
    border-radius: 12px;
    cursor: pointer;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.18), 0 1px 2px rgba(4, 120, 87, 0.2), 0 10px 24px -10px rgba(4, 120, 87, 0.6);
    transition: transform 0.2s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.2s, background 0.2s;
}

.lf-btn:hover {
    background: linear-gradient(180deg, #047857 0%, #065f46 100%);
    transform: translateY(-1px);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.18), 0 2px 4px rgba(4, 120, 87, 0.2), 0 14px 28px -10px rgba(4, 120, 87, 0.65);
}

.lf-btn:active {
    transform: translateY(0);
}

.lf-btn:focus-visible,
.lf-btn-outline:focus-visible,
.lf-link:focus-visible {
    outline: 2px solid #10b981;
    outline-offset: 2px;
}

.opacity-25 {
    opacity: 0.25;
}

.lf-divider-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 4px 0;
}

.lf-divider {
    flex: 1;
    height: 1px;
    background: #e5e7eb;
}

.lf-divider-text {
    font-size: 12.5px;
    color: #9ca3af;
    font-weight: 500;
}

.lf-btn-outline {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    padding: 14px;
    background: #ffffff;
    color: #374151;
    font-weight: 600;
    font-size: 14px;
    border: 1px solid #d9dedc;
    border-radius: 12px;
    cursor: pointer;
    text-decoration: none;
    box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04);
    transition: background 0.2s, border-color 0.2s;
}

.lf-btn-outline:hover {
    background: #f8faf9;
    border-color: #bfc7c4;
}

.lf-signup-text {
    text-align: center;
    margin-top: 8px;
    font-size: 14px;
    color: #6b7280;
}

.lf-link {
    color: #047857;
    font-weight: 600;
    text-decoration: none;
    background-image: linear-gradient(currentColor, currentColor);
    background-size: 0% 1.5px;
    background-position: 0 100%;
    background-repeat: no-repeat;
    transition: background-size 0.25s cubic-bezier(0.22, 1, 0.36, 1);
}

.lf-link:hover {
    background-size: 100% 1.5px;
}
</style>

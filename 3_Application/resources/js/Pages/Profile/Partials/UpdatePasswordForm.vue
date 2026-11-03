<script setup>
import Icon from '@/Components/Icon.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);
const enCours = ref(false);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const annuler = () => {
    form.reset();
    form.clearErrors();
    enCours.value = false;
};

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            enCours.value = false;
        },
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <section class="profile-card">
        <header class="profile-card__head">
            <div>
                <h2 class="section-title">
                    Mot de passe
                </h2>

                <p class="section-text">
                    Choisissez un mot de passe long et différent de ceux que vous utilisez ailleurs.
                </p>
            </div>

            <Transition
                enter-active-class="transition ease-in-out"
                enter-from-class="opacity-0"
                leave-active-class="transition ease-in-out"
                leave-to-class="opacity-0"
            >
                <p v-if="form.recentlySuccessful" class="section-ok">Enregistré.</p>
            </Transition>
            <button v-if="!enCours" type="button" class="btn-sm-edit" @click="enCours = true">
                Modifier <Icon name="pencil-simple" />
            </button>
        </header>

        <form v-if="enCours" @submit.prevent="updatePassword" class="profile-fields">
            <div class="profile-fields__large">
                <InputLabel for="current_password" value="Mot de passe actuel" />

                <TextInput
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    class="mt-1 block w-full"
                    autocomplete="current-password"
                    autofocus
                />

                <InputError
                    :message="form.errors.current_password"
                    class="mt-2"
                />
            </div>

            <div>
                <InputLabel for="password" value="Nouveau mot de passe" />

                <TextInput
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                />

                <InputError :message="form.errors.password" class="mt-2" />
            </div>

            <div>
                <InputLabel
                    for="password_confirmation"
                    value="Confirmer le nouveau mot de passe"
                />

                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                />

                <InputError
                    :message="form.errors.password_confirmation"
                    class="mt-2"
                />
            </div>

            <div class="profile-fields__large profile-actions">
                <SecondaryButton @click="annuler">Annuler</SecondaryButton>
                <PrimaryButton :disabled="form.processing">Enregistrer</PrimaryButton>
            </div>
        </form>

        <dl v-else class="profile-fields">
            <div>
                <dt>Mot de passe</dt>
                <dd>••••••••••</dd>
            </div>
        </dl>
    </section>
</template>

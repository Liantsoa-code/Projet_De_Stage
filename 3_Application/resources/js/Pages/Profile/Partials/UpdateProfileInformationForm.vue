<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
    organisation: user.organisation ?? '',
    fonction: user.fonction ?? '',
    adresse: user.adresse ?? '',
    telephone: user.telephone ?? '',
    piece_identite: user.piece_identite ?? '',
});

// Coordonnées du formulaire de demande (sections I et V), reprises à chaque nouvelle demande
const coordonnees = [
    { cle: 'organisation', label: 'Institution / Société de production / Organisation', autocomplete: 'organization' },
    { cle: 'fonction', label: 'Fonction', autocomplete: 'organization-title' },
    { cle: 'adresse', label: 'Adresse', autocomplete: 'street-address' },
    { cle: 'telephone', label: 'Téléphone', autocomplete: 'tel', type: 'tel' },
    { cle: 'piece_identite', label: 'N° de CIN ou de passeport', autocomplete: 'off' },
];
</script>

<template>
    <section>
        <header>
            <h2 class="section-title">
                Informations du compte
            </h2>

            <p class="section-text">
                Votre nom apparaît sur les autorisations délivrées. L'adresse e-mail reçoit les notifications.
                Les coordonnées sont reprises dans le formulaire de demande (et, pour un validateur, la fonction figure sur la décision).
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.update'))"
            class="mt-6 space-y-6"
        >
            <div>
                <InputLabel for="name" value="Nom complet" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Adresse e-mail" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div v-for="c in coordonnees" :key="c.cle">
                <InputLabel :for="c.cle" :value="c.label" />

                <TextInput
                    :id="c.cle"
                    :type="c.type ?? 'text'"
                    class="mt-1 block w-full"
                    v-model="form[c.cle]"
                    :autocomplete="c.autocomplete"
                />

                <InputError class="mt-2" :message="form.errors[c.cle]" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="section-text">
                    Votre adresse e-mail n'est pas encore vérifiée.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="text-link"
                    >
                        Renvoyer l'e-mail de vérification
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="section-ok"
                >
                    Un nouveau lien de vérification vient d'être envoyé.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Enregistrer</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="section-text"
                    >
                        Enregistré.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>

<script setup>
import Icon from '@/Components/Icon.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const page = usePage();
// Relu après chaque enregistrement : les cartes affichent ce qui est en base
const user = computed(() => page.props.auth.user);

const form = useForm({
    name: user.value.name,
    email: user.value.email,
    organisation: user.value.organisation ?? '',
    fonction: user.value.fonction ?? '',
    adresse: user.value.adresse ?? '',
    telephone: user.value.telephone ?? '',
    piece_identite: user.value.piece_identite ?? '',
});

const ROLE_LABELS = { admin: 'Admin', validateur: 'Validateur', demandeur: 'Demandeur' };

const initiales = computed(() =>
    user.value.name
        .split(/\s+/)
        .filter(Boolean)
        .map((w) => w[0])
        .slice(0, 2)
        .join('')
        .toUpperCase(),
);

const sousTitre = computed(() => [user.value.fonction, user.value.organisation].filter(Boolean).join(' · '));

// Une carte par groupe de champs. Les coordonnées sont reprises dans le formulaire de demande (sections I et V).
const cartes = [
    {
        cle: 'personnel',
        titre: 'Informations personnelles',
        texte: "Votre nom apparaît sur les autorisations délivrées. L'adresse e-mail reçoit les notifications.",
        champs: [
            { cle: 'name', label: 'Nom complet', autocomplete: 'name', requis: true },
            { cle: 'email', label: 'Adresse e-mail', type: 'email', autocomplete: 'username', requis: true },
            { cle: 'telephone', label: 'Téléphone', type: 'tel', autocomplete: 'tel' },
            { cle: 'piece_identite', label: 'N° de CIN ou de passeport', autocomplete: 'off' },
        ],
    },
    {
        cle: 'organisation',
        titre: 'Organisation',
        texte: 'Reprise dans le formulaire de demande. Pour un validateur, la fonction figure sur la décision.',
        champs: [
            { cle: 'organisation', label: 'Institution / Société de production / Organisation', autocomplete: 'organization', large: true },
            { cle: 'fonction', label: 'Fonction', autocomplete: 'organization-title' },
            { cle: 'adresse', label: 'Adresse', autocomplete: 'street-address' },
        ],
    },
];

const enCours = ref(null);
const enregistree = ref(null);

const modifier = (cle) => {
    form.reset();
    form.clearErrors();
    enCours.value = cle;
};

const annuler = () => {
    form.reset();
    form.clearErrors();
    enCours.value = null;
};

const enregistrer = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.defaults();
            enregistree.value = enCours.value;
            enCours.value = null;
        },
    });
};
</script>

<template>
    <section class="profile-card profile-id">
        <span class="profile-id__avatar">{{ initiales }}</span>
        <div class="profile-id__text">
            <div class="profile-id__name">{{ user.name }}</div>
            <div class="profile-id__role">{{ ROLE_LABELS[user.role] ?? user.role }}<template v-if="sousTitre"> · {{ sousTitre }}</template></div>
            <div v-if="user.adresse" class="profile-id__lieu">
                <Icon name="map-pin" />{{ user.adresse }}
            </div>
        </div>
        <button v-if="enCours !== 'personnel'" type="button" class="btn-sm-edit" @click="modifier('personnel')">
            Modifier <Icon name="pencil-simple" />
        </button>
    </section>

    <section v-for="carte in cartes" :key="carte.cle" class="profile-card">
        <header class="profile-card__head">
            <div>
                <h2 class="section-title">{{ carte.titre }}</h2>
                <p class="section-text">{{ carte.texte }}</p>
            </div>
            <Transition
                enter-active-class="transition ease-in-out"
                enter-from-class="opacity-0"
                leave-active-class="transition ease-in-out"
                leave-to-class="opacity-0"
            >
                <p v-if="form.recentlySuccessful && enregistree === carte.cle" class="section-ok">Enregistré.</p>
            </Transition>
            <button v-if="enCours !== carte.cle" type="button" class="btn-sm-edit" @click="modifier(carte.cle)">
                Modifier <Icon name="pencil-simple" />
            </button>
        </header>

        <form v-if="enCours === carte.cle" class="profile-fields" @submit.prevent="enregistrer">
            <div v-for="(c, i) in carte.champs" :key="c.cle" :class="{ 'profile-fields__large': c.large }">
                <InputLabel :for="c.cle" :value="c.label" />

                <TextInput
                    :id="c.cle"
                    :type="c.type ?? 'text'"
                    class="mt-1 block w-full"
                    v-model="form[c.cle]"
                    :required="c.requis"
                    :autofocus="i === 0"
                    :autocomplete="c.autocomplete"
                />

                <InputError class="mt-2" :message="form.errors[c.cle]" />
            </div>

            <div class="profile-fields__large profile-actions">
                <SecondaryButton @click="annuler">Annuler</SecondaryButton>
                <PrimaryButton :disabled="form.processing">Enregistrer</PrimaryButton>
            </div>
        </form>

        <dl v-else class="profile-fields">
            <div v-for="c in carte.champs" :key="c.cle" :class="{ 'profile-fields__large': c.large }">
                <dt>{{ c.label }}</dt>
                <dd v-if="user[c.cle]">{{ user[c.cle] }}</dd>
                <dd v-else class="profile-fields__vide">Non renseigné</dd>
            </div>
        </dl>

        <div v-if="carte.cle === 'personnel' && mustVerifyEmail && user.email_verified_at === null" class="profile-card__note">
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
    </section>
</template>

<style scoped>
.profile-id { display: flex; align-items: center; gap: 18px; }
.profile-id__avatar {
    width: 64px;
    height: 64px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: var(--forest-soft);
    color: var(--forest);
    font: 700 21px var(--f-body);
}
.profile-id__text { flex: 1; min-width: 0; display: grid; gap: 3px; }
.profile-id__name { font: 700 18px/1.25 var(--f-display); color: var(--ink); }
.profile-id__role { font-size: 14px; font-weight: 600; color: var(--forest); }
.profile-id__lieu { display: flex; align-items: center; gap: 5px; font-size: 13px; color: var(--ink-3); }
.profile-id__lieu svg { width: 14px; height: 14px; flex-shrink: 0; }
.profile-id .btn-sm-edit { align-self: flex-start; }
@media (max-width: 640px) {
    .profile-id { flex-wrap: wrap; }
}
</style>

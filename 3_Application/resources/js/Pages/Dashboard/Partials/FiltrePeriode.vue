<script setup>
import Icon from '@/Components/Icon.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

// Période observée par le tableau de bord (date d'envoi des demandes) : raccourcis ou dates libres.
// Les paramètres « du » et « au » sont lus par PeriodeStats côté serveur.
const page = usePage();
const periode = computed(() => page.props.periode || {});

// Date locale → AAAA-MM-JJ (toISOString passerait en UTC et décalerait d'un jour à Madagascar)
const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const aujourdHui = new Date();
const annee = aujourdHui.getFullYear();
const ilYA = (mois) => new Date(annee, aujourdHui.getMonth() - mois, aujourdHui.getDate() + 1);

const raccourcis = [
    { id: 'tout', label: 'Depuis le début', du: '', au: '' },
    { id: 'mois', label: 'Ce mois-ci', du: iso(new Date(annee, aujourdHui.getMonth(), 1)), au: iso(aujourdHui) },
    { id: '3mois', label: 'Les 3 derniers mois', du: iso(ilYA(3)), au: iso(aujourdHui) },
    { id: '12mois', label: 'Les 12 derniers mois', du: iso(ilYA(12)), au: iso(aujourdHui) },
    { id: 'annee', label: `Année ${annee}`, du: `${annee}-01-01`, au: `${annee}-12-31` },
    { id: 'annee-1', label: `Année ${annee - 1}`, du: `${annee - 1}-01-01`, au: `${annee - 1}-12-31` },
];

const du = ref(periode.value.du ?? '');
const au = ref(periode.value.au ?? '');
const reconnu = () => raccourcis.find((r) => r.du === du.value && r.au === au.value)?.id ?? 'perso';
const choix = ref(reconnu());

watch(periode, (p) => {
    du.value = p.du ?? '';
    au.value = p.au ?? '';
    choix.value = reconnu();
});

const appliquer = () => {
    const params = Object.fromEntries(Object.entries({ du: du.value, au: au.value }).filter(([, v]) => v));
    // Les graphiques sont recréés avec les nouvelles données : pas de conservation de l'état
    router.get(route('dashboard'), params, { preserveScroll: true, replace: true });
};

const choisir = () => {
    const r = raccourcis.find((x) => x.id === choix.value);
    if (!r) return; // « Période personnalisée » : l'utilisateur saisit ses dates
    du.value = r.du;
    au.value = r.au;
    appliquer();
};
</script>

<template>
    <div class="periode" role="group" aria-label="Période observée">
        <Icon name="calendar-blank" class="periode__icone" />
        <label class="periode__champ">
            <span>Demandes envoyées</span>
            <select v-model="choix" class="gf-select periode__select" @change="choisir">
                <option v-for="r in raccourcis" :key="r.id" :value="r.id">{{ r.label }}</option>
                <option value="perso">Période personnalisée</option>
            </select>
        </label>
        <template v-if="choix === 'perso'">
            <label class="periode__champ">
                <span>du</span>
                <input v-model="du" type="date" class="gf-input periode__date" :max="au || undefined" @change="appliquer" />
            </label>
            <label class="periode__champ">
                <span>au</span>
                <input v-model="au" type="date" class="gf-input periode__date" :min="du || undefined" @change="appliquer" />
            </label>
        </template>
    </div>
</template>

<style scoped>
.periode { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 14px; }
.periode__icone { width: 18px; height: 18px; color: var(--ink-3); }
.periode__champ { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; color: var(--ink-2); }
.periode__select { width: auto; min-width: 200px; padding-top: 6px; padding-bottom: 6px; }
.periode__date { width: auto; padding-top: 6px; padding-bottom: 6px; }
</style>

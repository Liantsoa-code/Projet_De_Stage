import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

// État de navigation partagé : un seul jeu d'écouteurs pour toute l'application
const loading = ref(false);
const samePage = ref(false);
const target = ref('');
let timer = null;
let installed = false;

// Délai avant d'afficher le squelette, pour éviter un flash sur les réponses rapides
const DELAI_MS = 120;

function install() {
    installed = true;

    router.on('start', (event) => {
        const visit = event.detail.visit;
        if (visit.method !== 'get' || visit.prefetch) return;
        const next = new URL(String(visit.url), window.location.origin);
        target.value = next.pathname;
        samePage.value = next.pathname === window.location.pathname;
        clearTimeout(timer);
        timer = setTimeout(() => { loading.value = true; }, DELAI_MS);
    });

    router.on('finish', () => {
        clearTimeout(timer);
        loading.value = false;
    });
}

export function useNavigation() {
    if (!installed && typeof window !== 'undefined') install();
    return { loading, samePage, target };
}

// Gabarit de squelette adapté à la page de destination
export function skeletonVariant(pathname) {
    if (pathname === '/dashboard') return 'dashboard';
    if (/\/(create|edit)$/.test(pathname)) return 'form';
    if (/^\/(validateur\/)?demandes\/\d+$/.test(pathname)) return 'detail';
    return 'list';
}

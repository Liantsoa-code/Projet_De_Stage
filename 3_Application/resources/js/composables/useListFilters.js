import { onScopeDispose, reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Filtres de liste synchronisés avec l'URL (pagination Laravel).
 * Chaque changement recharge la page 1 ; les champs texte sont temporisés.
 */
export function useListFilters(routeName, initial, { textFields = ['q'], delay = 350 } = {}) {
    const filters = reactive({ ...initial });
    let timer = null;

    const apply = () => {
        const params = Object.fromEntries(
            Object.entries(filters).filter(([, v]) => v !== '' && v !== null && v !== undefined && v !== false),
        );
        router.get(route(routeName), params, { preserveState: true, preserveScroll: true, replace: true });
    };

    watch(
        () => ({ ...filters }),
        (now, before) => {
            clearTimeout(timer);
            const textChanged = textFields.some((f) => now[f] !== before[f]);
            if (textChanged) timer = setTimeout(apply, delay);
            else apply();
        },
    );

    // Une saisie en attente ne doit pas relancer la recherche après avoir quitté la page
    onScopeDispose(() => clearTimeout(timer));

    const reset = () => {
        Object.keys(filters).forEach((k) => {
            filters[k] = typeof initial[k] === 'boolean' ? false : '';
        });
    };

    const isActive = () => Object.values(filters).some((v) => v !== '' && v !== null && v !== undefined && v !== false);

    return { filters, reset, isActive };
}

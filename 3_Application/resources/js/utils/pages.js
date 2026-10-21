import { usePage } from '@inertiajs/vue3';

// Page activée dans config/pages.php (une page absente du fichier reste visible)
export function pageVisible(cle) {
    return usePage().props.pages?.[cle] !== false;
}

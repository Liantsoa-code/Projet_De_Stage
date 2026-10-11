// Classe de badge associée à chaque code de statut de demande
const BADGES = {
    brouillon: 'badge-draft',
    soumise: 'badge-info',
    en_analyse: 'badge-analyse',
    a_completer: 'badge-todo',
    validee: 'badge-ok',
    rejetee: 'badge-err',
};

export function statutBadgeClass(code) {
    return ['badge-glass', BADGES[code] ?? 'badge-draft'];
}

// Référence officielle d'une demande : FAN-AAAA-NNNN (année de création)
export function refDemande(d) {
    if (!d?.id) return '';
    const annee = d.created_at ? new Date(d.created_at).getFullYear() : new Date().getFullYear();
    return `FAN-${annee}-${String(d.id).padStart(4, '0')}`;
}

// Jours écoulés depuis une date (arrondi à l'entier inférieur)
export function joursDepuis(value) {
    if (!value) return null;
    return Math.floor((Date.now() - new Date(value).getTime()) / 86400000);
}

// Date ISO (AAAA-MM-JJ ou datetime) → JJ/MM/AAAA
export function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('fr-FR');
}

// Classe de badge associée à chaque code de statut de demande
const BADGES = {
    brouillon: 'badge-draft',
    soumise: 'badge-info',
    en_analyse: 'badge-warn',
    a_completer: 'badge-todo',
    validee: 'badge-ok',
    rejetee: 'badge-err',
};

export function statutBadgeClass(code) {
    return ['badge-glass', BADGES[code] ?? 'badge-draft'];
}

// Date ISO (AAAA-MM-JJ ou datetime) → JJ/MM/AAAA
export function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('fr-FR');
}

// Règles des documents PDF reprises dans le navigateur pour les aperçus en direct (Components/Apercu/).
// Chaque fonction suit le code PHP cité : les garder en phase.

// Type de projet avec sa précision, comme Demande::typeProjet
export function typeProjet(demande) {
    const libelle = demande.type_demande?.libelle ?? '';
    return demande.type_precision ? `${libelle} (${demande.type_precision})` : libelle;
}

// Lignes non vides d'un texte saisi « une par ligne », comme DocumentsOfficiels::lignes
export function lignes(texte) {
    return String(texte ?? '').split(/\r\n|\r|\n/).map((l) => l.trim()).filter(Boolean);
}

// Redevance de la convention : montant par jour saisi, jours d'activités (début → fin, inclus) et total,
// comme DocumentsOfficiels::redevance. Montant vide : pas encore fixé (null) ; 0 : aucune redevance.
export function redevance(demande, contenu) {
    const saisie = String(contenu.redevance_jour ?? '').trim();
    const parJour = /^\d+$/.test(saisie) ? Number(saisie) : null;
    const jour = (v) => new Date(`${String(v).slice(0, 10)}T00:00:00`);
    const jours = demande.date_debut && demande.date_fin ? Math.round((jour(demande.date_fin) - jour(demande.date_debut)) / 86400000) + 1 : 0;
    return { parJour, jours, total: parJour === null ? null : parJour * jours };
}

// Montant en ariary, chiffres groupés par trois (« 8 000 000 »), comme DocumentsOfficiels::montant
export function montant(valeur) {
    return String(valeur).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
}

// Date et heure au format des PDF (d/m/Y à H:i)
export function horodatage(valeur = new Date()) {
    const date = new Date(valeur);
    return `${date.toLocaleDateString('fr-FR')} à ${date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`;
}

// Champs à compléter d'un modèle : textes entre crochets, sans doublon, comme ModelesReference::champs
export function champsModele(contenu) {
    return [...new Set([...contenu.matchAll(/\[([^[\]\r\n]+)\]/g)].map((m) => m[1].trim()))];
}

// Morceaux d'une ligne de modèle : texte, **gras** et [champ] (le champ garde ses crochets), comme ModelesReference::enLigne
function enLigne(texte) {
    return texte.split(/\*\*(.+?)\*\*/).flatMap((partie, i) => partie
        .split(/(\[[^[\]]+\])/)
        .filter(Boolean)
        .map((morceau) => ({ texte: morceau, gras: i % 2 === 1, champ: /^\[[^[\]]+\]$/.test(morceau) })));
}

/**
 * Blocs d'un modèle de la page Références, comme ModelesReference::blocs : h1, h3, pointilles,
 * liste, droite ou texte, chacun avec ses lignes. Vue affiche les blocs comme du texte, jamais comme du HTML.
 */
export function blocsModele(contenu) {
    const blocs = [];
    let bloc = null;
    const fermer = () => {
        if (bloc) blocs.push(bloc);
        bloc = null;
    };

    for (const brute of String(contenu ?? '').split(/\r\n|\r|\n/)) {
        const ligne = brute.trim();

        if (ligne === '') {
            fermer();
            continue;
        }
        const titre = ligne.match(/^(#{1,2})\s+(.+)$/);
        if (titre) {
            fermer();
            blocs.push({ type: titre[1].length === 1 ? 'h1' : 'h3', lignes: [enLigne(titre[2])] });
            continue;
        }
        if (ligne === '...') {
            fermer();
            blocs.push({ type: 'pointilles', lignes: [] });
            continue;
        }

        let type = 'texte';
        let texte = ligne;
        if (ligne.startsWith('- ')) [type, texte] = ['liste', ligne.slice(2)];
        else if (ligne.startsWith('>')) [type, texte] = ['droite', ligne.slice(1).trimStart()];

        if (bloc && bloc.type !== type) fermer();
        bloc ??= { type, lignes: [] };
        bloc.lignes.push(enLigne(texte));
    }
    fermer();

    return blocs;
}

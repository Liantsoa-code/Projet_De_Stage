/**
 * Documents produits par la plateforme (autorisation, cahier des charges, formulaire, modèles), au format
 * choisi, passé au serveur par le paramètre « format » (FormatDocument) : le PDF s'ouvre dans un nouvel
 * onglet, le fichier Word, que le navigateur n'affiche pas, se télécharge. Le nom du fichier est celui
 * donné par le serveur.
 */
export const FORMATS = [
    { cle: 'pdf', libelle: 'PDF', detail: 'Ouvrir dans le navigateur', icone: 'file-pdf', type: 'application/pdf' },
    { cle: 'docx', libelle: 'Word', detail: 'Télécharger pour le modifier', icone: 'file-doc', type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' },
];

const formatDe = (cle) => FORMATS.find((f) => f.cle === cle) ?? FORMATS[0];

/**
 * Récupère le document par une requête authentifiée, sans téléchargement HTTP direct
 * (évite l'interception par des gestionnaires de téléchargement type IDM).
 */
export async function ouvrirDocument(url, format = 'pdf') {
    const f = formatDe(format);
    const adresse = new URL(url, window.location.origin);
    adresse.searchParams.set('format', f.cle);

    const response = await fetch(adresse, {
        credentials: 'same-origin',
        headers: {
            Accept: f.type,
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        const message =
            response.status === 403
                ? 'Accès refusé ou document indisponible (demande non validée).'
                : 'Impossible de récupérer le document.';
        throw new Error(message);
    }

    remettre(await response.blob(), f, response.headers.get('Content-Disposition'));
}

/**
 * Envoie des données (POST, avec le jeton CSRF d'axios) et remet le document produit :
 * aperçu d'un texte pas encore enregistré.
 */
export async function ouvrirDocumentEnvoye(url, data, format = 'pdf') {
    const f = formatDe(format);
    let response;
    try {
        response = await window.axios.post(url, { ...data, format: f.cle }, { responseType: 'blob', headers: { Accept: f.type } });
    } catch (e) {
        throw new Error(e.response?.status === 422
            ? 'Le contenu est vide ou trop long pour produire un aperçu.'
            : 'Impossible de produire l\'aperçu.');
    }

    remettre(response.data, f, response.headers['content-disposition']);
}

// Nom du fichier dans l'en-tête Content-Disposition (forme UTF-8 si elle est donnée)
function nomFichier(disposition, format) {
    const utf8 = disposition?.match(/filename\*=utf-8''([^;]+)/i);
    if (utf8) return decodeURIComponent(utf8[1]);

    return disposition?.match(/filename="?([^";]+)"?/i)?.[1] ?? `document.${format.cle}`;
}

function remettre(blob, format, disposition) {
    const blobUrl = URL.createObjectURL(blob);

    // Avec « noopener », window.open renvoie toujours null : le repli téléchargerait le PDF en double.
    const opened = format.cle === 'pdf' ? window.open(blobUrl, '_blank') : null;
    if (opened) {
        opened.opener = null;
    } else {
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = nomFichier(disposition, format);
        link.rel = 'noopener';
        link.click();
    }

    window.setTimeout(() => URL.revokeObjectURL(blobUrl), 120_000);
}

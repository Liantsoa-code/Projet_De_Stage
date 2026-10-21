/**
 * Ouvre un PDF authentifié sans passer par un téléchargement HTTP direct
 * (évite l'interception par des gestionnaires de téléchargement type IDM).
 */
export async function openAuthenticatedPdf(url, filename = 'document.pdf') {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/pdf',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        const message =
            response.status === 403
                ? 'Accès refusé ou document indisponible (demande non validée).'
                : 'Impossible de récupérer le PDF.';
        throw new Error(message);
    }

    ouvrirBlob(await response.blob(), filename);
}

/**
 * Envoie des données (POST, avec le jeton CSRF d'axios) et ouvre le PDF renvoyé :
 * aperçu d'un texte pas encore enregistré.
 */
export async function openPostedPdf(url, data, filename = 'apercu.pdf') {
    let response;
    try {
        response = await window.axios.post(url, data, { responseType: 'blob', headers: { Accept: 'application/pdf' } });
    } catch (e) {
        throw new Error(e.response?.status === 422
            ? 'Le contenu est vide ou trop long pour produire un aperçu.'
            : 'Impossible de produire l\'aperçu.');
    }

    ouvrirBlob(response.data, filename);
}

function ouvrirBlob(blob, filename) {
    const blobUrl = URL.createObjectURL(blob);

    // Avec « noopener », window.open renvoie toujours null : le repli téléchargerait le PDF en double.
    const opened = window.open(blobUrl, '_blank');
    if (opened) {
        opened.opener = null;
    } else {
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = filename;
        link.rel = 'noopener';
        link.click();
    }

    window.setTimeout(() => URL.revokeObjectURL(blobUrl), 120_000);
}

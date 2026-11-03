// Directive v-glisse : une pastille glisse sous l'onglet actif d'un groupe d'onglets
// (enfant avec aria-selected="true" ou aria-current="page"). La pastille est le ::before
// du conteneur, positionné par des variables CSS (voir .glisse dans app.css).
// v-glisse="'cle'" retient la dernière position du groupe : quand la page change
// (connexion → inscription), la pastille repart de l'ancien onglet.
const positions = new Map();

const ACTIF = '[aria-selected="true"], [aria-current="page"]';

function appliquer(el, pos) {
    el.style.setProperty('--glisse-x', `${pos.x}px`);
    el.style.setProperty('--glisse-y', `${pos.y}px`);
    el.style.setProperty('--glisse-w', `${pos.w}px`);
    el.style.setProperty('--glisse-h', `${pos.h}px`);
    el.style.setProperty('--glisse-r', pos.r);
    el.style.setProperty('--glisse-o', '1');
}

function placer(el, anime) {
    const actif = el.querySelector(ACTIF);
    if (!actif) {
        el.style.setProperty('--glisse-o', '0');
        return;
    }
    const pos = {
        x: actif.offsetLeft,
        y: actif.offsetTop,
        w: actif.offsetWidth,
        h: actif.offsetHeight,
        r: getComputedStyle(actif).borderRadius,
    };
    el.classList.toggle('glisse--fixe', !anime);
    appliquer(el, pos);
    if (el._cle) positions.set(el._cle, pos);
}

export default {
    mounted(el, { value }) {
        el._cle = value;
        el.classList.add('glisse');

        const avant = value && positions.get(value);
        if (avant) {
            // Départ de l'ancienne position, sans animation, puis glissement vers l'onglet actif
            el.classList.add('glisse--fixe');
            appliquer(el, avant);
            el.getBoundingClientRect();
            requestAnimationFrame(() => placer(el, true));
        } else {
            placer(el, false);
        }

        // Retour à la ligne des onglets, chargement des polices : on recale sans animer
        el._observateur = new ResizeObserver(() => placer(el, false));
        el._observateur.observe(el);
    },
    updated(el) {
        placer(el, true);
    },
    unmounted(el) {
        el._observateur?.disconnect();
    },
};

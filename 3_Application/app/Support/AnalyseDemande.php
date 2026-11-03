<?php

namespace App\Support;

use App\Models\ConditionSite;
use App\Models\Demande;
use App\Models\PeriodeSensible;
use App\Models\Site;
use Illuminate\Support\Carbon;

/**
 * Moteur d'analyse d'une demande. Chaque règle examine le séjour demandé (aire protégée, dates, effectif)
 * et relève des contraintes ; le verdict découle de la plus grave. Le moteur explique et propose des
 * conditions, la décision reste au validateur : seule une période bloquante refuse l'envoi.
 *
 * Une contrainte : regle (code de la règle), niveau (voir NIVEAUX), titre, message (le pourquoi) et
 * condition proposée (ou null), reprise dans les conditions particulières de l'autorisation.
 * Le résultat est figé à l'envoi dans t_demandes.resultats_analyse ; le formulaire l'obtient en direct
 * par la route demandes.analyse. Verdict côté Vue : resources/js/utils/analyse.js, à garder en phase.
 */
class AnalyseDemande
{
    /**
     * Niveaux, du plus grave au moins grave : « bloquant » refuse l'envoi, « conflit » appelle un arbitrage
     * du validateur, « attention » rend le séjour possible sous conditions, « info » ne change pas le verdict.
     */
    public const NIVEAUX = ['bloquant', 'conflit', 'attention', 'info'];

    /** À partir de cet effectif, un accompagnateur de Fanamby est exigé. */
    public const EFFECTIF_ACCOMPAGNATEUR = 6;

    /** Au-delà de cette durée en jours, le séjour est long : points d'étape avec le gestionnaire. */
    public const DUREE_LONGUE = 14;

    /** Une période sensible qui commence dans les N jours suivant le départ est signalée. */
    public const MARGE_PERIODE = 7;

    /**
     * Analyse d'un séjour. $demandeId exclut la demande elle-même (modification d'un brouillon ou d'un complément).
     *
     * @param  array{site_id: int|string, date_debut: string, date_fin: string, equipe_nombre?: int|string|null}  $sejour
     * @return array{verdict: string, bloquant: bool, contraintes: array<int, array{regle: string, niveau: string, titre: string, message: string, condition: ?string}>}
     */
    public static function analyser(array $sejour, int $demandeurId, ?int $demandeId = null): array
    {
        $site = Site::with('conditions')->findOrFail($sejour['site_id']);
        $debut = Carbon::parse($sejour['date_debut'])->startOfDay();
        $fin = Carbon::parse($sejour['date_fin'])->startOfDay();
        $equipe = max(1, (int) ($sejour['equipe_nombre'] ?? 1));

        $contraintes = [
            ...self::periodes($site, $debut, $fin),
            ...self::periodeProche($site, $fin),
            ...self::capacite($site, $equipe),
            ...self::frequentation($site, $debut, $fin, $equipe, $demandeId),
            ...self::accompagnateur($equipe),
            ...self::duree($debut, $fin),
            ...self::delai($site, $debut),
            ...self::doublon($site, $debut, $fin, $demandeurId, $demandeId),
            ...self::reglesSite($site),
        ];

        // Les plus graves d'abord ; le tri est stable, l'ordre des règles est gardé à niveau égal.
        usort($contraintes, fn ($a, $b) => array_search($a['niveau'], self::NIVEAUX, true) <=> array_search($b['niveau'], self::NIVEAUX, true));

        return [
            'verdict' => self::verdict($contraintes),
            'bloquant' => collect($contraintes)->contains('niveau', 'bloquant'),
            'contraintes' => $contraintes,
        ];
    }

    /**
     * Verdict : « conflit » (rouge) dès qu'une contrainte est bloquante ou en conflit, « conditions » (orange)
     * s'il reste un point d'attention, sinon « compatible » (vert).
     */
    public static function verdict(?array $contraintes): string
    {
        $niveaux = collect($contraintes ?? [])->map(fn (array $c) => self::niveau($c));

        if ($niveaux->contains('bloquant') || $niveaux->contains('conflit')) {
            return 'conflit';
        }

        return $niveaux->contains('attention') ? 'conditions' : 'compatible';
    }

    /**
     * Niveau d'une contrainte. Les analyses enregistrées avant le moteur n'ont que { type, message } :
     * un avertissement y compte comme un point d'attention.
     */
    public static function niveau(array $contrainte): string
    {
        return $contrainte['niveau'] ?? (($contrainte['type'] ?? null) === 'bloquant' ? 'bloquant' : 'attention');
    }

    /**
     * Contraintes qui pèsent sur le verdict (sans les simples informations), reprises au cahier des charges.
     *
     * @return array<int, array>
     */
    public static function contraintesRelevees(?array $contraintes): array
    {
        return array_values(array_filter($contraintes ?? [], fn (array $c) => self::niveau($c) !== 'info'));
    }

    /**
     * Conditions proposées, sans doublon, dans l'ordre des contraintes.
     *
     * @return array<int, string>
     */
    public static function conditions(?array $contraintes): array
    {
        return collect($contraintes ?? [])->pluck('condition')->filter()->unique()->values()->all();
    }

    /**
     * Règle 1 — Périodes sensibles qui chevauchent le séjour. Une période bloquante interdit l'envoi ;
     * une période d'avertissement propose sa condition, à défaut une condition générale sur les jours communs.
     */
    private static function periodes(Site $site, Carbon $debut, Carbon $fin): array
    {
        // Deux intervalles se chevauchent si chacun commence avant la fin de l'autre.
        return $site->periodeSensibles()
            ->where('date_debut', '<=', $fin->toDateString())
            ->where('date_fin', '>=', $debut->toDateString())
            ->orderBy('date_debut')
            ->get()
            ->map(function (PeriodeSensible $p) use ($debut, $fin) {
                $du = $p->date_debut->max($debut);
                $au = $p->date_fin->min($fin);
                $jours = DelaiDecision::joursEntre($du, $au) + 1;
                $communs = $jours === 1 ? 'le ' . self::date($du) : "pendant {$jours} jours, du " . self::date($du) . ' au ' . self::date($au);
                $periode = "« {$p->description} » (du " . self::date($p->date_debut) . ' au ' . self::date($p->date_fin) . ')';

                if ($p->niveau_restriction === 'bloquant') {
                    return self::contrainte('periode', 'bloquant', "Période d'interdiction",
                        "Le séjour chevauche la période {$periode} {$communs} : l'accès à l'aire protégée est interdit.");
                }

                return self::contrainte('periode', 'attention', 'Période sensible',
                    "Le séjour chevauche la période {$periode} {$communs}.",
                    $p->condition_proposee ?: 'Du ' . self::date($du) . ' au ' . self::date($au)
                        . " (période « {$p->description} »), activités limitées aux consignes du gestionnaire de l'aire protégée.");
            })
            ->all();
    }

    /**
     * Règle 2 — Période sensible qui commence peu après le départ : le séjour ne pourra pas être prolongé.
     */
    private static function periodeProche(Site $site, Carbon $fin): array
    {
        $p = $site->periodeSensibles()
            ->where('date_debut', '>', $fin->toDateString())
            ->where('date_debut', '<=', $fin->copy()->addDays(self::MARGE_PERIODE)->toDateString())
            ->orderBy('date_debut')
            ->first();

        if (! $p) {
            return [];
        }

        $ecart = DelaiDecision::joursEntre($fin, $p->date_debut);

        return [self::contrainte('periode_proche', 'info', 'Période sensible proche',
            "La période « {$p->description} » commence le " . self::date($p->date_debut) . ', ' . self::pluriel($ecart, 'jour') . ' après la fin du séjour.',
            'Aucune prolongation du séjour au-delà du ' . self::date($p->date_debut->copy()->subDay()) . '.')];
    }

    /**
     * Règle 3 — Équipe plus nombreuse que la capacité d'accueil de l'aire protégée : elle se relaie par groupes.
     */
    private static function capacite(Site $site, int $equipe): array
    {
        if (! $site->capacite_accueil || $equipe <= $site->capacite_accueil) {
            return [];
        }

        return [self::contrainte('capacite', 'attention', "Capacité d'accueil dépassée",
            "L'équipe compte {$equipe} personnes pour une capacité de " . self::pluriel($site->capacite_accueil, 'personne') . ' présentes en même temps.',
            "Pas plus de {$site->capacite_accueil} membres de l'équipe présents en même temps sur l'aire protégée : l'équipe se relaie par groupes.")];
    }

    /**
     * Règle 4 — Autres séjours déjà validés sur l'aire protégée aux mêmes dates. On retient le jour le plus chargé :
     * aire protégée déjà pleine ce jour-là, conflit ; pleine avec l'équipe, l'effectif présent est limité.
     */
    private static function frequentation(Site $site, Carbon $debut, Carbon $fin, int $equipe, ?int $demandeId): array
    {
        $sejours = Demande::where('site_id', $site->id)
            ->whereHas('statut', fn ($q) => $q->where('code', 'validee'))
            ->when($demandeId, fn ($q) => $q->whereKeyNot($demandeId))
            ->where('date_debut', '<=', $fin->toDateString())
            ->where('date_fin', '>=', $debut->toDateString())
            ->get(['id', 'date_debut', 'date_fin', 'equipe_nombre']);

        if ($sejours->isEmpty()) {
            return [];
        }

        // Le jour le plus chargé est le premier jour du séjour ou l'arrivée d'une autre équipe.
        $pic = ['presents' => 0, 'jour' => $debut];
        $arrivees = $sejours->pluck('date_debut')->filter(fn (Carbon $d) => $d->gt($debut))->push($debut)->sort();
        foreach ($arrivees as $jour) {
            $presents = $sejours->filter(fn (Demande $s) => $s->date_debut->lte($jour) && $s->date_fin->gte($jour))->sum('equipe_nombre');
            if ($presents > $pic['presents']) {
                $pic = ['presents' => $presents, 'jour' => $jour];
            }
        }

        $autres = $sejours->count() > 1 ? "{$sejours->count()} autres équipes autorisées" : '1 autre équipe autorisée';
        $constat = self::pluriel($pic['presents'], 'personne') . ' déjà autorisée' . ($pic['presents'] > 1 ? 's' : '')
            . ' sur l\'aire protégée le ' . self::date($pic['jour']) . " ({$autres} sur ces dates)";
        $capacite = $site->capacite_accueil;

        if (! $capacite) {
            return [self::contrainte('frequentation', 'info', 'Autres équipes sur place', "{$constat}.")];
        }

        if ($pic['presents'] >= $capacite) {
            return [self::contrainte('frequentation', 'conflit', 'Aire protégée complète',
                "{$constat}, pour une capacité de " . self::pluriel($capacite, 'personne') . '.')];
        }

        if ($pic['presents'] + $equipe > $capacite) {
            $places = $capacite - $pic['presents'];

            return [self::contrainte('frequentation', 'attention', 'Fréquentation élevée',
                "{$constat} : avec l'équipe, " . ($pic['presents'] + $equipe) . " personnes pour une capacité de {$capacite}.",
                'Les jours de forte fréquentation (dont le ' . self::date($pic['jour']) . "), pas plus de {$places} membre"
                    . ($places > 1 ? 's' : '') . " de l'équipe présent" . ($places > 1 ? 's' : '') . ' en même temps sur l\'aire protégée.')];
        }

        return [self::contrainte('frequentation', 'info', 'Autres équipes sur place',
            "{$constat} ; la capacité de {$capacite} personnes reste respectée.")];
    }

    /**
     * Règle 5 — Équipe nombreuse : présence d'un accompagnateur de Fanamby.
     */
    private static function accompagnateur(int $equipe): array
    {
        if ($equipe < self::EFFECTIF_ACCOMPAGNATEUR) {
            return [];
        }

        return [self::contrainte('accompagnateur', 'attention', 'Équipe nombreuse',
            "L'équipe compte {$equipe} personnes : un accompagnement est exigé à partir de " . self::EFFECTIF_ACCOMPAGNATEUR . ' personnes.',
            "Présence d'un accompagnateur de Fanamby pendant toute la durée du séjour.")];
    }

    /**
     * Règle 6 — Séjour long : points d'étape réguliers avec le gestionnaire de l'aire protégée.
     */
    private static function duree(Carbon $debut, Carbon $fin): array
    {
        $jours = DelaiDecision::joursEntre($debut, $fin) + 1;

        if ($jours <= self::DUREE_LONGUE) {
            return [];
        }

        return [self::contrainte('duree', 'attention', 'Séjour long',
            "Le séjour dure {$jours} jours, au-delà de " . self::DUREE_LONGUE . ' jours.',
            "Point d'étape hebdomadaire avec le gestionnaire de l'aire protégée et rapport d'activité remis à la fin du séjour.")];
    }

    /**
     * Règle 7 — Arrivée proche : d'après le délai habituel de décision du site, la réponse risque
     * d'arriver après le début du séjour. Calculé à l'envoi, puis figé avec le reste de l'analyse.
     */
    private static function delai(Site $site, Carbon $debut): array
    {
        $delai = DelaiDecision::habituelParSite()($site->id);
        $avant = DelaiDecision::joursEntre(today(), $debut);

        if ($delai === null || $avant < 0 || $avant >= $delai) {
            return [];
        }

        return [self::contrainte('delai', 'info', 'Arrivée proche',
            'Envoi ' . self::pluriel($avant, 'jour') . " avant l'arrivée, alors que la décision prend habituellement "
                . str_replace('.', ',', (string) $delai) . ' jours sur cette aire protégée (estimée vers le '
                . self::date(today()->addDays((int) ceil($delai))) . ').')];
    }

    /**
     * Règle 8 — Autre demande du même demandeur, en cours ou validée, sur la même aire protégée à des dates communes.
     */
    private static function doublon(Site $site, Carbon $debut, Carbon $fin, int $demandeurId, ?int $demandeId): array
    {
        return Demande::where('user_id', $demandeurId)
            ->where('site_id', $site->id)
            ->when($demandeId, fn ($q) => $q->whereKeyNot($demandeId))
            ->whereHas('statut', fn ($q) => $q->whereNotIn('code', ['brouillon', 'rejetee']))
            ->where('date_debut', '<=', $fin->toDateString())
            ->where('date_fin', '>=', $debut->toDateString())
            ->orderBy('date_debut')
            ->get(['id', 'created_at', 'date_debut', 'date_fin'])
            ->map(fn (Demande $d) => self::contrainte('doublon', 'info', 'Autre demande sur ces dates',
                "La demande {$d->reference} du même demandeur porte déjà sur cette aire protégée, du "
                    . self::date($d->date_debut) . ' au ' . self::date($d->date_fin) . '.'))
            ->all();
    }

    /**
     * Règle 9 — Règles permanentes de l'aire protégée : toujours proposées, sans effet sur le verdict.
     */
    private static function reglesSite(Site $site): array
    {
        return $site->conditions
            ->map(fn (ConditionSite $c) => self::contrainte('regle_site', 'info', "Règle de l'aire protégée", $c->libelle, $c->libelle))
            ->all();
    }

    private static function contrainte(string $regle, string $niveau, string $titre, string $message, ?string $condition = null): array
    {
        return compact('regle', 'niveau', 'titre', 'message', 'condition');
    }

    private static function date(Carbon $date): string
    {
        return $date->format('d/m/Y');
    }

    private static function pluriel(int $n, string $mot): string
    {
        return $n . ' ' . $mot . ($n > 1 ? 's' : '');
    }
}

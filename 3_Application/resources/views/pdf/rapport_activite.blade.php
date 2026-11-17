<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rapport d'activité — Plateforme d'accès aux aires protégées</title>
    {{-- Données : StatsAdmin::rapport(), les mêmes que le tableau de bord de l'admin pour la même période --}}
    <style>
        @page { margin: 50px 55px 65px; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 11px; color: #15201A; line-height: 1.4; }
        .entete { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .entete td { vertical-align: middle; }
        .entete img { width: 54px; height: 54px; }
        h1 { font-size: 17px; margin: 0 0 3px; color: #00683A; }
        .sous-titre { font-size: 11.5px; color: #3D4A42; }
        h2 { font-size: 13px; margin: 20px 0 6px; padding-bottom: 3px; border-bottom: 1.5px solid #00683A; color: #00683A; }
        .note { font-size: 9.5px; color: #6E7A72; margin: 4px 0 0; }
        table.t { width: 100%; border-collapse: collapse; }
        table.t th { background: #EEF2EC; text-align: left; font-size: 9.5px; text-transform: uppercase; letter-spacing: .3px; color: #3D4A42; padding: 5px 6px; border-bottom: 1px solid #D3DAD1; }
        table.t td { padding: 4px 6px; border-bottom: 1px solid #E4E9E2; }
        table.t .n { text-align: right; white-space: nowrap; }
        table.t tr.total td { font-weight: bold; border-top: 1.5px solid #15201A; border-bottom: 0; }
        .kpis { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px; }
        .kpis td { width: 25%; padding: 8px 10px; background: #F4F6F2; border: 1px solid #E4E9E2; vertical-align: top; }
        .kpi-n { font-size: 20px; font-weight: bold; }
        .kpi-l { font-size: 10px; font-weight: bold; }
        .kpi-s { font-size: 9px; color: #6E7A72; }
        .barre { height: 7px; background: #00683A; }
        .barre--n1 { background: #B9C4B6; }
        .footer { position: fixed; bottom: -42px; left: 0; right: 0; text-align: center; font-size: 9px; color: #6E7A72; border-top: 1px solid #D3DAD1; padding-top: 5px; }
        .saut { page-break-before: always; }
    </style>
</head>
<body>
    @php
        $p = $principales;
        $q = $qualite;
        $pct = fn ($part, $total) => $total > 0 ? round($part / $total * 100) . ' %' : '—';
        $val = fn ($v, $suffixe = '') => $v === null ? '—' : str_replace('.', ',', (string) $v) . $suffixe;
        $mois = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
        $annee = $par_mois['annee'];
        $maxMois = max(1, max($par_mois['courante']), max($par_mois['precedente']));
        $totalTypes = array_sum(array_column($par_type, 'total'));
        $totalSites = collect($par_site);
        $maxFreq = max(1, collect($frequentation)->max('personnes_jours') ?? 0);
    @endphp

    <div class="footer">
        Rapport d'activité · Plateforme d'accès aux aires protégées Fanamby · Édité le {{ now()->format('d/m/Y à H:i') }} par {{ $auteur->name }}
    </div>

    <table class="entete">
        <tr>
            <td>
                <h1>Rapport d'activité</h1>
                <div class="sous-titre">
                    Demandes d'accès aux aires protégées —
                    <strong>{{ $periode->libelle() ? 'demandes envoyées ' . $periode->libelle() : 'toutes les demandes depuis l\'ouverture de la plateforme' }}</strong>
                </div>
            </td>
            <td style="text-align:right; width:60px"><img src="{{ public_path('assets/logo-fanamby-192.png') }}" alt="Fanamby"></td>
        </tr>
    </table>

    <h2>1. Vue d'ensemble</h2>
    <table class="kpis">
        <tr>
            <td>
                <div class="kpi-n">{{ $p['recues'] }}</div>
                <div class="kpi-l">Demandes reçues</div>
                <div class="kpi-s">brouillons exclus</div>
            </td>
            <td>
                <div class="kpi-n">{{ $val($p['taux_validation'], ' %') }}</div>
                <div class="kpi-l">Taux de validation</div>
                <div class="kpi-s">validées ÷ (validées + rejetées)</div>
            </td>
            <td>
                <div class="kpi-n">{{ $val($q['delai_median'], ' j') }}</div>
                <div class="kpi-l">Délai médian de décision</div>
                <div class="kpi-s">{{ $q['pct_objectif'] === null ? 'aucune décision' : $q['pct_objectif'] . ' % décidés en ' . $q['objectif_jours'] . ' j ou moins' }}</div>
            </td>
            <td>
                <div class="kpi-n">{{ $val($q['pct_complement'], ' %') }}</div>
                <div class="kpi-l">Passés par un complément</div>
                <div class="kpi-s">sur {{ $q['decisions'] }} dossiers décidés</div>
            </td>
        </tr>
    </table>

    <table class="t" style="margin-top:10px">
        <tr><th>Statut des demandes reçues</th><th class="n">Nombre</th><th class="n">Part</th></tr>
        <tr><td>En attente de décision (soumises, en analyse, à compléter)</td><td class="n">{{ $p['en_attente'] }}</td><td class="n">{{ $pct($p['en_attente'], $p['recues']) }}</td></tr>
        <tr><td>Validées</td><td class="n">{{ $p['validees'] }}</td><td class="n">{{ $pct($p['validees'], $p['recues']) }}</td></tr>
        <tr><td>Rejetées</td><td class="n">{{ $p['rejetees'] }}</td><td class="n">{{ $pct($p['rejetees'], $p['recues']) }}</td></tr>
        <tr><td>Retirées par le demandeur</td><td class="n">{{ $p['retirees'] }}</td><td class="n">{{ $pct($p['retirees'], $p['recues']) }}</td></tr>
        <tr class="total"><td>Total reçu</td><td class="n">{{ $p['recues'] }}</td><td class="n">{{ $p['recues'] ? '100 %' : '—' }}</td></tr>
    </table>

    <h2>2. Synthèse par aire protégée</h2>
    <table class="t">
        <tr>
            <th>Aire protégée</th><th class="n">Reçues</th><th class="n">Part</th><th class="n">En attente</th>
            <th class="n">Validées</th><th class="n">Rejetées</th><th class="n">Retirées</th><th class="n">Taux de validation</th><th class="n">Délai médian</th>
        </tr>
        @foreach ($par_site as $s)
            <tr>
                <td>{{ $s['nom'] }}</td>
                <td class="n">{{ $s['recues'] }}</td>
                <td class="n">{{ $pct($s['recues'], $p['recues']) }}</td>
                <td class="n">{{ $s['en_attente'] }}</td>
                <td class="n">{{ $s['validees'] }}</td>
                <td class="n">{{ $s['rejetees'] }}</td>
                <td class="n">{{ $s['retirees'] }}</td>
                <td class="n">{{ $val($s['taux_validation'], ' %') }}</td>
                <td class="n">{{ $val($s['delai_median'], ' j') }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td>Total</td>
            <td class="n">{{ $totalSites->sum('recues') }}</td>
            <td class="n">{{ $p['recues'] ? '100 %' : '—' }}</td>
            <td class="n">{{ $totalSites->sum('en_attente') }}</td>
            <td class="n">{{ $totalSites->sum('validees') }}</td>
            <td class="n">{{ $totalSites->sum('rejetees') }}</td>
            <td class="n">{{ $totalSites->sum('retirees') }}</td>
            <td class="n">{{ $val($p['taux_validation'], ' %') }}</td>
            <td class="n">{{ $val($q['delai_median'], ' j') }}</td>
        </tr>
    </table>
    <p class="note">Le délai médian d'une aire porte sur ses seuls dossiers décidés ; « — » : aucune décision.</p>

    <h2>3. Demandes reçues par mois : {{ $annee }} comparée à {{ $annee - 1 }}</h2>
    <table class="t">
        <tr><th>Mois</th><th class="n">{{ $annee - 1 }}</th><th style="width:28%"></th><th class="n">{{ $annee }}</th><th style="width:28%"></th></tr>
        @foreach ($mois as $i => $nom)
            <tr>
                <td>{{ $nom }}</td>
                <td class="n">{{ $par_mois['precedente'][$i] }}</td>
                <td>@if ($par_mois['precedente'][$i])<div class="barre barre--n1" style="width: {{ $par_mois['precedente'][$i] / $maxMois * 100 }}%"></div>@endif</td>
                <td class="n">{{ $par_mois['courante'][$i] }}</td>
                <td>@if ($par_mois['courante'][$i])<div class="barre" style="width: {{ $par_mois['courante'][$i] / $maxMois * 100 }}%"></div>@endif</td>
            </tr>
        @endforeach
        <tr class="total">
            <td>Total</td>
            <td class="n">{{ array_sum($par_mois['precedente']) }}</td><td></td>
            <td class="n">{{ array_sum($par_mois['courante']) }}</td><td></td>
        </tr>
    </table>
    <p class="note">Mois d'envoi de la demande, toutes périodes confondues pour ces deux années.</p>

    <h2>4. Demandes reçues par type de projet</h2>
    @if ($par_type)
        <table class="t">
            <tr><th>Type de projet</th><th class="n">Demandes</th><th class="n">Part</th></tr>
            @foreach ($par_type as $t)
                <tr><td>{{ $t['nom'] }}</td><td class="n">{{ $t['total'] }}</td><td class="n">{{ $pct($t['total'], $totalTypes) }}</td></tr>
            @endforeach
            <tr class="total"><td>Total</td><td class="n">{{ $totalTypes }}</td><td class="n">100 %</td></tr>
        </table>
    @else
        <p>Aucune demande reçue sur la période.</p>
    @endif

    <h2>5. Fréquentation autorisée {{ $periode->active() ? 'sur la période' : 'sur les 12 prochains mois' }}</h2>
    @if (count($frequentation))
        <table class="t">
            <tr><th>Aire protégée</th><th class="n">Séjours validés</th><th class="n">Personnes</th><th class="n">Personnes × jours</th><th style="width:30%"></th></tr>
            @foreach ($frequentation as $f)
                <tr>
                    <td>{{ $f['nom'] }}</td>
                    <td class="n">{{ $f['sejours'] }}</td>
                    <td class="n">{{ $f['personnes'] }}</td>
                    <td class="n">{{ number_format($f['personnes_jours'], 0, ',', ' ') }}</td>
                    <td><div class="barre" style="width: {{ $f['personnes_jours'] / $maxFreq * 100 }}%"></div></td>
                </tr>
            @endforeach
            <tr class="total">
                <td>Total</td>
                <td class="n">{{ collect($frequentation)->sum('sejours') }}</td>
                <td class="n">{{ collect($frequentation)->sum('personnes') }}</td>
                <td class="n">{{ number_format(collect($frequentation)->sum('personnes_jours'), 0, ',', ' ') }}</td>
                <td></td>
            </tr>
        </table>
        <p class="note">Personnes × jours : taille de l'équipe multipliée par ses jours de présence, de l'arrivée sur le terrain à la fin{{ $periode->active() ? ', comptés dans la période seulement' : '' }}.</p>
    @else
        <p>Aucun séjour autorisé {{ $periode->active() ? 'sur la période' : 'sur les 12 prochains mois' }}.</p>
    @endif

    <h2>Définitions</h2>
    <p class="note" style="font-size:10px; color:#3D4A42">
        Demande reçue : demande envoyée par le demandeur (les brouillons ne sont jamais comptés), datée de son envoi.
        Délai de décision : jours calendaires entre l'envoi et la validation ou le rejet.
        Taux de validation : calculé sur les seuls dossiers décidés, les dossiers en attente ne le font pas baisser.
        Les totaux des sections 1, 2 et 4 portent sur les mêmes demandes et concordent.
    </p>
</body>
</html>

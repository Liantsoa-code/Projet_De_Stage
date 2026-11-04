<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Convention de collaboration</title>
    {{-- Aperçu en direct dans le navigateur : resources/js/Components/Apercu/ApercuConvention.vue, à garder en phase --}}
    <style>
        @page { margin: 50px 60px 75px; }
        /* Un mot trop long pour la ligne est coupé au lieu de sortir de la page */
        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 13px; color: #15201A; line-height: 1.5; overflow-wrap: anywhere; }
        .logo { text-align: center; }
        .logo img { width: 72px; height: 72px; }
        h1 { font-size: 20px; text-transform: uppercase; text-align: center; margin: 10px 0 26px; }
        h3 { font-size: 13.5px; margin: 18px 0 6px; page-break-after: avoid; }
        p { margin: 0 0 8px; }
        ul { margin: 2px 0 8px; padding-left: 20px; }
        .signatures { width: 100%; margin-top: 24px; border-collapse: collapse; page-break-inside: avoid; }
        .signatures td { width: 50%; vertical-align: top; padding-right: 20px; }
        .signatures small { color: #6E7A72; }
        .footer { position: fixed; bottom: -45px; left: 0; right: 0; text-align: center; font-size: 11px; color: #6E7A72; border-top: 1px solid #D3DAD1; padding-top: 6px; }
    </style>
</head>
<body>
    @php
        $date = fn ($valeur) => $valeur ? \Carbon\Carbon::parse($valeur)->format('d/m/Y') : '';
        $lignes = fn ($texte) => \App\Support\DocumentsOfficiels::lignes($texte);
        $redevance = \App\Support\DocumentsOfficiels::redevance($demande, $contenu);
        $montant = fn (int $valeur) => \App\Support\DocumentsOfficiels::montant($valeur);
        $beneficiaire = $demande->user;
        $identite = collect([
            $beneficiaire->fonction,
            $beneficiaire->organisation,
            $beneficiaire->adresse ? "demeurant à {$beneficiaire->adresse}" : null,
            $beneficiaire->telephone ? "Tél. {$beneficiaire->telephone}" : null,
            "e-mail : {$beneficiaire->email}",
            $beneficiaire->piece_identite ? "CIN / passeport : {$beneficiaire->piece_identite}" : null,
        ])->filter()->implode(', ');
        $modalites = $lignes($contenu['modalites']);
    @endphp

    <div class="footer">
        Document officiel généré le {{ now()->format('d/m/Y à H:i') }} par la plateforme de gestion Fanamby. Référence : {{ $demande->reference }}
    </div>

    <div class="logo"><img src="{{ public_path('assets/logo-fanamby-192.png') }}" alt="Fanamby"></div>

    <h1>Convention de collaboration</h1>

    <p>Entre les soussignés :</p>
    <p>{!! nl2br(e($contenu['partie_fanamby'])) !!} ;</p>
    <p>Et</p>
    <p><strong>{{ $beneficiaire->name }}</strong>, {{ $identite }}, ci-après désigné(e) « le Bénéficiaire » ;</p>
    <p>Il a été convenu ce qui suit :</p>

    <h3>Article 1 – Objet de la convention</h3>
    <p>{!! nl2br(e($contenu['objet'])) !!}</p>

    <h3>Article 2 – Avis favorable</h3>
    <p>{!! nl2br(e($contenu['avis'])) !!}</p>

    <h3>Article 3 – Période et modalités des opérations</h3>
    <p>
        Arrivée sur le terrain le <strong>{{ $date($demande->arrivee()) }}</strong> ; activités du <strong>{{ $date($demande->date_debut) }}</strong>
        au <strong>{{ $date($demande->date_fin) }}</strong> ({{ $redevance['jours'] }} jour{{ $redevance['jours'] > 1 ? 's' : '' }}) ;
        équipe de {{ $demande->equipe_nombre }} personne(s).@if($modalites) Les opérations comprennent notamment :@endif
    </p>
    @if($modalites)
        <ul>
            @foreach($modalites as $modalite)
                <li>{{ $modalite }}</li>
            @endforeach
        </ul>
    @endif
    <p>Toute modification substantielle du calendrier devra être validée préalablement par Fanamby.</p>

    <h3>Article 4 – Redevance financière</h3>
    @if($redevance['par_jour'] === null)
        <p>Le montant de la redevance due par le Bénéficiaire sera communiqué par Fanamby.</p>
    @elseif($redevance['par_jour'] === 0)
        <p>La présente convention ne donne lieu à aucune redevance.</p>
    @else
        <p>
            En contrepartie de l'autorisation d'exploitation temporaire du site pour les besoins du projet, le Bénéficiaire s'engage à verser à Fanamby la somme de
            <strong>{{ $montant($redevance['par_jour']) }} Ariary par jour</strong>, soit pour {{ $redevance['jours'] }} jour{{ $redevance['jours'] > 1 ? 's' : '' }} d'activités :
            <strong>{{ $montant($redevance['total']) }} Ariary</strong>.
        </p>
    @endif
    <p>{!! nl2br(e($contenu['paiement'])) !!}</p>

    <h3>Article 5 – Affectation des recettes</h3>
    <p>{!! nl2br(e($contenu['affectation'])) !!}</p>

    <h3>Article 6 – Obligations du Bénéficiaire</h3>
    <p>Le Bénéficiaire s'engage à :</p>
    <ul>
        @foreach($lignes($contenu['obligations_beneficiaire']) as $ligne)
            <li>{{ $ligne }}</li>
        @endforeach
    </ul>

    <h3>Article 7 – Obligations de Fanamby</h3>
    <p>Fanamby s'engage à :</p>
    <ul>
        @foreach($lignes($contenu['obligations_fanamby']) as $ligne)
            <li>{{ $ligne }}</li>
        @endforeach
    </ul>

    <h3>Article 8 – Responsabilité</h3>
    <p>Le Bénéficiaire demeure seul responsable :</p>
    <ul>
        @foreach($lignes($contenu['responsabilite']) as $ligne)
            <li>{{ $ligne }}</li>
        @endforeach
    </ul>

    <h3>Article 9 – Litiges</h3>
    <p>{!! nl2br(e($contenu['litiges'])) !!}</p>

    <h3>Article 10 – Entrée en vigueur</h3>
    <p>{!! nl2br(e($contenu['entree_vigueur'])) !!}</p>

    <p style="margin-top: 18px">
        Fait à {{ $contenu['lieu'] }}, le {{ $validation ? $validation->date_changement->format('d/m/Y') : '___ / ___ / ______' }}, en deux exemplaires originaux.
    </p>

    <table class="signatures">
        <tr>
            <td>
                <strong>Pour Fanamby</strong><br>
                {{ $contenu['signataire'] }}<br>
                Signature et cachet :
                @if($validation)
                    <br><small>Validé et signé électroniquement par {{ $validation->user?->name ?? 'un validateur' }} le {{ $validation->date_changement->format('d/m/Y à H:i') }}</small>
                @endif
            </td>
            <td>
                <strong>Le Bénéficiaire</strong><br>
                {{ $beneficiaire->name }}<br>
                Signature :
            </td>
        </tr>
    </table>
</body>
</html>

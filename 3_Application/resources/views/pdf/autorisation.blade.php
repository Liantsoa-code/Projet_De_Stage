<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Autorisation d'accès</title>
    {{-- Aperçu en direct dans le navigateur : resources/js/Components/Apercu/ApercuAutorisation.vue, à garder en phase --}}
    <style>
        /* Un mot trop long pour la ligne est coupé au lieu de sortir de la page */
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #15201A; overflow-wrap: anywhere; }
        .logo { text-align: right; }
        .logo img { width: 72px; height: 72px; }
        .content { margin: 20px; line-height: 1.6; }
        .footer { position: absolute; bottom: 30px; width: 100%; text-align: center; font-size: 12px; color: #6E7A72; border-top: 1px solid #D3DAD1; padding-top: 10px; }
        h1 { font-size: 20px; text-transform: uppercase; text-align: center; margin-bottom: 40px; }
        .signature { text-align: right; }
        .signature small { color: #6E7A72; }
    </style>
</head>
<body>
    <div class="logo"><img src="{{ public_path('assets/logo-fanamby-192.png') }}" alt="Fanamby"></div>

    <h1>AUTORISATION OFFICIELLE D'ACCÈS</h1>

    <div class="content">
        <p>{!! nl2br(e($contenu['introduction'])) !!}</p>
        <p><strong>Monsieur / Madame :</strong> {{ $demande->user->name }} ({{ $demande->user->email }})</p>
        <p>À accéder à l'aire protégée de : <strong>{{ $demande->site->nom }}</strong></p>
        <p>Dans le cadre du projet suivant : <strong>{{ $demande->typeProjet() }}</strong>@if($demande->titre) — « {{ $demande->titre }} »@endif</p>
        <p><strong>Arrivée sur le terrain :</strong> Le {{ \Carbon\Carbon::parse($demande->arrivee())->format('d/m/Y') }}</p>
        <p><strong>Période autorisée :</strong> Du {{ \Carbon\Carbon::parse($demande->date_debut)->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($demande->date_fin)->format('d/m/Y') }}</p>
        <p><strong>Taille de l'équipe :</strong> {{ $demande->equipe_nombre }} personne(s)</p>
        <p><strong>Objet de la mission :</strong></p>
        <p>{!! nl2br(e($contenu['objet'])) !!}</p>

        @php($conditions = \App\Support\DocumentsOfficiels::lignes($contenu['conditions']))
        @if($conditions)
            <p><strong>Conditions particulières :</strong></p>
            <ul>
                @foreach($conditions as $condition)
                    <li>{{ $condition }}</li>
                @endforeach
            </ul>
        @endif

        <br><br>
        <p><em>{!! nl2br(e($contenu['reserve'])) !!}</em></p>
        <br>
        <p class="signature">
            <strong>Fait pour valoir ce que de droit,</strong><br>{{ $contenu['signataire'] }}
            @if($validation)
                <br><small>Validé et signé électroniquement par {{ $validation->user?->name ?? 'un validateur' }} le {{ $validation->date_changement->format('d/m/Y à H:i') }}</small>
            @endif
        </p>
    </div>

    <div class="footer">
        Document officiel généré le {{ now()->format('d/m/Y à H:i') }} par la plateforme de gestion Fanamby. Référence : {{ $demande->reference }}
    </div>
</body>
</html>

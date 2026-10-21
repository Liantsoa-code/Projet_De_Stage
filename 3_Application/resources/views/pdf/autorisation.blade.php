<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Autorisation d'accès</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #15201A; }
        .header { text-align: center; border-bottom: 2px solid #00683A; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { font-size: 24px; font-weight: bold; color: #00683A; }
        .content { margin: 20px; line-height: 1.6; }
        .footer { position: absolute; bottom: 30px; width: 100%; text-align: center; font-size: 12px; color: #6E7A72; border-top: 1px solid #D3DAD1; padding-top: 10px; }
        h1 { font-size: 20px; text-transform: uppercase; text-align: center; margin-bottom: 40px; }
        .projet { text-align: center; color: #A3271B; border: 2px dashed #A3271B; padding: 8px; margin-bottom: 20px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        .signature { text-align: right; }
        .signature small { color: #6E7A72; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">FONDATION FANAMBY</div>
        <p>Gestionnaire des Aires Protégées</p>
    </div>

    @if($projet)
        <div class="projet">Projet — document non validé, sans valeur officielle</div>
    @endif

    <h1>AUTORISATION OFFICIELLE D'ACCÈS</h1>

    <div class="content">
        <p>{!! nl2br(e($contenu['introduction'])) !!}</p>
        <p><strong>Monsieur / Madame :</strong> {{ $demande->user->name }} ({{ $demande->user->email }})</p>
        <p>À accéder à l'aire protégée de : <strong>{{ $demande->site->nom }}</strong></p>
        <p>Dans le cadre de l'activité suivante : <strong>{{ $demande->typeDemande->libelle }}</strong></p>
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

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cahier des Charges</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #15201A; }
        .header { text-align: center; border-bottom: 2px solid #9A5F0C; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { font-size: 24px; font-weight: bold; color: #00683A; }
        .content { margin: 20px; line-height: 1.6; }
        .footer { position: absolute; bottom: 30px; width: 100%; text-align: center; font-size: 12px; color: #6E7A72; border-top: 1px solid #D3DAD1; padding-top: 10px; }
        h1 { font-size: 20px; text-transform: uppercase; text-align: center; margin-bottom: 40px; }
        .alert { background-color: #F6EAD3; color: #9A5F0C; padding: 10px; border: 1px solid #E0C38F; margin-top: 20px; }
        .projet { text-align: center; color: #A3271B; border: 2px dashed #A3271B; padding: 8px; margin-bottom: 20px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">FONDATION FANAMBY</div>
        <p>Cahier des Charges - {{ $demande->typeDemande->libelle }}</p>
    </div>

    @if($projet)
        <div class="projet">Projet — document non validé, sans valeur officielle</div>
    @endif

    <h1>CAHIER DES CHARGES À RESPECTER</h1>

    <div class="content">
        <p>Ce document est une annexe obligatoire à l'autorisation d'accès pour <strong>{{ $demande->user->name }}</strong> dans l'aire protégée de <strong>{{ $demande->site->nom }}</strong>.</p>

        <h3>Règles spécifiques liées à l'activité :</h3>
        <ul>
            @forelse(\App\Support\DocumentsOfficiels::lignes($contenu['regles']) as $regle)
                <li>{{ $regle }}</li>
            @empty
                <li>Aucune règle spécifique supplémentaire enregistrée.</li>
            @endforelse
        </ul>

        @if(!empty($demande->resultats_analyse))
        <div class="alert">
            <strong>Avertissements lors de l'analyse :</strong>
            <ul>
                @foreach($demande->resultats_analyse as $alerte)
                    <li>{{ $alerte['message'] }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <h3>Engagement du demandeur</h3>
        <p>{!! nl2br(e($contenu['engagement'])) !!}</p>
        <br><br>
        <p><strong>Signature du demandeur :</strong> ___________________________</p>
    </div>

    <div class="footer">
        Document généré le {{ now()->format('d/m/Y à H:i') }} - Réf. : {{ $demande->reference }}
    </div>
</body>
</html>

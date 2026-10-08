<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cahier des Charges</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #333; }
        .header { text-align: center; border-bottom: 2px solid #e67e22; padding-bottom: 20px; margin-bottom: 30px; }
        .logo { font-size: 24px; font-weight: bold; color: #d35400; }
        .content { margin: 20px; line-height: 1.6; }
        .footer { position: absolute; bottom: 30px; width: 100%; text-align: center; font-size: 12px; color: #7f8c8d; border-top: 1px solid #ccc; padding-top: 10px; }
        h1 { font-size: 20px; text-transform: uppercase; text-align: center; margin-bottom: 40px; }
        .alert { background-color: #fff3cd; color: #856404; padding: 10px; border: 1px solid #ffeeba; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">FONDATION FANAMBY</div>
        <p>Cahier des Charges - {{ $demande->typeDemande->libelle }}</p>
    </div>

    <h1>CAHIER DES CHARGES À RESPECTER</h1>

    <div class="content">
        <p>Ce document est une annexe obligatoire à l'autorisation d'accès pour <strong>{{ $demande->user->name }}</strong> sur le site de <strong>{{ $demande->site->nom }}</strong>.</p>
        
        <h3>Règles spécifiques liées à l'activité :</h3>
        <ul>
            @if(is_array($demande->typeDemande->regles_specifiques))
                @foreach($demande->typeDemande->regles_specifiques as $key => $regle)
                    <li><strong>{{ is_string($key) ? ucfirst($key) . ' :' : '' }}</strong> {{ is_string($regle) ? $regle : json_encode($regle) }}</li>
                @endforeach
            @else
                <li>Aucune règle spécifique supplémentaire enregistrée.</li>
            @endif
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
        <p>Le demandeur certifie avoir pris connaissance des règlements internes de l'aire protégée et s'engage à respecter la faune, la flore et les communautés locales. Tout non-respect de ce cahier des charges entraînera des poursuites.</p>
        <br><br>
        <p><strong>Signature du demandeur :</strong> ___________________________</p>
    </div>

    <div class="footer">
        Document généré automatiquement le {{ now()->format('d/m/Y à H:i') }} - Réf: #{{ $demande->id }}
    </div>
</body>
</html>

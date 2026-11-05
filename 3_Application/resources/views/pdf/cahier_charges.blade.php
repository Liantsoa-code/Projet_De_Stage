<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cahier des Charges</title>
    {{-- Aperçu en direct dans le navigateur : resources/js/Components/Apercu/ApercuCahierCharges.vue, à garder en phase --}}
    <style>
        /* Un mot trop long pour la ligne est coupé au lieu de sortir de la page */
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #15201A; overflow-wrap: anywhere; }
        .logo { text-align: right; }
        .logo img { width: 72px; height: 72px; }
        .content { margin: 20px; line-height: 1.6; }
        .footer { position: absolute; bottom: 30px; width: 100%; text-align: center; font-size: 12px; color: #6E7A72; border-top: 1px solid #D3DAD1; padding-top: 10px; }
        h1 { font-size: 20px; text-transform: uppercase; text-align: center; margin-bottom: 40px; }
        .alert { background-color: #F6EAD3; color: #9A5F0C; padding: 10px; border: 1px solid #E0C38F; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="logo"><img src="{{ public_path('assets/logo-fanamby-192.png') }}" alt="Fanamby"></div>

    <h1>CAHIER DES CHARGES À RESPECTER</h1>

    <div class="content">
        <p>Ce document est une annexe obligatoire à l'autorisation d'accès pour <strong>{{ $demande->user->name }}</strong> dans l'aire protégée de <strong>{{ $demande->site->nom }}</strong>.</p>

        <h3>Règles spécifiques liées au type de projet :</h3>
        <ul>
            @forelse(\App\Support\DocumentsOfficiels::lignes($contenu['regles']) as $regle)
                <li>{{ $regle }}</li>
            @empty
                <li>Aucune règle spécifique supplémentaire enregistrée.</li>
            @endforelse
        </ul>

        @php($alertes = \App\Support\AnalyseDemande::contraintesRelevees($demande->resultats_analyse))
        @if($alertes)
        <div class="alert">
            <strong>Avertissements lors de l'analyse :</strong>
            <ul>
                @foreach($alertes as $alerte)
                    <li>{{ $alerte['message'] }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <h3>Engagement du demandeur</h3>
        <p>{!! nl2br(e($contenu['engagement'])) !!}</p>
        <br><br>
        @if($demande->date_soumission)
            <p><strong>Signature du demandeur :</strong> engagements acceptés en ligne par {{ $demande->user->name }} lors de l'envoi du dossier, le {{ $demande->date_soumission->format('d/m/Y') }}.</p>
        @else
            <p><strong>Signature du demandeur :</strong> ___________________________</p>
        @endif
    </div>

    <div class="footer">
        Document généré le {{ now()->format('d/m/Y à H:i') }} - Réf. : {{ $demande->reference }}
    </div>
</body>
</html>

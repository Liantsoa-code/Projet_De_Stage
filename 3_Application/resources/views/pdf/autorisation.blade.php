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
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">FONDATION FANAMBY</div>
        <p>Gestionnaire des Aires Protégées</p>
    </div>

    <h1>AUTORISATION OFFICIELLE D'ACCÈS</h1>

    <div class="content">
        <p>Nous soussignés, la Fondation FANAMBY, autorisons par la présente :</p>
        <p><strong>Monsieur / Madame :</strong> {{ $demande->user->name }} ({{ $demande->user->email }})</p>
        <p>À accéder à l'aire protégée de : <strong>{{ $demande->site->nom }}</strong></p>
        <p>Dans le cadre de l'activité suivante : <strong>{{ $demande->typeDemande->libelle }}</strong></p>
        <p><strong>Période autorisée :</strong> Du {{ \Carbon\Carbon::parse($demande->date_debut)->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($demande->date_fin)->format('d/m/Y') }}</p>
        <p><strong>Taille de l'équipe :</strong> {{ $demande->equipe_nombre }} personne(s)</p>
        <p><strong>Description du projet :</strong> {{ $demande->description }}</p>
        
        <br><br><br>
        <p><em>Cette autorisation est délivrée sous réserve du strict respect du cahier des charges qui l'accompagne. Toute infraction entraînera une annulation immédiate.</em></p>
        <br>
        <p style="text-align: right;"><strong>Fait pour valoir ce que de droit,</strong><br>La Direction FANAMBY</p>
    </div>

    <div class="footer">
        Document officiel généré automatiquement le {{ now()->format('d/m/Y à H:i') }} par la plateforme de gestion Fanamby. Référence : {{ $demande->reference }}
    </div>
</body>
</html>

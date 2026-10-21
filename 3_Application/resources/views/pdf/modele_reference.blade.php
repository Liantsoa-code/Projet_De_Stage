<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Modèle — {{ $titre }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #15201A; font-size: 13px; }
        .header { text-align: center; border-bottom: 2px solid #00683A; padding-bottom: 14px; margin-bottom: 20px; }
        .logo { font-size: 24px; font-weight: bold; color: #00683A; }
        .header p { margin: 4px 0 0; }
        .modele { text-align: center; color: #1D5C8A; border: 2px dashed #1D5C8A; padding: 6px; margin-bottom: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        .content { margin: 0 20px; line-height: 1.6; }
        .footer { position: absolute; bottom: 30px; width: 100%; text-align: center; font-size: 11px; color: #6E7A72; border-top: 1px solid #D3DAD1; padding-top: 10px; }
        h1 { font-size: 18px; text-transform: uppercase; text-align: center; margin: 24px 0 26px; }
        h3 { font-size: 14px; margin: 18px 0 6px; }
        p { margin: 0 0 12px; }
        ul { margin: 0 0 12px; }
        .champ { color: #1D5C8A; background: #DFEAF3; padding: 0 3px; }
        .droite { text-align: right; }
        .ligne { border-bottom: 1px dotted #6E7A72; height: 22px; margin-bottom: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">FONDATION FANAMBY</div>
        <p>Gestionnaire des Aires Protégées</p>
    </div>

    <div class="modele">Modèle de référence — à compléter avant usage, sans valeur officielle</div>

    {{-- Contenu saisi par un admin, échappé puis mis en forme par ModelesReference::html --}}
    <div class="content">
        {!! $html !!}
    </div>

    <div class="footer">
        Modèle « {{ $titre }} » — plateforme de gestion Fanamby. Les champs entre crochets sont à compléter.
    </div>
</body>
</html>

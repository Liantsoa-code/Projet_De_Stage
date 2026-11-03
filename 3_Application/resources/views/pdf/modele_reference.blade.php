<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Modèle — {{ $titre }}</title>
    {{-- Aperçu en direct dans le navigateur : resources/js/Components/Apercu/ApercuModele.vue, à garder en phase --}}
    <style>
        /* Un mot trop long pour la ligne est coupé au lieu de sortir de la page */
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #15201A; font-size: 13px; overflow-wrap: anywhere; }
        .logo { text-align: right; }
        .logo img { width: 72px; height: 72px; }
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
    <div class="logo"><img src="{{ public_path('assets/logo-fanamby-192.png') }}" alt="Fanamby"></div>

    {{-- Contenu saisi par un admin, échappé puis mis en forme par ModelesReference::html --}}
    <div class="content">
        {!! $html !!}
    </div>

    <div class="footer">
        Modèle « {{ $titre }} » — plateforme de gestion Fanamby. Les champs entre crochets sont à compléter.
    </div>
</body>
</html>

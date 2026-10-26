<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Formulaire de demande d'autorisation</title>
    <style>
        @page { margin: 60px 70px 70px; }
        body { font-family: 'Times New Roman', 'Times', serif; font-size: 12.5px; color: #15201A; line-height: 1.45; }
        h1 { font-size: 16px; text-align: center; margin: 0 0 30px; line-height: 1.35; }
        h2 { font-size: 13.5px; margin: 26px 0 10px; }
        .champs { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .champs th { width: 1%; white-space: nowrap; padding: 5px 6px 2px 0; font-weight: normal; text-align: left; vertical-align: bottom; }
        .champs td { padding: 5px 0 2px; border-bottom: 1px dotted #6E7A72; vertical-align: bottom; }
        .titre { margin: 12px 0 6px; }
        .texte { margin: 0 0 6px; padding: 4px 0 4px; border-bottom: 1px dotted #6E7A72; min-height: 16px; }
        .cases { width: 100%; border-collapse: collapse; }
        .cases td { width: 33%; padding: 2px 8px 2px 0; vertical-align: top; }
        .case { display: inline-block; width: 10px; height: 10px; border: 1px solid #15201A; margin-right: 6px; text-align: center; font: bold 9px/10px 'Helvetica', sans-serif; }
        .precision { display: block; margin-left: 18px; font-style: italic; }
        ul { margin: 4px 0 10px; padding-left: 18px; }
        .page2 { page-break-before: always; }
        .signature { margin-top: 10px; color: #3D4A42; font-style: italic; }
        .footer { position: fixed; bottom: -40px; left: 0; right: 0; text-align: center; font: 10px 'Helvetica', sans-serif; color: #6E7A72; border-top: 1px solid #D3DAD1; padding-top: 6px; }
    </style>
</head>
<body>
    @php
        $date = fn ($valeur) => $valeur ? \Carbon\Carbon::parse($valeur)->format('d/m/Y') : '';
        $coche = fn (bool $oui) => $oui ? 'X' : '&nbsp;';
        $favorable = $decision?->nouveauStatut?->code === 'validee';
        $defavorable = $decision?->nouveauStatut?->code === 'rejetee';
    @endphp

    <div class="footer">
        Formulaire rempli sur la plateforme de gestion Fanamby · Référence {{ $demande->reference }} · Édité le {{ now()->format('d/m/Y à H:i') }}
    </div>

    <h1>Formulaire de Demande d'Autorisation pour Projet Photographique /<br>Vidéographique dans les aires protégées gérées par Fanamby</h1>

    <h2>I. Informations sur le Demandeur</h2>
    <table class="champs">
        <tr><th>Nom et Prénoms :</th><td>{{ $demande->user->name }}</td></tr>
        <tr><th>Institution / Société de production / Organisation :</th><td>{{ $demande->user->organisation }}</td></tr>
        <tr><th>Fonction :</th><td>{{ $demande->user->fonction }}</td></tr>
        <tr><th>Adresse :</th><td>{{ $demande->user->adresse }}</td></tr>
        <tr><th>Téléphone :</th><td>{{ $demande->user->telephone }}</td></tr>
        <tr><th>Email :</th><td>{{ $demande->user->email }}</td></tr>
    </table>

    <h2>II. Informations sur le Projet</h2>
    <table class="champs">
        <tr><th><strong>Titre du projet :</strong></th><td>{{ $demande->titre }}</td></tr>
    </table>

    <p class="titre"><strong>Type de projet :</strong></p>
    <table class="cases">
        @foreach ($types->chunk(3) as $ligne)
            <tr>
                @foreach ($ligne as $type)
                    <td>
                        <span class="case">{!! $coche($type->id == $demande->type_demande_id) !!}</span>{{ $type->libelle }}@if ($type->precision_requise) (précisez) :@endif
                        @if ($type->precision_requise && $type->id == $demande->type_demande_id && $demande->type_precision)
                            <span class="precision">{{ $demande->type_precision }}</span>
                        @endif
                    </td>
                @endforeach
                @for ($i = $ligne->count(); $i < 3; $i++)<td></td>@endfor
            </tr>
        @endforeach
    </table>

    <p class="titre"><strong>Résumé du synopsis :</strong></p>
    <div class="texte">{!! nl2br(e($demande->description)) !!}</div>

    <p class="titre"><strong>Objectif(s) principal(aux) du projet :</strong></p>
    <div class="texte">{!! nl2br(e($demande->objectifs)) !!}</div>

    <p class="titre"><strong>Aire(s) Protégée(s) concernée(s) :</strong></p>
    <table class="cases">
        @foreach ($sites->chunk(3) as $ligne)
            <tr>
                @foreach ($ligne as $site)
                    <td><span class="case">{!! $coche($site->id == $demande->site_id) !!}</span>{{ $site->nom }}</td>
                @endforeach
                @for ($i = $ligne->count(); $i < 3; $i++)<td></td>@endfor
            </tr>
        @endforeach
    </table>

    <p class="titre"><strong>Période prévue :</strong></p>
    <table class="champs" style="width: 45%">
        <tr><th>- Début :</th><td>{{ $date($demande->date_debut) }}</td></tr>
        <tr><th>- Fin :</th><td>{{ $date($demande->date_fin) }}</td></tr>
    </table>

    <div class="page2">
        <h2>V. Engagement du Demandeur</h2>
        <table class="champs">
            <tr><th>Je soussigné(e)</th><td>{{ $demande->user->name }}</td></tr>
            <tr><th>CIN / Passeport N°</th><td>{{ $demande->user->piece_identite }}</td></tr>
        </table>
        <p style="margin: 8px 0 0">m'engage à :</p>
        <ul>
            @foreach ($engagements as $engagement)
                <li>{{ $engagement }}{{ $loop->last ? '.' : ' ;' }}</li>
            @endforeach
        </ul>
        <table class="champs" style="width: 45%">
            <tr><th>Date :</th><td>{{ $date($demande->date_soumission) }}</td></tr>
        </table>
        <p class="titre">Signature du demandeur :</p>
        @if ($demande->date_soumission)
            <p class="signature">Engagements acceptés en ligne par {{ $demande->user->name }} lors de l'envoi du dossier, le {{ $demande->date_soumission->format('d/m/Y à H:i') }}.</p>
        @endif

        <h2>VI. Décision de Fanamby (à remplir par le gestionnaire)</h2>
        <p style="margin: 0 0 4px">Avis de Fanamby :</p>
        <p style="margin: 0"><span class="case">{!! $coche($favorable) !!}</span>Avis favorable</p>
        <p style="margin: 2px 0 10px"><span class="case">{!! $coche($defavorable) !!}</span>Avis défavorable</p>

        <p class="titre">Observations :</p>
        <div class="texte">{!! $decision ? nl2br(e($decision->commentaire)) : '&nbsp;' !!}</div>

        <table class="champs">
            <tr><th>Date :</th><td>{{ $decision ? $decision->date_changement->format('d/m/Y') : '' }}</td></tr>
            <tr>
                <th>Nom et fonction du responsable Fanamby :</th>
                <td>
                    @if ($decision)
                        {{ $decision->user?->name ?? 'Compte supprimé' }}@if ($decision->user?->fonction), {{ $decision->user->fonction }}@endif
                    @endif
                </td>
            </tr>
            <tr>
                <th>Signature et cachet :</th>
                <td>
                    @if ($favorable)
                        Validé et signé électroniquement le {{ $decision->date_changement->format('d/m/Y à H:i') }}
                    @elseif ($defavorable)
                        Décision enregistrée sur la plateforme le {{ $decision->date_changement->format('d/m/Y à H:i') }}
                    @endif
                </td>
            </tr>
        </table>
    </div>
</body>
</html>

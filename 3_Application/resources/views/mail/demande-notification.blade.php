<x-mail::message>
Bonjour {{ $destinataire->name }},

{{ $contenu }}

<x-mail::button :url="$lien">
Ouvrir le dossier
</x-mail::button>

Cette notification est aussi disponible dans votre espace, sous la cloche.

{{ config('app.name') }}
</x-mail::message>

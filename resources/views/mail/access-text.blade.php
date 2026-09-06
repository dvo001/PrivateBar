PrivateBar

@if($kind === 'invite')
Du bist zur privaten Hausbar eingeladen. Lege über diesen Link deinen Namen und ein Passwort fest:
@else
Bestätige deine E-Mail-Adresse über diesen Link. Melde dich bei Bedarf mit deinem Konto an:
@endif

{!! $accessUrl !!}

Der Link gilt 30 Minuten.
@if($kind === 'invite')
Die Einladung ist einmal verwendbar. Danach erhältst du eine separate E-Mail zur Bestätigung deiner E-Mail-Adresse.
@endif

Falls du diese E-Mail nicht erwartet hast, kannst du sie ignorieren.

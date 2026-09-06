<!doctype html>
<html lang="de-CH">
<head><meta charset="utf-8"><title>PrivateBar</title></head>
<body style="background:#171d1c;color:#f4e8c1;font-family:Arial,sans-serif;padding:24px;line-height:1.6">
    <h1>PrivateBar</h1>
    @if($kind === 'invite')
        <h2>Du bist eingeladen</h2>
        <p>Ein Mitglied lädt dich zur privaten Hausbar ein. Lege über den folgenden Link deinen Namen und ein Passwort fest.</p>
        <p>Danach erhältst du eine separate E-Mail zur Bestätigung deiner E-Mail-Adresse.</p>
    @else
        <h2>Bestätige deine E-Mail-Adresse</h2>
        <p>Öffne den folgenden Link, um deine E-Mail-Adresse für PrivateBar zu bestätigen. Melde dich bei Bedarf mit deinem Konto an.</p>
    @endif
    <p><a href="{{ $accessUrl }}" style="display:inline-block;background:#f0cf63;color:#171d1c;padding:12px 20px;border-radius:8px">{{ $kind === 'invite' ? 'Einladung annehmen' : 'E-Mail-Adresse bestätigen' }}</a></p>
    <p>Der Link gilt 30 Minuten. {{ $kind === 'invite' ? 'Die Einladung ist einmal verwendbar.' : '' }}</p>
    <p>Falls die Schaltfläche nicht funktioniert, kopiere diese Adresse in deinen Browser:<br>{{ $accessUrl }}</p>
    <p>Falls du diese E-Mail nicht erwartet hast, kannst du sie ignorieren.</p>
</body>
</html>

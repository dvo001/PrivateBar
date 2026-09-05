@extends('layout')
@section('title','Lokale Einstellungen')
@section('content')<section class="panel auth-card"><h1>Lokale Einstellungen</h1><p>Bitte bestätige die Kiosk-PIN erneut.</p><form method="post" action="/einstellungen/lokal/oeffnen" class="form-stack">@csrf<label>Kiosk-PIN<input name="pin" type="password" inputmode="numeric" pattern="[0-9]{6}" required maxlength="6" autocomplete="off"></label><button class="primary">Einstellungen öffnen</button></form></section>@endsection

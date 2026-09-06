@extends('layout')
@section('title', 'E-Mail-Adresse bestätigen')
@section('content')
<section class="panel auth-card">
    <h1>E-Mail-Adresse bestätigen</h1>
    <p>Bestätige <strong>{{ auth()->user()->email }}</strong>, um deine Bar online zu öffnen.</p>
    <p>Öffne dazu den Link in der Bestätigungs-E-Mail. Er gilt 30 Minuten. Falls du ihn auf einem anderen Gerät öffnest, melde dich dort mit diesem Konto an.</p>
    <form method="post" action="{{ route('verification.send') }}" class="form-stack">
        @csrf
        <button class="primary">Bestätigungs-E-Mail senden</button>
    </form>
    <p class="muted">Prüfe auch deinen Spamordner. Du kannst einmal pro Minute eine E-Mail anfordern.</p>
    <form method="post" action="/abmelden">@csrf<button>Abmelden</button></form>
</section>
@endsection

@extends('layout')
@section('title','Start')
@section('content')
<header class="page-heading"><div><p class="eyebrow">WILLKOMMEN ZU HAUSE</p><h1>Ein guter Drink.<br>Ein schöner Moment.</h1><p class="muted">Deine Bar. Deine Zutaten. Dein nächster Lieblingsdrink.</p></div><span class="sun-mark" aria-hidden="true">☀</span></header>
@if($empty)<section class="empty-bar"><div><p class="eyebrow">HIER FÄNGT ES AN</p><h2>Bar bitte füllen</h2><p>Füge deine erste Flasche hinzu und entdecke, was in deiner Bar steckt.</p></div><div class="actions"><a class="button primary" href="/scannen">Flasche scannen <span aria-hidden="true">↗</span></a><a class="button" href="/meine-bar/neu">Manuell hinzufügen</a></div></section>@endif
<section class="hero">
    <div class="hero-copy"><p class="eyebrow">{{ $daily ? 'DEINE TAGESEMPFEHLUNG' : 'EIN WENIG RIVIERA FÜR ZU HAUSE' }}</p><h2>{{ $daily?->name ?? 'Was darf es sein?' }}</h2><p>{{ $daily ? 'Alles dafür ist schon da. Ein Glas, ein paar Handgriffe – und der Abend kann beginnen.' : 'Fülle deine Bar mit deinen Lieblingszutaten. Wir finden die passenden Rezepte dazu.' }}</p>
    @if($daily)<span class="badge rank-0">✦ Machbar</span><a class="button light" href="/rezepte/{{ $daily->id }}">Rezept entdecken <span aria-hidden="true">↗</span></a>@else<a class="button light" href="/entdecken">Rezepte entdecken <span aria-hidden="true">↗</span></a>@endif</div>
    <img class="hero-art" src="/assets/riviera.svg" alt="Illustration einer mediterranen Bar mit Meerblick, Flaschen und Cocktailgläsern" width="720" height="640">
</section>
<div class="quick-actions"><a href="/scannen"><span aria-hidden="true">▥</span><div><strong>Flasche scannen</strong><small>Ein neuer Gast in deiner Bar</small></div><span aria-hidden="true">↗</span></a><a href="/einkaufsliste"><span aria-hidden="true">≡</span><div><strong>Einkaufsliste</strong><small>Für deinen nächsten Drink</small></div><span aria-hidden="true">↗</span></a><form method="post" action="/zufall">@csrf<button><span aria-hidden="true">✦</span><div><strong>Ich weiss nicht</strong><small>Lass dich überraschen</small></div><span aria-hidden="true">↗</span></button></form></div>
<div class="section-heading"><h2>Bereit zum Mixen</h2><a href="/machbar">Alle machbaren Drinks →</a></div>
<div class="recipe-grid">@forelse($recipes as $recipe)@include('recipes.card')@empty<p class="muted">Noch kein Drink vollständig machbar. <a href="/entdecken?rank=2">Fast machbare Rezepte ansehen</a></p>@endforelse</div>
@endsection

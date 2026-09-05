@extends('layout')
@section('title','Cocktailzutaten & Synonyme')
@section('content')
<header class="page-heading"><div><p class="eyebrow">DAS COCKTAILGLOSSAR</p><h1>Cocktailzutaten & Synonyme</h1><p class="muted">Korrigiere Namen und Kategorien. Frühere Namen bleiben als Synonym bekannt.</p></div></header>
@foreach($ingredients as $ingredient)
<details class="panel"><summary>{{ $ingredient->name }}</summary><form method="post" action="/einstellungen/zutaten/{{ $ingredient->id }}" class="form-stack">@csrf
<label>Deutscher Name<input name="name" value="{{ $ingredient->name }}" required maxlength="255"></label>
<label>Kategorie<select name="category_id">@foreach($categories as $category)<option value="{{ $category->id }}" @selected($category->id===$ingredient->category_id)>{{ $category->name }}</option>@endforeach</select></label>
<label>Weitere Synonyme, mit Komma getrennt<input name="synonyms" maxlength="1000" placeholder="Zum Beispiel lemon juice, Zitronensaft"></label><button>Speichern</button></form></details>
@endforeach
<nav class="pagination" aria-label="Zutatenseiten">@if($ingredients->previousPageUrl())<a class="button" href="{{ $ingredients->previousPageUrl() }}">Zurück</a>@endif<span>{{ $ingredients->currentPage() }} / {{ $ingredients->lastPage() }}</span>@if($ingredients->nextPageUrl())<a class="button" href="{{ $ingredients->nextPageUrl() }}">Weiter</a>@endif</nav>
@endsection

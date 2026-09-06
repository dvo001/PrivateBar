@extends('layout')
@section('title','Cocktailzutaten & Synonyme')
@section('content')
<header class="page-heading"><div><p class="eyebrow">DAS COCKTAILGLOSSAR</p><h1>Cocktailzutaten & Synonyme</h1><p class="muted">Korrigiere Namen und Kategorien. Frühere Namen bleiben als Synonym bekannt.</p></div></header>
<form method="get" class="panel form-grid">
<label>Zutat oder Synonym suchen<input name="q" value="{{ request('q') }}" maxlength="255"></label>
<label>Bereich<select name="category"><option value="">Alle Bereiche</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(request('category')===$category->id)>{{ $category->name }}</option>@endforeach</select></label><button>Filtern</button>
</form>
<details class="panel"><summary>Neue Cocktailzutat ergänzen</summary><form method="post" action="/einstellungen/zutaten" class="form-stack">@csrf
<label>Deutscher Name<input name="name" value="{{ old('name') }}" required maxlength="255"></label>
<label>Bereich<select name="category_id" required>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id','other')===$category->id)>{{ $category->name }}</option>@endforeach</select></label>
<label>Synonyme, mit Komma getrennt<input name="synonyms" value="{{ old('synonyms') }}" maxlength="1000"></label>
<button>Neue Zutat speichern</button></form></details>
@if($ingredients->isEmpty())<p class="notice">Keine passende Cocktailzutat gefunden.</p>@endif
@foreach($ingredients as $ingredient)
<details class="panel"><summary>{{ $ingredient->name }}</summary><form method="post" action="/einstellungen/zutaten/{{ $ingredient->id }}" class="form-stack">@csrf
<label>Deutscher Name<input name="name" value="{{ $ingredient->name }}" required maxlength="255"></label>
<label>Kategorie<select name="category_id">@foreach($categories as $category)<option value="{{ $category->id }}" @selected($category->id===$ingredient->category_id)>{{ $category->name }}</option>@endforeach</select></label>
<label>Synonyme, mit Komma getrennt<textarea name="synonyms" rows="3" maxlength="1000">{{ ($synonyms[$ingredient->id] ?? collect())->pluck('name')->implode(', ') }}</textarea></label><p class="muted">Ergänze oder entferne einzelne Synonyme. Beim Umbenennen bleibt der bisherige Zutatenname als Synonym erhalten.</p><button>Speichern</button></form></details>
@endforeach
<nav class="pagination" aria-label="Zutatenseiten">@if($ingredients->previousPageUrl())<a class="button" href="{{ $ingredients->previousPageUrl() }}">Zurück</a>@endif<span>{{ $ingredients->currentPage() }} / {{ $ingredients->lastPage() }}</span>@if($ingredients->nextPageUrl())<a class="button" href="{{ $ingredients->nextPageUrl() }}">Weiter</a>@endif</nav>
@endsection

<div data-ingredient-picker class="form-stack">
    <div class="form-grid" data-ingredient-filters hidden>
        <label>Bereich<select data-ingredient-category><option value="">Alle Bereiche</option>
            @foreach($ingredients->groupBy('category_name') as $category => $group)<option value="{{ $category }}">{{ $category }}</option>@endforeach
        </select></label>
        <label>Zutat suchen<input type="search" data-ingredient-search placeholder="Zum Beispiel Amaretto oder Cranberry"></label>
    </div>
    <label>Allgemeine Cocktailzutat<select name="ingredient_id" required data-ingredient-select>
        <option value="">Zuordnung wählen</option>
        @foreach($ingredients->groupBy('category_name') as $category => $group)
            <optgroup label="{{ $category }}">
                @foreach($group as $ingredient)<option value="{{ $ingredient->id }}" @selected(old('ingredient_id',$product['ingredient_id'] ?? '')===$ingredient->id)>{{ $ingredient->name }}</option>@endforeach
            </optgroup>
        @endforeach
    </select></label>
    <p class="muted" data-ingredient-results aria-live="polite"></p>
    <p class="muted">Noch unsicher? Wähle im passenden Bereich «noch nicht zugeordnet». Diese Flasche erfüllt erst nach einer genaueren Zuordnung eine bestimmte Rezeptzutat.</p>
    <a href="/einstellungen/zutaten">Fehlende Cocktailzutat ergänzen</a>
</div>

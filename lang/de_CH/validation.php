<?php

return [
    'required_if' => 'Bitte :attribute für die Pflichtzutat angeben.',
    'accepted' => 'Bitte :attribute bestätigen.', 'required' => 'Das Feld :attribute ist erforderlich.',
    'present' => 'Das Feld :attribute muss vorhanden sein.', 'string' => ':attribute muss Text sein.',
    'email' => 'Bitte eine gültige E-Mail-Adresse eingeben.', 'uuid' => ':attribute enthält keine gültige Kennung.',
    'exists' => 'Der ausgewählte Eintrag für :attribute wurde nicht gefunden.', 'unique' => ':attribute wird bereits verwendet.',
    'boolean' => ':attribute muss ja oder nein sein.', 'integer' => ':attribute muss eine ganze Zahl sein.',
    'numeric' => ':attribute muss eine Zahl sein.', 'array' => ':attribute muss eine Liste sein.',
    'in' => 'Die Auswahl für :attribute ist ungültig.', 'regex' => 'Das Format von :attribute ist ungültig.',
    'confirmed' => 'Die Passwortwiederholung stimmt nicht überein.', 'different' => ':attribute und :other müssen unterschiedlich sein.',
    'date' => ':attribute muss ein gültiges Datum sein.', 'date_format' => ':attribute muss dem Format :format entsprechen.',
    'url' => ':attribute muss eine gültige HTTPS-Adresse sein.', 'file' => ':attribute muss eine Datei sein.',
    'mimes' => 'Bitte JPEG, PNG oder WebP verwenden. HEIC wird nicht unterstützt.',
    'min' => ['string' => ':attribute muss mindestens :min Zeichen lang sein.', 'numeric' => ':attribute muss mindestens :min sein.', 'array' => ':attribute muss mindestens :min Eintrag enthalten.', 'file' => ':attribute muss mindestens :min KB gross sein.'],
    'max' => ['string' => ':attribute darf höchstens :max Zeichen lang sein.', 'numeric' => ':attribute darf höchstens :max sein.', 'array' => ':attribute darf höchstens :max Einträge enthalten.', 'file' => ':attribute darf höchstens :max KB gross sein.'],
    'between' => ['numeric' => ':attribute muss zwischen :min und :max liegen.', 'string' => ':attribute muss zwischen :min und :max Zeichen lang sein.'],
    'size' => ['string' => ':attribute muss genau :size Zeichen lang sein.'],
    'attributes' => ['email' => 'E-Mail-Adresse', 'password' => 'Passwort', 'pin' => 'Kiosk-PIN', 'name' => 'Name', 'brand' => 'Marke', 'barcode' => 'Barcode', 'ingredient_id' => 'Cocktailzutat', 'abv' => 'Alkoholgehalt', 'confirmed' => 'Bestätigung', 'ingredients' => 'Zutaten', 'instructions' => 'Zubereitung', 'photo' => 'Foto', 'stars' => 'Sterne'],
];

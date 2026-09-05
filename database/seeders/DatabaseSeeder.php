<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class DatabaseSeeder extends Seeder
{
    public static function id(string $name): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'privatebar:seed:'.$name)->toString();
    }

    public function run(): void
    {
        foreach (['gin' => ['Gin', 40], 'rum' => ['Rum', 40], 'vodka' => ['Wodka', 40], 'tequila' => ['Tequila', 40], 'whisky' => ['Whisky', 40], 'liqueur' => ['Liköre', 25], 'wine' => ['Wein und Wermut', 16], 'bitters' => ['Bitter', 40], 'juice' => ['Säfte', 0], 'syrup' => ['Sirupe', 0], 'soft' => ['Softdrinks', 0], 'garnish' => ['Garnituren', 0], 'basic' => ['Grundzutaten', 0], 'other' => ['Weitere Zutaten', null]] as $id => $v) {
            DB::table('ingredient_categories')->insertOrIgnore(['id' => $id, 'name' => $v[0], 'typical_abv' => $v[1]]);
        }
        $ingredients = [
            'Gin' => ['gin', false, ['gin']], 'Weisser Rum' => ['rum', false, ['light rum', 'white rum']], 'Dunkler Rum' => ['rum', false, ['dark rum']], 'Wodka' => ['vodka', false, ['vodka']], 'Tequila' => ['tequila', false, ['tequila']], 'Whisky' => ['whisky', false, ['whiskey', 'bourbon', 'scotch']],
            'Orangenlikör' => ['liqueur', false, ['triple sec', 'cointreau']], 'Campari' => ['liqueur', false, ['campari']], 'Roter Wermut' => ['wine', false, ['sweet vermouth', 'red vermouth']], 'Angostura' => ['bitters', false, ['angostura bitters']],
            'Zitronensaft' => ['juice', false, ['lemon juice']], 'Limettensaft' => ['juice', false, ['lime juice']], 'Orangensaft' => ['juice', false, ['orange juice']], 'Ananassaft' => ['juice', false, ['pineapple juice']],
            'Zuckersirup' => ['syrup', false, ['sugar syrup', 'simple syrup']], 'Grenadine' => ['syrup', false, ['grenadine']], 'Tonic Water' => ['soft', false, ['tonic water']], 'Sodawasser' => ['soft', false, ['soda water', 'club soda', 'carbonated water']], 'Ginger Ale' => ['soft', false, ['ginger ale']],
            'Minze' => ['garnish', false, ['mint']], 'Limette' => ['garnish', false, ['lime']], 'Zitrone' => ['garnish', false, ['lemon']], 'Orange' => ['garnish', false, ['orange']], 'Wasser' => ['basic', true, ['water']], 'Eis' => ['basic', true, ['ice', 'ice cubes']], 'Zucker' => ['basic', true, ['sugar']], 'Salz' => ['basic', true, ['salt']],
        ];
        foreach ($ingredients as $name => $data) {
            $id = self::id($name);
            DB::table('ingredients')->insertOrIgnore(['id' => $id, 'name' => $name, 'category_id' => $data[0], 'automatic' => $data[1], 'created_at' => now(), 'updated_at' => now()]);
            foreach (array_unique(array_merge([mb_strtolower($name)], $data[2])) as $synonym) {
                DB::table('ingredient_synonyms')->insertOrIgnore(['name' => $synonym, 'ingredient_id' => $id]);
            }
        }
        foreach ([['Zitronensaft', 'Limettensaft'], ['Limettensaft', 'Zitronensaft'], ['Weisser Rum', 'Dunkler Rum']] as [$required,$replacement]) {
            DB::table('ingredient_substitutions')->insertOrIgnore(['id' => self::id($required.'>'.$replacement), 'required_id' => self::id($required), 'replacement_id' => self::id($replacement), 'enabled' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        // Eigene, kurze Startrezepte; keine fremden Texte oder Bilder.
        $recipes = [
            ['Gin Tonic', 'Gin', 'frisch', 'Im Glas bauen', true, 'Gin mit Eis in ein Longdrinkglas geben. Mit Tonic Water auffüllen und vorsichtig umrühren. Nach Wunsch mit Zitrone garnieren.', ['Gin' => 4, 'Tonic Water' => 12]],
            ['Daiquiri', 'Rum', 'sauer', 'Schütteln', true, 'Rum, Limettensaft und Zuckersirup mit Eis kräftig schütteln. In ein gekühltes Cocktailglas abseihen.', ['Weisser Rum' => 6, 'Limettensaft' => 3, 'Zuckersirup' => 2]],
            ['Negroni', 'Gin', 'bitter', 'Rühren', true, 'Gin, Campari und roten Wermut mit Eis verrühren. In ein Glas auf frisches Eis abseihen.', ['Gin' => 3, 'Campari' => 3, 'Roter Wermut' => 3]],
            ['Margarita', 'Tequila', 'sauer', 'Schütteln', true, 'Tequila, Orangenlikör und Limettensaft mit Eis schütteln. In ein gekühltes Glas abseihen.', ['Tequila' => 5, 'Orangenlikör' => 2, 'Limettensaft' => 2]],
            ['Cuba Ginger', 'Rum', 'würzig', 'Im Glas bauen', true, 'Dunklen Rum und Limettensaft auf Eis geben. Mit Ginger Ale auffüllen und kurz umrühren.', ['Dunkler Rum' => 4, 'Limettensaft' => 2, 'Ginger Ale' => 12]],
            ['Zitronenlimonade', null, 'frisch', 'Im Glas bauen', false, 'Zitronensaft und Zuckersirup im Glas verrühren. Eis dazugeben, mit Sodawasser auffüllen und kurz umrühren.', ['Zitronensaft' => 3, 'Zuckersirup' => 2, 'Sodawasser' => 15]],
            ['Riviera Sunrise', null, 'fruchtig', 'Im Glas bauen', false, 'Orangen- und Ananassaft auf Eis geben. Grenadine langsam dazugiessen und vor dem Trinken leicht umrühren.', ['Orangensaft' => 10, 'Ananassaft' => 6, 'Grenadine' => 1]],
            ['Whisky Sour', 'Whisky', 'sauer', 'Schütteln', true, 'Whisky, Zitronensaft und Zuckersirup mit Eis schütteln. In ein Glas auf frisches Eis abseihen.', ['Whisky' => 5, 'Zitronensaft' => 3, 'Zuckersirup' => 2]],
        ];
        foreach ($recipes as [$name,$base,$taste,$method,$alcoholic,$instructions,$lines]) {
            $id = self::id('recipe:'.$name);
            if (DB::table('recipes')->where('id', $id)->exists()) {
                continue;
            }
            DB::table('recipes')->insert(['id' => $id, 'name' => $name, 'base_spirit' => $base, 'taste' => $taste, 'method' => $method, 'alcoholic' => $alcoholic, 'instructions' => $instructions, 'household' => true, 'glass' => 'Cocktailglas', 'created_at' => now(), 'updated_at' => now()]);
            foreach ($lines as $ingredient => $amount) {
                DB::table('recipe_ingredients')->insert(['recipe_id' => $id, 'ingredient_id' => self::id($ingredient), 'amount' => $amount, 'unit' => 'cl', 'role' => 'required']);
            }
            DB::table('recipe_ingredients')->insert(['recipe_id' => $id, 'ingredient_id' => self::id('Eis'), 'amount' => null, 'unit' => null, 'role' => 'optional', 'position' => 20]);
        }
    }
}

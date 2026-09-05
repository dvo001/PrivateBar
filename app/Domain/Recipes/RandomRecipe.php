<?php

namespace App\Domain\Recipes;

use App\Domain\Settings\Settings;
use Illuminate\Support\Facades\DB;

final class RandomRecipe
{
    public function __construct(private Catalog $catalog, private Settings $settings) {}

    public function choose(): ?string
    {
        $ids = array_map(fn ($r) => $r->id, $this->catalog->matching(['rank' => 0]));
        if (! $ids) {
            return null;
        }

        return DB::transaction(function () use ($ids) {
            $this->settings->assertRunning();
            $recent = DB::table('random_history')->orderByDesc('id')->limit(6)->pluck('recipe_id')->all();
            $candidates = array_values(array_diff($ids, $recent));
            if (! $candidates) {
                $candidates = array_values(array_diff($ids, array_slice($recent, 0, max(0, count($ids) - 1))));
            }
            $id = $candidates[random_int(0, count($candidates) - 1)];
            DB::table('random_history')->insert(['recipe_id' => $id, 'created_at' => now()]);
            $keep = DB::table('random_history')->orderByDesc('id')->limit(6)->pluck('id');
            DB::table('random_history')->whereNotIn('id', $keep)->delete();

            return $id;
        });
    }

    public function daily(): ?string
    {
        $day = now()->timezone('Europe/Zurich')->format('Y-m-d');
        $ids = array_map(fn ($r) => $r->id, $this->catalog->matching(['rank' => 0]));
        if (! $ids) {
            return null;
        }
        $saved = $this->settings->get('daily_recommendation', []);
        if (($saved['day'] ?? null) === $day && in_array($saved['id'] ?? null, $ids, true)) {
            return $saved['id'];
        }
        $id = $ids[random_int(0, count($ids) - 1)];
        $this->settings->set('daily_recommendation', ['day' => $day, 'id' => $id]);

        return $id;
    }
}

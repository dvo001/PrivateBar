<?php

namespace App\Domain\Sync;

use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class Journal
{
    public function actor(): string
    {
        $user = auth()->guard()->user();

        return $user instanceof User ? 'user:'.$user->uuid : (config('privatebar.mode') === 'pi' ? 'kiosk:' : 'system:').config('privatebar.instance_id');
    }

    public function record(string $entity, string $id, array $payload, bool $deleted = false, ?string $actor = null): string
    {
        app(Settings::class)->assertRunning();
        if ($entity === 'setting' && in_array($id, Projector::SHARED_KEYS, true)) {
            $payload['key'] = $id;
            $id = Settings::sharedId($id);
        }
        $event = (string) Str::uuid();
        $cloud = config('privatebar.mode') === 'cloud';
        if ($cloud) {
            DB::table('sync_cursors')->insertOrIgnore(['peer' => 'server', 'cursor' => 0, 'epoch' => (string) Str::uuid()]);
            DB::table('sync_cursors')->where('peer', 'server')->lockForUpdate()->first();
        }
        $sequence = DB::table('sync_events')->insertGetId([
            'id' => $event, 'entity' => $entity, 'entity_id' => $id, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'deleted' => $deleted, 'version' => 0, 'actor' => $actor ?? $this->actor(), 'origin' => config('privatebar.instance_id'),
            'confirmed_at' => $cloud ? now() : null, 'created_at' => now(),
        ], 'sequence');
        if ($cloud) {
            DB::table('sync_events')->where('id', $event)->update(['version' => $sequence]);
        }
        DB::table('audit_entries')->insert(['actor' => $actor ?? $this->actor(), 'action' => ($deleted ? 'delete:' : 'write:').$entity, 'entity_id' => $id, 'details' => json_encode($payload, JSON_THROW_ON_ERROR), 'created_at' => now()]);
        if ($deleted) {
            DB::table('sync_tombstones')->updateOrInsert(['entity' => $entity, 'entity_id' => $id], ['version' => $sequence, 'created_at' => now()]);
        }

        return $event;
    }
}

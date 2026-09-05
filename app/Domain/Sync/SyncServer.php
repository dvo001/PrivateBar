<?php

namespace App\Domain\Sync;

use App\Domain\Settings\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class SyncServer
{
    public function __construct(private Projector $projector, private Settings $settings) {}

    public function exchange(array $request, object $device): array
    {
        $this->settings->assertRunning();
        abort_unless(config('privatebar.mode') === 'cloud', 404);
        Validator::make($request, ['schema_version' => 'required|integer|in:1', 'cursor' => 'required|integer|min:0', 'epoch' => 'nullable|uuid', 'events' => 'present|array|max:50', 'events.*.id' => 'required|uuid', 'events.*.entity' => 'required|string|max:40', 'events.*.entity_id' => 'required|string|max:100', 'events.*.payload' => 'present|array', 'events.*.deleted' => 'required|boolean'])->validate();

        return DB::transaction(function () use ($request, $device) {
            $this->settings->assertRunning();
            DB::table('sync_cursors')->insertOrIgnore(['peer' => 'server', 'cursor' => 0, 'epoch' => (string) Str::uuid()]);
            $server = DB::table('sync_cursors')->where('peer', 'server')->lockForUpdate()->first();
            if (($request['epoch'] ?? null) && $request['epoch'] !== $server->epoch) {
                abort(409, 'Die Serverdatenbank wurde wiederhergestellt. Ein kontrollierter Neuabgleich ist erforderlich.');
            }
            $latest = (int) DB::table('sync_events')->max('sequence');
            if ($request['cursor'] > $latest) {
                abort(409, 'Der Cursor liegt vor dem Serverstand. Bitte Wiederherstellung prüfen.');
            }
            $accepted = [];
            foreach ($request['events'] as $event) {
                $existing = DB::table('sync_events')->where('id', $event['id'])->first();
                if ($existing) {
                    abort_unless($existing->origin === 'device:'.$device->id, 409, 'Der Idempotenzschlüssel gehört zu einem anderen Ursprung.');
                    $accepted[] = $event['id'];

                    continue;
                }
                $p = $this->projector->validate($event['entity'], $event['entity_id'], $event['payload'], true);
                $this->projector->apply($event['entity'], $event['entity_id'], $p, $event['deleted']);
                $sequence = DB::table('sync_events')->insertGetId(['id' => $event['id'], 'entity' => $event['entity'], 'entity_id' => $event['entity_id'], 'payload' => json_encode($p, JSON_THROW_ON_ERROR), 'deleted' => $event['deleted'], 'version' => 0, 'actor' => 'kiosk:'.$device->id, 'origin' => 'device:'.$device->id, 'confirmed_at' => now(), 'created_at' => now()], 'sequence');
                DB::table('sync_events')->where('id', $event['id'])->update(['version' => $sequence]);
                DB::table('audit_entries')->insert(['actor' => 'kiosk:'.$device->id, 'action' => 'sync:'.$event['entity'], 'entity_id' => $event['entity_id'], 'details' => json_encode($p, JSON_THROW_ON_ERROR), 'created_at' => now()]);
                if ($event['deleted']) {
                    DB::table('sync_tombstones')->updateOrInsert(['entity' => $event['entity'], 'entity_id' => $event['entity_id']], ['version' => $sequence, 'created_at' => now()]);
                }
                $accepted[] = $event['id'];
            }
            $rows = DB::table('sync_events')->where('sequence', '>', $request['cursor'])->whereNotNull('confirmed_at')->orderBy('sequence')->limit(100)->get();
            $events = $rows->map(function ($row) {
                $row->payload = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);

                return (array) $row;
            })->all();
            DB::table('devices')->where('id', $device->id)->update(['last_seen_at' => now()]);

            return ['schema_version' => 1, 'epoch' => $server->epoch, 'accepted' => $accepted, 'events' => $events, 'cursor' => $rows->last()->sequence ?? $request['cursor'], 'has_more' => $rows->count() === 100];
        });
    }
}

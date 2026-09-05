<?php

namespace App\Http\Controllers;

use App\Domain\Access\AccessGuard;
use App\Domain\Recipes\IngredientGlossary;
use App\Domain\Settings\Settings;
use App\Domain\Sync\Journal;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Ramsey\Uuid\Uuid;

final class SettingsController
{
    public function index(Settings $settings)
    {
        return view('settings.index', ['settings' => $settings, 'ingredients' => DB::table('ingredients')->orderBy('name')->get(), 'substitutions' => DB::table('ingredient_substitutions')->get()]);
    }

    public function shared(Request $request, Settings $settings, Journal $journal)
    {
        $data = $request->validate(['automatic' => 'nullable|array', 'automatic.*' => 'uuid|exists:ingredients,id', 'import_hour' => 'required|integer|between:0,23', 'import_frequency_hours' => 'required|integer|between:1,168']);
        DB::transaction(function () use ($data, $settings, $journal) {
            foreach (['import_hour', 'import_frequency_hours'] as $key) {
                $settings->set($key, (int) $data[$key], false);
                $journal->record('setting', $key, ['value' => (int) $data[$key]]);
            }
            foreach (DB::table('ingredients')->get() as $ingredient) {
                $automatic = in_array($ingredient->id, $data['automatic'] ?? [], true);
                if ((bool) $ingredient->automatic === $automatic) {
                    continue;
                }
                DB::table('ingredients')->where('id', $ingredient->id)->update(['automatic' => $automatic]);
                $journal->record('ingredient', $ingredient->id, ['name' => $ingredient->name, 'category_id' => $ingredient->category_id, 'automatic' => $automatic]);
            }
        });

        return back()->with('message', 'Gemeinsame Einstellungen gespeichert.');
    }

    public function substitution(Request $request, Journal $journal)
    {
        $data = $request->validate(['required_id' => 'required|uuid|exists:ingredients,id', 'replacement_id' => 'required|uuid|exists:ingredients,id|different:required_id', 'enabled' => 'required|boolean']);
        $id = Uuid::uuid5(Uuid::NAMESPACE_URL, 'privatebar:sub:'.$data['required_id'].':'.$data['replacement_id'])->toString();
        DB::transaction(function () use ($id, $data, $journal) {
            $existing = DB::table('ingredient_substitutions')->where('required_id', $data['required_id'])->where('replacement_id', $data['replacement_id'])->first();
            $id = $existing->id ?? $id;
            DB::table('ingredient_substitutions')->updateOrInsert(['id' => $id], $data + ['created_at' => now(), 'updated_at' => now()]);
            $journal->record('substitution', $id, $data);
        });

        return back()->with('message', 'Gerichtete Ersatzregel gespeichert.');
    }

    public function localForm()
    {
        return view('settings.local-unlock');
    }

    public function local(Request $request, AccessGuard $guard, Settings $settings)
    {
        $this->pin($request, $guard);

        return view('settings.local', ['settings' => $settings]);
    }

    private function pin(Request $request, AccessGuard $guard): void
    {
        if (! $guard->pin((string) $request->input('pin'))) {
            throw ValidationException::withMessages(['pin' => 'Die PIN ist nicht korrekt.']);
        }
    }

    public function saveLocal(Request $request, AccessGuard $guard, Settings $settings)
    {
        $this->pin($request, $guard);
        $data = $request->validate(['frame_idle_minutes' => 'required|integer|between:1,120', 'frame_seconds' => 'required|integer|between:3,300', 'frame_fade' => 'required|numeric|between:0,3', 'photo_cache_mb' => 'required|integer|between:1,8192', 'monitor_enabled' => 'required|boolean', 'monitor_off' => 'required|date_format:H:i', 'monitor_on' => 'required|date_format:H:i', 'smb_server' => 'nullable|regex:/^[a-zA-Z0-9.-]+$/D|max:253', 'smb_share' => 'nullable|regex:/^[\pL\pN _.-]+$/u|max:100', 'smb_subpath' => 'nullable|string|max:255', 'smb_user' => 'nullable|string|max:100', 'smb_password' => 'nullable|string|max:255', 'new_pin' => 'nullable|regex:/^[0-9]{6}$/D']);
        if (str_contains($data['smb_subpath'] ?? '', '..') || str_contains($data['smb_subpath'] ?? '', ',') || preg_match('/[\r\n]/', ($data['smb_user'] ?? '').($data['smb_password'] ?? '').($data['smb_subpath'] ?? ''))) {
            throw ValidationException::withMessages(['smb_subpath' => 'Pfad und Zugangsdaten enthalten ungültige Zeichen.']);
        }
        DB::transaction(function () use ($data, $settings) {
            foreach ($data as $key => $value) {
                if ($key === 'smb_password') {
                    if ($value) {
                        $settings->setSecret($key, $value);
                    }
                } elseif ($key === 'new_pin') {
                    if ($value) {
                        $settings->set('pin_hash', Hash::make($value));
                    }
                } else {
                    $settings->set($key, $value);
                }
            }
            $settings->set('photo_index_cursor', '');
            $settings->set('photo_index_pending', true);
            $settings->set('smb_mount_requested', true);
        });

        return redirect('/einstellungen')->with('message', 'Lokale Einstellungen gespeichert. Der lokale Dienst übernimmt die Fotoquelle beim nächsten Lauf.');
    }

    public function testSmb(Request $request, AccessGuard $guard, Settings $settings)
    {
        $this->pin($request, $guard);
        $settings->set('smb_mount_requested', true);
        $settings->set('smb_test_requested', true);

        return redirect('/einstellungen')->with('message', 'Verbindungstest angefordert. Das Ergebnis erscheint hier nach dem nächsten lokalen Dienstlauf.');
    }

    public function maintenance(Request $request, AccessGuard $guard, Settings $settings)
    {
        $this->pin($request, $guard);
        $settings->set('maintenance', true);

        return redirect('/');
    }

    public function sync(Settings $settings)
    {
        abort_unless(config('privatebar.mode') === 'pi', 404);
        $settings->set('sync_requested', true);

        return back()->with('message', 'Synchronisation angefordert. Der nächste Minutenlauf startet den Abgleich.');
    }

    public function recovery(Request $request, AccessGuard $guard)
    {
        $this->pin($request, $guard);
        $data = $request->validate(['email' => 'required|email|max:255']);
        $url = rtrim(config('privatebar.cloud_url'), '/');
        if (! str_starts_with($url, 'https://') || ! config('privatebar.device_token')) {
            throw ValidationException::withMessages(['recovery' => 'Der Gerätezugang zu Cyon ist noch nicht eingerichtet.']);
        }
        try {
            $link = Http::withToken(config('privatebar.device_token'))->connectTimeout(3)->timeout(10)->post($url.'/api/v1/recovery', $data)->throw()->json();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['recovery' => 'Reset nicht möglich. Verbindung prüfen; falls noch ein Mitglied angemeldet ist, erstellt dieses den Link.']);
        }
        $qr = (new Writer(new ImageRenderer(new RendererStyle(240), new SvgImageBackEnd)))->writeString($link['url']);

        return view('settings.link', ['link' => $link, 'qr' => 'data:image/svg+xml;base64,'.base64_encode($qr)]);
    }

    public function ingredients()
    {
        return view('settings.ingredients', ['ingredients' => DB::table('ingredients')->orderBy('name')->paginate(30), 'categories' => DB::table('ingredient_categories')->orderBy('name')->get()]);
    }

    public function ingredient(Request $request, string $id, IngredientGlossary $glossary)
    {
        $data = $request->validate(['name' => 'required|string|max:255|unique:ingredients,name,'.$id, 'category_id' => 'required|exists:ingredient_categories,id', 'synonyms' => 'nullable|string|max:1000']);
        $glossary->update($id, $data);

        return back()->with('message', 'Cocktailzutat und Synonyme gespeichert.');
    }
}

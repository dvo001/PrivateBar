<?php

namespace App\Domain\Access;

use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class MemberLinks
{
    public function issue(string $type, string $email, ?int $creator): array
    {
        $table = $this->table($type);
        $email = mb_strtolower(trim($email));
        if ($type === 'reset' && ! User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Dieses Mitglied wurde nicht gefunden.']);
        }
        if ($type === 'invite' && User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Diese Person ist bereits Mitglied.']);
        }
        $token = bin2hex(random_bytes(32));
        $id = (string) Str::uuid();
        DB::table($table)->insert(['id' => $id, 'email' => $email, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addMinutes(30), 'created_by' => $creator, 'created_at' => now(), 'updated_at' => now()]);

        return ['id' => $id, 'token' => $token, 'type' => $type, 'url' => rtrim(config('app.url'), '/').'/zugang/'.$type.'/'.$token];
    }

    public function consume(string $type, string $token, string $password, string $name): User
    {
        if (mb_strlen($password) < 12) {
            throw ValidationException::withMessages(['password' => 'Das Passwort muss mindestens zwölf Zeichen lang sein.']);
        }

        return DB::transaction(function () use ($type, $token, $password, $name) {
            app(Settings::class)->assertRunning();
            $table = $this->table($type);
            $link = DB::table($table)->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if (! $link || $link->used_at || $link->revoked_at || now()->gte($link->expires_at)) {
                throw ValidationException::withMessages(['access' => 'Dieser Link ist abgelaufen, widerrufen oder bereits verwendet.']);
            }
            if ($type === 'invite') {
                if (User::query()->where('email', $link->email)->exists()) {
                    throw ValidationException::withMessages(['access' => 'Diese Person ist bereits Mitglied.']);
                }
                $user = new User;
                $user->uuid = (string) Str::uuid();
                $user->email = $link->email;
                $user->name = $name;
            } else {
                $user = User::query()->where('email', $link->email)->lockForUpdate()->firstOrFail();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                DB::table('password_reset_tokens')->where('email', $link->email)->where('id', '!=', $link->id)->update(['revoked_at' => now()]);
            }
            $user->password = Hash::make($password);
            $user->setRememberToken(Str::random(60));
            $user->save();
            DB::table($table)->where('id', $link->id)->update(['used_at' => now(), 'updated_at' => now()]);

            return $user;
        });
    }

    public function remove(int $id): void
    {
        DB::transaction(function () use ($id) {
            app(Settings::class)->assertRunning();
            $users = DB::table('users')->orderBy('id')->lockForUpdate()->get();
            if ($users->count() <= 1) {
                throw ValidationException::withMessages(['members' => 'Das letzte Mitglied kann nicht entfernt werden.']);
            }
            $user = $users->firstWhere('id', $id);
            abort_unless($user !== null, 404);
            DB::table('sessions')->where('user_id', $id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->update(['revoked_at' => now()]);
            DB::table('users')->where('id', $id)->delete();
        });
    }

    public function table(string $type): string
    {
        return match ($type) {
            'invite' => 'invitations','reset' => 'password_reset_tokens',default => throw new \InvalidArgumentException('Unbekannter Linktyp.')
        };
    }
}

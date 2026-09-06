<?php

namespace App\Http\Controllers;

use App\Domain\Access\AccessMail;
use App\Domain\Access\MemberLinks;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MemberController
{
    private function guard(): void
    {
        abort_unless(config('privatebar.mode') === 'cloud' && auth()->guard()->check(), 403);
    }

    public function index()
    {
        $this->guard();

        return view('settings.members', ['members' => User::query()->orderBy('name')->get(), 'invitations' => DB::table('invitations')->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', now())->get(), 'link' => null]);
    }

    public function issue(Request $request, MemberLinks $links)
    {
        $this->guard();
        $data = $request->validate(['email' => 'required|email|max:255', 'type' => 'required|in:invite,reset']);
        if ($data['type'] === 'reset' && mb_strtolower($data['email']) === User::query()->where('id', auth()->guard()->id())->value('email')) {
            abort(422, 'Ein anderes Mitglied muss deinen Reset-Link erstellen.');
        }
        $link = $links->issue($data['type'], $data['email'], auth()->guard()->id());
        if ($data['type'] === 'invite' && ! app(AccessMail::class)->invitation(mb_strtolower(trim($data['email'])), $link['url'])) {
            DB::table('invitations')->where('id', $link['id'])->update(['revoked_at' => now()]);
            throw ValidationException::withMessages(['email' => AccessMail::FAILED.' Die Einladung wurde widerrufen; du kannst sie erneut erstellen.']);
        }
        $qr = (new Writer(new ImageRenderer(new RendererStyle(240), new SvgImageBackEnd)))->writeString($link['url']);

        return view('settings.link', ['link' => $link, 'qr' => 'data:image/svg+xml;base64,'.base64_encode($qr)]);
    }

    public function revoke(string $id)
    {
        $this->guard();
        DB::table('invitations')->where('id', $id)->update(['revoked_at' => now()]);

        return back();
    }

    public function remove(string $id, MemberLinks $links)
    {
        $this->guard();
        $links->remove((int) $id);

        return redirect('/einstellungen/mitglieder');
    }

    public function sessions(string $id)
    {
        $this->guard();
        DB::table('sessions')->where('user_id', $id)->delete();

        return back()->with('message', 'Sitzungen widerrufen.');
    }
}

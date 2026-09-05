<!doctype html>
<html lang="de-CH">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}"><meta name="theme-color" content="#171d1c">
    <title>@yield('title', 'PrivateBar') · PrivateBar</title>
    <link rel="icon" href="/assets/icon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/app.css"><script src="/assets/app.js" defer></script>
</head>
<body data-mode="{{ config('privatebar.mode') }}" @if(session('kiosk_unlocked')) data-frame-idle="{{ app(\App\Domain\Settings\Settings::class)->get('frame_idle_minutes',5) * 60 }}" @endif>
<a class="skip" href="#main">Zum Inhalt</a>
<aside class="sidebar" aria-label="Hauptnavigation">
    <a class="brand" href="/" aria-label="PrivateBar Start"><img src="/assets/logo.svg" alt="PrivateBar" width="190" height="88"></a>
    <span class="sidebar-caption">DEINE HAUSBAR</span>
    <nav>
    @foreach([['home','/','Start','◒'],['feasible','/machbar','Machbar','✦'],['discover','/entdecken','Entdecken','◎'],['bar','/meine-bar','Meine Bar','▥'],['shopping','/einkaufsliste','Einkaufsliste','≡'],['favorites','/favoriten','Favoriten','♡'],['settings','/einstellungen','Einstellungen','⚙']] as [$route,$href,$label,$icon])
        <a href="{{ $href }}" @if(request()->routeIs($route)) aria-current="page" @endif><span class="nav-icon" aria-hidden="true">{{ $icon }}</span>{{ $label }}</a>
    @endforeach
    </nav>
    <div class="sidebar-bottom"><span class="mode-dot"></span>{{ config('privatebar.mode') === 'pi' ? 'Zu Hause' : 'Unterwegs' }}
    @if(session('kiosk_unlocked') || auth()->check())<form method="post" action="/abmelden">@csrf<button class="quiet" type="submit">{{ auth()->check() ? 'Abmelden' : 'Bar sperren' }}</button></form>@endif</div>
</aside>
<div class="mobile-header"><a href="/"><img src="/assets/logo.svg" alt="PrivateBar" width="135" height="64"></a><button type="button" id="nav-toggle" aria-expanded="false" aria-controls="mobile-nav">Menü <span aria-hidden="true">☰</span></button></div>
<nav class="mobile-nav" id="mobile-nav" aria-label="Mobile Hauptnavigation" hidden>@foreach([['/','Start'],['/machbar','Machbar'],['/entdecken','Entdecken'],['/meine-bar','Meine Bar'],['/einkaufsliste','Einkaufsliste'],['/favoriten','Favoriten'],['/einstellungen','Einstellungen']] as [$href,$label])<a href="{{ $href }}">{{ $label }}</a>@endforeach</nav>
<main id="main" tabindex="-1">
    @if(session('message'))<div class="notice" role="status">{{ session('message') }}</div>@endif
    @if($errors->any())<div class="error" role="alert"><strong>Bitte prüfe deine Eingabe.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
@if(config('privatebar.mode') === 'pi' && session('kiosk_unlocked'))<div id="photo-frame" role="dialog" aria-modal="true" aria-label="Fotorahmen. Zum Zurückkehren berühren." tabindex="0" hidden><img alt="" id="frame-a"><img alt="" id="frame-b"></div>@endif
</body></html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>cjmanajemen - Digital Scoring</title>
    
    <link rel="stylesheet" href="{{ asset('css/app.css?v=' . time() . '') }}">
    <link rel="stylesheet" href="{{ asset('bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
    <div id="loader" class="loader-bar"></div>
    
    <header class="app-header d-flex justify-content-start @if(request()->segment(3) == 'display-arena' || request()->segment(3) == 'referees' || request()->segment(3) == 'judges' || request()->segment(4) == 'recap') light @endif">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            @if(request()->segment(3) == 'display-arena' || request()->segment(3) == 'referees' || request()->segment(3) == 'judges' || request()->segment(4) == 'recap')
                <img src="{{ asset('images/ipsi.png') }}" alt="IPSI">
            @else
                <div class="logo">
                    <img src="{{ asset('images/logo.png') }}" alt="">
                </div>
                <div class="seni-match-info light roboto-bold" id="tournament-name">-</div>
            @endif

           

            
            @if(session('role') && request()->segment(3) != 'display-arena' && request()->segment(3) != 'referees' && request()->segment(4) != 'recap')
            <div class="dropdown ms-auto">
                @php
                    $roleLabel = session('juri_number') 
                        ? 'Juri ' . session('juri_number') 
                        : ucfirst(session('role') ?? 'Guest');
                @endphp
                <button class="btn btn-dark dropdown-toggle btn-sm" type="button" id="userInfoDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    {{ $roleLabel }}
                </button>
                <ul class="dropdown-menu dropdown-menu-end text-small" aria-labelledby="userInfoDropdown">
                    @if(session('arena_name'))
                        <li><span class="dropdown-item-text"><strong>Arena:</strong> {{ session('arena_name') }}</span></li>
                    @endif
                    
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form action="{{ config('app_settings.path_prefix') }}/logout" method="POST" class="px-3 m-0">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-danger w-100">Logout</button>
                        </form>
                    </li>
                </ul>
            </div>
            @endif
        </div>
    </header>



   

    <main class="app-content">
        <input type="hidden" id="session-arena" value="{{ session('arena_name') }}">
        @yield('content')
    </main>

    <!-- JS -->
    <script src="{{ asset('js/jquery.js') }}"></script>
    <script src="{{ asset('bootstrap/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('js/app.js') }}"></script>

    <!-- Local Pusher & Echo -->
    <script src="{{ asset('js/pusher.min.js') }}"></script>
    <script src="{{ asset('js/echo.js') }}"></script>

    <script>
    window.Pusher = Pusher;

    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: 'reverb',
        wsHost: window.location.hostname,
        wsPort: 6001,
        forceTLS: false,
        encrypted: false,
        disableStats: true,
        cluster: 'mt1',
        wsPath: '',
    });

   
    </script>

    <script>
        window.APP_PATH_PREFIX = "{{ config('app_settings.path_prefix') }}";
        window.APP = {
            baseUrl: window.location.origin + window.APP_PATH_PREFIX
        };
    </script>

    @if(isset($js))
        <script src="{{ asset('js/'.$js.'?v='.time()) }}"></script>
    @endif
    
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>cjmanajemen - Digital Scoring</title>
    
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('bootstrap/css/bootstrap.min.css') }}">

    <link rel="stylesheet" href="{{ asset('css/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/fonts.css') }}">

    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
    <div id="loader" class="loader-bar"></div>
    
    <header class="app-header d-flex justify-content-start  light">
        <div class="container-fluid d-flex justify-content-between align-items-center">
           <img src="{{ asset('images/ipsi.png') }}" alt="IPSI">


            @if(request()->segment(2) == 'display-arena')
                <div id="timer" class="timer arena roboto-bold text-white">
                    00:00
                </div>
            @endif

            

            
            @if(session('role') && (request()->segment(2) != 'display-arena' && request()->segment(3) != 'live'))
             <!-- ⏬ Navigasi ditambahkan di sini -->
            
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
                    @if(session('role') !== 'admin' && session('arena_name'))
                        <li><span class="dropdown-item-text"><strong>Arena:</strong> {{ session('arena_name') }}</span></li>
                    @endif

                   

                   
                    <li>
                        <form action="/logout" method="POST" class="px-3 m-0">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-danger w-100">Logout</button>
                        </form>
                    </li>
                </ul>

            </div>
            @endif
        </div>
    </header>
   
    @if(session('role') === 'admin')
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
           
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link {{ Request::is('dashboard') ? 'active' : '' }}" href="{{ url('/dashboard') }}">
                            <i class="bi bi-house"></i> Medals Recapitulation
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::is('medal-recap') ? 'active' : '' }}" href="{{ url('/medal-recap') }}">
                            <i class="bi bi-trophy"></i> Winners Recapitulation
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::is('/') ? 'active' : '' }}" href="{{ url('/') }}">
                            <i class="bi bi-award"></i> Go to Setup
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::is('import-matches') ? 'active' : '' }}" href="{{ url('/import-matches') }}">
                            <i class="bi bi-upload"></i> Import Matches
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::is('matches/tanding/admin') ? 'active' : '' }}" href="{{ url('/matches/tanding/admin') }}">
                            <i class="bi bi-trophy"></i> Match Tanding
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::is('matches/seni/admin') ? 'active' : '' }}" href="{{ url('/matches/seni/admin') }}">
                            <i class="bi bi-trophy"></i> Match Seni
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::is('matches/live') ? 'active' : '' }}" href="{{ url('/matches/live') }}">
                            <i class="bi bi-tv"></i> Live Match
                        </a>
                    </li>
  
                </ul>
            </div>
        </div>
    </nav>
    @endif



   

    <main class="app-content">
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

    
    @if(isset($js))
        <script src="{{ asset('js/'.$js.'?v='.time()) }}"></script>
    @endif
    



</body>
</html>

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

            @if(request()->segment(1) == 'import-matches')
                <a href="{{ url('/') }}" class="btn btn-dark">Go to Matches</a>
            @endif

            @if(request()->segment(2) == 'display-arena')
                <div id="timer" class="timer arena roboto-bold text-white">
                    00:00
                </div>
            @endif

            
            @if(session('role') && (request()->segment(2) != 'display-arena' && request()->segment(3) != 'live'))
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

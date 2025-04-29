<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>cjmanajemen - Digital Scoring</title>
    
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-bracket/0.11.1/jquery.bracket.min.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:ital,wght@0,300..900;1,300..900&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Urbanist:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
    <div id="loader" class="loader-bar"></div>
    @if(request()->segment(2) != 'judges')
    <header class="app-header d-flex justify-content-start  @if(request()->segment(2) == 'display-arena' || request()->segment(2) == 'referees' || request()->segment(3) == 'recap') light @endif">
        <div class="container-fluid d-flex justify-content-between">
            @if(request()->segment(2) == 'display-arena' || request()->segment(2) == 'referees' || request()->segment(3) == 'recap')
                <img src="{{ asset('images/ipsi.png') }}" alt="IPSI">
            @else
                <div class="logo">
                    <img src="{{ asset('images/logo.png') }}" alt="">
                </div>
            @endif
            @if(request()->segment(2) == 'display-arena' || request()->segment(2) == 'referees')
                <div id="timer" class="timer arena roboto-bold">
                    00:00
                </div>
            @endif
        </div>
        
    </header>
    @endif

    <main class="app-content">
        @yield('content')
    </main>

    <!-- JS -->
    <script src="{{ asset('js/jquery.js') }}"></script>
    <script src="{{ asset('bootstrap/js/bootstrap.min.js') }}"></script>
    <script src="{{ asset('bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@7.2.0/dist/web/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo/dist/echo.iife.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


    <script>
        window.Pusher = Pusher;
        const host = window.location.hostname;
        window.Echo = new Echo({
            broadcaster: 'pusher',
            key: 'reverb',
            wsHost: host,
            wsPort: 6001,
            forceTLS: false,
            encrypted: false,
            disableStats: true,
            cluster: 'mt1',
            wsPath: '', // penting buat Reverb
        });
    </script>


    
    @if(isset($js))
        <script src="{{ asset('js/'.$js.'?v='.time()) }}"></script>
    @endif
    



</body>
</html>

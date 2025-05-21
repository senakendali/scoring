<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>cjmanajemen - Digital Scoring</title>
    
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-bracket/0.11.1/jquery.bracket.min.css">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:ital,wght@0,300..900;1,300..900&family=Roboto:ital,wght@0,100;0,300;0,400;0,500;0,700;0,900;1,100;1,300;1,400;1,500;1,700;1,900&family=Urbanist:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
    <div id="loader" class="loader-bar"></div>
    
    <header class="app-header d-flex justify-content-start @if(request()->segment(2) == 'display-arena' || request()->segment(2) == 'judges' || request()->segment(2) == 'referees' || request()->segment(3) == 'recap') light @endif">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            @if(request()->segment(2) == 'display-arena' || request()->segment(2) == 'judges' || request()->segment(2) == 'referees' ||  request()->segment(3) == 'recap')
                <img src="{{ asset('images/ipsi.png') }}" alt="IPSI">
            @else
                <div class="logo">
                    <img src="{{ asset('images/logo.png') }}" class="img-fluid" alt="IPSI">
                </div>
            @endif

            @if(request()->segment(2) == 'display-arena')
                <div id="timer" class="timer arena roboto-bold">
                    00:00
                </div>
            @endif

            
            @if(session('role') && request()->segment(2) != 'display-arena')
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
   
    

    <!-- Jquery Bracket -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-bracket/0.11.1/jquery.bracket.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-bracket/0.11.1/jquery.bracket.min.js"></script>



   <script src="https://cdn.jsdelivr.net/npm/pusher-js@7.2.0/dist/web/pusher.min.js"></script>

    <script type="module">
        import Echo from 'https://cdn.skypack.dev/laravel-echo';

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

        console.log('✅ Echo initialized as module:', window.Echo);
    </script>







    








    
    @if(isset($js))
        <script src="{{ asset('js/'.$js.'?v='.time()) }}"></script>
    @endif
    



</body>
</html>

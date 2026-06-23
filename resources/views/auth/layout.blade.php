<!doctype html>
<html lang="ar" class="layout-wide" dir="rtl" data-skin="default" data-bs-theme="dark"
      data-assets-path="/assets/" data-template="vertical-menu-template">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0"/>
    <meta name="robots" content="noindex, nofollow"/>
    <title>@yield('title', 'تسجيل الدخول')</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{asset('assets/img/favicon/favicon.ico')}}"/>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&ampdisplay=swap"
        rel="stylesheet"/>

    <link rel="stylesheet" href="{{asset('assets/vendor/fonts/iconify-icons.css')}}"/>
    <link rel="stylesheet" href="{{asset('assets/vendor/fonts/fontawesome.css')}}"/>

    <!-- Core CSS -->
    <link rel="stylesheet" href="{{asset('assets/vendor/libs/node-waves/node-waves.css')}}"/>
    <link rel="stylesheet" href="{{asset('assets/vendor/libs/pickr/pickr-themes.css')}}"/>
    <link rel="stylesheet" href="{{asset('assets/vendor/css/core.css')}}"/>
    <link rel="stylesheet" href="{{asset('assets/css/demo.css')}}"/>

    <link rel="stylesheet" href="{{asset('assets/vendor/css/pages/page-auth.css')}}"/>

    @livewireStyles

    <!-- Helpers -->
    <script src="{{asset('assets/vendor/js/helpers.js')}}"></script>
    <script src="{{asset('assets/vendor/js/template-customizer.js')}}"></script>
    <script src="{{asset('assets/js/config.js')}}"></script>
</head>

<body>
<div class="authentication-wrapper authentication-cover">
    <div class="authentication-inner row m-0">
        @yield('content')
    </div>
</div>

<!-- Core JS -->
<script src="{{asset('assets/vendor/libs/jquery/jquery.js')}}"></script>
<script src="{{asset('assets/vendor/libs/popper/popper.js')}}"></script>
<script src="{{asset('assets/vendor/js/bootstrap.js')}}"></script>
<script src="{{asset('assets/vendor/libs/node-waves/node-waves.js')}}"></script>
<script src="{{asset('assets/vendor/libs/pickr/pickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js')}}"></script>
<script src="{{asset('assets/vendor/libs/hammer/hammer.js')}}"></script>
<script src="{{asset('assets/vendor/libs/i18n/i18n.js')}}"></script>
<script src="{{asset('assets/vendor/js/menu.js')}}"></script>
<script src="{{asset('assets/js/main.js')}}"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    window.toast = function (type = 'success', message = '', opts = {}) {
        const Toast = Swal.mixin({
            toast: true,
            position: opts.position || 'bottom-left',
            showConfirmButton: false,
            timer: opts.timer || 2500,
            timerProgressBar: true,
            customClass: {
                popup: 'custom-toast-height'
            },
            didOpen: (toast) => {
                toast.style.cursor = 'pointer';
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
                toast.addEventListener('click', () => Swal.close());
            }
        });
        return Toast.fire({icon: type, title: message});
    };

    document.addEventListener('livewire:init', () => {
        Livewire.on('toast', (data) => {
            const toastData = Array.isArray(data) ? data[0] : data;
            window.toast(toastData.type || 'success', toastData.message || '');
        });
        Livewire.on('error', (message) => {
            window.toast('error', Array.isArray(message) ? message[0] : message);
        });
    });
</script>

@livewireScripts
</body>
</html>

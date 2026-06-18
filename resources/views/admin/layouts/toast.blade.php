<style>
    .custom-toast-height {
        position: relative !important;
        height: auto !important;
        padding: 0px 12px !important;
        font-size: 14px !important;
        line-height: 1.2 !important;

        & * {
            cursor: pointer !important;
        }

    }

</style>
<script>
    window.toast = function (type = 'success', message = '', opts = {}) {
        const Toast = Swal.mixin({
            toast: true,
            // position: opts.position || 'bottom-{{ app()->getLocale() == 'ar' ? 'left' : 'right' }}',
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
    @if (session('success'))
    window.toast('success', @json(session('success')));
    @endif

    @if (session('error'))
    window.toast('error', @json(session('error')));
    @endif

    @if (session('warning'))
    window.toast('warning', @json(session('warning')));
    @endif

    @if (session('info'))
    window.toast('info', @json(session('info')));
    @endif

    document.addEventListener('livewire:init', () => {
        // Handle toast event
        Livewire.on('toast', (data) => {
            const toastData = Array.isArray(data) ? data[0] : data;
            window.toast(toastData.type || 'success', toastData.message || '');
        });

        // Handle alert event
        Livewire.on('alert', (data) => {
            const toastData = Array.isArray(data) ? data[0] : data;
            window.toast(toastData.type || 'info', toastData.message || '');
        });

        // Handle success event
        Livewire.on('success', (message) => {
            window.toast('success', Array.isArray(message) ? message[0] : message);
        });

        // Handle error event
        Livewire.on('error', (message) => {
            window.toast('error', Array.isArray(message) ? message[0] : message);
        });

        // Handle warning event
        Livewire.on('warning', (message) => {
            window.toast('warning', Array.isArray(message) ? message[0] : message);
        });

        // Handle info event
        Livewire.on('info', (message) => {
            window.toast('info', Array.isArray(message) ? message[0] : message);
        });
    });
</script>

@php
    $sessionToast = session('toast');
    $legacyStatus = session('status');

    if (! $sessionToast && $legacyStatus) {
        $sessionToast = match ($legacyStatus) {
            'verification-link-sent' => [
                'type' => 'success',
                'message' => 'Se envio un nuevo enlace de verificacion a tu correo.',
            ],
            default => [
                'type' => 'success',
                'message' => $legacyStatus,
            ],
        };
    }
@endphp

<div
    x-data="toastStack(@js($sessionToast))"
    x-init="init()"
    x-on:toast.window="push($event.detail)"
    class="pointer-events-none fixed bottom-4 right-4 z-50 flex w-full max-w-sm flex-col-reverse gap-3 sm:bottom-6 sm:right-6"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="toast.visible"
            x-transition:enter="transform transition ease-out duration-300"
            x-transition:enter-start="translate-y-3 opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transform transition ease-in duration-200"
            x-transition:leave-start="translate-y-0 opacity-100"
            x-transition:leave-end="translate-y-2 opacity-0"
            class="pointer-events-auto overflow-hidden rounded-2xl border border-l-4 shadow-2xl backdrop-blur"
            :class="toastTheme(toast.type)"
        >
            <div class="flex items-start gap-3 px-4 py-4">
                <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full" :class="toastIconTheme(toast.type)">
                    <svg x-show="toast.type === 'success'" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <svg x-show="toast.type === 'error'" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    <svg x-show="toast.type === 'warning'" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a1 1 0 00.86 1.5h18.64a1 1 0 00.86-1.5L13.71 3.86a1 1 0 00-1.72 0z" />
                    </svg>
                    <svg x-show="toast.type === 'info' || !['success','error','warning'].includes(toast.type)" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>

                <div class="min-w-0 flex-1">
                    <p x-show="toast.title" x-text="toast.title" class="text-sm font-black uppercase tracking-[0.18em]"></p>
                    <p x-text="toast.message" class="mt-1 text-sm leading-5"></p>
                </div>

                <button
                    type="button"
                    class="rounded-full px-2 py-1 text-xs font-semibold uppercase tracking-[0.18em] opacity-70 transition hover:opacity-100"
                    x-on:click="dismiss(toast.id)"
                >
                    Cerrar
                </button>
            </div>
        </div>
    </template>
</div>

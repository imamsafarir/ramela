<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />

    <!-- PWA Settings & Web App Manifest -->
    <link rel="manifest" href="/manifest.json" />
    <meta name="theme-color" content="#17231f" />
    <meta name="mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
    <meta name="apple-mobile-web-app-title" content="RAMELA" />
    <meta name="application-name" content="RAMELA" />
    <meta name="msapplication-TileColor" content="#17231f" />

    <!-- Icons -->
    <link rel="icon" type="image/svg+xml" href="/icons/icon.svg" />
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192x192.png" />
    <link rel="apple-touch-icon" href="/icons/icon-192x192.png" />
    <link rel="apple-touch-icon" sizes="512x512" href="/icons/icon-512x512.png" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-inertia::head>
        <title>{{ config('app.name', 'RAMELA') }}</title>
    </x-inertia::head>

    <!-- Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then((registration) => {
                        console.log('[RAMELA PWA] Service Worker aktif dengan scope:', registration.scope);
                    })
                    .catch((error) => {
                        console.warn('[RAMELA PWA] Registrasi Service Worker gagal:', error);
                    });
            });
        }
    </script>
</head>

<body class="antialiased">
    <x-inertia::app />

    <!-- PWA Install Prompt Banner -->
    <div id="pwa-install-banner" class="fixed bottom-20 md:bottom-6 left-4 right-4 md:left-auto md:right-6 md:w-96 bg-[#17231f] border border-[#0d685b]/60 text-[#f3f2e7] p-4 rounded-2xl shadow-2xl z-50 transform translate-y-32 opacity-0 transition-all duration-300 pointer-events-none flex items-start gap-3">
        <div class="w-12 h-12 rounded-xl bg-[#0d685b] flex items-center justify-center shrink-0 shadow">
            <img src="/icons/icon-192x192.png" alt="RAMELA" class="w-8 h-8 rounded-lg" />
        </div>
        <div class="flex-1">
            <h4 class="font-bold text-sm text-[#f3f2e7]">Pasang Aplikasi RAMELA</h4>
            <p class="text-xs text-[#a3b8b0] mt-0.5 leading-relaxed">Pasang di layar utama untuk akses instan dan pengalaman aplikasi yang lebih lancar.</p>
            <div class="mt-3 flex items-center gap-2">
                <button id="pwa-install-btn" type="button" class="bg-[#0d685b] hover:bg-[#149683] text-[#f3f2e7] text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors cursor-pointer">
                    Install Sekarang
                </button>
                <button id="pwa-dismiss-btn" type="button" class="bg-white/5 hover:bg-white/10 text-[#a3b8b0] hover:text-[#f3f2e7] text-xs font-medium px-3 py-1.5 rounded-lg transition-colors cursor-pointer">
                    Nanti
                </button>
            </div>
        </div>
        <button id="pwa-close-btn" type="button" class="text-white/40 hover:text-white/80 p-1 cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <script>
        (function() {
            let deferredPrompt = null;
            const banner = document.getElementById('pwa-install-banner');
            const installBtn = document.getElementById('pwa-install-btn');
            const dismissBtn = document.getElementById('pwa-dismiss-btn');
            const closeBtn = document.getElementById('pwa-close-btn');

            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
                if (!sessionStorage.getItem('ramela_pwa_dismissed') && banner) {
                    setTimeout(() => {
                        banner.classList.remove('translate-y-32', 'opacity-0', 'pointer-events-none');
                    }, 2500);
                }
            });

            function hideBanner() {
                if (banner) {
                    banner.classList.add('translate-y-32', 'opacity-0', 'pointer-events-none');
                }
                sessionStorage.setItem('ramela_pwa_dismissed', 'true');
            }

            if (installBtn) {
                installBtn.addEventListener('click', async () => {
                    if (!deferredPrompt) return;
                    deferredPrompt.prompt();
                    const { outcome } = await deferredPrompt.userChoice;
                    console.log('[RAMELA PWA] Pilihan user:', outcome);
                    deferredPrompt = null;
                    hideBanner();
                });
            }

            if (dismissBtn) dismissBtn.addEventListener('click', hideBanner);
            if (closeBtn) closeBtn.addEventListener('click', hideBanner);

            window.addEventListener('appinstalled', () => {
                hideBanner();
                console.log('[RAMELA PWA] Aplikasi RAMELA terpasang di perangkat');
            });
        })();
    </script>
</body>

</html>

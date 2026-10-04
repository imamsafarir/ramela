<script setup>
import { Link, router, usePage } from "@inertiajs/vue3";
import { computed, onMounted, onUnmounted, provide, ref } from "vue";

const page = usePage();
const user = computed(() => page.props.auth?.user);
const flash = computed(() => page.props.flash?.success);

// State Izin & Akses Lokasi (GPS)
const locationStatus = ref("prompt"); // 'prompt' | 'granted' | 'denied' | 'unsupported'
const isRequesting = ref(false);
const errorMessage = ref("");
const coords = ref({ lat: null, lng: null });
let watchId = null;

// Cek apakah konteks aman (HTTPS atau localhost)
const isSecure = typeof window !== "undefined" ? (window.isSecureContext !== false) : true;

const handleSuccess = (pos) => {
    coords.value = {
        lat: Number(pos.coords.latitude.toFixed(6)),
        lng: Number(pos.coords.longitude.toFixed(6)),
    };
    locationStatus.value = "granted";
    isRequesting.value = false;
    errorMessage.value = "";
    startWatch();
};

const handleFailure = (err) => {
    isRequesting.value = false;
    locationStatus.value = "denied";

    if (err.code === 1 /* PERMISSION_DENIED */) {
        errorMessage.value =
            "Izin lokasi ditolak oleh browser. Di iPhone (Safari), buka Pengaturan iOS > Safari > Lokasi > Izinkan. Di Android (Chrome), buka Ikon Gembok / Pengaturan Situs > Lokasi > Izinkan.";
    } else if (err.code === 2 /* POSITION_UNAVAILABLE */) {
        errorMessage.value =
            "Sinyal GPS tidak terdeteksi. Pastikan Layanan Lokasi (GPS) di Pengaturan ponsel Anda telah diaktifkan.";
    } else if (err.code === 3 /* TIMEOUT */) {
        errorMessage.value =
            "Waktu pencarian GPS habis. Anda dapat mencoba menekan tombol kembali atau menggunakan opsi 'Masuk dengan Lokasi Toko' di bawah.";
    } else {
        errorMessage.value = err.message || "Gagal membaca koordinat GPS perangkat.";
    }
};

const requestLocation = () => {
    if (!("geolocation" in navigator)) {
        locationStatus.value = "unsupported";
        errorMessage.value =
            "Perangkat atau browser ini tidak mendukung fitur Geolocation / GPS.";
        return;
    }

    if (!isSecure) {
        errorMessage.value =
            "Perhatian: Koneksi saat ini menggunakan HTTP biasa. Browser iPhone & Android membatasi GPS pada koneksi aman (HTTPS). Jika GPS tidak merespons, silakan gunakan tombol 'Masuk dengan Lokasi Toko'.";
    }

    isRequesting.value = true;
    errorMessage.value = "";

    // 1. Coba dengan akurasi tinggi terlebih dahulu (timeout 5 detik)
    // 2. Jika gagal atau timeout (sering terjadi di dalam ruangan), otomatis coba lagi dengan low accuracy (Wi-Fi/seluler)
    navigator.geolocation.getCurrentPosition(
        handleSuccess,
        (err) => {
            // Jika izin ditolak secara permanen oleh pengguna, jangan retry
            if (err.code === 1) {
                handleFailure(err);
                return;
            }

            // Retry dengan enableHighAccuracy: false (sangat responsif di mobile via Wi-Fi/BTS seluler)
            navigator.geolocation.getCurrentPosition(
                handleSuccess,
                handleFailure,
                { enableHighAccuracy: false, timeout: 6000, maximumAge: 60000 }
            );
        },
        { enableHighAccuracy: true, timeout: 5000, maximumAge: 10000 }
    );
};

// Opsi fallback jika GPS di ponsel sedang bermasalah atau koneksi HTTP
const useFallbackLocation = () => {
    coords.value = { lat: -6.989720, lng: 110.421930 };
    locationStatus.value = "granted";
    isRequesting.value = false;
    errorMessage.value = "";
};

const startWatch = () => {
    if (watchId !== null || !("geolocation" in navigator)) return;

    watchId = navigator.geolocation.watchPosition(
        (pos) => {
            coords.value = {
                lat: Number(pos.coords.latitude.toFixed(6)),
                lng: Number(pos.coords.longitude.toFixed(6)),
            };
            if (locationStatus.value !== "granted") {
                locationStatus.value = "granted";
            }
        },
        (err) => {
            console.warn("Watch location notice:", err.message);
        },
        { enableHighAccuracy: true, maximumAge: 3000, timeout: 10000 }
    );
};

onMounted(() => {
    // Pada mobile browser, JANGAN langsung aktifkan isRequesting = true tanpa interaksi pengguna,
    // karena iOS Safari memerlukan "user gesture" (sentuhan tombol) untuk memunculkan prompt izin lokasi.
    if ("permissions" in navigator && navigator.permissions?.query) {
        navigator.permissions
            .query({ name: "geolocation" })
            .then((result) => {
                if (result.state === "granted") {
                    // Jika sebelumnya sudah pernah diizinkan, baca posisi secara otomatis
                    requestLocation();
                } else {
                    locationStatus.value = "prompt";
                    isRequesting.value = false;
                }
                result.onchange = () => {
                    if (result.state === "granted") requestLocation();
                };
            })
            .catch(() => {
                locationStatus.value = "prompt";
                isRequesting.value = false;
            });
    } else {
        // iOS Safari tidak mendukung Permissions API untuk geolocation
        // Biarkan tombol aktif dan siap ditekan oleh pengguna
        locationStatus.value = "prompt";
        isRequesting.value = false;
    }
});

onUnmounted(() => {
    if (watchId !== null) {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
    }
});

provide("courierCoords", coords);
provide(
    "locationGranted",
    computed(() => locationStatus.value === "granted")
);

const logout = () => router.post("/logout");
</script>

<template>
    <div class="min-h-screen bg-[#17231f] text-[#f3f2e7]">
        <!-- HEADER KURIR -->
        <header
            class="sticky top-0 z-30 border-b border-[#0d685b]/30 bg-[#17231f]/95 backdrop-blur-md header-safe"
        >
            <nav
                class="mx-auto flex max-w-5xl items-center justify-between px-4 sm:px-6 lg:px-8 py-3"
            >
                <div class="flex items-center gap-3 sm:gap-6">
                    <Link href="/kurir" class="flex items-center gap-2 group">
                        <span
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-500 text-base font-black text-slate-900 shadow-xs group-hover:scale-105 transition transform"
                        >
                            🛵
                        </span>
                        <div>
                            <span
                                class="block text-base font-black tracking-tight text-[#f3f2e7]"
                                >RAMELA Kurir</span
                            >
                            <span
                                class="block text-[10px] font-bold uppercase tracking-wider text-amber-300"
                                >Armada Pengantaran</span
                            >
                        </div>
                    </Link>
                </div>

                <div class="flex items-center gap-3 text-xs">
                    <!-- Status GPS Badge di Header -->
                    <div
                        v-if="
                            locationStatus === 'granted' &&
                            coords.lat &&
                            coords.lng
                        "
                        class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-[#0d685b]/30 px-2.5 py-1 font-semibold text-emerald-300 border border-[#0d685b]/50"
                        title="GPS Terhubung Aktif"
                    >
                        <span
                            class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"
                        ></span>
                        <span
                            >GPS Aktif: {{ coords.lat.toFixed(4) }},
                            {{ coords.lng.toFixed(4) }}</span
                        >
                    </div>
                    <div
                        v-else
                        class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-amber-950/40 px-2.5 py-1 font-semibold text-amber-200 border border-amber-500/40"
                    >
                        <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                        <span>GPS Menunggu Izin</span>
                    </div>

                    <div class="flex items-center gap-2 pl-2 border-l border-[#0d685b]/30">
                        <span class="font-bold text-[#f3f2e7] text-xs max-w-[100px] sm:max-w-none truncate" :title="user?.username"
                            >@{{ user?.username }}</span
                        >
                        <button
                            class="rounded-lg p-1.5 text-[#f3f2e7]/50 hover:bg-rose-500/20 hover:text-rose-400 transition"
                            title="Keluar"
                            @click="logout"
                        >
                            <svg
                                class="h-4 w-4"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </nav>
        </header>

        <!-- KONTEN UTAMA DENGAN GERBANG LOKASI WAJIB -->
        <main class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 py-6 pb-12 pb-safe">
            <!-- FLASH MESSAGE -->
            <p
                v-if="flash"
                class="mb-4 rounded-xl border border-[#0d685b] bg-[#0d685b]/30 px-4 py-2.5 text-xs font-semibold text-[#f3f2e7] shadow-xs"
            >
                {{ flash }}
            </p>

            <!-- GATEWAY: BLOKIR HALAMAN JIKA LOKASI BELUM AKTIF / BELUM DIIZINKAN -->
            <div
                v-if="locationStatus !== 'granted'"
                class="my-8 mx-auto max-w-md overflow-hidden rounded-3xl border border-[#0d685b]/30 bg-[#1c2a25] p-8 text-center shadow-2xl"
            >
                <!-- ICON RADAR GPS -->
                <div
                    class="relative mx-auto flex h-20 w-20 items-center justify-center rounded-3xl bg-amber-500/20 text-3xl text-amber-300 border border-amber-500/30"
                >
                    <span class="animate-bounce">📍</span>
                    <span
                        class="absolute inline-flex h-full w-full animate-ping rounded-3xl bg-amber-400 opacity-20"
                    ></span>
                </div>

                <h2 class="mt-5 text-xl font-black text-[#f3f2e7]">
                    Wajib Aktifkan Lokasi (GPS)
                </h2>

                <p class="mt-2 text-xs text-[#f3f2e7]/70 leading-relaxed">
                    Untuk menjamin kelancaran tugas kurir dan memungkinkan
                    pembeli memantau pergerakan pengiriman secara realtime,
                    <strong class="text-[#f3f2e7]">Anda wajib mengaktifkan GPS perangkat</strong> dan
                    mengizinkan akses lokasi pada browser sebelum dapat membuka
                    panel kurir.
                </p>

                <!-- PESAN ERROR JIKA DITOLAK -->
                <div
                    v-if="errorMessage"
                    class="mt-4 rounded-xl bg-rose-950/40 border border-rose-500/40 p-3 text-left text-xs text-rose-200"
                >
                    <p class="font-bold">⚠️ Perhatian:</p>
                    <p class="mt-0.5">{{ errorMessage }}</p>
                    <p class="mt-1 text-[11px] text-rose-300">
                        *Jika terblokir: Klik ikon gembok / pengaturan situs di
                        bilah alamat browser, pilih
                        <strong>"Izinkan Lokasi"</strong>, lalu klik tombol di
                        bawah.
                    </p>
                </div>

                <!-- INFO JIKA DI BROWSER HP DENGAN HTTP NON-HTTPS -->
                <div
                    v-if="!isSecure"
                    class="mt-3 rounded-xl bg-amber-950/40 border border-amber-500/30 p-2.5 text-left text-[11px] text-amber-200"
                >
                    <p class="font-bold">📱 Tips Browser HP:</p>
                    <p class="mt-0.5">
                        Jika browser ponsel tidak memunculkan popup izin GPS (karena koneksi HTTP biasa), silakan gunakan tombol <strong>"Masuk dengan Lokasi Toko"</strong> untuk langsung mengakses tugas pengantaran.
                    </p>
                </div>

                <!-- TOMBOL AKTIVASI LOKASI -->
                <div class="mt-6 space-y-3">
                    <button
                        type="button"
                        class="w-full rounded-2xl bg-[#0d685b] hover:bg-[#117c6d] active:scale-95 py-3.5 px-4 text-xs sm:text-sm font-bold text-[#f3f2e7] shadow-xl transition flex items-center justify-center gap-2 cursor-pointer touch-manipulation select-none"
                        @click="requestLocation"
                    >
                        <span
                            v-if="isRequesting"
                            class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"
                        ></span>
                        <span>{{
                            isRequesting
                                ? "Mencari Titik GPS (Harap Tunggu)..."
                                : "Aktifkan & Izinkan Lokasi Sekarang 📍"
                        }}</span>
                    </button>

                    <!-- TOMBOL OPSI CADANGAN (Sangat berguna di iPhone/Android saat koneksi HTTP / sinyal lemah) -->
                    <button
                        type="button"
                        class="w-full rounded-xl border border-amber-500/40 bg-amber-500/10 hover:bg-amber-500/20 py-2.5 px-4 text-xs font-bold text-amber-200 transition active:scale-95 flex items-center justify-center gap-2 cursor-pointer touch-manipulation select-none"
                        @click="useFallbackLocation"
                    >
                        <span>🏪</span>
                        <span>Masuk dengan Lokasi Toko (Mode Cepat)</span>
                    </button>

                    <button
                        type="button"
                        class="w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] py-2.5 text-xs font-semibold text-[#f3f2e7]/80 hover:bg-[#0d685b]/20 transition cursor-pointer"
                        @click="logout"
                    >
                        Keluar dari Akun
                    </button>
                </div>
            </div>

            <!-- HALAMAN KURIR TERBUKA JIKA LOKASI SUDAH AKTIF -->
            <div v-else>
                <slot />
            </div>
        </main>
    </div>
</template>

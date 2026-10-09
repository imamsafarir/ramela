<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, inject, onMounted, onUnmounted, ref, watch } from 'vue';
import CourierLayout from '../../Layouts/CourierLayout.vue';
import DeliveryMap from '../../Components/DeliveryMap.vue';
import { fmtDate, rupiah } from '../../utils/format';

defineOptions({ layout: CourierLayout });

const props = defineProps({
    activeDelivery: Object,
    availableOrders: Array,
    history: Array,
    stores: {
        type: Array,
        default: () => [],
    },
});

// Filter & Pencarian Orderan Tersedia
const searchAvailable = ref('');
const storeFilter = ref('');

const filteredAvailableOrders = computed(() => {
    return (props.availableOrders || []).filter((o) => {
        const matchesStore = !storeFilter.value || String(o.store_id) === String(storeFilter.value);
        const q = searchAvailable.value.toLowerCase().trim();
        const matchesQ = !q ||
            o.invoice_number?.toLowerCase().includes(q) ||
            o.recipient_name?.toLowerCase().includes(q) ||
            o.shipping_address?.toLowerCase().includes(q) ||
            o.recipient_phone?.includes(q);
        return matchesStore && matchesQ;
    });
});

const formatWeight = (w) => {
    const num = Number(w || 1000);
    return num >= 1000
        ? (num / 1000).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + ' kg'
        : num.toLocaleString('id-ID') + ' g';
};

// Hitung jarak garis lurus haversine (km)
const computeDistanceKm = (lat1, lon1, lat2, lon2) => {
    if (!lat1 || !lon1 || !lat2 || !lon2) return null;
    const R = 6371; // Radius bumi dalam km
    const dLat = ((lat2 - lat1) * Math.PI) / 180;
    const dLon = ((lon2 - lon1) * Math.PI) / 180;
    const a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos((lat1 * Math.PI) / 180) *
            Math.cos((lat2 * Math.PI) / 180) *
            Math.sin(dLon / 2) *
            Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return Number((R * c).toFixed(1));
};

// Estimasi durasi tempuh motor (asumsi kecepatan rata-rata perkotaan 30 km/jam + buffer 3-5 menit)
const estimateDurationText = (lat1, lon1, lat2, lon2) => {
    const km = computeDistanceKm(lat1, lon1, lat2, lon2);
    if (!km) return null;
    // Jarak jalan raya perkotaan biasanya ~1.3x jarak garis lurus
    const roadKm = km * 1.3;
    const minutes = Math.max(5, Math.round((roadKm / 30) * 60) + 3);
    if (minutes < 60) {
        return `± ${minutes} menit (${km} km)`;
    }
    const hours = Math.floor(minutes / 60);
    const remainingMins = minutes % 60;
    return `± ${hours} jam ${remainingMins > 0 ? remainingMins + ' mnt' : ''} (${km} km)`;
};

// URL navigasi Google Maps
const getGoogleMapsDirUrl = (destLat, destLng, originLat = null, originLng = null) => {
    if (!destLat || !destLng) return '#';
    if (originLat && originLng) {
        return `https://www.google.com/maps/dir/?api=1&origin=${originLat},${originLng}&destination=${destLat},${destLng}&travelmode=driving`;
    }
    return `https://www.google.com/maps/dir/?api=1&destination=${destLat},${destLng}&travelmode=driving`;
};

// Koordinat yang diinjeksi dari CourierLayout (sudah dipastikan GPS aktif)
const layoutCoords = inject('courierCoords', null);

// Selaraskan koordinat lokal jika layout berhasil mendapatkan GPS baru
watch(
    () => layoutCoords?.value,
    (coords) => {
        if (coords?.lat && coords?.lng) {
            currentCoords.value = { lat: coords.lat, lng: coords.lng };
        }
    },
    { immediate: true, deep: true }
);

// Modal & Unggah Foto State
const showCamera = ref(false);
const cameraAction = ref(''); // 'pickup' | 'dropoff'
const uploadTab = ref('camera'); // 'camera' | 'file'
const videoElement = ref(null);
const canvasElement = ref(null);
const fileInputRef = ref(null);
let mediaStream = null;
const cameraError = ref('');
const capturedBlob = ref(null);
const capturedPreview = ref('');
const selectedFile = ref(null);

// Geo location state lokal
const currentCoords = ref({ lat: null, lng: null });
let watchId = null;
let syncInterval = null;

// Ambil koordinat efektif (prioritas: GPS live, inject layout, atau koordinat tersimpan)
const getEffectiveCoords = () => {
    if (currentCoords.value.lat && currentCoords.value.lng) {
        return currentCoords.value;
    }
    if (layoutCoords?.value?.lat && layoutCoords?.value?.lng) {
        return layoutCoords.value;
    }
    if (props.activeDelivery?.current_lat && props.activeDelivery?.current_lng) {
        return {
            lat: props.activeDelivery.current_lat,
            lng: props.activeDelivery.current_lng,
        };
    }
    return { lat: null, lng: null };
};

const displayCoords = computed(() => getEffectiveCoords());

// Buka modal dengan mode awal tertentu ('camera' atau 'file')
const openUploadModal = async (action, defaultTab = 'camera') => {
    cameraAction.value = action;
    uploadTab.value = defaultTab;
    cameraError.value = '';
    capturedBlob.value = null;
    capturedPreview.value = '';
    selectedFile.value = null;
    showCamera.value = true;

    // Perbarui GPS saat membuka modal
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                currentCoords.value = {
                    lat: pos.coords.latitude,
                    lng: pos.coords.longitude,
                };
            },
            (err) => console.warn('Gagal membaca GPS modal:', err.message),
            { enableHighAccuracy: true, timeout: 8000 }
        );
    }

    if (defaultTab === 'camera') {
        await startCamera();
    } else {
        stopCamera();
    }
};

// Ganti tab kamera / file
const switchTab = async (tab) => {
    uploadTab.value = tab;
    if (tab === 'camera') {
        await startCamera();
    } else {
        stopCamera();
    }
};

const startCamera = async () => {
    cameraError.value = '';
    try {
        stopCamera();
        mediaStream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } },
            audio: false,
        });
        if (videoElement.value) {
            videoElement.value.srcObject = mediaStream;
        }
    } catch (e) {
        cameraError.value = 'Tidak dapat mengakses kamera web. Silakan gunakan tab "Unggah File / Galeri" untuk memilih foto.';
    }
};

const stopCamera = () => {
    if (mediaStream) {
        mediaStream.getTracks().forEach((t) => t.stop());
        mediaStream = null;
    }
};

const capturePhoto = () => {
    if (!videoElement.value || !canvasElement.value) return;

    const video = videoElement.value;
    const canvas = canvasElement.value;
    canvas.width = video.videoWidth || 640;
    canvas.height = video.videoHeight || 480;

    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    canvas.toBlob((blob) => {
        capturedBlob.value = blob;
        capturedPreview.value = URL.createObjectURL(blob);
        stopCamera();
    }, 'image/jpeg', 0.85);
};

// Handle file yang dipilih secara manual dari galeri atau penyimpanan
const onManualFileSelected = (e) => {
    const file = e.target.files?.[0];
    if (!file) return;

    // Validasi tipe file
    if (!file.type.startsWith('image/')) {
        alert('Harap pilih file gambar (JPG, PNG, atau WebP).');
        return;
    }

    // Validasi ukuran file (maks 5MB)
    if (file.size > 5 * 1024 * 1024) {
        alert('Ukuran file terlalu besar. Maksimal 5MB.');
        return;
    }

    selectedFile.value = file;
    capturedBlob.value = file;
    capturedPreview.value = URL.createObjectURL(file);
    stopCamera();
};

const resetCapturedPhoto = () => {
    capturedPreview.value = '';
    capturedBlob.value = null;
    selectedFile.value = null;
    if (fileInputRef.value) fileInputRef.value.value = '';
    if (uploadTab.value === 'camera') {
        startCamera();
    }
};

const closeCameraModal = () => {
    stopCamera();
    showCamera.value = false;
    capturedBlob.value = null;
    capturedPreview.value = '';
    selectedFile.value = null;
    if (fileInputRef.value) fileInputRef.value.value = '';
};

// Form upload foto
const uploadForm = useForm({
    photo: null,
    latitude: null,
    longitude: null,
});

const submitPhoto = () => {
    if (!capturedBlob.value) return;

    const coords = getEffectiveCoords();

    // Buat objek File valid baik dari kamera blob maupun input file
    const photoFile = capturedBlob.value instanceof File
        ? capturedBlob.value
        : new File([capturedBlob.value], `${cameraAction.value}_${Date.now()}.jpg`, {
            type: 'image/jpeg',
        });

    uploadForm.photo = photoFile;
    uploadForm.latitude = coords.lat;
    uploadForm.longitude = coords.lng;

    const endpoint = cameraAction.value === 'pickup' ? '/kurir/pickup' : '/kurir/dropoff';

    uploadForm.post(endpoint, {
        onSuccess: () => {
            closeCameraModal();
            uploadForm.reset();
        },
    });
};

// Klaim order
const claimOrder = (invoice) => {
    if (confirm(`Ambil tugas pengantaran ${invoice}?`)) {
        router.post(`/kurir/tugas/${invoice}/ambil`);
    }
};

// Lepas order (kembalikan ke antrean jika belum pickup)
const releaseOrder = (invoice) => {
    if (confirm(`Apakah Anda yakin ingin melepas tugas ${invoice}? Order ini akan dikembalikan ke antrean pengantaran agar dapat diambil oleh kurir lain.`)) {
        router.post(`/kurir/tugas/${invoice}/lepas`);
    }
};

// Manual refresh GPS dengan akurasi tinggi
const isRefreshingGps = ref(false);
const refreshGpsLocation = () => {
    if (!navigator.geolocation) return;
    isRefreshingGps.value = true;
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            isRefreshingGps.value = false;
            currentCoords.value = {
                lat: pos.coords.latitude,
                lng: pos.coords.longitude,
            };
            if (props.activeDelivery) {
                window.axios.post('/kurir/location', {
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude,
                }).catch((err) => console.warn('Sync GPS manual gagal:', err));
            }
        },
        (err) => {
            isRefreshingGps.value = false;
            console.warn('Refresh GPS gagal:', err.message);
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
};

// Background GPS Tracker saat ada tugas aktif
const startGpsTracking = () => {
    if (!navigator.geolocation) return;

    // Ambil posisi GPS fisik saat ini secara instan
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            currentCoords.value = {
                lat: pos.coords.latitude,
                lng: pos.coords.longitude,
            };
            if (props.activeDelivery) {
                window.axios.post('/kurir/location', {
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude,
                }).catch((err) => console.warn('Sync initial GPS gagal:', err));
            }
        },
        (err) => console.warn('GPS getCurrentPosition awal:', err.message),
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );

    if (!watchId) {
        watchId = navigator.geolocation.watchPosition(
            (pos) => {
                currentCoords.value = {
                    lat: pos.coords.latitude,
                    lng: pos.coords.longitude,
                };
            },
            (err) => console.warn('GPS tracking error:', err.message),
            { enableHighAccuracy: true, maximumAge: 3000, timeout: 10000 }
        );
    }

    if (!syncInterval) {
        syncInterval = setInterval(() => {
            const coords = getEffectiveCoords();
            if (coords.lat && coords.lng && props.activeDelivery) {
                window.axios.post('/kurir/location', {
                    latitude: coords.lat,
                    longitude: coords.lng,
                }).catch((err) => console.warn('Sync location gagal:', err));
            }
        }, 5000);
    }
};

// Kalibrasi posisi kurir agar selalu akurat di koridor rute toko - tujuan
const calibrateToRoute = (ratio = 0.6) => {
    if (!props.activeDelivery) return;
    const sLat = Number(props.activeDelivery.transaction.store_latitude || -6.989720);
    const sLng = Number(props.activeDelivery.transaction.store_longitude || 110.421930);
    const dLat = Number(props.activeDelivery.transaction.shipping_latitude || -6.992440);
    const dLng = Number(props.activeDelivery.transaction.shipping_longitude || 110.428450);

    const newLat = Number((sLat + (dLat - sLat) * ratio).toFixed(6));
    const newLng = Number((sLng + (dLng - sLng) * ratio).toFixed(6));

    // Langsung update posisi lokal agar marker di peta langsung berpindah secara realtime
    currentCoords.value = { lat: newLat, lng: newLng };

    window.axios.post('/kurir/location', {
        latitude: newLat,
        longitude: newLng,
    }).catch((err) => console.warn('Gagal kalibrasi lokasi:', err));
};

const stopGpsTracking = () => {
    if (watchId !== null) {
        navigator.geolocation.clearWatch(watchId);
        watchId = null;
    }
    if (syncInterval) {
        clearInterval(syncInterval);
        syncInterval = null;
    }
};

onMounted(() => {
    if (props.activeDelivery) {
        startGpsTracking();
    }
});

onUnmounted(() => {
    stopCamera();
    stopGpsTracking();
});
</script>

<template>
    <Head title="Panel Kurir" />

    <!-- TUGAS AKTIF KURIR -->
    <div v-if="activeDelivery" class="rounded-2xl border-2 border-[#0d685b] bg-[#1c2a25] p-4.5 sm:p-6 shadow-xl text-[#f3f2e7]">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[#0d685b]/30 pb-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-[#0d685b]/40 px-3 py-1 text-xs font-semibold text-[#f3f2e7] border border-[#0d685b]/60 uppercase tracking-wider">
                        <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        Tugas Aktif ({{ activeDelivery.status === 'waiting_pickup' ? 'Ambil Barang di Toko' : 'Dalam Perjalanan' }})
                    </span>
                </div>
                <h2 class="mt-2 text-2xl font-black text-[#f3f2e7] tracking-tight">{{ activeDelivery.transaction.invoice_number }}</h2>
                <p class="text-sm text-[#f3f2e7]/70 font-medium">🏪 Toko: <span class="text-[#f3f2e7]">{{ activeDelivery.transaction.store }}</span></p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <!-- Tahap 1: Ambil Barang (Pick-up) -->
                <template v-if="activeDelivery.status === 'waiting_pickup'">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-[#0d685b] px-4 py-2.5 text-xs sm:text-sm font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 hover:bg-[#0d685b]/90 transition active:scale-95"
                        @click="openUploadModal('pickup', 'camera')"
                    >
                        <span>📸</span>
                        <span>Foto Pickup (Kamera)</span>
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-4 py-2.5 text-xs sm:text-sm font-bold text-[#f3f2e7] hover:border-[#0d685b] hover:bg-[#1c2a25] transition active:scale-95"
                        @click="openUploadModal('pickup', 'file')"
                    >
                        <span>📁</span>
                        <span>Unggah Manual (Galeri)</span>
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-rose-500/40 bg-rose-500/10 px-3.5 py-2.5 text-xs sm:text-sm font-bold text-rose-300 hover:bg-rose-500/20 hover:border-rose-500 transition active:scale-95"
                        title="Kembalikan order ke antrean jika belum mengambil barang"
                        @click="releaseOrder(activeDelivery.transaction.invoice_number)"
                    >
                        <span>✕</span>
                        <span>Lepas Tugas</span>
                    </button>
                </template>

                <!-- Tahap 3: Selesaikan (Drop-off) -->
                <template v-if="activeDelivery.status === 'en_route'">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-xs sm:text-sm font-bold text-white shadow-lg shadow-emerald-900/30 hover:bg-emerald-500 transition active:scale-95 cursor-pointer"
                        @click="openUploadModal('dropoff')"
                    >
                        <span>✅</span>
                        <span>Selesaikan</span>
                    </button>
                </template>
            </div>
        </div>

        <div class="mt-5 grid gap-6 lg:grid-cols-2">
            <div class="space-y-4">
                <div class="rounded-xl border border-[#0d685b]/30 bg-[#131d1a] p-4.5 space-y-3">
                    <div class="flex items-center justify-between border-b border-[#0d685b]/20 pb-2">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-400 flex items-center gap-1.5">
                            <span>📍</span> Titik Tujuan Pengiriman
                        </h3>
                        <span
                            v-if="estimateDurationText(activeDelivery.transaction.store_latitude, activeDelivery.transaction.store_longitude, activeDelivery.transaction.shipping_latitude, activeDelivery.transaction.shipping_longitude)"
                            class="inline-flex items-center gap-1 rounded-full bg-emerald-950/60 border border-emerald-500/40 px-2.5 py-0.5 text-[11px] font-bold text-emerald-300"
                        >
                            🛵 {{ estimateDurationText(activeDelivery.transaction.store_latitude, activeDelivery.transaction.store_longitude, activeDelivery.transaction.shipping_latitude, activeDelivery.transaction.shipping_longitude) }} dari Toko
                        </span>
                    </div>

                    <!-- Penerima & Kontak -->
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <p class="text-base font-bold text-[#f3f2e7]">
                                {{ activeDelivery.transaction.recipient_name }}
                            </p>
                            <p class="text-xs text-[#f3f2e7]/70">
                                {{ activeDelivery.transaction.recipient_phone }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a
                                v-if="activeDelivery.transaction.recipient_phone"
                                :href="`tel:${activeDelivery.transaction.recipient_phone}`"
                                class="inline-flex items-center gap-1 rounded-lg border border-[#0d685b]/40 bg-[#1c2a25] px-2.5 py-1 text-xs font-semibold text-sky-300 hover:bg-[#131d1a] transition"
                            >
                                📞 Telepon
                            </a>
                            <a
                                v-if="activeDelivery.transaction.recipient_phone"
                                :href="`https://wa.me/${activeDelivery.transaction.recipient_phone.replace(/^0/, '62').replace(/[^0-9]/g, '')}`"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1 rounded-lg bg-emerald-600/80 hover:bg-emerald-600 px-2.5 py-1 text-xs font-bold text-white transition"
                            >
                                💬 WhatsApp
                            </a>
                        </div>
                    </div>

                    <!-- Alamat Lengkap & Wilayah -->
                    <div>
                        <p class="whitespace-pre-line text-sm text-[#f3f2e7]/90 leading-relaxed font-medium">
                            {{ activeDelivery.transaction.shipping_address }}
                        </p>
                        <p
                            v-if="activeDelivery.transaction.shipping_district || activeDelivery.transaction.shipping_city"
                            class="mt-1 text-xs text-[#f3f2e7]/60"
                        >
                            {{ [activeDelivery.transaction.shipping_district, activeDelivery.transaction.shipping_city, activeDelivery.transaction.shipping_postal_code].filter(Boolean).join(', ') }}
                        </p>
                    </div>

                    <p v-if="activeDelivery.transaction.note" class="text-xs italic text-amber-300/90 bg-amber-500/10 p-2 rounded-lg border border-amber-500/20">
                        Catatan Penerima: {{ activeDelivery.transaction.note }}
                    </p>

                    <!-- Tombol Navigasi Google Maps -->
                    <div class="pt-1 border-t border-[#0d685b]/20 flex flex-wrap items-center gap-2">
                        <a
                            :href="getGoogleMapsDirUrl(
                                activeDelivery.transaction.shipping_latitude,
                                activeDelivery.transaction.shipping_longitude,
                                displayCoords.lat || activeDelivery.transaction.store_latitude,
                                displayCoords.lng || activeDelivery.transaction.store_longitude
                            )"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 text-xs font-bold shadow-md shadow-blue-900/30 transition active:scale-95"
                        >
                            <span>🗺️</span>
                            <span>Buka Navigasi di Google Maps</span>
                            <span class="text-[10px] opacity-75">↗</span>
                        </a>
                        <span class="text-[11px] text-[#f3f2e7]/50">
                            Peta utama di sebelah kanan menggunakan Leaflet OpenStreetMap.
                        </span>
                    </div>
                </div>

                <div class="rounded-xl border border-[#0d685b]/20 bg-[#131d1a] p-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-[#0d685b]">Daftar Barang Pesanan</h3>
                    <ul class="mt-2 space-y-1 text-sm text-[#f3f2e7]/90">
                        <li v-for="(it, i) in activeDelivery.transaction.details" :key="i" class="flex justify-between items-center py-1 border-b border-[#0d685b]/10 last:border-0">
                            <span>📦 {{ it.product_name }}</span>
                            <span class="font-bold text-[#f3f2e7]">× {{ it.quantity }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Peta Rute & Titik Kurir -->
            <div class="rounded-xl border border-[#0d685b]/30 overflow-hidden shadow-inner">
                <DeliveryMap
                    :store-lat="activeDelivery.transaction.store_latitude"
                    :store-lng="activeDelivery.transaction.store_longitude"
                    :store-name="activeDelivery.transaction.store"
                    :store-address="activeDelivery.transaction.store_address"
                    :courier-lat="displayCoords.lat || activeDelivery.current_lat"
                    :courier-lng="displayCoords.lng || activeDelivery.current_lng"
                    :dest-lat="activeDelivery.transaction.shipping_latitude"
                    :dest-lng="activeDelivery.transaction.shipping_longitude"
                    :destination-address="activeDelivery.transaction.shipping_address"
                    :recipient-name="activeDelivery.transaction.recipient_name"
                    :delivery-status="activeDelivery.status"
                    :route-history="activeDelivery.locations || []"
                    @refresh="router.reload({ preserveScroll: true })"
                />
            </div>
        </div>
    </div>

    <!-- DAFTAR TUGAS TERSEDIA -->
    <div class="mt-8">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <h2 class="text-xl font-bold text-[#f3f2e7]">Tugas Pengantaran Siap Diambil</h2>
                <p class="text-xs text-[#f3f2e7]/60">Pilih sendiri orderan yang ingin Anda antar sesuai rute dan ketersediaan Anda.</p>
            </div>
            <span class="rounded-full bg-[#0d685b]/30 border border-[#0d685b]/50 px-3 py-1 text-xs font-bold text-[#f3f2e7]">
                {{ filteredAvailableOrders.length }} dari {{ availableOrders.length }} Pesanan
            </span>
        </div>

        <!-- Filter & Search Bar -->
        <div class="mb-4 grid gap-3 sm:grid-cols-3">
            <div class="sm:col-span-2 relative">
                <input
                    v-model="searchAvailable"
                    type="text"
                    placeholder="Cari no invoice, nama penerima, no HP, atau alamat tujuan..."
                    class="w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-4 py-2.5 pl-10 text-xs sm:text-sm text-[#f3f2e7] placeholder-[#f3f2e7]/40 focus:border-[#0d685b] focus:outline-none focus:ring-1 focus:ring-[#0d685b]"
                />
                <span class="absolute left-3.5 top-3 text-xs sm:text-sm text-[#f3f2e7]/40">🔍</span>
            </div>
            <div>
                <select
                    v-model="storeFilter"
                    class="w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2.5 text-xs sm:text-sm text-[#f3f2e7] focus:border-[#0d685b] focus:outline-none focus:ring-1 focus:ring-[#0d685b]"
                >
                    <option value="">Semua Toko ({{ availableOrders.length }})</option>
                    <option v-for="st in stores" :key="st.id" :value="st.id">{{ st.name }}</option>
                </select>
            </div>
        </div>

        <!-- Grid Orderan Tersedia -->
        <div v-if="filteredAvailableOrders.length" class="grid gap-4 md:grid-cols-2">
            <div
                v-for="order in filteredAvailableOrders"
                :key="order.invoice_number"
                class="flex flex-col justify-between rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-5 shadow-lg hover:border-[#0d685b]/70 transition"
            >
                <div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="rounded-full bg-amber-500/20 border border-amber-500/40 px-2.5 py-0.5 text-xs font-semibold text-amber-300">Siap Dikirim</span>
                        <span class="text-xs text-[#f3f2e7]/50">{{ fmtDate(order.created_at) }}</span>
                    </div>

                    <div class="mt-2.5 flex items-baseline justify-between gap-2">
                        <h3 class="text-lg font-bold text-[#f3f2e7] tracking-tight">{{ order.invoice_number }}</h3>
                        <span class="text-sm font-bold text-emerald-400">{{ rupiah(order.final_amount || order.total_amount) }}</span>
                    </div>

                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs font-medium text-[#f3f2e7]/70">
                        <span>🏪 {{ order.store }}</span>
                        <span>•</span>
                        <span>⚖️ {{ formatWeight(order.total_weight) }}</span>
                        <span>•</span>
                        <span>📦 {{ order.items_count }} jenis barang</span>
                    </div>

                    <!-- Rincian Produk -->
                    <div v-if="order.details && order.details.length" class="mt-3 rounded-xl bg-[#131d1a]/80 border border-[#0d685b]/20 p-2.5 text-xs">
                        <p class="font-bold text-[#f3f2e7]/80 mb-1">Rincian Paket:</p>
                        <div class="space-y-1">
                            <div v-for="(d, i) in order.details" :key="i" class="flex justify-between text-[#f3f2e7]/70">
                                <span class="truncate pr-2">- {{ d.product_name }}</span>
                                <span class="font-semibold text-[#f3f2e7]">× {{ d.quantity }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Informasi Penerima & Alamat -->
                    <div class="mt-3.5 border-t border-[#0d685b]/20 pt-3 text-sm text-[#f3f2e7]/80">
                        <div class="flex items-center justify-between">
                            <p class="font-bold text-[#f3f2e7]">👤 {{ order.recipient_name }}</p>
                            <div class="flex items-center gap-2">
                                <a
                                    v-if="order.recipient_phone"
                                    :href="`tel:${order.recipient_phone}`"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-sky-400 hover:underline"
                                    title="Hubungi Penerima"
                                >
                                    📞 Telp
                                </a>
                                <a
                                    v-if="order.recipient_phone"
                                    :href="`https://wa.me/${order.recipient_phone.replace(/^0/, '62').replace(/[^0-9]/g, '')}`"
                                    target="_blank"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-400 hover:underline"
                                    title="Chat WhatsApp"
                                >
                                    💬 WA
                                </a>
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-[#f3f2e7]/70 leading-relaxed">
                            📍 {{ order.shipping_address }}
                            <span v-if="order.shipping_district || order.shipping_city" class="block text-[11px] text-[#f3f2e7]/50 mt-0.5">
                                {{ [order.shipping_district, order.shipping_city, order.shipping_postal_code].filter(Boolean).join(', ') }}
                            </span>
                        </p>
                        <p v-if="order.note" class="mt-2 text-xs italic text-amber-300/80 bg-amber-500/10 p-1.5 rounded border border-amber-500/20">
                            Catatan: {{ order.note }}
                        </p>

                        <!-- Estimasi Waktu & Lokasi Google Maps -->
                        <div class="mt-2.5 pt-2 border-t border-[#0d685b]/20 flex flex-wrap items-center justify-between gap-2">
                            <span
                                v-if="estimateDurationText(order.store_latitude, order.store_longitude, order.shipping_latitude, order.shipping_longitude)"
                                class="inline-flex items-center gap-1 rounded-md bg-emerald-950/70 border border-emerald-500/30 px-2 py-0.5 text-[10px] font-semibold text-emerald-300"
                            >
                                🛵 Estimasi Toko: {{ estimateDurationText(order.store_latitude, order.store_longitude, order.shipping_latitude, order.shipping_longitude) }}
                            </span>
                            <span v-else class="text-[10px] text-[#f3f2e7]/40">
                                📍 Koordinat tujuan tersedia
                            </span>

                            <a
                                v-if="order.shipping_latitude && order.shipping_longitude"
                                :href="getGoogleMapsDirUrl(order.shipping_latitude, order.shipping_longitude, order.store_latitude, order.store_longitude)"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center gap-1 text-[11px] font-bold text-blue-400 hover:text-blue-300 underline"
                                title="Lihat rute di Google Maps"
                            >
                                🗺️ Buka Rute Google Maps ↗
                            </a>
                        </div>
                    </div>
                </div>

                <button
                    :disabled="!!activeDelivery"
                    class="mt-5 w-full rounded-xl bg-[#0d685b] py-2.5 text-sm font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 transition hover:bg-[#0d685b]/90 disabled:cursor-not-allowed disabled:bg-[#131d1a] disabled:text-[#f3f2e7]/30 disabled:border disabled:border-[#0d685b]/20 active:scale-95"
                    @click="claimOrder(order.invoice_number)"
                >
                    {{ activeDelivery ? 'Selesaikan tugas aktif dulu' : '🛵 Pilih & Ambil Orderan Ini' }}
                </button>
            </div>
        </div>
        <div v-else class="rounded-2xl border border-[#0d685b]/20 bg-[#1c2a25] p-8 text-center text-sm text-[#f3f2e7]/60">
            {{ availableOrders.length ? '🔍 Tidak ada pesanan yang sesuai dengan kata kunci / filter Anda.' : '🍃 Tidak ada tugas pengantaran yang tersedia saat ini.' }}
        </div>
    </div>

    <!-- RIWAYAT PENGANTARAN -->
    <div v-if="history.length" class="mt-10 mb-8">
        <h2 class="text-lg font-bold text-[#f3f2e7]">Riwayat Pengantaran Selesai</h2>
        <div class="mt-3 overflow-x-auto rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-lg">
            <table class="w-full text-left text-sm text-[#f3f2e7]">
                <thead class="border-b border-[#0d685b]/30 bg-[#131d1a] text-xs font-bold uppercase tracking-wider text-[#f3f2e7]/70">
                    <tr>
                        <th class="w-12 px-3 py-3 text-center">#</th>
                        <th class="px-4 py-3">Invoice</th>
                        <th class="px-4 py-3">Toko</th>
                        <th class="px-4 py-3">Penerima</th>
                        <th class="px-4 py-3">Alamat</th>
                        <th class="px-4 py-3">Waktu Selesai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#0d685b]/20">
                    <tr v-for="(h, idx) in history" :key="h.invoice_number" class="hover:bg-[#131d1a]/50 transition">
                        <td class="px-3 py-3 text-center font-bold text-xs text-[#f3f2e7]/50">{{ idx + 1 }}</td>
                        <td class="px-4 py-3 font-semibold text-[#f3f2e7]">{{ h.invoice_number }}</td>
                        <td class="px-4 py-3 text-sm text-[#f3f2e7]/80">{{ h.store }}</td>
                        <td class="px-4 py-3 text-sm text-[#f3f2e7]/80">{{ h.recipient_name }}</td>
                        <td class="px-4 py-3 max-w-xs truncate text-xs text-[#f3f2e7]/60">{{ h.shipping_address }}</td>
                        <td class="px-4 py-3 text-xs text-[#f3f2e7]/60">{{ fmtDate(h.completed_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL FOTO BUKTI PENGANTARAN (KAMERA & GALERI) -->
    <div v-if="showCamera" class="fixed inset-0 z-50 flex items-center justify-center bg-[#17231f]/85 p-4 backdrop-blur-md">
        <div class="w-full max-w-lg overflow-hidden rounded-2xl bg-[#1c2a25] shadow-2xl border border-[#0d685b]/40 text-[#f3f2e7]">
            <!-- Modal Header -->
            <div class="border-b border-[#0d685b]/30 px-5 py-4 bg-[#131d1a]">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-[#f3f2e7]">
                        {{ cameraAction === 'pickup' ? '📸 Bukti Pengambilan Barang (Pickup)' : '✅ Bukti Serah Terima Barang (Dropoff)' }}
                    </h3>
                    <button class="rounded-lg p-1 text-[#f3f2e7]/60 hover:bg-[#1c2a25] hover:text-[#f3f2e7] transition" @click="closeCameraModal">
                        ✕
                    </button>
                </div>
                <p class="mt-1 text-xs text-[#f3f2e7]/70">
                    {{ cameraAction === 'pickup'
                        ? 'Unggah foto barang pesanan di toko sebelum memulai perjalanan pengantaran.'
                        : 'Unggah foto bukti barang telah diterima oleh pelanggan di lokasi tujuan.' }}
                </p>
            </div>

            <!-- Tab Switcher (Kamera vs Galeri) -->
            <div v-if="!capturedPreview" class="border-b border-[#0d685b]/30 bg-[#131d1a] p-2">
                <div class="flex rounded-xl bg-[#17231f] p-1 border border-[#0d685b]/20">
                    <button
                        type="button"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-lg py-2 text-xs font-bold transition"
                        :class="uploadTab === 'camera' ? 'bg-[#0d685b] text-[#f3f2e7] shadow-sm' : 'text-[#f3f2e7]/60 hover:text-[#f3f2e7]'"
                        @click="switchTab('camera')"
                    >
                        <span>📸</span>
                        <span>Jepret Kamera</span>
                    </button>
                    <button
                        type="button"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-lg py-2 text-xs font-bold transition"
                        :class="uploadTab === 'file' ? 'bg-[#0d685b] text-[#f3f2e7] shadow-sm' : 'text-[#f3f2e7]/60 hover:text-[#f3f2e7]'"
                        @click="switchTab('file')"
                    >
                        <span>📁</span>
                        <span>Unggah File / Galeri</span>
                    </button>
                </div>
            </div>

            <!-- Modal Body Content -->
            <div class="p-5">
                <!-- PREVIEW FOTO JIKA SUDAH DIPILIH / DIJEPRET -->
                <div v-if="capturedPreview" class="space-y-4">
                    <div class="overflow-hidden rounded-xl border border-[#0d685b]/40 bg-black text-center">
                        <img :src="capturedPreview" class="h-64 sm:h-72 w-full object-contain" alt="Preview Foto Bukti" />
                    </div>

                    <!-- Keterangan & Status GPS -->
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-[#131d1a] border border-[#0d685b]/20 p-3 text-xs">
                        <div class="flex items-center gap-2">
                            <span class="flex h-2 w-2 rounded-full bg-emerald-400"></span>
                            <span class="font-medium text-[#f3f2e7]">Foto siap dikirim</span>
                            <span v-if="selectedFile" class="text-[#f3f2e7]/50">
                                ({{ (selectedFile.size / 1024).toFixed(0) }} KB)
                            </span>
                        </div>
                        <div class="flex items-center gap-1 text-[11px] font-semibold text-[#f3f2e7]/80">
                            <span>📍 GPS:</span>
                            <span v-if="displayCoords.lat && displayCoords.lng" class="text-emerald-400">
                                {{ Number(displayCoords.lat).toFixed(5) }}, {{ Number(displayCoords.lng).toFixed(5) }}
                            </span>
                            <span v-else class="text-amber-400">
                                Menggunakan titik GPS saat ini
                            </span>
                        </div>
                    </div>

                    <!-- Tombol Aksi Foto -->
                    <div class="flex gap-3">
                        <button
                            type="button"
                            class="flex-1 rounded-xl border border-[#0d685b]/40 bg-[#131d1a] py-2.5 text-xs font-semibold text-[#f3f2e7] hover:bg-[#17231f] transition"
                            @click="resetCapturedPhoto"
                        >
                            🔄 Ganti / Foto Ulang
                        </button>
                        <button
                            type="button"
                            :disabled="uploadForm.processing"
                            class="flex-1 rounded-xl bg-[#0d685b] py-2.5 text-xs font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 hover:bg-[#0d685b]/90 transition disabled:opacity-50"
                            @click="submitPhoto"
                        >
                            <span v-if="uploadForm.processing">Mengunggah...</span>
                            <span v-else>
                                🚀 Konfirmasi & Kirim Bukti
                            </span>
                        </button>
                    </div>
                </div>

                <!-- JIKA BELUM ADA FOTO PREVIEW: PILIHAN INPUT -->
                <div v-else>
                    <!-- TAB 1: KAMERA WEBCAM / PERANGKAT -->
                    <div v-show="uploadTab === 'camera'" class="space-y-4">
                        <div class="relative overflow-hidden rounded-xl bg-black border border-[#0d685b]/30">
                            <video
                                ref="videoElement"
                                autoplay
                                playsinline
                                class="h-64 sm:h-72 w-full object-cover"
                            ></video>
                            <canvas ref="canvasElement" class="hidden"></canvas>
                        </div>

                        <div v-if="cameraError" class="rounded-xl border border-rose-500/40 bg-rose-950/40 p-3 text-xs text-rose-300">
                            <p class="font-bold">Akses Kamera Terkendala</p>
                            <p class="mt-0.5">{{ cameraError }}</p>
                            <button
                                type="button"
                                class="mt-2 text-xs font-bold text-rose-200 underline"
                                @click="switchTab('file')"
                            >
                                Klik di sini untuk unggah foto dari galeri HP →
                            </button>
                        </div>

                        <button
                            v-if="!cameraError"
                            type="button"
                            class="w-full rounded-xl bg-[#0d685b] py-3 text-center text-sm font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 hover:bg-[#0d685b]/90 active:scale-95 transition"
                            @click="capturePhoto"
                        >
                            📸 Jepret Foto Sekarang
                        </button>
                    </div>

                    <!-- TAB 2: UNGGAH MANUAL DARI GALERI / FILE -->
                    <div v-show="uploadTab === 'file'" class="space-y-4">
                        <div
                            class="group relative flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-[#0d685b]/40 bg-[#131d1a] p-8 text-center transition hover:border-[#0d685b] hover:bg-[#1c2a25] cursor-pointer"
                            @click="fileInputRef?.click()"
                        >
                            <input
                                ref="fileInputRef"
                                type="file"
                                accept="image/*"
                                class="hidden"
                                @change="onManualFileSelected"
                            />
                            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#0d685b]/30 text-2xl text-[#f3f2e7] transition group-hover:scale-110">
                                🖼️
                            </div>
                            <h4 class="mt-3 text-sm font-bold text-[#f3f2e7]">
                                Pilih Gambar dari Galeri atau File
                            </h4>
                            <p class="mt-1 text-xs text-[#f3f2e7]/60 max-w-xs">
                                Klik area ini untuk membuka galeri foto di perangkat Anda (format JPG, PNG, atau WebP, maks 5MB).
                            </p>
                            <button
                                type="button"
                                class="mt-4 rounded-xl bg-[#0d685b] px-4 py-2 text-xs font-bold text-[#f3f2e7] shadow hover:bg-[#0d685b]/90 transition"
                            >
                                📂 Buka Galeri Perangkat
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="flex items-center justify-between border-t border-[#0d685b]/20 bg-[#131d1a] px-5 py-3">
                <span class="text-[11px] text-[#f3f2e7]/40">
                    Sistem Ramela Express Courier
                </span>
                <button
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold text-[#f3f2e7]/70 hover:bg-[#1c2a25] hover:text-[#f3f2e7] transition"
                    @click="closeCameraModal"
                >
                    Batalkan
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref, watch } from 'vue';
import L from 'leaflet';
import axios from 'axios';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    midtrans: Object,
    google: Object,
    features: Object,
    stores: {
        type: Array,
        default: () => [],
    },
    webhookUrl: String,
    urls: Object,
});

const defaultStoreLocations = {
    eats: {
        address: 'Jl. Pandanaran No. 58, Mugassari, Semarang Selatan, Kota Semarang',
        latitude: -6.989720,
        longitude: 110.421930,
    },
    hampers: {
        address: 'Jl. Pemuda No. 142, Sekayu, Semarang Tengah, Kota Semarang',
        latitude: -6.973050,
        longitude: 110.428510,
    },
    beton: {
        address: 'Kawasan Industri Candi Blok 8 No. 12, Ngaliyan, Kota Semarang',
        latitude: -6.987540,
        longitude: 110.345020,
    },
};

const storeMeta = {
    eats: {
        badge: 'Kuliner',
        emoji: '🍲',
        color: '#0d685b',
        bgBadge: 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40',
    },
    hampers: {
        badge: 'Bingkisan',
        emoji: '🎁',
        color: '#d97706',
        bgBadge: 'bg-amber-500/20 text-amber-300 border border-amber-500/40',
    },
    beton: {
        badge: 'Ready Mix',
        emoji: '🏗️',
        color: '#2563eb',
        bgBadge: 'bg-blue-500/20 text-blue-300 border border-blue-500/40',
    },
};

const form = useForm({
    is_production: props.midtrans.is_production,
    merchant_id: '',
    client_key: '',
    server_key: '',
    google_maps_api_key: '',
    feature_blog: props.features.blog,
    feature_faq: props.features.faq,
    stores: (props.stores || []).map((s) => ({
        id: s.id,
        slug: s.slug,
        name: s.name,
        tagline: s.tagline || '',
        address: s.address || '',
        latitude: s.latitude !== null && s.latitude !== undefined ? s.latitude : '',
        longitude: s.longitude !== null && s.longitude !== undefined ? s.longitude : '',
    })),
});

const copied = ref(false);
const copyWebhook = () => {
    navigator.clipboard.writeText(props.webhookUrl);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
};

const submit = () => {
    form.put('/admin/pengaturan', {
        preserveScroll: true,
        onSuccess: () => form.reset('merchant_id', 'client_key', 'server_key', 'google_maps_api_key'),
    });
};

const inputClass = 'w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2.5 text-xs text-[#f3f2e7] shadow-sm placeholder:text-[#f3f2e7]/40 focus:border-[#0d685b] focus:outline-none';
const secrets = [
    ['merchant_id', 'Merchant ID'],
    ['client_key', 'Client Key'],
    ['server_key', 'Server Key'],
];

// Geocoding helper untuk nama jalan toko
const storeGeocoding = ref({});
const fetchStoreStreetName = async (index, lat, lng) => {
    if (!lat || !lng || isNaN(Number(lat)) || isNaN(Number(lng))) return;
    storeGeocoding.value[index] = true;
    try {
        const res = await axios.get('/api/location/reverse', {
            params: { lat: Number(lat), lng: Number(lng) },
        });
        if (res.data?.success && (res.data.formatted_address || res.data.street_name)) {
            form.stores[index].address = res.data.formatted_address || res.data.street_name;
        }
    } catch (e) {
        console.warn('Gagal mengambil nama jalan:', e);
    } finally {
        storeGeocoding.value[index] = false;
    }
};

// Geolocation helper
const geoLoading = ref({});
const getCurrentLocation = (index) => {
    if (!navigator.geolocation) {
        alert('Fitur Geolocation tidak didukung di browser ini.');
        return;
    }
    geoLoading.value[index] = true;
    navigator.geolocation.getCurrentPosition(
        async (pos) => {
            const lat = Number(pos.coords.latitude.toFixed(6));
            const lng = Number(pos.coords.longitude.toFixed(6));
            form.stores[index].latitude = lat;
            form.stores[index].longitude = lng;
            syncMarkersFromInputs();
            await fetchStoreStreetName(index, lat, lng);
            geoLoading.value[index] = false;
        },
        (err) => {
            geoLoading.value[index] = false;
            alert('Gagal mendeteksi lokasi GPS: ' + err.message);
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
};

// Reset store to default location
const resetStore = (index) => {
    const s = form.stores[index];
    const def = defaultStoreLocations[s.slug];
    if (def) {
        s.address = def.address;
        s.latitude = def.latitude;
        s.longitude = def.longitude;
        syncMarkersFromInputs();
    }
};

const getGmapsUrl = (lat, lng) => {
    const nLat = Number(lat);
    const nLng = Number(lng);
    if (isNaN(nLat) || isNaN(nLng) || nLat === 0) return '#';
    return `https://www.google.com/maps?q=${nLat},${nLng}`;
};

// Leaflet Map Preview
const mapContainer = ref(null);
let map = null;
const mapMarkers = [];

const createMarkerIcon = (slug) => {
    const meta = storeMeta[slug] || { emoji: '🏪', color: '#0d685b' };
    return L.divIcon({
        className: 'custom-store-pin',
        html: `
            <div style="position: relative; display: flex; flex-direction: column; align-items: center; cursor: grab;">
                <div style="background-color: ${meta.color}; color: white; width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2.5px solid white; box-shadow: 0 4px 10px rgba(0,0,0,0.5); font-size: 16px;">
                    ${meta.emoji}
                </div>
                <div style="width: 0; height: 0; border-left: 6px solid transparent; border-right: 6px solid transparent; border-top: 7px solid ${meta.color}; margin-top: -1px;"></div>
            </div>
        `,
        iconSize: [36, 42],
        iconAnchor: [18, 41],
    });
};

const initMap = () => {
    if (!mapContainer.value) return;

    map = L.map(mapContainer.value, {
        zoomControl: true,
        attributionControl: false,
    }).setView([-6.985, 110.40], 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
    }).addTo(map);

    const bounds = [];

    form.stores.forEach((store, i) => {
        const lat = Number(store.latitude);
        const lng = Number(store.longitude);
        if (!isNaN(lat) && !isNaN(lng) && lat !== 0) {
            const marker = L.marker([lat, lng], {
                icon: createMarkerIcon(store.slug),
                draggable: true,
            }).addTo(map);

            marker.bindPopup(`
                <div style="font-size:12px; line-height:1.4;">
                    <b>${store.name}</b><br>
                    <span style="font-size:11px; color:#555;">${store.tagline || ''}</span><br>
                    <span style="font-size:10px; color:#0d685b; font-weight:bold;">Seret pin untuk memindahkan titik</span>
                </div>
            `);

            marker.on('dragend', async (e) => {
                const pos = e.target.getLatLng();
                const lat = Number(pos.lat.toFixed(6));
                const lng = Number(pos.lng.toFixed(6));
                form.stores[i].latitude = lat;
                form.stores[i].longitude = lng;
                await fetchStoreStreetName(i, lat, lng);
            });

            mapMarkers[i] = marker;
            bounds.push([lat, lng]);
        }
    });

    if (bounds.length > 1) {
        map.fitBounds(bounds, { padding: [50, 50], maxZoom: 14 });
    }

    setTimeout(() => {
        if (map) map.invalidateSize();
    }, 300);
};

const syncMarkersFromInputs = () => {
    if (!map) return;
    form.stores.forEach((store, i) => {
        const lat = Number(store.latitude);
        const lng = Number(store.longitude);
        if (!isNaN(lat) && !isNaN(lng) && lat !== 0) {
            if (mapMarkers[i]) {
                mapMarkers[i].setLatLng([lat, lng]);
            } else {
                const marker = L.marker([lat, lng], {
                    icon: createMarkerIcon(store.slug),
                    draggable: true,
                }).addTo(map);

                marker.on('dragend', async (e) => {
                    const pos = e.target.getLatLng();
                    const lat = Number(pos.lat.toFixed(6));
                    const lng = Number(pos.lng.toFixed(6));
                    form.stores[i].latitude = lat;
                    form.stores[i].longitude = lng;
                    await fetchStoreStreetName(i, lat, lng);
                });
                mapMarkers[i] = marker;
            }
        }
    });
};

const fitAllStores = () => {
    if (!map) return;
    const bounds = [];
    form.stores.forEach((store) => {
        const lat = Number(store.latitude);
        const lng = Number(store.longitude);
        if (!isNaN(lat) && !isNaN(lng) && lat !== 0) {
            bounds.push([lat, lng]);
        }
    });
    if (bounds.length > 1) {
        map.fitBounds(bounds, { padding: [50, 50], maxZoom: 14 });
    } else if (bounds.length === 1) {
        map.setView(bounds[0], 14);
    }
};

watch(
    () => form.stores.map((s) => `${s.latitude},${s.longitude}`),
    () => {
        syncMarkersFromInputs();
    },
    { deep: true }
);

onMounted(() => {
    initMap();
});

onUnmounted(() => {
    if (map) {
        map.remove();
        map = null;
    }
});
</script>

<template>
    <Head title="Pengaturan Website & Midtrans - Admin" />

    <div class="mx-auto max-w-4xl space-y-6">
        <!-- HEADER -->
        <div>
            <h1 class="text-2xl font-black tracking-tight text-[#f3f2e7] sm:text-3xl">
                Pengaturan Sistem & Gateway
            </h1>
            <p class="mt-1 text-xs text-[#f3f2e7]/60 sm:text-sm">
                Konfigurasi payment gateway Midtrans untuk isi saldo otomatis, biaya pengiriman kurir, serta sakelar fitur publik website.
            </p>
        </div>

        <!-- PINTASAN PENGATURAN TARIF ONGKIR KAB/KOTA -->
        <div class="overflow-hidden rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4.5 sm:p-6 shadow-xl text-[#f3f2e7]">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#0d685b]/30 text-xl text-[#f3f2e7]">
                        📍
                    </span>
                    <div>
                        <h2 class="text-base font-black text-[#f3f2e7]">Tarif & Biaya Pengiriman Kurir (Kab/Kota)</h2>
                        <p class="text-xs text-[#f3f2e7]/60">
                            Atur daftar Kabupaten/Kota, biaya ongkir, estimasi waktu, dan status aktif pengantaran kurir.
                        </p>
                    </div>
                </div>
                <Link
                    href="/admin/kurir?tab=rates"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#0d685b] hover:bg-[#117c6d] px-4 py-2.5 text-xs font-black text-[#f3f2e7] shadow-sm transition active:scale-95 whitespace-nowrap"
                >
                    <span>Kelola Tarif Ongkir</span>
                    <span>→</span>
                </Link>
            </div>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <!-- SECTION LOKASI 3 TOKO RAMELA -->
            <div class="overflow-hidden rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4.5 sm:p-6 shadow-xl text-[#f3f2e7]">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-[#0d685b]/20 pb-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#0d685b]/30 text-xl text-[#f3f2e7]">
                            🏪
                        </span>
                        <div>
                            <h2 class="text-base font-black text-[#f3f2e7]">Lokasi & Titik Pusat 3 Toko RAMELA</h2>
                            <p class="text-xs text-[#f3f2e7]/60">
                                Konfigurasi alamat fisik dan koordinat GPS (Latitude / Longitude) toko. Digunakan untuk titik penjemputan kurir, kalkulasi rute jalan raya OSRM, dan peta pelacakan.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 self-start sm:self-auto rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3 py-1.5 text-xs font-bold text-[#f3f2e7] hover:bg-[#0d685b]/20 transition cursor-pointer"
                        @click="fitAllStores"
                    >
                        <span>🎯</span>
                        <span>Pusatkan Semua Toko</span>
                    </button>
                </div>

                <!-- KARTU 3 TOKO -->
                <div class="mt-5 space-y-4">
                    <div
                        v-for="(store, index) in form.stores"
                        :key="store.id || store.slug"
                        class="rounded-xl border border-[#0d685b]/25 bg-[#131d1a] p-4 sm:p-5 transition hover:border-[#0d685b]/50"
                    >
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-[#0d685b]/15 pb-3">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#1c2a25] text-lg border border-[#0d685b]/30">
                                    {{ storeMeta[store.slug]?.emoji || '🏪' }}
                                </span>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-xs font-black text-[#f3f2e7] sm:text-sm">{{ store.name }}</h3>
                                        <span
                                            class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                                            :class="storeMeta[store.slug]?.bgBadge || 'bg-[#0d685b]/30 text-emerald-300'"
                                        >
                                            {{ storeMeta[store.slug]?.badge || store.slug }}
                                        </span>
                                    </div>
                                    <p class="text-[11px] text-[#f3f2e7]/60">{{ store.tagline }}</p>
                                </div>
                            </div>

                            <!-- Tombol Helper Tiap Toko -->
                            <div class="flex items-center gap-2 flex-wrap">
                                <button
                                    type="button"
                                    :disabled="geoLoading[index]"
                                    class="inline-flex items-center gap-1 rounded-lg border border-sky-500/40 bg-sky-500/10 px-2.5 py-1 text-[11px] font-bold text-sky-300 hover:bg-sky-500/20 transition disabled:opacity-50 cursor-pointer"
                                    title="Ambil titik GPS dari perangkat Anda saat ini"
                                    @click="getCurrentLocation(index)"
                                >
                                    <span>📍</span>
                                    <span>{{ geoLoading[index] ? 'Mencari GPS...' : 'GPS Saya' }}</span>
                                </button>

                                <a
                                    :href="getGmapsUrl(store.latitude, store.longitude)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-1 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-2.5 py-1 text-[11px] font-bold text-emerald-300 hover:bg-emerald-500/20 transition cursor-pointer"
                                    title="Periksa titik koordinat ini di Google Maps"
                                >
                                    <span>🗺️</span>
                                    <span>Google Maps</span>
                                    <span>↗</span>
                                </a>

                                <button
                                    type="button"
                                    :disabled="storeGeocoding[index]"
                                    class="inline-flex items-center gap-1 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-2.5 py-1 text-[11px] font-bold text-emerald-300 hover:bg-emerald-500/20 transition disabled:opacity-50 cursor-pointer"
                                    title="Ambil nama jalan otomatis dari titik koordinat"
                                    @click="fetchStoreStreetName(index, store.latitude, store.longitude)"
                                >
                                    <span>{{ storeGeocoding[index] ? '⏳' : '📍' }}</span>
                                    <span>{{ storeGeocoding[index] ? 'Mengambil...' : 'Ambil Nama Jalan' }}</span>
                                </button>

                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg border border-rose-500/30 bg-rose-500/10 px-2 py-1 text-[11px] font-bold text-rose-300 hover:bg-rose-500/20 transition cursor-pointer"
                                    title="Kembalikan ke alamat dan koordinat default Semarang"
                                    @click="resetStore(index)"
                                >
                                    <span>🔄</span>
                                    <span>Reset</span>
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 space-y-3">
                            <!-- Alamat Fisik Lengkap -->
                            <div>
                                <label class="mb-1 block text-xs font-bold text-[#f3f2e7]">
                                    Alamat Fisik / Workshop / Hub Cabang
                                </label>
                                <textarea
                                    v-model="store.address"
                                    rows="2"
                                    :class="inputClass"
                                    placeholder="Contoh: Jl. Pandanaran No. 58, Mugassari, Semarang Selatan"
                                ></textarea>
                                <p
                                    v-if="form.errors[`stores.${index}.address`]"
                                    class="mt-1 text-xs text-rose-400"
                                >
                                    {{ form.errors[`stores.${index}.address`] }}
                                </p>
                            </div>

                            <!-- Latitude & Longitude -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="text-xs font-bold text-[#f3f2e7]">Latitude (Lintang)</label>
                                        <span class="text-[10px] text-[#f3f2e7]/50">-90.0 s/d 90.0</span>
                                    </div>
                                    <input
                                        v-model="store.latitude"
                                        type="text"
                                        :class="inputClass"
                                        placeholder="Contoh: -6.989720"
                                    />
                                    <p
                                        v-if="form.errors[`stores.${index}.latitude`]"
                                        class="mt-1 text-xs text-rose-400"
                                    >
                                        {{ form.errors[`stores.${index}.latitude`] }}
                                    </p>
                                </div>

                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="text-xs font-bold text-[#f3f2e7]">Longitude (Bujur)</label>
                                        <span class="text-[10px] text-[#f3f2e7]/50">-180.0 s/d 180.0</span>
                                    </div>
                                    <input
                                        v-model="store.longitude"
                                        type="text"
                                        :class="inputClass"
                                        placeholder="Contoh: 110.421930"
                                    />
                                    <p
                                        v-if="form.errors[`stores.${index}.longitude`]"
                                        class="mt-1 text-xs text-rose-400"
                                    >
                                        {{ form.errors[`stores.${index}.longitude`] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PETA PRATINJAU LEAFLET INTERAKTIF -->
                <div class="mt-6 rounded-xl border border-[#0d685b]/30 bg-[#131d1a] p-3.5 sm:p-4">
                    <div class="flex items-center justify-between gap-2 mb-2.5">
                        <div class="flex items-center gap-2">
                            <span class="text-base">🗺️</span>
                            <span class="text-xs font-bold text-[#f3f2e7]">Pratinjau Peta Interaktif (3 Toko)</span>
                            <span class="hidden sm:inline-block text-[11px] text-[#f3f2e7]/50">
                                · Seret (drag) pin toko pada peta untuk memperbarui koordinat secara otomatis
                            </span>
                        </div>
                        <button
                            type="button"
                            class="sm:hidden inline-flex items-center gap-1 rounded-lg border border-[#0d685b]/40 bg-[#1c2a25] px-2 py-1 text-[11px] font-bold text-[#f3f2e7]"
                            @click="fitAllStores"
                        >
                            <span>🎯</span>
                            <span>Pusatkan</span>
                        </button>
                    </div>

                    <div ref="mapContainer" class="h-64 sm:h-72 w-full rounded-lg overflow-hidden border border-[#0d685b]/20 z-0"></div>

                    <div class="mt-2.5 flex flex-wrap items-center justify-between gap-2 text-[11px] text-[#f3f2e7]/70">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="inline-flex items-center gap-1">
                                <span class="h-2.5 w-2.5 rounded-full bg-[#0d685b]"></span>
                                <span>RAMELA EATS</span>
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <span class="h-2.5 w-2.5 rounded-full bg-[#d97706]"></span>
                                <span>RAMELA HAMPERS</span>
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <span class="h-2.5 w-2.5 rounded-full bg-[#2563eb]"></span>
                                <span>RAMELA BETON</span>
                            </span>
                        </div>
                        <span class="text-[10px] text-[#f3f2e7]/50">
                            💡 Seret pin toko di peta untuk memperbarui koordinat GPS secara presisi.
                        </span>
                    </div>
                </div>
            </div>

            <!-- SECTION PAYMENT GATEWAY MIDTRANS -->
            <div class="overflow-hidden rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4.5 sm:p-6 shadow-xl text-[#f3f2e7]">
                <div class="flex items-center justify-between border-b border-[#0d685b]/20 pb-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#0d685b]/30 text-xl text-[#f3f2e7]">
                            💳
                        </span>
                        <div>
                            <h2 class="text-base font-black text-[#f3f2e7]">Payment Gateway Midtrans</h2>
                            <p class="text-xs text-[#f3f2e7]/60">Kunci API disimpan secara terenkripsi aman di database server.</p>
                        </div>
                    </div>
                    <span
                        class="rounded-full px-3 py-1 text-[11px] font-bold"
                        :class="form.is_production ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-amber-500/20 text-amber-300 border border-amber-500/40'"
                    >
                        {{ form.is_production ? '● Mode Production (Live)' : '● Mode Sandbox (Testing)' }}
                    </span>
                </div>

                <div class="mt-5 space-y-5">
                    <!-- Environment Mode Switch -->
                    <label class="flex items-start gap-3 rounded-xl border border-[#0d685b]/20 bg-[#131d1a] p-3.5 cursor-pointer">
                        <input
                            v-model="form.is_production"
                            type="checkbox"
                            class="mt-0.5 h-4 w-4 rounded accent-[#0d685b]"
                        />
                        <div>
                            <span class="block text-xs font-bold text-[#f3f2e7]">Aktifkan Mode Production</span>
                            <span class="block text-[11px] text-[#f3f2e7]/60">
                                Centang untuk menerima pembayaran uang asli melalui Midtrans. Hilangkan centang jika ingin menggunakan simulasi Sandbox simulator.
                            </span>
                        </div>
                    </label>

                    <!-- API Keys -->
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div v-for="[name, title] in secrets" :key="name">
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#0d685b]">{{ title }}</label>
                            <input
                                v-model="form[name]"
                                type="password"
                                autocomplete="off"
                                :class="inputClass"
                                :placeholder="midtrans.secrets[name] ? `Tersimpan (${midtrans.secrets[name]})` : 'Belum dikonfigurasi'"
                            />
                            <p v-if="form.errors[name]" class="mt-1 text-xs text-rose-400">{{ form.errors[name] }}</p>
                        </div>
                    </div>

                    <!-- Webhook Notification URL -->
                    <div class="rounded-xl border border-[#0d685b]/30 bg-[#131d1a] p-4">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <p class="text-xs font-bold text-[#f3f2e7]">URL Notifikasi Webhook (Payment Notification URL)</p>
                                <p class="text-[11px] text-[#f3f2e7]/60">Salin URL ini dan tempelkan ke Dashboard Midtrans → Settings → Configuration.</p>
                            </div>
                            <button
                                type="button"
                                class="shrink-0 rounded-xl bg-[#0d685b] px-3.5 py-1.5 text-xs font-bold text-[#f3f2e7] shadow-sm hover:bg-[#0d685b]/90 transition"
                                @click="copyWebhook"
                            >
                                {{ copied ? 'Tersalin! ✓' : 'Salin URL' }}
                            </button>
                        </div>
                        <div class="mt-2.5 rounded-xl bg-[#17231f] p-2.5 text-xs font-mono text-emerald-300 break-all select-all border border-[#0d685b]/30">
                            {{ webhookUrl }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION GOOGLE MAPS & REVERSE GEOCODING NAMA JALAN -->
            <div class="overflow-hidden rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4.5 sm:p-6 shadow-xl text-[#f3f2e7]">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-[#0d685b]/20 pb-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#0d685b]/30 text-xl text-[#f3f2e7]">
                            🗺️
                        </span>
                        <div>
                            <h2 class="text-base font-black text-[#f3f2e7]">Peta & Deteksi Nama Jalan (OpenStreetMap & Google Maps)</h2>
                            <p class="text-xs text-[#f3f2e7]/60">
                                Sistem secara default menggunakan <strong>OpenStreetMap (100% Gratis)</strong> untuk peta dan deteksi nama jalan. Google Maps API Key bersifat opsional.
                            </p>
                        </div>
                    </div>
                    <span
                        class="rounded-full px-3 py-1 text-[11px] font-bold"
                        :class="google?.has_key ? 'bg-blue-500/20 text-blue-300 border border-blue-500/40' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'"
                    >
                        {{ google?.has_key ? `● Google Maps Aktif (${google.key_hint})` : '● OpenStreetMap Aktif (100% Gratis)' }}
                    </span>
                </div>

                <div class="mt-5 space-y-4">
                    <div>
                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#0d685b]">
                            Google Maps API Key (Opsional — Boleh Dikosongkan)
                        </label>
                        <input
                            v-model="form.google_maps_api_key"
                            type="password"
                            autocomplete="off"
                            :class="inputClass"
                            :placeholder="google?.has_key ? `Tersimpan (${google.key_hint}) — biarkan kosong jika tidak diubah` : 'Biarkan kosong untuk memakai OpenStreetMap gratis'"
                        />
                        <p class="mt-1.5 text-[11px] text-[#f3f2e7]/60 leading-relaxed">
                            💡 Tanpa API Key Google, form pemesanan pelanggan dan pengaturan toko tetap berfungsi 100% normal mengambil nama jalan dan menampilkan peta via OpenStreetMap gratis.
                        </p>
                        <p v-if="form.errors.google_maps_api_key" class="mt-1 text-xs text-rose-400">
                            {{ form.errors.google_maps_api_key }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- SECTION FITUR PUBLIK -->
            <div class="overflow-hidden rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4.5 sm:p-6 shadow-xl text-[#f3f2e7]">
                <div class="flex items-center gap-3 border-b border-[#0d685b]/20 pb-4">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#0d685b]/30 text-xl text-[#f3f2e7]">
                        ⚡
                    </span>
                    <div>
                        <h2 class="text-base font-black text-[#f3f2e7]">Sakelar Fitur Website</h2>
                        <p class="text-xs text-[#f3f2e7]/60">Kontrol ketersediaan modul halaman publik di website.</p>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="flex items-center gap-3 rounded-xl border border-[#0d685b]/20 bg-[#131d1a] p-3.5 cursor-pointer hover:border-[#0d685b]/50 transition">
                        <input
                            v-model="form.feature_blog"
                            type="checkbox"
                            class="h-4 w-4 rounded accent-[#0d685b]"
                        />
                        <div>
                            <span class="block text-xs font-bold text-[#f3f2e7]">Aktifkan Halaman Blog & Berita</span>
                            <span class="block text-[11px] text-[#f3f2e7]/60">Tampilkan artikel berita di menu publik toko</span>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 rounded-xl border border-[#0d685b]/20 bg-[#131d1a] p-3.5 cursor-pointer hover:border-[#0d685b]/50 transition">
                        <input
                            v-model="form.feature_faq"
                            type="checkbox"
                            class="h-4 w-4 rounded accent-[#0d685b]"
                        />
                        <div>
                            <span class="block text-xs font-bold text-[#f3f2e7]">Aktifkan Tanya Jawab (FAQ)</span>
                            <span class="block text-[11px] text-[#f3f2e7]/60">Tampilkan pertanyaan umum pelanggan di landing page</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- TOMBOL SIMPAN -->
            <div class="flex justify-end gap-3">
                <button
                    :disabled="form.processing"
                    class="rounded-xl bg-[#0d685b] px-6 py-2.5 text-xs font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 transition hover:bg-[#0d685b]/90 disabled:opacity-50"
                >
                    {{ form.processing ? 'Menyimpan...' : 'Simpan Semua Pengaturan' }}
                </button>
            </div>
        </form>
    </div>
</template>

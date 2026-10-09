<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import L from "leaflet";
import axios from "axios";

const props = defineProps({
    latitude: {
        type: [Number, String],
        default: null,
    },
    longitude: {
        type: [Number, String],
        default: null,
    },
    address: {
        type: String,
        default: "",
    },
    district: {
        type: String,
        default: "",
    },
    postalCode: {
        type: String,
        default: "",
    },
    defaultCenter: {
        type: Object,
        default: () => ({ lat: -6.989720, lng: 110.421930 }), // Default Semarang
    },
    label: {
        type: String,
        default: "Titik Lokasi Pengiriman",
    },
});

const emit = defineEmits([
    "update:latitude",
    "update:longitude",
    "update:address",
    "update:district",
    "update:postalCode",
    "update:postal-code",
    "locationSelected",
]);

const mapContainer = ref(null);
let map = null;
let marker = null;

// Search & Geocoding State
const searchQuery = ref("");
const searchResults = ref([]);
const isSearching = ref(false);
const showSearchResults = ref(false);
let searchTimeout = null;

const isGeocoding = ref(false);
const isGpsLoading = ref(false);
const lastResolved = ref(null);
const geocodeError = ref("");

const effectiveCoords = computed(() => {
    const lat = Number(props.latitude);
    const lng = Number(props.longitude);
    if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
        return { lat, lng };
    }
    return props.defaultCenter;
});

const hasValidCoordinates = computed(() => {
    const lat = Number(props.latitude);
    const lng = Number(props.longitude);
    return !isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0;
});

const createPinIcon = () =>
    L.divIcon({
        className: "custom-delivery-pin",
        html: `
            <div style="position: relative; display: flex; flex-direction: column; align-items: center; cursor: grab;">
                <div style="background-color: #10b981; color: #ffffff; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2.5px solid #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.5); font-size: 18px;">
                    📍
                </div>
                <div style="width: 0; height: 0; border-left: 6px solid transparent; border-right: 6px solid transparent; border-top: 7px solid #10b981; margin-top: -1px;"></div>
            </div>
        `,
        iconSize: [36, 42],
        iconAnchor: [18, 41],
    });

// Helper emit semua data lokasi ke komponen induk
const applyLocationData = (data) => {
    lastResolved.value = data;

    if (data.latitude && data.longitude) {
        emit("update:latitude", Number(data.latitude));
        emit("update:longitude", Number(data.longitude));
    }
    if (data.formatted_address || data.street_name || data.address) {
        emit("update:address", data.formatted_address || data.address || data.street_name);
    }
    if (data.district) {
        emit("update:district", data.district);
    }
    if (data.postal_code) {
        emit("update:postalCode", data.postal_code);
        emit("update:postal-code", data.postal_code);
    }
    emit("locationSelected", data);
};

// Inisialisasi Peta Leaflet (OpenStreetMap)
const initMap = () => {
    if (!mapContainer.value) return;

    const initial = effectiveCoords.value;

    map = L.map(mapContainer.value, {
        zoomControl: true,
        attributionControl: false,
    }).setView([initial.lat, initial.lng], hasValidCoordinates.value ? 16 : 13);

    // Tile Layer: OpenStreetMap gratis ("mapsnya dari openmaps saja")
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
    }).addTo(map);

    // Inisialisasi Marker jika sudah ada koordinat awal
    if (hasValidCoordinates.value) {
        setMarkerPosition(initial.lat, initial.lng, false);
    }

    // Klik di peta untuk memindahkan marker
    map.on("click", (e) => {
        const { lat, lng } = e.latlng;
        setMarkerPosition(lat, lng, true);
    });

    setTimeout(() => {
        if (map) map.invalidateSize();
    }, 300);
};

// Set posisi marker & picu reverse geocoding
const setMarkerPosition = (lat, lng, triggerGeocode = true) => {
    const fixedLat = Number(Number(lat).toFixed(6));
    const fixedLng = Number(Number(lng).toFixed(6));

    emit("update:latitude", fixedLat);
    emit("update:longitude", fixedLng);

    if (!marker) {
        marker = L.marker([fixedLat, fixedLng], {
            icon: createPinIcon(),
            draggable: true,
        }).addTo(map);

        marker.on("dragend", (e) => {
            const pos = e.target.getLatLng();
            setMarkerPosition(pos.lat, pos.lng, true);
        });
    } else {
        marker.setLatLng([fixedLat, fixedLng]);
    }

    if (triggerGeocode) {
        fetchStreetName(fixedLat, fixedLng);
    }
};

// Ambil Nama Jalan via Backend (Google Geocoding API dengan Fallback OpenStreetMap)
const fetchStreetName = async (lat, lng) => {
    isGeocoding.value = true;
    geocodeError.value = "";

    try {
        const res = await axios.get("/api/location/reverse", {
            params: { lat, lng },
        });

        if (res.data && res.data.success) {
            applyLocationData(res.data);

            // Update popup pada marker
            if (marker) {
                const sourceBadge = res.data.source === "google"
                    ? "<span style='color:#3b82f6; font-weight:700;'>Google Maps</span>"
                    : "<span style='color:#10b981; font-weight:700;'>OpenStreetMap</span>";

                marker.bindPopup(`
                    <div style="font-size: 11px; line-height: 1.4; color: #1f2937;">
                        <b style="color: #065f46;">${res.data.street_name || 'Titik Terpilih'}</b><br/>
                        <span style="font-size: 10px; color: #4b5563;">${res.data.formatted_address || ''}</span><br/>
                        <span style="font-size: 9px; color: #9ca3af;">Sumber: ${sourceBadge}</span>
                    </div>
                `).openPopup();
            }
        }
    } catch (err) {
        console.warn("Gagal mengambil nama jalan:", err);
        geocodeError.value = "Gagal mengambil nama jalan dari Google / OpenStreetMap.";
    } finally {
        isGeocoding.value = false;
    }
};

// Ambil Lokasi GPS Perangkat Pengguna
const detectGps = async () => {
    isGpsLoading.value = true;
    geocodeError.value = "";

    const onCoordsFound = (lat, lng) => {
        isGpsLoading.value = false;
        if (map) {
            map.setView([lat, lng], 17);
        }
        setMarkerPosition(lat, lng, true);
    };

    const tryIpFallback = async (reasonMsg = "") => {
        try {
            const res = await axios.get("/api/location/detect");
            if (res.data && res.data.latitude && res.data.longitude) {
                isGpsLoading.value = false;
                const lat = Number(res.data.latitude);
                const lng = Number(res.data.longitude);
                if (map) {
                    map.setView([lat, lng], 17);
                }
                setMarkerPosition(lat, lng, false);
                applyLocationData(res.data);
                if (reasonMsg) {
                    geocodeError.value = `${reasonMsg} Menggunakan perkiraan wilayah.`;
                }
                return;
            }
        } catch (e) {
            console.warn("Gagal deteksi lokasi IP fallback:", e);
        }

        isGpsLoading.value = false;
        const def = props.defaultCenter || { lat: -6.989720, lng: 110.421930 };
        onCoordsFound(def.lat, def.lng);
        if (reasonMsg) {
            geocodeError.value = reasonMsg;
        }
    };

    // Jika browser tidak mendukung Geolocation
    if (!navigator.geolocation) {
        await tryIpFallback("Browser tidak mendukung GPS.");
        return;
    }

    // Jika diakses lewat HTTP di host non-localhost, browser memblokir Geolocation API
    const isLocalhost = window.location.hostname === "localhost" || window.location.hostname === "127.0.0.1";
    if (!window.isSecureContext && !isLocalhost) {
        await tryIpFallback("Akses HTTP membatasi GPS browser.");
        return;
    }

    // Coba deteksi GPS perangkat (opsi standar cepat tanpa satelit timeout)
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            const lat = Number(Number(pos.coords.latitude).toFixed(6));
            const lng = Number(Number(pos.coords.longitude).toFixed(6));
            onCoordsFound(lat, lng);
        },
        async (err) => {
            let reason = "GPS tidak aktif / izin ditolak.";
            if (err.code === 1) reason = "Izin lokasi belum diberikan.";
            if (err.code === 2) reason = "Sinyal GPS tidak ditemukan.";
            if (err.code === 3) reason = "Pencarian GPS melebihi batas waktu.";
            await tryIpFallback(reason);
        },
        { enableHighAccuracy: false, timeout: 8000, maximumAge: 60000 }
    );
};

// Eksekusi Pencarian Alamat / Nama Jalan
const executeSearch = async () => {
    const q = searchQuery.value.trim();
    if (q.length < 2) {
        searchResults.value = [];
        showSearchResults.value = false;
        return;
    }

    isSearching.value = true;
    try {
        const res = await axios.get("/api/location/search", { params: { q } });
        if (res.data && res.data.results) {
            searchResults.value = res.data.results;
            showSearchResults.value = res.data.results.length > 0;
        }
    } catch (e) {
        console.warn("Gagal mencari alamat:", e);
    } finally {
        isSearching.value = false;
    }
};

const handleSearchInput = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    const q = searchQuery.value.trim();
    if (q.length < 2) {
        searchResults.value = [];
        showSearchResults.value = false;
        return;
    }

    searchTimeout = setTimeout(executeSearch, 300);
};

const searchImmediately = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    executeSearch();
};

const selectSearchResult = (item) => {
    searchQuery.value = item.street_name || item.title || item.address;
    showSearchResults.value = false;

    // Langsung terapkan data lokasi hasil pencarian seketika tanpa delay reverse geocode!
    applyLocationData(item);

    if (map) {
        map.setView([item.latitude, item.longitude], 17);
    }
    // Update marker tanpa reverse geocoding tambahan
    setMarkerPosition(item.latitude, item.longitude, false);

    if (marker) {
        const sourceBadge = item.source === "google"
            ? "<span style='color:#3b82f6; font-weight:700;'>Google Maps</span>"
            : "<span style='color:#10b981; font-weight:700;'>OpenStreetMap</span>";

        marker.bindPopup(`
            <div style="font-size: 11px; line-height: 1.4; color: #1f2937;">
                <b style="color: #065f46;">${item.street_name || item.title || 'Titik Terpilih'}</b><br/>
                <span style="font-size: 10px; color: #4b5563;">${item.address || ''}</span><br/>
                <span style="font-size: 9px; color: #9ca3af;">Sumber: ${sourceBadge}</span>
            </div>
        `).openPopup();
    }
};

// Sinkronkan marker jika koordinat luar berubah
watch(
    () => [props.latitude, props.longitude],
    ([newLat, newLng]) => {
        const lat = Number(newLat);
        const lng = Number(newLng);
        if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
            if (marker) {
                const current = marker.getLatLng();
                if (Math.abs(current.lat - lat) > 0.00001 || Math.abs(current.lng - lng) > 0.00001) {
                    marker.setLatLng([lat, lng]);
                    if (map) map.panTo([lat, lng]);
                }
            } else if (map) {
                setMarkerPosition(lat, lng, false);
            }
        }
    }
);

onMounted(() => {
    nextTick(() => {
        initMap();
    });
});

onBeforeUnmount(() => {
    if (map) {
        map.remove();
        map = null;
    }
});
</script>

<template>
    <div class="space-y-3 rounded-2xl border border-[#0d685b]/40 bg-[#131d1a] p-3.5 sm:p-4">
        <!-- HEADER & TOMBOL AKSI -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
            <div>
                <div class="flex items-center gap-1.5">
                    <span class="text-sm">🗺️</span>
                    <label class="text-xs font-bold text-[#f3f2e7]">{{ label }}</label>
                    <span class="rounded bg-emerald-500/20 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-300">
                        OpenStreetMap
                    </span>
                </div>
                <p class="text-[11px] text-[#f3f2e7]/60">
                    Pilih titik di peta, gunakan GPS, atau cari alamat di bawah.
                </p>
            </div>

            <!-- TOMBOL GPS SAAT INI -->
            <button
                type="button"
                :disabled="isGpsLoading"
                class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-3.5 py-2 text-xs font-bold text-emerald-300 hover:bg-emerald-500/20 active:scale-95 transition disabled:opacity-50 cursor-pointer shadow-xs"
                title="Gunakan posisi GPS saat ini dari perangkat"
                @click="detectGps"
            >
                <span v-if="isGpsLoading" class="animate-spin text-xs">⏳</span>
                <span v-else>📍</span>
                <span>{{ isGpsLoading ? 'Mencari Lokasi...' : 'Gunakan GPS Saya' }}</span>
            </button>
        </div>

        <!-- NOTIFIKASI ERROR JIKA ADA -->
        <div
            v-if="geocodeError"
            class="flex items-center justify-between gap-2 rounded-xl border border-rose-500/30 bg-rose-950/40 p-2.5 text-xs text-rose-300"
        >
            <div class="flex items-center gap-1.5">
                <span>⚠️</span>
                <span>{{ geocodeError }}</span>
            </div>
            <button
                type="button"
                class="text-rose-300 hover:text-white text-xs px-1"
                @click="geocodeError = ''"
            >
                ✕
            </button>
        </div>

        <!-- SEARCH BAR ALAMAT / NAMA JALAN -->
        <div class="relative">
            <div class="relative flex items-center">
                <span class="absolute left-3 text-xs text-[#f3f2e7]/50">🔍</span>
                <input
                    v-model="searchQuery"
                    type="text"
                    class="w-full rounded-xl border border-[#0d685b]/40 bg-[#1c2a25] pl-8 pr-16 py-2 text-xs text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:border-emerald-400 focus:outline-none focus:ring-1 focus:ring-emerald-400"
                    placeholder="Cari jalan atau nama lokasi... (Tekan Enter)"
                    @input="handleSearchInput"
                    @keydown.enter.prevent="searchImmediately"
                    @focus="showSearchResults = searchResults.length > 0"
                />
                <div class="absolute right-3 flex items-center gap-1.5">
                    <span v-if="isSearching" class="animate-spin text-xs text-emerald-400">
                        ⏳
                    </span>
                    <button
                        v-if="searchQuery"
                        type="button"
                        class="text-xs text-[#f3f2e7]/50 hover:text-[#f3f2e7]"
                        @click="searchQuery = ''; showSearchResults = false;"
                    >
                        ✕
                    </button>
                </div>
            </div>

            <!-- DROPDOWN HASIL PENCARIAN -->
            <div
                v-if="showSearchResults && searchResults.length"
                class="absolute z-50 mt-1 max-h-56 w-full overflow-y-auto rounded-xl border border-[#0d685b]/60 bg-[#131d1a] shadow-2xl"
            >
                <div
                    v-for="(item, idx) in searchResults"
                    :key="idx"
                    class="border-b border-[#0d685b]/20 p-2.5 text-xs hover:bg-[#1c2a25] cursor-pointer transition flex items-start gap-2"
                    @click="selectSearchResult(item)"
                >
                    <span class="mt-0.5 text-emerald-400">📍</span>
                    <div class="flex-1 min-w-0">
                        <div class="font-bold text-[#f3f2e7] truncate">{{ item.title }}</div>
                        <div class="text-[10px] text-[#f3f2e7]/60 truncate">{{ item.address }}</div>
                        <div v-if="item.district" class="mt-0.5 text-[9px] text-emerald-400 font-semibold truncate">
                            ✨ {{ item.district }} <span v-if="item.postal_code">· Kode Pos {{ item.postal_code }}</span>
                        </div>
                    </div>
                    <span class="rounded bg-emerald-500/20 px-1 py-0.5 text-[9px] font-semibold text-emerald-300 shrink-0">
                        {{ item.source === 'google' ? 'Google' : 'OSM' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- CONTAINER PETA LEAFLET (OPENSTREETMAP) -->
        <div class="relative overflow-hidden rounded-xl border border-[#0d685b]/30">
            <div
                ref="mapContainer"
                class="h-60 sm:h-72 w-full bg-[#0d1614] z-10"
            ></div>

            <!-- INDIKATOR SEDANG MENGAMBIL NAMA JALAN -->
            <div
                v-if="isGeocoding"
                class="absolute bottom-2 left-2 z-20 flex items-center gap-2 rounded-lg bg-black/80 backdrop-blur px-3 py-1.5 text-xs text-emerald-300 border border-emerald-500/30 shadow-lg"
            >
                <span class="animate-spin text-xs">🔄</span>
                <span>Mendeteksi nama jalan & wilayah...</span>
            </div>
        </div>

        <!-- STATUS KOORDINAT & NAMA JALAN TERDETEKSI -->
        <div
            v-if="hasValidCoordinates"
            class="rounded-xl border border-emerald-500/30 bg-emerald-950/30 p-3 text-xs space-y-1.5"
        >
            <div class="flex items-center justify-between flex-wrap gap-1.5">
                <div class="flex items-center gap-1.5 font-bold text-emerald-300">
                    <span>📍</span>
                    <span>{{ lastResolved?.street_name || 'Titik Koordinat Terpilih' }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span
                        v-if="lastResolved?.source"
                        class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                        :class="lastResolved.source === 'google' ? 'bg-blue-500/20 text-blue-300 border border-blue-500/40' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'"
                    >
                        {{ lastResolved.source === 'google' ? '✨ Sumber: Google Maps' : '🗺️ Sumber: OpenStreetMap' }}
                    </span>
                    <span class="text-[10px] text-[#f3f2e7]/60 font-mono">
                        {{ Number(latitude).toFixed(5) }}, {{ Number(longitude).toFixed(5) }}
                    </span>
                </div>
            </div>

            <p v-if="lastResolved?.formatted_address || lastResolved?.address" class="text-[11px] text-[#f3f2e7]/80 leading-relaxed">
                {{ lastResolved?.formatted_address || lastResolved?.address }}
            </p>

            <div v-if="lastResolved?.district || lastResolved?.postal_code" class="flex items-center gap-2 text-[10px] text-[#f3f2e7]/60 flex-wrap">
                <span v-if="lastResolved?.district" class="text-emerald-300 font-semibold">
                    Kec/Kel: <strong>{{ lastResolved.district }}</strong>
                </span>
                <span v-if="lastResolved?.postal_code" class="text-emerald-300 font-semibold">
                    Kode Pos: <strong>{{ lastResolved.postal_code }}</strong>
                </span>
            </div>
        </div>

        <div
            v-else
            class="rounded-xl border border-[#0d685b]/30 bg-[#1c2a25]/50 p-2.5 text-center text-[11px] text-[#f3f2e7]/60"
        >
            Pilih titik lokasi di peta, gunakan GPS, atau ketik pencarian alamat.
        </div>
    </div>
</template>

<style scoped>
:deep(.leaflet-popup-content-wrapper) {
    background: #ffffff;
    color: #1f2937;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
}
:deep(.leaflet-popup-tip) {
    background: #ffffff;
}
</style>


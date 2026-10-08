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
        default: "Titik Lokasi Pengiriman (OpenStreetMap)",
    },
});

const emit = defineEmits([
    "update:latitude",
    "update:longitude",
    "update:address",
    "update:district",
    "update:postalCode",
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
            lastResolved.value = res.data;

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

            // Emit event lokasi lengkap
            emit("locationSelected", res.data);

            // Auto-fill form fields
            if (res.data.street_name) {
                emit("update:address", res.data.formatted_address || res.data.street_name);
            }
            if (res.data.district) {
                emit("update:district", res.data.district);
            }
            if (res.data.postal_code) {
                emit("update:postalCode", res.data.postal_code);
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
const detectGps = () => {
    if (!navigator.geolocation) {
        alert("Browser perangkat Anda tidak mendukung fitur Geolocation.");
        return;
    }

    isGpsLoading.value = true;
    geocodeError.value = "";

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            const lat = pos.coords.latitude;
            const lng = pos.coords.longitude;
            isGpsLoading.value = false;

            if (map) {
                map.setView([lat, lng], 17);
            }
            setMarkerPosition(lat, lng, true);
        },
        (err) => {
            isGpsLoading.value = false;
            let msg = "Gagal mendeteksi GPS.";
            if (err.code === 1) msg = "Izin akses lokasi ditolak. Silakan izinkan browser mengakses GPS.";
            if (err.code === 2) msg = "Posisi GPS tidak ditemukan. Pastikan GPS aktif.";
            if (err.code === 3) msg = "Waktu pencarian GPS habis.";
            alert(msg);
        },
        { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 }
    );
};

// Pencarian Alamat / Nama Jalan
const handleSearchInput = () => {
    if (searchTimeout) clearTimeout(searchTimeout);

    const q = searchQuery.value.trim();
    if (q.length < 3) {
        searchResults.value = [];
        showSearchResults.value = false;
        return;
    }

    searchTimeout = setTimeout(async () => {
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
    }, 400);
};

const selectSearchResult = (item) => {
    searchQuery.value = item.title || item.address;
    showSearchResults.value = false;

    if (map) {
        map.setView([item.latitude, item.longitude], 17);
    }
    setMarkerPosition(item.latitude, item.longitude, true);
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
                    Klik peta atau geser pin 📍 untuk menetapkan titik akurat. Nama jalan terdeteksi otomatis.
                </p>
            </div>

            <!-- TOMBOL GPS SAAT INI -->
            <button
                type="button"
                :disabled="isGpsLoading"
                class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-3 py-1.5 text-xs font-bold text-emerald-300 hover:bg-emerald-500/20 active:scale-95 transition disabled:opacity-50 cursor-pointer"
                title="Gunakan posisi GPS saat ini dari perangkat"
                @click="detectGps"
            >
                <span v-if="isGpsLoading" class="animate-spin text-xs">⏳</span>
                <span v-else>📍</span>
                <span>{{ isGpsLoading ? 'Mencari GPS...' : 'Gunakan GPS Saya' }}</span>
            </button>
        </div>

        <!-- SEARCH BAR ALAMAT / NAMA JALAN -->
        <div class="relative">
            <div class="relative flex items-center">
                <span class="absolute left-3 text-xs text-[#f3f2e7]/50">🔍</span>
                <input
                    v-model="searchQuery"
                    type="text"
                    class="w-full rounded-xl border border-[#0d685b]/40 bg-[#1c2a25] pl-8 pr-8 py-2 text-xs text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:border-emerald-400 focus:outline-none focus:ring-1 focus:ring-emerald-400"
                    placeholder="Cari nama jalan, perumahan, kelurahan..."
                    @input="handleSearchInput"
                    @focus="showSearchResults = searchResults.length > 0"
                />
                <span v-if="isSearching" class="absolute right-3 animate-spin text-xs text-emerald-400">
                    ⏳
                </span>
                <button
                    v-else-if="searchQuery"
                    type="button"
                    class="absolute right-3 text-xs text-[#f3f2e7]/50 hover:text-[#f3f2e7]"
                    @click="searchQuery = ''; showSearchResults = false;"
                >
                    ✕
                </button>
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
                    </div>
                    <span class="rounded bg-emerald-500/20 px-1 py-0.5 text-[9px] font-semibold text-emerald-300">
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
                <span>Mendeteksi nama jalan...</span>
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

            <p v-if="lastResolved?.formatted_address" class="text-[11px] text-[#f3f2e7]/80 leading-relaxed">
                {{ lastResolved.formatted_address }}
            </p>

            <div v-if="lastResolved?.district || lastResolved?.postal_code" class="flex items-center gap-2 text-[10px] text-[#f3f2e7]/60 flex-wrap">
                <span v-if="lastResolved?.district">Kec/Kel: <strong>{{ lastResolved.district }}</strong></span>
                <span v-if="lastResolved?.postal_code">Kode Pos: <strong>{{ lastResolved.postal_code }}</strong></span>
            </div>
        </div>

        <div
            v-else
            class="rounded-xl border border-[#0d685b]/30 bg-[#1c2a25]/50 p-2.5 text-center text-[11px] text-[#f3f2e7]/60"
        >
            ⚠️ Belum ada titik koordinat yang dipilih. Klik tombol <strong>"Gunakan GPS Saya"</strong> atau klik langsung pada peta di atas.
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


<script setup>
import { onMounted, onUnmounted, ref, watch } from "vue";
import L from "leaflet";

const props = defineProps({
    courierLat: [Number, String],
    courierLng: [Number, String],
    destLat: [Number, String],
    destLng: [Number, String],
    recipientName: String,
    storeName: String,
});

const mapContainer = ref(null);
let map = null;
let courierMarker = null;
let destMarker = null;
let routeLine = null;
let routeCasing = null;

// Routing State
const roadDistance = ref("");
const roadDuration = ref("");
const isRouting = ref(false);

// Leaflet Icons
const defaultIcon = L.icon({
    iconUrl: "https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png",
    iconRetinaUrl:
        "https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png",
    shadowUrl: "https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png",
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41],
});

const courierIcon = L.divIcon({
    className: "custom-courier-marker",
    html: `
        <div style="position: relative; display: flex; align-items: center; justify-content: center;">
            <div style="position: absolute; width: 44px; height: 44px; background: rgba(59, 130, 246, 0.35); border-radius: 50%; animation: ping 1.5s cubic-bezier(0, 0, 0.2, 1) infinite;"></div>
            <div style="background-color: #2563eb; color: white; width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2.5px solid white; box-shadow: 0 4px 8px rgba(0,0,0,0.3); font-size: 17px; z-index: 10;">
                🛵
            </div>
        </div>
    `,
    iconSize: [36, 36],
    iconAnchor: [18, 18],
});

const destIcon = L.divIcon({
    className: "custom-dest-marker",
    html: `
        <div style="background-color: #dc2626; color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2.5px solid white; box-shadow: 0 4px 8px rgba(0,0,0,0.3); font-size: 15px;">
            📍
        </div>
    `,
    iconSize: [32, 32],
    iconAnchor: [16, 16],
});

const initMap = () => {
    if (!mapContainer.value) return;

    const cLat = Number(props.courierLat);
    const cLng = Number(props.courierLng);
    const dLat = Number(props.destLat);
    const dLng = Number(props.destLng);

    const hasCourier = !isNaN(cLat) && !isNaN(cLng) && cLat !== 0;
    const hasDest = !isNaN(dLat) && !isNaN(dLng) && dLat !== 0;

    const centerLat = hasCourier ? cLat : hasDest ? dLat : -6.2088;
    const centerLng = hasCourier ? cLng : hasDest ? dLng : 106.8456;

    map = L.map(mapContainer.value).setView([centerLat, centerLng], 14);

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap contributors",
        maxZoom: 19,
    }).addTo(map);

    updateMap();
};

// Ambil rute jalan nyata via OSRM (Open Source Routing Machine)
const fetchRoadRoute = async (cLat, cLng, dLat, dLng) => {
    isRouting.value = true;
    try {
        const url = `https://router.project-osrm.org/route/v1/driving/${cLng},${cLat};${dLng},${dLat}?overview=full&geometries=geojson`;
        const res = await fetch(url);
        if (!res.ok) throw new Error("OSRM network response not ok");
        const data = await res.json();

        if (data.routes && data.routes.length > 0) {
            const route = data.routes[0];
            const distKm = (route.distance / 1000).toFixed(1);
            const durationMin = Math.max(1, Math.round(route.duration / 60));

            roadDistance.value = `${distKm} km`;
            roadDuration.value = `~${durationMin} mnt`;

            // GeoJSON coordinates are [lng, lat], convert to Leaflet [lat, lng]
            const latLngs = route.geometry.coordinates.map((coord) => [
                coord[1],
                coord[0],
            ]);

            renderRouteLine(latLngs);
        } else {
            renderDirectLine(cLat, cLng, dLat, dLng);
        }
    } catch (e) {
        console.warn("Gagal memuat rute OSRM jalan:", e.message);
        renderDirectLine(cLat, cLng, dLat, dLng);
    } finally {
        isRouting.value = false;
    }
};

const renderRouteLine = (latLngs) => {
    if (!map) return;

    // Hapus rute lama
    if (routeCasing) map.removeLayer(routeCasing);
    if (routeLine) map.removeLayer(routeLine);

    // Casing rute (garis latar lebih tebal untuk kontras)
    routeCasing = L.polyline(latLngs, {
        color: "#1d4ed8",
        weight: 7,
        opacity: 0.4,
        lineCap: "round",
        lineJoin: "round",
    }).addTo(map);

    // Garis rute utama jalan raya
    routeLine = L.polyline(latLngs, {
        color: "#3b82f6",
        weight: 4.5,
        opacity: 0.95,
        lineCap: "round",
        lineJoin: "round",
    }).addTo(map);
};

const renderDirectLine = (cLat, cLng, dLat, dLng) => {
    if (!map) return;
    if (routeCasing) map.removeLayer(routeCasing);
    if (routeLine) map.removeLayer(routeLine);

    const latLngs = [
        [cLat, cLng],
        [dLat, dLng],
    ];
    routeLine = L.polyline(latLngs, {
        color: "#3b82f6",
        weight: 3.5,
        opacity: 0.8,
        dashArray: "8, 8",
    }).addTo(map);
};

const updateMap = () => {
    if (!map) return;

    const cLat = Number(props.courierLat);
    const cLng = Number(props.courierLng);
    const dLat = Number(props.destLat);
    const dLng = Number(props.destLng);

    const hasCourier = !isNaN(cLat) && !isNaN(cLng) && cLat !== 0;
    const hasDest = !isNaN(dLat) && !isNaN(dLng) && dLat !== 0;

    const bounds = [];

    if (hasCourier) {
        if (!courierMarker) {
            courierMarker = L.marker([cLat, cLng], { icon: courierIcon })
                .addTo(map)
                .bindPopup(
                    '<b style="font-size:12px;">Posisi Kurir Aktif 🛵</b>',
                );
        } else {
            courierMarker.setLatLng([cLat, cLng]);
        }
        bounds.push([cLat, cLng]);
    }

    if (hasDest) {
        if (!destMarker) {
            destMarker = L.marker([dLat, dLng], { icon: destIcon })
                .addTo(map)
                .bindPopup(
                    `<b style="font-size:12px;">Alamat Tujuan: ${props.recipientName || "Pelanggan"} 📍</b>`,
                );
        } else {
            destMarker.setLatLng([dLat, dLng]);
        }
        bounds.push([dLat, dLng]);
    }

    if (hasCourier && hasDest) {
        fetchRoadRoute(cLat, cLng, dLat, dLng);
    }

    if (bounds.length > 1) {
        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 });
    } else if (bounds.length === 1) {
        map.setView(bounds[0], 15);
    }
};

const openGoogleMaps = () => {
    const cLat = Number(props.courierLat);
    const cLng = Number(props.courierLng);
    const dLat = Number(props.destLat);
    const dLng = Number(props.destLng);

    if (dLat && dLng) {
        const url =
            cLat && cLng
                ? `https://www.google.com/maps/dir/?api=1&origin=${cLat},${cLng}&destination=${dLat},${dLng}&travelmode=driving`
                : `https://www.google.com/maps/search/?api=1&query=${dLat},${dLng}`;
        window.open(url, "_blank");
    }
};

watch(
    () => [props.courierLat, props.courierLng, props.destLat, props.destLng],
    () => {
        updateMap();
    },
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
    <div
        class="overflow-hidden rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-xl"
    >
        <!-- HEADER PETA TRACKING JALAN -->
        <div
            class="flex flex-wrap items-center justify-between gap-2 border-b border-[#0d685b]/20 bg-[#131d1a] px-4 py-3"
        >
            <div class="flex items-center gap-2">
                <span class="relative flex h-2.5 w-2.5">
                    <span
                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
                    ></span>
                    <span
                        class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"
                    ></span>
                </span>
                <span
                    class="text-xs font-bold uppercase tracking-wider text-[#f3f2e7]"
                >
                    Live Tracking Rute Jalan
                </span>
            </div>

            <!-- Jarak & Estimasi -->
            <div class="flex items-center gap-2">
                <span
                    v-if="roadDistance && roadDuration"
                    class="inline-flex items-center gap-1.5 rounded-full bg-[#0d685b]/40 border border-[#0d685b]/60 px-2.5 py-1 text-[11px] font-bold text-emerald-300 shadow-sm"
                >
                    <span>🛵</span>
                    <span>Jarak: {{ roadDistance }}</span>
                    <span class="text-emerald-400/50">·</span>
                    <span>Tiba: {{ roadDuration }}</span>
                </span>
                <span
                    v-else-if="courierLat && courierLng"
                    class="text-[11px] font-medium text-emerald-400 flex items-center gap-1"
                >
                    <span
                        class="h-1.5 w-1.5 rounded-full bg-emerald-400"
                    ></span>
                    GPS Terhubung
                </span>
                <span v-else class="text-[11px] text-[#f3f2e7]/50">
                    Menunggu GPS kurir...
                </span>

                <!-- Tombol Google Maps -->
                <button
                    v-if="destLat && destLng"
                    class="inline-flex items-center gap-1 rounded-lg border border-[#0d685b]/40 bg-[#131d1a] px-2.5 py-1 text-[11px] font-bold text-[#f3f2e7] shadow-sm hover:bg-[#0d685b]/20 transition"
                    title="Buka rute navigasi di Google Maps"
                    @click="openGoogleMaps"
                >
                    <span>Maps</span>
                    <span>↗</span>
                </button>
            </div>
        </div>

        <!-- CONTAINER LEAFLET MAP -->
        <div ref="mapContainer" class="h-80 w-full z-0"></div>

        <!-- FOOTER KETERANGAN TITIK -->
        <div
            class="flex flex-wrap items-center justify-between gap-2 border-t border-[#0d685b]/20 bg-[#131d1a] px-4 py-2 text-[11px] text-[#f3f2e7]/70"
        >
            <div class="flex items-center gap-3">
                <span class="flex items-center gap-1">
                    <span class="text-sm">🛵</span>
                    <span class="text-[#f3f2e7]">Posisi Kurir</span>
                </span>
                <span class="flex items-center gap-1">
                    <span class="text-sm">📍</span>
                    <span class="text-[#f3f2e7]">Lokasi Tujuan</span>
                </span>
                <span class="flex items-center gap-1">
                    <span
                        class="h-1.5 w-4 rounded-full bg-[#0d685b] inline-block"
                    ></span>
                    <span class="text-[#f3f2e7]">Jalur Jalan Raya</span>
                </span>
            </div>
            <span class="text-[#f3f2e7]/50">Pembaruan GPS berkala realtime</span>
        </div>
    </div>
</template>

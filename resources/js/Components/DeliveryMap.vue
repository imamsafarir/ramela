<script setup>
import { onMounted, onUnmounted, ref, watch } from "vue";
import L from "leaflet";

const props = defineProps({
    courierLat: [Number, String],
    courierLng: [Number, String],
    destLat: [Number, String],
    destLng: [Number, String],
    routeHistory: {
        type: Array,
        default: () => [],
    },
    recipientName: String,
    storeName: String,
});

const mapContainer = ref(null);
let map = null;
let courierMarker = null;
let destMarker = null;
let routeLine = null;
let routeCasing = null;
let historyLine = null;
let historyCasing = null;
let historyMarkers = [];

// Routing State
const roadDistance = ref("");
const roadDuration = ref("");
const isRouting = ref(false);
const routingError = ref(false);

// Leaflet Icons
const courierIcon = L.divIcon({
    className: "custom-courier-marker",
    html: `
        <div style="position: relative; display: flex; align-items: center; justify-content: center;">
            <div style="position: absolute; width: 44px; height: 44px; background: rgba(14, 165, 233, 0.4); border-radius: 50%; animation: ping 1.5s cubic-bezier(0, 0, 0.2, 1) infinite;"></div>
            <div style="background-color: #0284c7; color: white; width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2.5px solid white; box-shadow: 0 4px 10px rgba(0,0,0,0.4); font-size: 18px; z-index: 10;">
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
        <div style="position: relative; display: flex; align-items: center; justify-content: center;">
            <div style="position: absolute; width: 38px; height: 38px; background: rgba(239, 68, 68, 0.25); border-radius: 50%;"></div>
            <div style="background-color: #dc2626; color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2.5px solid white; box-shadow: 0 4px 8px rgba(0,0,0,0.3); font-size: 16px; z-index: 10;">
                📍
            </div>
        </div>
    `,
    iconSize: [32, 32],
    iconAnchor: [16, 16],
});

const breadcrumbDotIcon = L.divIcon({
    className: "custom-breadcrumb-marker",
    html: `
        <div style="background-color: #10b981; width: 8px; height: 8px; border-radius: 50%; border: 1.5px solid white; box-shadow: 0 1px 3px rgba(0,0,0,0.3);"></div>
    `,
    iconSize: [8, 8],
    iconAnchor: [4, 4],
});

const initMap = () => {
    if (!mapContainer.value) return;

    const cLat = Number(props.courierLat);
    const cLng = Number(props.courierLng);
    const dLat = Number(props.destLat);
    const dLng = Number(props.destLng);

    const hasCourier = !isNaN(cLat) && !isNaN(cLng) && cLat !== 0;
    const hasDest = !isNaN(dLat) && !isNaN(dLng) && dLat !== 0;

    // Default center ke area Semarang jika belum ada koordinat
    const centerLat = hasCourier ? cLat : hasDest ? dLat : -6.9932;
    const centerLng = hasCourier ? cLng : hasDest ? dLng : 110.4208;

    map = L.map(mapContainer.value, {
        zoomControl: true,
        attributionControl: false,
    }).setView([centerLat, centerLng], 14);

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
    }).addTo(map);

    // Pastikan container ukuran leaflet selalu fresh (tidak terpotong oleh CSS flex/grid)
    setTimeout(() => {
        if (map) map.invalidateSize();
    }, 200);
    setTimeout(() => {
        if (map) map.invalidateSize();
    }, 600);

    updateMap();
};

// Ambil rute jalan raya via OSRM (dengan mirror fallback yang teruji)
const fetchRoadRoute = async (cLat, cLng, dLat, dLng) => {
    isRouting.value = true;
    routingError.value = false;

    // Daftar server rute OSRM terpercaya
    const routingEndpoints = [
        `https://routing.openstreetmap.de/routed-car/route/v1/driving/${cLng},${cLat};${dLng},${dLat}?overview=full&geometries=geojson`,
        `https://router.project-osrm.org/route/v1/driving/${cLng},${cLat};${dLng},${dLat}?overview=full&geometries=geojson`,
    ];

    let loaded = false;

    for (const url of routingEndpoints) {
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 4000);

            const res = await fetch(url, { signal: controller.signal });
            clearTimeout(timeoutId);

            if (res.ok) {
                const data = await res.json();
                if (data.routes && data.routes.length > 0) {
                    const route = data.routes[0];
                    const distKm = (route.distance / 1000).toFixed(1);
                    const durationMin = Math.max(1, Math.round(route.duration / 60));

                    roadDistance.value = `${distKm} km`;
                    roadDuration.value = `~${durationMin} mnt`;

                    // Konversi GeoJSON [lng, lat] ke Leaflet [lat, lng]
                    const latLngs = route.geometry.coordinates.map((coord) => [
                        coord[1],
                        coord[0],
                    ]);

                    renderRouteLine(latLngs);
                    loaded = true;
                    break;
                }
            }
        } catch (e) {
            // Coba endpoint berikutnya
        }
    }

    if (!loaded) {
        routingError.value = true;
        renderDirectLine(cLat, cLng, dLat, dLng);
    }

    isRouting.value = false;
};

// Render garis rute jalan raya utama (kontras tinggi, tebal, jelas)
const renderRouteLine = (latLngs) => {
    if (!map) return;

    if (routeCasing) map.removeLayer(routeCasing);
    if (routeLine) map.removeLayer(routeLine);

    // Casing rute (outer stroke gelap untuk kontras maksimal)
    routeCasing = L.polyline(latLngs, {
        color: "#0f172a",
        weight: 9,
        opacity: 0.65,
        lineCap: "round",
        lineJoin: "round",
    }).addTo(map);

    // Garis rute jalan raya utama (neon sky blue)
    routeLine = L.polyline(latLngs, {
        color: "#0284c7",
        weight: 5.5,
        opacity: 0.95,
        lineCap: "round",
        lineJoin: "round",
    }).addTo(map);
};

// Render rute cadangan jika server OSRM sedang lambat
const renderDirectLine = (cLat, cLng, dLat, dLng) => {
    if (!map) return;
    if (routeCasing) map.removeLayer(routeCasing);
    if (routeLine) map.removeLayer(routeLine);

    const latLngs = [
        [cLat, cLng],
        [dLat, dLng],
    ];

    routeCasing = L.polyline(latLngs, {
        color: "#0f172a",
        weight: 8,
        opacity: 0.5,
    }).addTo(map);

    routeLine = L.polyline(latLngs, {
        color: "#0284c7",
        weight: 5,
        opacity: 0.9,
        dashArray: "8, 8",
    }).addTo(map);

    // Hitung jarak garis lurus kasar
    const R = 6371; // km
    const dLatRad = ((dLat - cLat) * Math.PI) / 180;
    const dLngRad = ((dLng - cLng) * Math.PI) / 180;
    const a =
        Math.sin(dLatRad / 2) * Math.sin(dLatRad / 2) +
        Math.cos((cLat * Math.PI) / 180) *
            Math.cos((dLat * Math.PI) / 180) *
            Math.sin(dLngRad / 2) *
            Math.sin(dLngRad / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    const distEst = (R * c).toFixed(1);
    roadDistance.value = `±${distEst} km`;
    roadDuration.value = `~${Math.round(distEst * 3)} mnt`;
};

// Render garis jejak fisik riwayat GPS kurir (breadcrumbs history)
const renderHistoryLine = (historyPoints, currentCourierPoint) => {
    if (!map) return;

    if (historyCasing) map.removeLayer(historyCasing);
    if (historyLine) map.removeLayer(historyLine);
    historyMarkers.forEach((m) => map.removeLayer(m));
    historyMarkers = [];

    if (!historyPoints || historyPoints.length === 0) return;

    const fullHistory = [...historyPoints];
    if (currentCourierPoint) {
        fullHistory.push(currentCourierPoint);
    }

    if (fullHistory.length < 2) return;

    // Casing riwayat jejak kurir
    historyCasing = L.polyline(fullHistory, {
        color: "#064e3b",
        weight: 7,
        opacity: 0.45,
        lineCap: "round",
        lineJoin: "round",
    }).addTo(map);

    // Garis jejak riwayat kurir (warna emerald cerah putus-putus)
    historyLine = L.polyline(fullHistory, {
        color: "#10b981",
        weight: 4.5,
        opacity: 0.95,
        dashArray: "6, 6",
        lineCap: "round",
        lineJoin: "round",
    }).addTo(map);

    // Tambahkan titik checkpoint breadcrumbs
    historyPoints.forEach((pt, i) => {
        const dot = L.marker(pt, { icon: breadcrumbDotIcon })
            .addTo(map)
            .bindPopup(`<div style="font-size:11px;">Titik GPS #${i + 1}</div>`);
        historyMarkers.push(dot);
    });
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

    // 1. Marker Kurir
    if (hasCourier) {
        if (!courierMarker) {
            courierMarker = L.marker([cLat, cLng], { icon: courierIcon })
                .addTo(map)
                .bindPopup(
                    '<b style="font-size:12px;">Posisi Kurir Aktif 🛵</b><br><span style="font-size:11px;color:#555;">GPS Realtime</span>',
                );
        } else {
            courierMarker.setLatLng([cLat, cLng]);
        }
        bounds.push([cLat, cLng]);
    }

    // 2. Marker Tujuan Pelanggan
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

    // 3. Garis Jejak Riwayat Kurir
    if (props.routeHistory && props.routeHistory.length > 0) {
        props.routeHistory.forEach((pt) => bounds.push(pt));
        renderHistoryLine(props.routeHistory, hasCourier ? [cLat, cLng] : null);
    }

    // 4. Garis Rute Jalan Raya Menuju Tujuan
    if (hasCourier && hasDest) {
        fetchRoadRoute(cLat, cLng, dLat, dLng);
    }

    // Fit view area peta agar seluruh rute dan titik terlihat
    if (bounds.length > 1) {
        map.fitBounds(bounds, { padding: [50, 50], maxZoom: 16 });
    } else if (bounds.length === 1) {
        map.setView(bounds[0], 15);
    }
};

const centerMap = () => {
    if (!map) return;
    map.invalidateSize();
    updateMap();
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
    () => [props.courierLat, props.courierLng, props.destLat, props.destLng, props.routeHistory],
    () => {
        updateMap();
    },
    { deep: true },
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
        <!-- HEADER PETA TRACKING RUTE JALAN -->
        <div
            class="flex flex-wrap items-center justify-between gap-2 border-b border-[#0d685b]/20 bg-[#131d1a] px-4 py-3"
        >
            <div class="flex items-center gap-2">
                <span class="relative flex h-2.5 w-2.5">
                    <span
                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-sky-400 opacity-75"
                    ></span>
                    <span
                        class="relative inline-flex h-2.5 w-2.5 rounded-full bg-sky-500"
                    ></span>
                </span>
                <span
                    class="text-xs font-bold uppercase tracking-wider text-[#f3f2e7]"
                >
                    Live Tracking & Garis Rute Jalan
                </span>
                <span
                    v-if="routeHistory.length"
                    class="rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 px-2 py-0.2 text-[10px] font-bold"
                >
                    {{ routeHistory.length }} Titik GPS
                </span>
            </div>

            <!-- Jarak, Estimasi, & Tombol Kontrol -->
            <div class="flex items-center gap-2">
                <span
                    v-if="roadDistance && roadDuration"
                    class="inline-flex items-center gap-1.5 rounded-full bg-[#0284c7]/30 border border-[#0284c7]/50 px-2.5 py-1 text-[11px] font-bold text-sky-200 shadow-sm"
                >
                    <span>🛣️</span>
                    <span>Jarak: {{ roadDistance }}</span>
                    <span class="text-sky-300/50">·</span>
                    <span>Estimasi: {{ roadDuration }}</span>
                </span>
                <span
                    v-else-if="courierLat && courierLng"
                    class="text-[11px] font-medium text-emerald-400 flex items-center gap-1"
                >
                    <span
                        class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"
                    ></span>
                    GPS Kurir Aktif
                </span>
                <span v-else class="text-[11px] text-[#f3f2e7]/50">
                    Menunggu titik koordinat kurir...
                </span>

                <!-- Tombol Pusatkan Peta -->
                <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-lg border border-[#0d685b]/40 bg-[#1c2a25] px-2 py-1 text-[11px] font-bold text-[#f3f2e7] hover:bg-[#131d1a] transition cursor-pointer"
                    title="Pusatkan peta ke rute & kurir"
                    @click="centerMap"
                >
                    <span>🎯</span>
                    <span class="hidden sm:inline">Pusatkan</span>
                </button>

                <!-- Tombol Navigasi Google Maps -->
                <button
                    v-if="destLat && destLng"
                    type="button"
                    class="inline-flex items-center gap-1 rounded-lg border border-[#0d685b]/40 bg-[#131d1a] px-2.5 py-1 text-[11px] font-bold text-[#f3f2e7] shadow-sm hover:bg-[#0d685b]/20 transition cursor-pointer"
                    title="Buka rute navigasi di Google Maps"
                    @click="openGoogleMaps"
                >
                    <span>Maps</span>
                    <span>↗</span>
                </button>
            </div>
        </div>

        <!-- CONTAINER LEAFLET MAP -->
        <div ref="mapContainer" class="h-80 sm:h-96 w-full z-0"></div>

        <!-- FOOTER LEGENDA GARIS RUTE JALAN -->
        <div
            class="flex flex-wrap items-center justify-between gap-2.5 border-t border-[#0d685b]/20 bg-[#131d1a] px-4 py-2.5 text-[11px] text-[#f3f2e7]/75"
        >
            <div class="flex flex-wrap items-center gap-4">
                <span class="flex items-center gap-1.5 font-medium">
                    <span class="text-sm">🛵</span>
                    <span class="text-[#f3f2e7]">Posisi Kurir</span>
                </span>
                <span class="flex items-center gap-1.5 font-medium">
                    <span class="text-sm">📍</span>
                    <span class="text-[#f3f2e7]">Alamat Tujuan</span>
                </span>
                <span class="flex items-center gap-1.5 font-medium">
                    <span
                        class="h-2 w-5 rounded-full bg-[#0284c7] border border-white/60 inline-block"
                    ></span>
                    <span class="text-sky-300 font-bold">Garis Rute Jalan Raya</span>
                </span>
                <span v-if="routeHistory.length" class="flex items-center gap-1.5 font-medium">
                    <span
                        class="h-2 w-5 rounded-full bg-[#10b981] border border-white/60 inline-block border-dashed"
                    ></span>
                    <span class="text-emerald-300 font-bold">Jejak GPS Kurir</span>
                </span>
            </div>
            <span class="text-[10px] text-[#f3f2e7]/50">
                Pembaruan GPS berkala realtime · Jalan raya OpenStreetMap
            </span>
        </div>
    </div>
</template>

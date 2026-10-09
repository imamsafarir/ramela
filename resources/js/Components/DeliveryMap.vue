<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from "vue";
import L from "leaflet";

const props = defineProps({
    storeLat: [Number, String],
    storeLng: [Number, String],
    storeName: String,
    storeAddress: String,
    courierLat: [Number, String],
    courierLng: [Number, String],
    destLat: [Number, String],
    destLng: [Number, String],
    recipientName: String,
    destinationAddress: String,
    deliveryStatus: String, // 'waiting_pickup', 'en_route', 'delivered'
    routeHistory: {
        type: Array,
        default: () => [],
    },
});

const mapContainer = ref(null);
let map = null;
let storeMarker = null;
let courierMarker = null;
let destMarker = null;
let routeLine = null;
let routeCasing = null;
let historyLine = null;
let historyCasing = null;
let historyMarkers = [];

// Routing & Estimation State
const roadDistance = ref("");
const roadDuration = ref("");
const estimatedArrival = ref("");
const isEstimateDestination = ref(false);
const isRouting = ref(false);
const routingError = ref(false);

const calculateEta = (durationMinutes) => {
    const now = new Date();
    const arrivalTime = new Date(now.getTime() + durationMinutes * 60 * 1000);
    const hours = String(arrivalTime.getHours()).padStart(2, "0");
    const minutes = String(arrivalTime.getMinutes()).padStart(2, "0");
    return `${hours}:${minutes} WIB`;
};

// Fallback koordinat toko jika tidak dikirimkan dari backend
const getEffectiveStoreCoords = () => {
    const sLat = Number(props.storeLat);
    const sLng = Number(props.storeLng);
    if (!isNaN(sLat) && !isNaN(sLng) && sLat !== 0) {
        return { lat: sLat, lng: sLng };
    }

    const name = (props.storeName || "").toUpperCase();
    if (name.includes("BETON")) {
        return { lat: -6.987540, lng: 110.345020 }; // Candi Ngaliyan Semarang
    }
    if (name.includes("HAMPERS")) {
        return { lat: -6.973050, lng: 110.428510 }; // Pemuda Semarang
    }
    return { lat: -6.989720, lng: 110.421930 }; // Pandanaran Semarang (Eats / Pusat)
};

// Koordinat tujuan efektif: gunakan koordinat pemesan jika ada, atau estimasi titik Semarang jika belum diset
const getEffectiveDestCoords = () => {
    const dLat = Number(props.destLat);
    const dLng = Number(props.destLng);
    if (!isNaN(dLat) && !isNaN(dLng) && dLat !== 0) {
        return { lat: dLat, lng: dLng, isEstimate: false };
    }

    // Jika pesanan belum memiliki koordinat GPS tersimpan, lakukan estimasi posisi tujuan di area Kota Semarang
    const storeCoords = getEffectiveStoreCoords();
    return {
        lat: Number((storeCoords.lat + 0.0085).toFixed(6)),
        lng: Number((storeCoords.lng + 0.0145).toFixed(6)),
        isEstimate: true,
    };
};

// Leaflet Custom Marker Icons
const createStoreIcon = (name) =>
    L.divIcon({
        className: "custom-store-marker",
        html: `
        <div style="position: relative; display: flex; flex-direction: column; align-items: center; cursor: pointer; filter: drop-shadow(0 6px 12px rgba(0,0,0,0.55));">
            <!-- Label Badge Nama Toko -->
            <div style="background: #0d685b; color: #f3f2e7; font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 12px; border: 1.5px solid #f3f2e7; white-space: nowrap; margin-bottom: 3px; display: flex; align-items: center; gap: 4px; box-shadow: 0 2px 6px rgba(0,0,0,0.4);">
                <span>🏪</span>
                <span>${name || 'Toko RAMELA'}</span>
            </div>
            <!-- Pin Lingkaran Gambar Toko Ramela -->
            <div style="position: relative; width: 50px; height: 50px; border-radius: 50%; background: #131d1a; border: 3px solid #149683; display: flex; align-items: center; justify-content: center; overflow: hidden; box-shadow: 0 0 12px rgba(20, 150, 131, 0.6);">
                <img src="/images/store-ramela.svg" alt="Toko Ramela" style="width: 100%; height: 100%; object-fit: cover;" />
            </div>
            <!-- Segitiga Pin Pointer Bawah -->
            <div style="width: 0; height: 0; border-left: 7px solid transparent; border-right: 7px solid transparent; border-top: 9px solid #149683; margin-top: -1px;"></div>
        </div>
    `,
        iconSize: [120, 84],
        iconAnchor: [60, 84],
        popupAnchor: [0, -84],
    });

const createCourierIcon = () =>
    L.divIcon({
        className: "custom-courier-marker",
        html: `
        <div style="position: relative; display: flex; flex-direction: column; align-items: center; cursor: pointer; filter: drop-shadow(0 6px 14px rgba(0,0,0,0.6));">
            <!-- Label Badge Kurir Realtime -->
            <div style="background: #0284c7; color: #ffffff; font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 12px; border: 1.5px solid #ffffff; white-space: nowrap; margin-bottom: 3px; display: flex; align-items: center; gap: 4px; box-shadow: 0 2px 6px rgba(0,0,0,0.4);">
                <span style="display: inline-block; width: 6px; height: 6px; border-radius: 50%; background: #38bdf8; animation: ping 1.2s cubic-bezier(0, 0, 0.2, 1) infinite;"></span>
                <span>🛵 Kurir Realtime</span>
            </div>
            <!-- Lingkaran Pin Kurir dengan Ping Radar -->
            <div style="position: relative; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center;">
                <div style="position: absolute; width: 44px; height: 44px; background: rgba(14, 165, 233, 0.45); border-radius: 50%; animation: ping 1.8s cubic-bezier(0, 0, 0.2, 1) infinite;"></div>
                <div style="background-color: #0284c7; color: white; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2.5px solid white; font-size: 19px; z-index: 10; box-shadow: 0 0 10px rgba(14, 165, 233, 0.7);">
                    🛵
                </div>
            </div>
            <!-- Segitiga Pin Pointer Bawah -->
            <div style="width: 0; height: 0; border-left: 6px solid transparent; border-right: 6px solid transparent; border-top: 8px solid #0284c7; margin-top: -1px;"></div>
        </div>
    `,
        iconSize: [110, 76],
        iconAnchor: [55, 76],
        popupAnchor: [0, -76],
    });

const createDestIcon = (recipientName) =>
    L.divIcon({
        className: "custom-dest-marker",
        html: `
        <div style="position: relative; display: flex; flex-direction: column; align-items: center; cursor: pointer; filter: drop-shadow(0 6px 12px rgba(0,0,0,0.55));">
            <!-- Label Badge Tujuan -->
            <div style="background: #dc2626; color: #ffffff; font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 12px; border: 1.5px solid #ffffff; white-space: nowrap; margin-bottom: 3px; max-width: 140px; overflow: hidden; text-overflow: ellipsis; box-shadow: 0 2px 6px rgba(0,0,0,0.4);">
                📍 ${recipientName ? 'Tujuan: ' + recipientName : 'Alamat Tujuan'}
            </div>
            <!-- Lingkaran Pin Tujuan -->
            <div style="position: relative; width: 38px; height: 38px; display: flex; align-items: center; justify-content: center;">
                <div style="position: absolute; width: 38px; height: 38px; background: rgba(239, 68, 68, 0.3); border-radius: 50%;"></div>
                <div style="background-color: #dc2626; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2.5px solid white; font-size: 15px; z-index: 10;">
                    📍
                </div>
            </div>
            <!-- Segitiga Pin Pointer Bawah -->
            <div style="width: 0; height: 0; border-left: 6px solid transparent; border-right: 6px solid transparent; border-top: 8px solid #dc2626; margin-top: -1px;"></div>
        </div>
    `,
        iconSize: [130, 70],
        iconAnchor: [65, 70],
        popupAnchor: [0, -70],
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

    const storeCoords = getEffectiveStoreCoords();
    const destCoords = getEffectiveDestCoords();
    const cLat = Number(props.courierLat);
    const cLng = Number(props.courierLng);

    const hasCourier = !isNaN(cLat) && !isNaN(cLng) && cLat !== 0;

    const centerLat = hasCourier ? cLat : destCoords.lat;
    const centerLng = hasCourier ? cLng : destCoords.lng;

    map = L.map(mapContainer.value, {
        zoomControl: true,
        attributionControl: false,
    }).setView([centerLat, centerLng], 14);

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
    }).addTo(map);

    setTimeout(() => {
        if (map) map.invalidateSize();
    }, 200);
    setTimeout(() => {
        if (map) map.invalidateSize();
    }, 600);

    updateMap();
};

let lastRouteRequest = { fromLat: 0, fromLng: 0, toLat: 0, toLng: 0, time: 0 };

// Ambil rute jalan raya via OSRM (dengan mirror fallback yang teruji)
const fetchRoadRoute = async (fromLat, fromLng, toLat, toLng) => {
    const distDelta = Math.hypot(fromLat - lastRouteRequest.fromLat, fromLng - lastRouteRequest.fromLng);
    const timeDelta = Date.now() - lastRouteRequest.time;
    if (lastRouteRequest.time > 0 && distDelta < 0.0004 && timeDelta < 20000) {
        return;
    }

    lastRouteRequest = { fromLat, fromLng, toLat, toLng, time: Date.now() };
    isRouting.value = true;
    routingError.value = false;

    const routingEndpoints = [
        `https://routing.openstreetmap.de/routed-car/route/v1/driving/${fromLng},${fromLat};${toLng},${toLat}?overview=full&geometries=geojson`,
        `https://router.project-osrm.org/route/v1/driving/${fromLng},${fromLat};${toLng},${toLat}?overview=full&geometries=geojson`,
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
                    estimatedArrival.value = calculateEta(durationMin);

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
            // Coba server mirror selanjutnya
        }
    }

    if (!loaded) {
        routingError.value = true;
        renderDirectLine(fromLat, fromLng, toLat, toLng);
    }

    isRouting.value = false;
};

// Render garis rute jalan raya utama (kontras tinggi, tebal, jelas)
const renderRouteLine = (latLngs) => {
    if (!map) return;

    if (routeCasing) map.removeLayer(routeCasing);
    if (routeLine) map.removeLayer(routeLine);

    routeCasing = L.polyline(latLngs, {
        color: "#0f172a",
        weight: 9,
        opacity: 0.65,
        lineCap: "round",
        lineJoin: "round",
    }).addTo(map);

    routeLine = L.polyline(latLngs, {
        color: "#0284c7",
        weight: 5.5,
        opacity: 0.95,
        lineCap: "round",
        lineJoin: "round",
    }).addTo(map);
};

// Render rute cadangan jika server OSRM sedang lambat
const renderDirectLine = (fromLat, fromLng, toLat, toLng) => {
    if (!map) return;
    if (routeCasing) map.removeLayer(routeCasing);
    if (routeLine) map.removeLayer(routeLine);

    const R = 6371; // km
    const dLatRad = ((toLat - fromLat) * Math.PI) / 180;
    const dLngRad = ((toLng - fromLng) * Math.PI) / 180;
    const a =
        Math.sin(dLatRad / 2) * Math.sin(dLatRad / 2) +
        Math.cos((fromLat * Math.PI) / 180) *
            Math.cos((toLat * Math.PI) / 180) *
            Math.sin(dLngRad / 2) *
            Math.sin(dLngRad / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    const straightDist = R * c;

    // Estimasi jarak jalan raya perkotaan (faktor 1.35x dari garis lurus)
    const roadDistEst = (straightDist * 1.35).toFixed(1);
    // Estimasi waktu tempuh motor (kecepatan rata-rata ~25 km/jam di perkotaan)
    const durationEst = Math.max(2, Math.round(Number(roadDistEst) * 2.4));

    roadDistance.value = `±${roadDistEst} km`;
    roadDuration.value = `~${durationEst} mnt`;
    estimatedArrival.value = calculateEta(durationEst);

    const latLngs = [
        [fromLat, fromLng],
        [fromLat + (toLat - fromLat) * 0.33, fromLng + (toLng - fromLng) * 0.33],
        [fromLat + (toLat - fromLat) * 0.66, fromLng + (toLng - fromLng) * 0.66],
        [toLat, toLng],
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
};

// Render garis jejak fisik riwayat GPS kurir (breadcrumbs history)
const renderHistoryLine = (historyPoints, currentPoint) => {
    if (!map) return;

    if (historyCasing) map.removeLayer(historyCasing);
    if (historyLine) map.removeLayer(historyLine);
    historyMarkers.forEach((m) => map.removeLayer(m));
    historyMarkers = [];

    if (!historyPoints || historyPoints.length === 0) return;

    const fullHistory = [...historyPoints];
    if (currentPoint) {
        fullHistory.push(currentPoint);
    }

    if (fullHistory.length < 2) return;

    historyCasing = L.polyline(fullHistory, {
        color: "#064e3b",
        weight: 7,
        opacity: 0.45,
        lineCap: "round",
        lineJoin: "round",
    }).addTo(map);

    historyLine = L.polyline(fullHistory, {
        color: "#10b981",
        weight: 4.5,
        opacity: 0.95,
        dashArray: "6, 6",
        lineCap: "round",
        lineJoin: "round",
    }).addTo(map);

    historyPoints.forEach((pt, i) => {
        const dot = L.marker(pt, { icon: breadcrumbDotIcon })
            .addTo(map)
            .bindPopup(`<div style="font-size:11px;">Jejak GPS #${i + 1}</div>`);
        historyMarkers.push(dot);
    });
};

const updateMap = () => {
    if (!map) return;

    const storeCoords = getEffectiveStoreCoords();
    const destCoords = getEffectiveDestCoords();
    const cLat = Number(props.courierLat);
    const cLng = Number(props.courierLng);

    const hasCourier = !isNaN(cLat) && !isNaN(cLng) && cLat !== 0;
    const isEnRoute = props.deliveryStatus === 'en_route';
    const isDelivered = props.deliveryStatus === 'delivered';
    isEstimateDestination.value = destCoords.isEstimate;

    const bounds = [];

    // 1. Marker Toko Asal (TITIK AWAL RUTE: Selalu ada dengan Gambar Toko Ramela!)
    const storePopupHtml = `
        <div style="min-width: 190px; font-family: sans-serif;">
            <img src="/images/store-ramela.svg" style="width: 100%; height: 85px; object-fit: cover; border-radius: 8px; margin-bottom: 6px; border: 1.5px solid #0d685b;" alt="Toko Ramela" />
            <b style="font-size: 13px; color: #0f172a; display: block;">🏪 ${props.storeName || "Toko RAMELA"}</b>
            <span style="font-size: 11px; color: #475569; display: block; margin-top: 2px;">${props.storeAddress || "Pusat Pengiriman Toko"}</span>
            <div style="margin-top: 6px; padding: 3px 8px; background: #e6f4f1; color: #0d685b; font-size: 10px; font-weight: 800; border-radius: 6px; display: inline-block;">
                🚩 Titik Awal Rute Pengiriman
            </div>
        </div>
    `;

    if (!storeMarker) {
        storeMarker = L.marker([storeCoords.lat, storeCoords.lng], { icon: createStoreIcon(props.storeName) })
            .addTo(map)
            .bindPopup(storePopupHtml);
    } else {
        storeMarker.setLatLng([storeCoords.lat, storeCoords.lng]);
        storeMarker.setIcon(createStoreIcon(props.storeName));
        storeMarker.setPopupContent(storePopupHtml);
    }
    bounds.push([storeCoords.lat, storeCoords.lng]);

    // 2. Marker Tujuan Pelanggan (Selalu ada)
    const destPopupHtml = destCoords.isEstimate
        ? `<div style="font-family: sans-serif;"><b style="font-size:12px; color:#0f172a;">📍 Tujuan (Lokasi Estimasi): ${props.recipientName || "Pelanggan"}</b><br><span style="font-size:11px;color:#475569;">${props.destinationAddress || "Alamat Pengiriman"}</span><br><span style="font-size:10px;color:#0284c7;font-weight:bold;">*Titik koordinat diestimasi dari wilayah alamat penerima</span></div>`
        : `<div style="font-family: sans-serif;"><b style="font-size:12px; color:#0f172a;">📍 Tujuan: ${props.recipientName || "Pelanggan"}</b><br><span style="font-size:11px;color:#475569;">${props.destinationAddress || "Alamat Pengiriman"}</span></div>`;

    if (!destMarker) {
        destMarker = L.marker([destCoords.lat, destCoords.lng], { icon: createDestIcon(props.recipientName) })
            .addTo(map)
            .bindPopup(destPopupHtml);
    } else {
        destMarker.setLatLng([destCoords.lat, destCoords.lng]);
        destMarker.setIcon(createDestIcon(props.recipientName));
        destMarker.setPopupContent(destPopupHtml);
    }
    bounds.push([destCoords.lat, destCoords.lng]);

    // 3. Marker Kurir & Posisi Realtime
    let actualCourierLat = cLat;
    let actualCourierLng = cLng;

    if (hasCourier) {
        const isIdenticalToStore = Math.abs(cLat - storeCoords.lat) < 0.0001 && Math.abs(cLng - storeCoords.lng) < 0.0001;
        // Hanya beri offset visual tipis jika kurir masih waiting_pickup dan koordinat sama persis dengan toko,
        // agar kedua marker bisa diklik secara terpisah tanpa tumpang tindih total.
        if (isIdenticalToStore && !isEnRoute && !isDelivered) {
            actualCourierLat = Number((cLat + 0.0007).toFixed(6));
            actualCourierLng = Number((cLng + 0.0007).toFixed(6));
        }

        const courierPopupHtml = `
            <div style="font-family: sans-serif; min-width: 175px;">
                <b style="font-size: 13px; color: #0284c7; display: flex; align-items: center; gap: 4px;">
                    <span>🛵</span>
                    <span>${isEnRoute ? "Kurir Sedang Mengantar" : isDelivered ? "Kurir Tiba di Tujuan" : "Kurir Siap Antar"}</span>
                </b>
                <span style="font-size: 11px; color: #475569; display: block; margin-top: 3px;">
                    ${isEnRoute ? "Sedang dalam perjalanan menuju pemesan" : isDelivered ? "Paket telah diserahterimakan" : "Bersiap melakukan pengantaran"}
                </span>
                <div style="margin-top: 6px; padding: 4px 6px; background: #e0f2fe; border: 1px solid #bae6fd; border-radius: 4px; font-size: 10px; color: #0369a1; font-family: monospace;">
                    📍 GPS Realtime: ${actualCourierLat.toFixed(5)}, ${actualCourierLng.toFixed(5)}
                </div>
            </div>
        `;

        if (!courierMarker) {
            courierMarker = L.marker([actualCourierLat, actualCourierLng], { icon: createCourierIcon() })
                .addTo(map)
                .bindPopup(courierPopupHtml);
        } else {
            courierMarker.setLatLng([actualCourierLat, actualCourierLng]);
            courierMarker.setIcon(createCourierIcon());
            courierMarker.setPopupContent(courierPopupHtml);
        }
        bounds.push([actualCourierLat, actualCourierLng]);
    } else {
        // Jika kurir belum aktif/belum ada sinyal GPS, hapus marker kurir agar tidak menutup toko
        if (courierMarker) {
            map.removeLayer(courierMarker);
            courierMarker = null;
        }
    }

    // 4. Riwayat Jejak GPS Kurir (Breadcrumbs)
    let historyPoints = [...(props.routeHistory || [])];
    if (historyPoints.length === 0 && (isEnRoute || isDelivered) && hasCourier) {
        historyPoints = [[storeCoords.lat, storeCoords.lng]];
    }

    if (historyPoints.length > 0) {
        historyPoints.forEach((pt) => bounds.push(pt));
        renderHistoryLine(historyPoints, hasCourier ? [actualCourierLat, actualCourierLng] : null);
    }

    // 5. Garis Rute Jalan Raya (OSRM):
    // Jika kurir sedang aktif mengantar (en_route), rute jalan dinavigasikan dari kurir langsung ke tujuan!
    // Jika belum jalan (waiting_pickup) atau kurir belum ada, rute dari Toko Ramela ke Tujuan.
    if (isEnRoute && hasCourier) {
        fetchRoadRoute(actualCourierLat, actualCourierLng, destCoords.lat, destCoords.lng);
    } else {
        fetchRoadRoute(storeCoords.lat, storeCoords.lng, destCoords.lat, destCoords.lng);
    }

    // Jika kurir sedang en_route dan memiliki posisi realtime yang berbeda, perbarui estimasi jarak/waktu dari posisi kurir ke tujuan
    if (isEnRoute && hasCourier) {
        const R = 6371; // km
        const dLatRad = ((destCoords.lat - cLat) * Math.PI) / 180;
        const dLngRad = ((destCoords.lng - cLng) * Math.PI) / 180;
        const a =
            Math.sin(dLatRad / 2) * Math.sin(dLatRad / 2) +
            Math.cos((cLat * Math.PI) / 180) *
                Math.cos((destCoords.lat * Math.PI) / 180) *
                Math.sin(dLngRad / 2) *
                Math.sin(dLngRad / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        const roadDistEst = (R * c * 1.35).toFixed(1);
        const durationEst = Math.max(1, Math.round(Number(roadDistEst) * 2.4));

        roadDistance.value = `${roadDistEst} km`;
        roadDuration.value = `~${durationEst} mnt`;
        estimatedArrival.value = calculateEta(durationEst);
    }

    // Fit view area peta agar seluruh rute dan titik terlihat
    fitAllBounds();
};

const fitAllBounds = () => {
    if (!map) return;
    map.invalidateSize();

    const storeCoords = getEffectiveStoreCoords();
    const destCoords = getEffectiveDestCoords();
    const cLat = Number(props.courierLat);
    const cLng = Number(props.courierLng);
    const hasCourier = !isNaN(cLat) && !isNaN(cLng) && cLat !== 0;

    const bounds = [];
    bounds.push([storeCoords.lat, storeCoords.lng]);
    bounds.push([destCoords.lat, destCoords.lng]);

    if (hasCourier) {
        bounds.push([cLat, cLng]);
    }

    if (props.routeHistory && props.routeHistory.length) {
        props.routeHistory.forEach((pt) => bounds.push(pt));
    }

    if (bounds.length > 1) {
        map.fitBounds(bounds, { padding: [50, 50], maxZoom: 16, animate: true });
    } else if (bounds.length === 1) {
        map.setView(bounds[0], 15, { animate: true });
    }
};

const centerMap = () => {
    fitAllBounds();
};

const openGoogleMaps = () => {
    const storeCoords = getEffectiveStoreCoords();
    const destCoords = getEffectiveDestCoords();
    const cLat = Number(props.courierLat);
    const cLng = Number(props.courierLng);

    const hasCourier = !isNaN(cLat) && !isNaN(cLng) && cLat !== 0;
    const originLat = hasCourier ? cLat : storeCoords.lat;
    const originLng = hasCourier ? cLng : storeCoords.lng;
    const url = `https://www.google.com/maps/dir/?api=1&origin=${originLat},${originLng}&destination=${destCoords.lat},${destCoords.lng}&travelmode=driving`;
    window.open(url, "_blank");
};

watch(
    () => [
        props.storeLat,
        props.storeLng,
        props.courierLat,
        props.courierLng,
        props.destLat,
        props.destLng,
        props.routeHistory,
        props.deliveryStatus,
    ],
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
        class="relative w-full overflow-hidden rounded-2xl bg-[#1c2a25] border border-[#0d685b]/30 shadow-xl"
    >
        <!-- BAR STATUS PETA & KONTROL -->
        <div
            class="flex flex-wrap items-center justify-between gap-2 border-b border-[#0d685b]/20 bg-[#131d1a] px-4 py-3"
        >
            <div class="flex items-center gap-2 flex-wrap">
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
                    Rute Jalan & Pelacakan Kurir
                </span>
                <span
                    v-if="deliveryStatus === 'waiting_pickup'"
                    class="rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/40 px-2 py-0.5 text-[10px] font-bold"
                >
                    Di Toko
                </span>
                <span
                    v-else-if="deliveryStatus === 'en_route'"
                    class="rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 px-2 py-0.5 text-[10px] font-bold"
                >
                    Sedang Diantar
                </span>
                <span
                    v-else-if="deliveryStatus === 'delivered'"
                    class="rounded-full bg-teal-500/20 text-teal-300 border border-teal-500/40 px-2 py-0.5 text-[10px] font-bold"
                >
                    Selesai Diterima
                </span>
                <span
                    v-if="isEstimateDestination"
                    class="rounded-full bg-blue-500/20 text-blue-300 border border-blue-500/40 px-2 py-0.5 text-[10px] font-bold"
                    title="Titik tujuan diestimasi dari wilayah alamat penerima"
                >
                    Lokasi Estimasi
                </span>
            </div>

            <!-- Jarak, Estimasi, & Tombol Kontrol -->
            <div class="flex items-center gap-2 flex-wrap">
                <span
                    v-if="roadDistance && roadDuration"
                    class="inline-flex items-center gap-1.5 rounded-full bg-[#0284c7]/30 border border-[#0284c7]/50 px-2.5 py-1 text-[11px] font-bold text-sky-200 shadow-sm"
                >
                    <span>🛣️</span>
                    <span>Jarak: {{ roadDistance }}</span>
                    <span class="text-sky-300/50">·</span>
                    <span>Waktu: {{ roadDuration }}</span>
                    <span v-if="estimatedArrival" class="text-sky-300/50">·</span>
                    <span v-if="estimatedArrival" class="text-emerald-300 font-extrabold">🏁 Tiba: {{ estimatedArrival }}</span>
                </span>
                <span
                    v-else-if="isRouting"
                    class="text-[11px] text-[#f3f2e7]/60 animate-pulse"
                >
                    Menghitung rute & estimasi...
                </span>

                <!-- Tombol Pusatkan Peta -->
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-[#0d685b]/40 bg-[#1c2a25] px-2.5 py-1 text-[11px] font-bold text-[#f3f2e7] hover:bg-[#131d1a] active:scale-95 transition cursor-pointer shadow-xs"
                    title="Pusatkan peta ke seluruh rute"
                    @click="centerMap"
                >
                    <span>🎯</span>
                    <span>Pusatkan</span>
                </button>

                <!-- Tombol Navigasi Google Maps -->
                <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-lg border border-[#0d685b]/40 bg-[#1c2a25] px-2.5 py-1 text-[11px] font-bold text-[#f3f2e7] shadow-sm hover:bg-[#0d685b]/20 transition cursor-pointer"
                    title="Buka rute navigasi di Google Maps"
                    @click="openGoogleMaps"
                >
                    <span>Maps</span>
                    <span>↗</span>
                </button>
            </div>
        </div>

        <!-- CONTAINER LEAFLET MAP & FLOATING CONTROLS -->
        <div class="relative w-full">
            <div ref="mapContainer" class="h-80 sm:h-96 w-full z-0"></div>

            <!-- Tombol Floating Pusatkan Peta Langsung di Atas Peta -->
            <button
                type="button"
                class="absolute bottom-3 right-3 z-[400] inline-flex items-center gap-1.5 rounded-xl border border-[#0d685b]/60 bg-[#131d1a]/95 backdrop-blur px-3 py-1.5 text-xs font-bold text-[#f3f2e7] shadow-xl hover:bg-[#1c2a25] active:scale-95 transition cursor-pointer"
                title="Pusatkan tampilan peta ke seluruh rute"
                @click="centerMap"
            >
                <span>🎯</span>
                <span>Pusatkan Peta</span>
            </button>
        </div>

        <!-- FOOTER LEGENDA GARIS RUTE JALAN -->
        <div
            class="flex flex-wrap items-center justify-between gap-2.5 border-t border-[#0d685b]/20 bg-[#131d1a] px-4 py-2.5 text-[11px] text-[#f3f2e7]/75"
        >
            <div class="flex flex-wrap items-center gap-3 sm:gap-4">
                <span class="flex items-center gap-1.5 font-medium">
                    <img src="/images/store-ramela.svg" class="w-4 h-4 rounded-full border border-emerald-400 object-cover" alt="Toko Ramela" />
                    <span class="text-[#f3f2e7]">Toko: {{ storeName || 'Toko RAMELA' }} (Awal Rute)</span>
                </span>
                <span class="flex items-center gap-1.5 font-medium">
                    <span class="text-sm">🛵</span>
                    <span class="text-sky-300 font-semibold">Kurir Realtime (GPS)</span>
                </span>
                <span class="flex items-center gap-1 font-medium">
                    <span class="text-sm">📍</span>
                    <span class="text-[#f3f2e7]">Tujuan: {{ recipientName || 'Pelanggan' }}</span>
                </span>
                <span class="flex items-center gap-1 font-medium">
                    <span
                        class="h-2 w-5 rounded-full bg-[#0284c7] border border-white/60 inline-block"
                    ></span>
                    <span class="text-sky-300 font-bold">Rute Jalan Raya</span>
                </span>
                <span v-if="routeHistory.length" class="flex items-center gap-1 font-medium">
                    <span
                        class="h-2 w-5 rounded-full bg-[#10b981] border border-white/60 inline-block border-dashed"
                    ></span>
                    <span class="text-emerald-300 font-bold">Jejak GPS ({{ routeHistory.length }})</span>
                </span>
            </div>
            <span class="text-[10px] text-[#f3f2e7]/50">
                Jalan raya OpenStreetMap · Rute real OSRM
            </span>
        </div>
    </div>
</template>

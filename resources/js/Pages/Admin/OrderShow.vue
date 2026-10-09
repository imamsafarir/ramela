<script setup>
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { onMounted, onUnmounted, ref } from "vue";
import AdminLayout from "../../Layouts/AdminLayout.vue";
import DeliveryMap from "../../Components/DeliveryMap.vue";
import { fmtDate, rupiah, statusClass } from "../../utils/format";

defineOptions({ layout: AdminLayout });

const props = defineProps({
    order: Object,
    actions: Array,
    couriers: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({ status: "", note: "" });

const selectedCourierId = ref(props.order.delivery?.courier_id || "");
const showReassign = ref(false);
const isAssigning = ref(false);
let pollTimer = null;
const isRefreshing = ref(false);

const refreshOrder = () => {
    isRefreshing.value = true;
    router.reload({
        only: ["order"],
        preserveScroll: true,
        onFinish: () => {
            isRefreshing.value = false;
        },
    });
};

onMounted(() => {
    if (props.order.status === "shipping") {
        pollTimer = setInterval(() => {
            refreshOrder();
        }, 5000);
    }
});

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
});

const assignCourier = () => {
    if (!selectedCourierId.value) return;
    isAssigning.value = true;
    router.post(
        "/admin/kurir/assign",
        {
            invoice_number: props.order.invoice_number,
            courier_id: selectedCourierId.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showReassign.value = false;
                isAssigning.value = false;
            },
            onFinish: () => {
                isAssigning.value = false;
            },
        },
    );
};

const apply = (a) => {
    const msg =
        a.value === "cancelled"
            ? "Batalkan pesanan? Saldo pelanggan akan dikembalikan dan stok dipulihkan."
            : `Ubah status menjadi "${a.label}"?`;
    if (!confirm(msg)) return;
    form.status = a.value;
    form.patch(`/admin/pesanan/${props.order.invoice_number}/status`, {
        preserveScroll: true,
        onSuccess: () => form.reset("note"),
    });
};

const computeDistanceKm = (lat1, lon1, lat2, lon2) => {
    if (!lat1 || !lon1 || !lat2 || !lon2) return null;
    const R = 6371;
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

const estimateDurationText = (lat1, lon1, lat2, lon2) => {
    const km = computeDistanceKm(lat1, lon1, lat2, lon2);
    if (!km) return null;
    const roadKm = km * 1.3;
    const minutes = Math.max(5, Math.round((roadKm / 30) * 60) + 3);
    if (minutes < 60) return `± ${minutes} menit (${km} km)`;
    const hours = Math.floor(minutes / 60);
    const rem = minutes % 60;
    return `± ${hours} jam ${rem > 0 ? rem + " mnt" : ""} (${km} km)`;
};

const getGoogleMapsDirUrl = (
    destLat,
    destLng,
    originLat = null,
    originLng = null,
) => {
    if (!destLat || !destLng) return "#";
    if (originLat && originLng) {
        return `https://www.google.com/maps/dir/?api=1&origin=${originLat},${originLng}&destination=${destLat},${destLng}&travelmode=driving`;
    }
    return `https://www.google.com/maps/dir/?api=1&destination=${destLat},${destLng}&travelmode=driving`;
};
</script>

<template>
    <Head :title="order.invoice_number" />

    <!-- TOMBOL KEMBALI -->
    <div class="mb-4">
        <Link
            href="/admin/pesanan"
            class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#f3f2e7]/70 hover:text-[#f3f2e7] transition"
        >
            <span>←</span>
            <span>Kembali ke Daftar Pesanan</span>
        </Link>
    </div>

    <!-- HEADER KARTU UTAMA -->
    <div
        class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 sm:p-5 shadow-lg text-[#f3f2e7]"
    >
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-xl sm:text-2xl font-black text-[#f3f2e7] tracking-tight">
                        {{ order.invoice_number }}
                    </h1>
                    <span
                        class="rounded-full px-2.5 py-0.5 text-xs font-bold"
                        :class="statusClass(order.status)"
                    >
                        {{ order.status_label }}
                    </span>
                    <span
                        class="rounded-full px-2.5 py-0.5 text-[11px] font-bold"
                        :class="
                            order.delivery_type === 'pickup'
                                ? 'bg-teal-500/20 text-teal-300 border border-teal-500/40'
                                : 'bg-sky-500/20 text-sky-300 border border-sky-500/40'
                        "
                    >
                        {{ order.delivery_type === 'pickup' ? '🏪 Pickup Toko' : '🛵 Kurir Pengiriman' }}
                    </span>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-[#f3f2e7]/70">
                    <span class="flex items-center gap-1.5">
                        <span>🏪</span>
                        <span>Toko: <strong class="text-[#f3f2e7]">{{ order.store }}</strong></span>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span>👤</span>
                        <span>Pelanggan: <strong class="text-[#f3f2e7]">{{ order.customer }}</strong></span>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span>🕒</span>
                        <span>{{ fmtDate(order.created_at) }}</span>
                    </span>
                </div>
            </div>

            <!-- Tombol Refresh (Kurir) -->
            <div v-if="order.delivery_type === 'courier'" class="flex items-center gap-2 self-start sm:self-center">
                <button
                    type="button"
                    :disabled="isRefreshing"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3 py-1.5 text-xs font-bold text-[#f3f2e7] shadow-sm hover:bg-[#0d685b]/20 active:scale-95 transition disabled:opacity-50 cursor-pointer"
                    title="Perbarui data pesanan & kurir"
                    @click="refreshOrder"
                >
                    <span :class="{ 'animate-spin': isRefreshing }">🔄</span>
                    <span>{{ isRefreshing ? 'Memperbarui...' : 'Perbarui' }}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- FORM TINDAKAN ADMIN -->
    <div
        v-if="actions.length"
        class="mt-4 sm:mt-5 rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 sm:p-5 shadow-lg"
    >
        <div class="flex items-center justify-between mb-3 border-b border-[#0d685b]/20 pb-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-[#0d685b] flex items-center gap-1.5">
                <span>⚡</span>
                <span>Tindakan Status Admin</span>
            </h3>
            <span class="text-[11px] text-[#f3f2e7]/50">Pilih status berikutnya</span>
        </div>

        <div class="space-y-3">
            <input
                v-model="form.note"
                placeholder="Tuliskan catatan status (opsional)..."
                class="w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2.5 text-xs sm:text-sm text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:outline-none focus:border-[#0d685b]"
            />
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="a in actions"
                    :key="a.value"
                    :disabled="form.processing"
                    class="rounded-xl px-4 py-2.5 text-xs sm:text-sm font-bold text-[#f3f2e7] shadow-sm transition disabled:opacity-50 active:scale-95 cursor-pointer flex items-center gap-1.5"
                    :class="
                        a.value === 'cancelled'
                            ? 'bg-rose-700/80 hover:bg-rose-600 border border-rose-500/30'
                            : a.value === 'ready_for_pickup'
                              ? 'bg-teal-600 hover:bg-teal-500 ring-1 ring-teal-300/40 shadow-teal-900/40'
                              : a.value === 'completed' && order.delivery_type === 'pickup'
                                ? 'bg-emerald-600 hover:bg-emerald-500 ring-1 ring-emerald-300/40 shadow-emerald-900/40'
                                : 'bg-[#0d685b] hover:bg-[#0d685b]/90 shadow-[#0d685b]/30'
                    "
                    @click="apply(a)"
                >
                    {{
                        a.value === 'cancelled'
                            ? '⚠️ Batalkan & Refund'
                            : `✓ Tandai ${a.label}`
                    }}
                </button>
            </div>
        </div>
        <p v-if="form.errors.status" class="mt-2 text-xs text-rose-400 font-semibold">
            {{ form.errors.status }}
        </p>
    </div>

    <!-- BAGIAN KURIR: PENUGASAN & PETA TRACKING (KHUSUS PENGIRIMAN KURIR) -->
    <div v-if="order.delivery_type === 'courier'" class="mt-4 sm:mt-5 space-y-4">
        <!-- Penunjukan / Penugasan Kurir Pengantar -->
        <div
            class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 sm:p-5 shadow-lg text-[#f3f2e7] space-y-3"
        >
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[#0d685b]/20 pb-2.5">
                <h2 class="text-xs sm:text-sm font-bold uppercase tracking-wider text-[#0d685b] flex items-center gap-2">
                    <span>🛵</span>
                    <span>Penunjukan & Personil Kurir</span>
                </h2>
                <span
                    v-if="order.delivery?.courier_name"
                    class="rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 px-2.5 py-0.5 text-xs font-semibold"
                >
                    ✓ Sudah Ditugaskan
                </span>
                <span
                    v-else
                    class="rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/40 px-2.5 py-0.5 text-xs font-semibold animate-pulse"
                >
                    ⚠️ Menunggu Kurir
                </span>
            </div>

            <!-- Info Kurir Saat Ini -->
            <div
                v-if="order.delivery?.courier_name"
                class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-[#131d1a] p-3.5 rounded-xl border border-[#0d685b]/20"
            >
                <div>
                    <p class="text-[11px] text-[#f3f2e7]/70">
                        Kurir Pengantar Bertugas:
                    </p>
                    <p class="text-sm font-bold text-emerald-400">
                        👤 {{ order.delivery.courier_name }}
                    </p>
                    <p
                        v-if="order.delivery.courier_phone"
                        class="text-xs text-[#f3f2e7]/60"
                    >
                        Telp: {{ order.delivery.courier_phone }}
                    </p>
                </div>
                <button
                    type="button"
                    @click="showReassign = !showReassign"
                    class="rounded-xl border border-[#0d685b]/40 bg-[#1c2a25] px-3.5 py-2 text-xs font-semibold text-[#f3f2e7] hover:bg-[#131d1a] transition cursor-pointer self-start sm:self-center"
                >
                    {{ showReassign ? 'Tutup Pilihan' : 'Ganti / Tunjuk Ulang' }}
                </button>
            </div>

            <!-- Form Penunjukan Kurir -->
            <div
                v-if="!order.delivery?.courier_name || showReassign"
                class="space-y-2.5 pt-1"
            >
                <p class="text-xs text-[#f3f2e7]/80">
                    {{
                        order.delivery?.courier_name
                            ? "Pilih kurir pengganti:"
                            : "Pilih personil kurir untuk ditugaskan mengantar pesanan ini:"
                    }}
                </p>
                <div class="flex flex-col sm:flex-row sm:items-center gap-2.5">
                    <select
                        v-model="selectedCourierId"
                        class="w-full sm:flex-1 rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2.5 text-xs text-[#f3f2e7] focus:border-[#0d685b] focus:outline-none"
                    >
                        <option value="">
                            -- Pilih Kurir Pengantar --
                        </option>
                        <option
                            v-for="c in couriers"
                            :key="c.id"
                            :value="c.id"
                        >
                            {{ c.name }} ({{ c.username }})
                            {{ c.is_busy ? '[Sedang Mengantar]' : '[🟢 Siap]' }}
                        </option>
                    </select>
                    <button
                        type="button"
                        :disabled="!selectedCourierId || isAssigning"
                        @click="assignCourier"
                        class="w-full sm:w-auto rounded-xl bg-[#0d685b] px-4 py-2.5 text-xs font-bold text-[#f3f2e7] shadow-sm hover:bg-[#0d685b]/90 disabled:opacity-50 transition cursor-pointer flex items-center justify-center gap-1.5"
                    >
                        <span>🚀</span>
                        <span>{{ isAssigning ? 'Menugaskan...' : 'Tugaskan Kurir' }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Peta Rute Pengiriman Real-Time -->
        <div class="rounded-2xl border border-[#0d685b]/30 overflow-hidden shadow-lg bg-[#131d1a]">
            <DeliveryMap
                :store-lat="order.store_latitude"
                :store-lng="order.store_longitude"
                :store-name="order.store"
                :store-address="order.store_address"
                :courier-lat="order.delivery?.current_lat"
                :courier-lng="order.delivery?.current_lng"
                :dest-lat="order.shipping_latitude"
                :dest-lng="order.shipping_longitude"
                :destination-address="order.shipping_address"
                :recipient-name="order.recipient_name"
                :delivery-status="order.delivery?.status"
                :route-history="order.delivery?.locations || []"
                @refresh="refreshOrder"
            />
        </div>
    </div>

    <!-- GRID DUA KOLOM: RINCIAN PESANAN & INFORMASI PENERIMA -->
    <div class="mt-4 sm:mt-5 grid gap-4 sm:gap-6 lg:grid-cols-12">
        <!-- KOLOM KIRI (7/12): DAFTAR ITEM PESANAN & RINCIAN BIAYA -->
        <div class="lg:col-span-7 space-y-4 sm:space-y-6">
            <!-- DETAIL ITEM PESANAN -->
            <div
                class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 sm:p-5 shadow-lg text-[#f3f2e7]"
            >
                <div class="flex items-center justify-between border-b border-[#0d685b]/20 pb-2.5 mb-3">
                    <h2 class="text-xs sm:text-sm font-bold uppercase tracking-wider text-[#0d685b] flex items-center gap-2">
                        <span>📦</span>
                        <span>Daftar Item Pesanan</span>
                    </h2>
                    <span class="text-xs text-[#f3f2e7]/60">
                        {{ order.details?.length || 0 }} Produk
                    </span>
                </div>

                <ul class="divide-y divide-[#0d685b]/20 text-sm">
                    <li
                        v-for="(d, i) in order.details"
                        :key="i"
                        class="flex justify-between items-center py-2.5 gap-2"
                    >
                        <div>
                            <span class="block font-medium text-[#f3f2e7]">{{ d.product_name }}</span>
                            <span class="text-xs text-[#f3f2e7]/60">{{ d.quantity }} barang</span>
                        </div>
                        <span class="font-bold text-[#f3f2e7] shrink-0">
                            {{ rupiah(d.subtotal) }}
                        </span>
                    </li>
                </ul>

                <!-- Rincian Biaya & Total -->
                <div
                    class="mt-4 border-t border-[#0d685b]/30 pt-3 space-y-2 text-xs text-[#f3f2e7]/80"
                >
                    <div class="flex justify-between">
                        <span>Subtotal Produk:</span>
                        <span class="font-semibold text-[#f3f2e7]">{{ rupiah(order.total_amount) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Ongkir Kurir:</span>
                        <span
                            class="font-semibold"
                            :class="order.delivery_type === 'pickup' ? 'text-emerald-400' : 'text-[#f3f2e7]'"
                        >
                            {{ order.delivery_type === "pickup" ? "Gratis (Pickup Toko)" : rupiah(order.shipping_cost || 0) }}
                        </span>
                    </div>
                    <div
                        v-if="Number(order.discount_amount) > 0"
                        class="flex justify-between text-emerald-400 font-semibold"
                    >
                        <span>Diskon Promo:</span>
                        <span>− {{ rupiah(order.discount_amount) }}</span>
                    </div>
                    <div
                        class="border-t border-[#0d685b]/20 pt-2.5 flex justify-between items-baseline text-base font-bold text-[#f3f2e7]"
                    >
                        <span class="text-sm">Total Dibayar:</span>
                        <span class="text-lg font-black text-emerald-400">{{ rupiah(order.final_amount) }}</span>
                    </div>
                </div>
            </div>

            <!-- FOTO VALIDASI KURIR (JIKA ADA) -->
            <div
                v-if="order.delivery?.photos?.length"
                class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 sm:p-5 shadow-lg text-[#f3f2e7]"
            >
                <h2
                    class="mb-3 text-xs sm:text-sm font-bold uppercase tracking-wider text-[#0d685b] flex items-center gap-2"
                >
                    <span>📷</span>
                    <span>Foto Validasi Kurir</span>
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                    <div
                        v-for="p in order.delivery?.photos || []"
                        :key="p.type"
                        class="space-y-1.5 bg-[#131d1a] p-3 rounded-xl border border-[#0d685b]/20"
                    >
                        <span class="text-xs font-semibold text-[#f3f2e7]/80 block">
                            {{ p.type === "pickup" ? "📸 Ambil di Toko (Pickup)" : "✅ Tiba di Pelanggan (Dropoff)" }}
                        </span>
                        <a
                            :href="p.url"
                            target="_blank"
                            class="block overflow-hidden rounded-xl border border-[#0d685b]/30 bg-black hover:opacity-90 transition"
                        >
                            <img
                                :src="p.url"
                                class="h-36 w-full object-cover"
                                alt="Foto Bukti Kurir"
                            />
                        </a>
                        <p class="text-[11px] text-[#f3f2e7]/50">
                            {{ fmtDate(p.taken_at) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- KOLOM KANAN (5/12): INFORMASI PENERIMA & TIMELINE STATUS -->
        <div class="lg:col-span-5 space-y-4 sm:space-y-6">
            <!-- INFORMASI PENGIRIMAN / PENGAMBILAN -->
            <div
                class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 sm:p-5 shadow-lg text-[#f3f2e7] space-y-3"
            >
                <div class="flex items-center justify-between border-b border-[#0d685b]/20 pb-2.5">
                    <h2
                        class="text-xs sm:text-sm font-bold uppercase tracking-wider text-[#0d685b] flex items-center gap-2"
                    >
                        <span>{{ order.delivery_type === 'pickup' ? '🏪' : '📍' }}</span>
                        <span>{{ order.delivery_type === 'pickup' ? 'Info Pengambilan Toko' : 'Info Alamat Tujuan' }}</span>
                    </h2>
                    <span
                        class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                        :class="
                            order.delivery_type === 'pickup'
                                ? 'bg-teal-500/20 text-teal-300 border border-teal-500/40'
                                : 'bg-[#0d685b]/40 text-emerald-300 border border-[#0d685b]/60'
                        "
                    >
                        {{ order.delivery_type === 'pickup' ? 'Ambil Sendiri' : 'Kurir' }}
                    </span>
                </div>

                <!-- Kontak Penerima -->
                <div class="bg-[#131d1a] p-3 rounded-xl border border-[#0d685b]/20 space-y-1">
                    <p class="text-[11px] text-[#f3f2e7]/60">Penerima / Pemesan:</p>
                    <p class="text-sm font-bold text-[#f3f2e7]">
                        {{ order.recipient_name }}
                    </p>
                    <div class="flex items-center gap-2 pt-0.5">
                        <a
                            v-if="order.recipient_phone"
                            :href="`tel:${order.recipient_phone}`"
                            class="text-xs font-semibold text-emerald-400 hover:underline flex items-center gap-1"
                        >
                            <span>📞</span>
                            <span>{{ order.recipient_phone }}</span>
                        </a>
                    </div>
                </div>

                <!-- Alamat Kurir -->
                <div
                    v-if="order.delivery_type === 'courier'"
                    class="space-y-2 text-xs text-[#f3f2e7]/80"
                >
                    <div v-if="order.shipping_city" class="flex items-start gap-1.5">
                        <span class="text-emerald-400">📍</span>
                        <div>
                            <span class="text-[10px] text-[#f3f2e7]/50 block">Kabupaten / Kota:</span>
                            <span class="font-bold text-emerald-300">{{ order.shipping_city }}</span>
                        </div>
                    </div>
                    <div v-if="order.shipping_district" class="flex items-start gap-1.5">
                        <span class="text-emerald-400">🏘️</span>
                        <div>
                            <span class="text-[10px] text-[#f3f2e7]/50 block">Kecamatan / Kelurahan:</span>
                            <span>{{ order.shipping_district }}</span>
                            <span v-if="order.shipping_postal_code" class="text-emerald-400"> (Kode Pos: {{ order.shipping_postal_code }})</span>
                        </div>
                    </div>
                    <div class="flex items-start gap-1.5">
                        <span class="text-emerald-400">🏠</span>
                        <div>
                            <span class="text-[10px] text-[#f3f2e7]/50 block">Detail Alamat Lengkap:</span>
                            <p class="whitespace-pre-line leading-relaxed text-[#f3f2e7]">
                                {{ order.shipping_address || '-' }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Info Pickup -->
                <div v-else class="space-y-2">
                    <div
                        class="text-xs text-emerald-300 bg-emerald-950/40 p-2.5 rounded-xl border border-emerald-500/30"
                    >
                        🏪 Pelanggan akan mengambil pesanan langsung di toko <strong>{{ order.store }}</strong>.
                    </div>
                    <div
                        v-if="order.status === 'processed'"
                        class="text-xs text-indigo-200 bg-indigo-950/60 p-2.5 rounded-xl border border-indigo-500/40 flex items-start gap-2"
                    >
                        <span class="text-sm">👨‍🍳</span>
                        <div>
                            <strong class="text-indigo-300 block mb-0.5">Sedang Diproses Toko</strong>
                            Jika barang selesai disiapkan, klik <strong>"✓ Tandai Siap Dijemput"</strong> di atas.
                        </div>
                    </div>
                    <div
                        v-else-if="order.status === 'ready_for_pickup'"
                        class="text-xs text-teal-200 bg-teal-950/60 p-2.5 rounded-xl border border-teal-500/40 flex items-start gap-2"
                    >
                        <span class="text-sm">🔔</span>
                        <div>
                            <strong class="text-teal-300 block mb-0.5">Pesanan Siap Dijemput</strong>
                            Saat pelanggan mengambil barang di toko, klik <strong>"✓ Tandai Selesai"</strong> di atas.
                        </div>
                    </div>
                    <div
                        v-else-if="order.status === 'completed'"
                        class="text-xs text-emerald-200 bg-emerald-950/60 p-2.5 rounded-xl border border-emerald-500/30 flex items-center gap-2"
                    >
                        <span>✅</span>
                        <span>Pesanan telah berhasil diambil oleh pelanggan di toko (Selesai).</span>
                    </div>
                </div>

                <!-- Catatan Pemesan -->
                <div
                    v-if="order.note"
                    class="text-xs italic text-amber-300/90 bg-amber-500/10 p-2.5 rounded-xl border border-amber-500/20"
                >
                    <span class="font-bold not-italic text-amber-400 block mb-0.5">Catatan Pemesan:</span>
                    {{ order.note }}
                </div>
            </div>

            <!-- RIWAYAT STATUS TIMELINE -->
            <div
                class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 sm:p-5 shadow-lg text-[#f3f2e7]"
            >
                <div class="flex items-center justify-between border-b border-[#0d685b]/20 pb-2.5 mb-3">
                    <h2 class="text-xs sm:text-sm font-bold uppercase tracking-wider text-[#0d685b] flex items-center gap-2">
                        <span>📋</span>
                        <span>Riwayat Status & Catatan</span>
                    </h2>
                    <span class="text-xs text-[#f3f2e7]/50">{{ order.timeline?.length || 0 }} Aktivitas</span>
                </div>

                <ol class="space-y-3 text-xs">
                    <li
                        v-for="(t, i) in order.timeline"
                        :key="i"
                        class="relative pl-5 border-l-2 border-[#0d685b] py-0.5"
                    >
                        <span class="absolute -left-[5px] top-1.5 h-2 w-2 rounded-full bg-emerald-400 ring-2 ring-[#1c2a25]"></span>
                        <div class="font-bold text-[#f3f2e7]">
                            {{ t.note ?? t.status }}
                        </div>
                        <div class="text-[11px] text-[#f3f2e7]/55 mt-0.5">
                            Oleh: <strong class="text-[#f3f2e7]/70">{{ t.by ?? "sistem" }}</strong> · {{ fmtDate(t.at) }}
                        </div>
                    </li>
                </ol>
            </div>
        </div>
    </div>
</template>

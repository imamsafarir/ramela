<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import { computed, onMounted, onUnmounted, ref } from "vue";
import UserLayout from "../../Layouts/UserLayout.vue";
import DeliveryMap from "../../Components/DeliveryMap.vue";
import { fmtDate, rupiah, statusClass } from "../../utils/format";

defineOptions({ layout: UserLayout });

const props = defineProps({ order: Object });

let pollTimer = null;
const isRefreshing = ref(false);
const lastUpdatedAt = ref(new Date());

const refreshOrder = () => {
    isRefreshing.value = true;
    router.reload({
        only: ["order"],
        preserveScroll: true,
        onFinish: () => {
            isRefreshing.value = false;
            lastUpdatedAt.value = new Date();
        },
    });
};

onMounted(() => {
    if (props.order.status === "shipping") {
        pollTimer = setInterval(() => {
            refreshOrder();
        }, 4000);
    }
});

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
});

// Format link WhatsApp kurir
const waLink = computed(() => {
    const phone = props.order.delivery?.courier_phone;
    if (!phone) return null;
    let clean = phone.replace(/[^0-9]/g, "");
    if (clean.startsWith("0")) {
        clean = "62" + clean.slice(1);
    }
    const msg = encodeURIComponent(
        `Halo ${props.order.delivery?.courier_name || "Kurir"}, saya penerima pesanan ${props.order.invoice_number}. Mau menanyakan status pengantaran.`,
    );
    return `https://wa.me/${clean}?text=${msg}`;
});
</script>

<template>
    <Head :title="order.invoice_number" />

    <div class="mb-4">
        <Link
            href="/pesanan"
            class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#f3f2e7]/70 hover:text-[#f3f2e7] transition"
        >
            <span>←</span>
            <span>Kembali ke Semua Pesanan</span>
        </Link>
    </div>

    <!-- HEADER STATUS PESANAN -->
    <div
        class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-[#1c2a25] p-4 sm:p-5 shadow-xl border border-[#0d685b]/30"
    >
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-xl font-black text-[#f3f2e7]">
                    {{ order.invoice_number }}
                </h1>
                <span
                    class="rounded-full px-2.5 py-0.5 text-xs font-bold"
                    :class="statusClass(order.status)"
                >
                    {{ order.status_label }}
                </span>
            </div>
            <p class="mt-1 text-xs text-[#f3f2e7]/60">
                Toko:
                <span class="font-semibold text-emerald-300">{{
                    order.store
                }}</span>
                · Waktu Pesan: {{ fmtDate(order.created_at) }}
            </p>
        </div>

        <div v-if="order.status === 'shipping'" class="flex items-center gap-2">
            <span
                class="inline-flex items-center gap-1.5 rounded-full bg-orange-950/40 px-3 py-1 text-xs font-bold text-orange-200 border border-orange-500/40"
            >
                <span class="relative flex h-2 w-2">
                    <span
                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-orange-400 opacity-75"
                    ></span>
                    <span
                        class="relative inline-flex h-2 w-2 rounded-full bg-orange-500"
                    ></span>
                </span>
                Sedang Diantar Kurir
            </span>
        </div>
        <div v-else-if="order.delivery_type === 'pickup' && order.status === 'ready_for_pickup'" class="flex items-center gap-2">
            <span
                class="inline-flex items-center gap-1.5 rounded-full bg-teal-950/60 px-3 py-1 text-xs font-bold text-teal-200 border border-teal-500/50"
            >
                <span class="relative flex h-2 w-2">
                    <span
                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-teal-400 opacity-75"
                    ></span>
                    <span
                        class="relative inline-flex h-2 w-2 rounded-full bg-teal-400"
                    ></span>
                </span>
                Siap Dijemput di Toko
            </span>
        </div>
    </div>

    <!-- BANNER PESANAN PICKUP SIAP DIJEMPUT -->
    <div
        v-if="order.delivery_type === 'pickup' && order.status === 'ready_for_pickup'"
        class="mt-6 rounded-2xl border-2 border-teal-500/60 bg-gradient-to-r from-teal-950/80 via-[#1c2a25] to-teal-950/80 p-5 shadow-xl text-[#f3f2e7]"
    >
        <div class="flex items-start gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-teal-500/20 text-2xl border border-teal-500/40">
                🎉
            </div>
            <div>
                <h2 class="text-base font-black text-teal-300">
                    Pesanan Anda Sudah Siap Dijemput!
                </h2>
                <p class="mt-1 text-xs text-[#f3f2e7]/80 leading-relaxed">
                    Barang pesanan Anda telah selesai disiapkan oleh toko <strong class="text-teal-200">{{ order.store }}</strong>. Silakan datang langsung ke lokasi toko dan tunjukkan nomor invoice <strong class="text-teal-200 font-mono">{{ order.invoice_number }}</strong> kepada kasir / staf toko.
                </p>
            </div>
        </div>
    </div>

    <!-- KARTU UTAMA: LIVE TRACKING RUTE JALAN KURIR (KETIKA SHIPPING ATAU COMPLETED) -->
    <div
        v-if="
            order.delivery &&
            (order.status === 'shipping' || order.status === 'completed')
        "
        class="mt-6 overflow-hidden rounded-2xl border-2 border-[#0d685b] bg-[#1c2a25] shadow-xl"
    >
        <!-- Bar Judul & Kontrol Live Tracking -->
        <div
            class="flex flex-wrap items-center justify-between gap-3 border-b border-[#0d685b]/30 bg-gradient-to-r from-[#131d1a] to-[#1a2d26] px-4 py-3.5 sm:px-5 sm:py-4"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#0d685b] text-lg text-[#f3f2e7] shadow-sm"
                >
                    🛵
                </div>
                <div>
                    <h2 class="text-base font-bold text-[#f3f2e7]">
                        {{
                            order.status === "shipping"
                                ? "Pelacakan Pengantaran Realtime (Live Road Route)"
                                : "Riwayat Rute Pengantaran Kurir"
                        }}
                    </h2>
                    <p class="text-xs text-[#f3f2e7]/70">
                        {{
                            order.status === "shipping"
                                ? "Pantau rute jalan raya dan pergerakan kurir langsung ke alamat tujuan Anda."
                                : "Pesanan telah berhasil diantar ke lokasi tujuan oleh kurir."
                        }}
                    </p>
                </div>
            </div>

            <!-- Tombol Refresh & Indikator -->
            <div class="flex items-center gap-2">
                <button
                    v-if="order.status === 'shipping'"
                    type="button"
                    :disabled="isRefreshing"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3 py-1.5 text-xs font-bold text-[#f3f2e7] shadow-sm hover:bg-[#0d685b]/20 transition active:scale-95 disabled:opacity-50"
                    title="Perbarui koordinat kurir sekarang"
                    @click="refreshOrder"
                >
                    <span :class="{ 'animate-spin': isRefreshing }">🔄</span>
                    <span>{{
                        isRefreshing ? "Memperbarui..." : "Perbarui Posisi"
                    }}</span>
                </button>
            </div>
        </div>

        <!-- Info Kurir & Kontak Cepat -->
        <div
            v-if="order.delivery?.courier_name"
            class="flex flex-wrap items-center justify-between gap-3 border-b border-[#0d685b]/20 bg-[#1c2a25] px-5 py-3.5"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-9 w-9 items-center justify-center rounded-full bg-[#131d1a] border border-[#0d685b]/30 text-sm font-bold text-[#f3f2e7]"
                >
                    👤
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-semibold text-[#f3f2e7]/70"
                            >Kurir Pengantar:</span
                        >
                        <span class="text-sm font-bold text-[#f3f2e7]">{{
                            order.delivery?.courier_name
                        }}</span>
                    </div>
                    <p
                        v-if="order.delivery?.courier_phone"
                        class="text-xs text-[#f3f2e7]/60"
                    >
                        Telp: {{ order.delivery?.courier_phone }}
                    </p>
                </div>
            </div>

            <!-- Tombol Hubungi Kurir -->
            <div class="flex flex-wrap items-center gap-2">
                <a
                    v-if="waLink"
                    :href="waLink"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-[#0d685b] px-3.5 py-1.5 text-xs font-bold text-[#f3f2e7] shadow-sm hover:bg-[#117c6d] transition"
                >
                    <span>💬</span>
                    <span>Chat WhatsApp Kurir</span>
                </a>
                <a
                    v-if="order.delivery?.courier_phone"
                    :href="'tel:' + order.delivery?.courier_phone"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3 py-1.5 text-xs font-semibold text-[#f3f2e7] shadow-sm hover:bg-[#0d685b]/20 transition"
                >
                    <span>📞</span>
                    <span>Telepon</span>
                </a>
            </div>
        </div>

        <!-- PETA RUTE JALAN INTERAKTIF -->
        <div class="p-4 sm:p-5">
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
            />
        </div>

        <!-- Foto Bukti Pickup & Dropoff Validasi -->
        <div
            v-if="order.delivery?.photos?.length"
            class="border-t border-[#0d685b]/20 bg-[#131d1a]/50 p-5"
        >
            <h3
                class="text-xs font-bold uppercase tracking-wider text-[#f3f2e7]/80 mb-3"
            >
                📷 Foto Bukti Validasi Pengantaran
            </h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div
                    v-for="p in (order.delivery?.photos || [])"
                    :key="p.type"
                    class="overflow-hidden rounded-xl border border-[#0d685b]/30 bg-[#1c2a25] p-3 shadow-lg"
                >
                    <div class="flex items-center justify-between pb-2 text-xs">
                        <span class="font-bold text-[#f3f2e7]">
                            {{
                                p.type === "pickup"
                                    ? "📦 Bukti Barang Diambil di Toko"
                                    : "🏠 Bukti Diterima Pelanggan"
                            }}
                        </span>
                        <span class="text-[#f3f2e7]/60 text-[11px]">{{
                            fmtDate(p.taken_at)
                        }}</span>
                    </div>
                    <a
                        :href="p.url"
                        target="_blank"
                        class="group block overflow-hidden rounded-lg border border-[#0d685b]/20"
                    >
                        <img
                            :src="p.url"
                            class="h-40 w-full object-cover transition duration-200 group-hover:scale-105"
                            alt="Foto Bukti"
                        />
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- DETAIL ITEM & RINCIAN PENGIRIMAN -->
    <div class="mt-6 grid gap-6 md:grid-cols-2">
        <!-- Kolom Kiri: Rincian Pesanan & Pembayaran -->
        <div
            class="rounded-2xl bg-[#1c2a25] p-4 sm:p-5 shadow-xl border border-[#0d685b]/30"
        >
            <h2
                class="mb-4 text-base font-bold text-[#f3f2e7] border-b border-[#0d685b]/20 pb-2"
            >
                Rincian Item Belanja
            </h2>
            <ul class="divide-y divide-[#0d685b]/20 text-sm">
                <li
                    v-for="(d, i) in order.details"
                    :key="i"
                    class="flex items-center justify-between py-3"
                >
                    <div>
                        <p class="font-semibold text-[#f3f2e7]">
                            {{ d.product_name }}
                        </p>
                        <p class="text-xs text-[#f3f2e7]/60">
                            {{ d.quantity }} barang
                        </p>
                    </div>
                    <span class="font-bold text-[#f3f2e7]">{{
                        rupiah(d.subtotal)
                    }}</span>
                </li>
            </ul>

            <div class="mt-4 border-t border-[#0d685b]/20 pt-3">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between text-[#f3f2e7]/70">
                        <dt>Subtotal Item</dt>
                        <dd>{{ rupiah(order.total_amount) }}</dd>
                    </div>
                    <div class="flex justify-between text-[#f3f2e7]/70">
                        <dt>
                            {{
                                order.delivery_type === "pickup"
                                    ? "Metode Pengiriman"
                                    : "Ongkos Kirim Kurir"
                            }}
                        </dt>
                        <dd
                            :class="
                                order.delivery_type === 'pickup'
                                    ? 'text-emerald-400 font-bold'
                                    : ''
                            "
                        >
                            {{
                                order.delivery_type === "pickup"
                                    ? "Gratis (Ambil Sendiri)"
                                    : rupiah(order.shipping_cost || 0)
                            }}
                        </dd>
                    </div>
                    <div
                        v-if="Number(order.discount_amount) > 0"
                        class="flex justify-between font-semibold text-emerald-400"
                    >
                        <dt>Diskon ({{ order.promo_code ?? "Promo" }})</dt>
                        <dd>− {{ rupiah(order.discount_amount) }}</dd>
                    </div>
                    <div
                        class="flex justify-between border-t border-[#0d685b]/20 pt-2 text-base font-black text-[#f3f2e7]"
                    >
                        <dt>Total Dibayar</dt>
                        <dd class="text-emerald-400">
                            {{ rupiah(order.final_amount) }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Kolom Kanan: Alamat Pengiriman & Riwayat Status -->
        <div class="space-y-6">
            <!-- Alamat Pengiriman / Pengambilan -->
            <div
                class="rounded-2xl bg-[#1c2a25] p-5 shadow-xl border border-[#0d685b]/30"
            >
                <div
                    class="flex items-center justify-between border-b border-[#0d685b]/20 pb-2 mb-3"
                >
                    <h2 class="text-base font-bold text-[#f3f2e7]">
                        {{
                            order.delivery_type === "pickup"
                                ? "Informasi Pengambilan"
                                : "Informasi Pengiriman"
                        }}
                    </h2>
                    <span
                        class="rounded-full px-2.5 py-0.5 text-[11px] font-bold"
                        :class="
                            order.delivery_type === 'pickup'
                                ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'
                                : 'bg-[#0d685b]/30 text-emerald-300 border border-[#0d685b]/50'
                        "
                    >
                        {{
                            order.delivery_type === "pickup"
                                ? "🏪 Ambil Sendiri (Pickup)"
                                : "🛵 Kirim via Kurir"
                        }}
                    </span>
                </div>
                <div class="space-y-2 text-sm">
                    <p class="font-bold text-[#f3f2e7]">
                        {{ order.recipient_name }}
                        <span class="font-normal text-[#f3f2e7]/60"
                            >({{ order.recipient_phone }})</span
                        >
                    </p>

                    <!-- Khusus Kurir: Tampilkan Kota, Kecamatan/Kelurahan, Kode Pos, Detail -->
                    <div
                        v-if="order.delivery_type === 'courier'"
                        class="space-y-1 text-xs text-[#f3f2e7]/80"
                    >
                        <p
                            v-if="order.shipping_city"
                            class="font-semibold text-emerald-400"
                        >
                            Wilayah: {{ order.shipping_city }}
                        </p>
                        <p v-if="order.shipping_district">
                            Kec/Kel: {{ order.shipping_district }}
                            <span v-if="order.shipping_postal_code"
                                >· Kode Pos:
                                {{ order.shipping_postal_code }}</span
                            >
                        </p>
                        <p class="whitespace-pre-line leading-relaxed">
                            Detail: {{ order.shipping_address }}
                        </p>
                    </div>

                    <!-- Khusus Pickup: Tampilkan Info Pengambilan Toko -->
                    <div
                        v-else
                        class="rounded-xl bg-[#131d1a] border border-[#0d685b]/30 p-3 text-xs text-[#f3f2e7]/80"
                    >
                        <p class="font-semibold text-emerald-300 mb-1">
                            🏪 Lokasi Pengambilan: {{ order.store }}
                        </p>
                        <p class="text-[11px] text-[#f3f2e7]/60">
                            Silakan tunjukkan nomor invoice ({{
                                order.invoice_number
                            }}) kepada staf toko saat mengambil pesanan.
                        </p>
                    </div>

                    <p
                        v-if="order.delivery?.courier_name"
                        class="mt-2 text-xs font-semibold text-emerald-300"
                    >
                        Kurir Ditugaskan: {{ order.delivery.courier_name }}
                    </p>
                    <p
                        v-if="order.note"
                        class="mt-2 rounded-xl bg-[#131d1a] border border-[#0d685b]/30 p-3 text-xs text-[#f3f2e7]/80 italic"
                    >
                        Catatan: "{{ order.note }}"
                    </p>
                </div>
            </div>

            <!-- Status Timeline -->
            <div
                class="rounded-2xl bg-[#1c2a25] p-5 shadow-xl border border-[#0d685b]/30"
            >
                <h2
                    class="mb-4 text-base font-bold text-[#f3f2e7] border-b border-[#0d685b]/20 pb-2"
                >
                    Riwayat Status Pesanan
                </h2>
                <ol
                    class="relative border-l border-[#0d685b]/40 pl-4 space-y-4 text-xs"
                >
                    <li
                        v-for="(t, i) in order.timeline"
                        :key="i"
                        class="relative"
                    >
                        <div
                            class="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full border-2 border-[#1c2a25] bg-[#0d685b] shadow-xs"
                        ></div>
                        <span class="font-bold text-[#f3f2e7]">
                            {{ t.note ?? t.status }}
                        </span>
                        <p class="text-[#f3f2e7]/50 mt-0.5">
                            {{ fmtDate(t.at) }}
                        </p>
                    </li>
                </ol>
            </div>
        </div>
    </div>
</template>

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
        }
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
</script>

<template>
    <Head :title="order.invoice_number" />
    <Link
        href="/admin/pesanan"
        class="inline-flex items-center gap-1 text-xs font-semibold text-[#f3f2e7]/70 hover:text-[#f3f2e7] transition"
    >
        ← Kembali ke Daftar Pesanan
    </Link>

    <div class="mt-3 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-black text-[#f3f2e7] tracking-tight">
            {{ order.invoice_number }}
        </h1>
        <span
            class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
            :class="statusClass(order.status)"
            >{{ order.status_label }}</span
        >
    </div>
    <p class="text-xs text-[#f3f2e7]/60 mt-1">
        🏪 {{ order.store }} · 👤 {{ order.customer }} · 🕒
        {{ fmtDate(order.created_at) }}
    </p>

    <!-- FORM TINDAKAN ADMIN -->
    <div
        v-if="actions.length"
        class="mt-5 rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 sm:p-5 shadow-lg"
    >
        <h3
            class="text-xs font-bold uppercase tracking-wider text-[#0d685b] mb-2"
        >
            Tindakan Admin
        </h3>
        <input
            v-model="form.note"
            placeholder="Catatan status (opsional)..."
            class="mb-3 w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2 text-sm text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:outline-none focus:border-[#0d685b]"
        />
        <div class="flex flex-wrap gap-2">
            <button
                v-for="a in actions"
                :key="a.value"
                :disabled="form.processing"
                class="rounded-xl px-4 py-2 text-xs font-bold text-[#f3f2e7] shadow-sm transition disabled:opacity-50"
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
                    a.value === "cancelled"
                        ? "⚠️ Batalkan & Refund"
                        : `✓ Tandai ${a.label}`
                }}
            </button>
        </div>
        <p v-if="form.errors.status" class="mt-2 text-xs text-rose-400">
            {{ form.errors.status }}
        </p>
    </div>

    <div class="mt-6 grid gap-6 md:grid-cols-2">
        <!-- DETAIL ITEM PESANAN -->
        <div
            class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 sm:p-5 shadow-lg text-[#f3f2e7]"
        >
            <h2
                class="mb-3 text-sm font-bold uppercase tracking-wider text-[#0d685b]"
            >
                Daftar Item Pesanan
            </h2>
            <ul class="divide-y divide-[#0d685b]/20 text-sm">
                <li
                    v-for="(d, i) in order.details"
                    :key="i"
                    class="flex justify-between py-2.5"
                >
                    <span
                        >📦 {{ d.product_name }}
                        <strong class="text-[#f3f2e7]"
                            >× {{ d.quantity }}</strong
                        ></span
                    >
                    <span class="font-semibold text-[#f3f2e7]">{{
                        rupiah(d.subtotal)
                    }}</span>
                </li>
            </ul>
            <div
                class="mt-4 border-t border-[#0d685b]/30 pt-3 space-y-1.5 text-xs text-[#f3f2e7]/80"
            >
                <div class="flex justify-between">
                    <span>Subtotal Produk:</span>
                    <span class="font-semibold text-[#f3f2e7]">{{
                        rupiah(order.total_amount)
                    }}</span>
                </div>
                <div class="flex justify-between">
                    <span>Ongkir Kurir:</span>
                    <span
                        class="font-semibold"
                        :class="
                            order.delivery_type === 'pickup'
                                ? 'text-emerald-400'
                                : 'text-[#f3f2e7]'
                        "
                    >
                        {{
                            order.delivery_type === "pickup"
                                ? "Gratis (Pickup Toko)"
                                : rupiah(order.shipping_cost || 0)
                        }}
                    </span>
                </div>
                <div
                    v-if="Number(order.discount_amount) > 0"
                    class="flex justify-between text-emerald-400"
                >
                    <span>Diskon:</span>
                    <span>− {{ rupiah(order.discount_amount) }}</span>
                </div>
                <div
                    class="border-t border-[#0d685b]/20 pt-2 flex justify-between text-base font-bold text-[#f3f2e7]"
                >
                    <span>Total Dibayar:</span>
                    <span class="text-emerald-400">{{
                        rupiah(order.final_amount)
                    }}</span>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <!-- Penunjukan & Penugasan Kurir Pengantar -->
            <div
                v-if="order.delivery_type === 'courier'"
                class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-5 shadow-lg text-[#f3f2e7] space-y-3"
            >
                <div class="flex items-center justify-between border-b border-[#0d685b]/20 pb-2.5">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-[#0d685b] flex items-center gap-2">
                        <span>🛵</span> Penunjukan & Personil Kurir
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
                    class="flex flex-wrap items-center justify-between gap-3 bg-[#131d1a] p-3.5 rounded-xl border border-[#0d685b]/20"
                >
                    <div>
                        <p class="text-[11px] text-[#f3f2e7]/70">Kurir Pengantar Bertugas:</p>
                        <p class="text-sm font-bold text-emerald-400">👤 {{ order.delivery.courier_name }}</p>
                        <p v-if="order.delivery.courier_phone" class="text-xs text-[#f3f2e7]/60">
                            Telp: {{ order.delivery.courier_phone }}
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="showReassign = !showReassign"
                        class="rounded-xl border border-[#0d685b]/40 bg-[#1c2a25] px-3 py-1.5 text-xs font-semibold text-[#f3f2e7] hover:bg-[#131d1a] transition cursor-pointer"
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
                        {{ order.delivery?.courier_name ? 'Pilih kurir pengganti:' : 'Pilih personil kurir untuk ditugaskan mengantar pesanan ini:' }}
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <select
                            v-model="selectedCourierId"
                            class="flex-1 min-w-[200px] rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2 text-xs text-[#f3f2e7] focus:border-[#0d685b] focus:outline-none"
                        >
                            <option value="">-- Pilih Kurir Pengantar --</option>
                            <option v-for="c in couriers" :key="c.id" :value="c.id">
                                {{ c.name }} ({{ c.username }}) {{ c.is_busy ? '[Sedang Mengantar]' : '[🟢 Siap]' }}
                            </option>
                        </select>
                        <button
                            type="button"
                            :disabled="!selectedCourierId || isAssigning"
                            @click="assignCourier"
                            class="rounded-xl bg-[#0d685b] px-4 py-2 text-xs font-bold text-[#f3f2e7] shadow-sm hover:bg-[#0d685b]/90 disabled:opacity-50 transition cursor-pointer"
                        >
                            {{ isAssigning ? 'Menugaskan...' : '🚀 Tugaskan Kurir' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Pelacakan Kurir Real-Time & Peta Rute Jalan -->
            <div v-if="order.delivery_type === 'courier'" class="space-y-4">
                <div
                    class="rounded-2xl border border-[#0d685b]/30 overflow-hidden shadow-lg"
                >
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

                <div
                    v-if="order.delivery?.photos?.length"
                    class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 sm:p-5 shadow-lg text-[#f3f2e7]"
                >
                    <h2
                        class="mb-3 text-sm font-bold uppercase tracking-wider text-[#0d685b]"
                    >
                        Foto Validasi Kurir
                    </h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div
                            v-for="p in (order.delivery?.photos || [])"
                            :key="p.type"
                            class="space-y-1"
                        >
                            <span
                                class="text-xs font-semibold text-[#f3f2e7]/70"
                            >
                                {{
                                    p.type === "pickup"
                                        ? "📸 Toko (Pickup)"
                                        : "✅ Pelanggan (Dropoff)"
                                }}
                            </span>
                            <a
                                :href="p.url"
                                target="_blank"
                                class="block overflow-hidden rounded-xl border border-[#0d685b]/30 bg-black hover:opacity-90 transition"
                            >
                                <img
                                    :src="p.url"
                                    class="h-32 w-full object-cover"
                                />
                            </a>
                            <p class="text-[11px] text-[#f3f2e7]/50">
                                {{ fmtDate(p.taken_at) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- INFORMASI PENGIRIMAN -->
            <div
                class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 sm:p-5 shadow-lg text-[#f3f2e7]"
            >
                <div class="flex items-center justify-between mb-2">
                    <h2
                        class="text-sm font-bold uppercase tracking-wider text-[#0d685b]"
                    >
                        {{
                            order.delivery_type === "pickup"
                                ? "Informasi Pengambilan"
                                : "Informasi Pengiriman"
                        }}
                    </h2>
                    <span
                        class="rounded-full px-2.5 py-0.5 text-[10px] font-bold"
                        :class="
                            order.delivery_type === 'pickup'
                                ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'
                                : 'bg-[#0d685b]/40 text-emerald-300 border border-[#0d685b]/60'
                        "
                    >
                        {{
                            order.delivery_type === "pickup"
                                ? "🏪 Ambil Sendiri (Pickup)"
                                : "🛵 Kirim via Kurir"
                        }}
                    </span>
                </div>
                <p class="text-sm font-bold text-[#f3f2e7]">
                    👤 {{ order.recipient_name }}
                    <span class="text-xs font-normal text-[#f3f2e7]/60"
                        >({{ order.recipient_phone }})</span
                    >
                </p>

                <div
                    v-if="order.delivery_type === 'courier'"
                    class="mt-2 space-y-1 text-xs text-[#f3f2e7]/80"
                >
                    <p
                        v-if="order.shipping_city"
                        class="font-bold text-emerald-400"
                    >
                        📍 Kab/Kota: {{ order.shipping_city }}
                    </p>
                    <p v-if="order.shipping_district">
                        🏘️ Kec/Kel: {{ order.shipping_district }}
                        <span v-if="order.shipping_postal_code"
                            >· Kode Pos: {{ order.shipping_postal_code }}</span
                        >
                    </p>
                    <p class="whitespace-pre-line leading-relaxed">
                        🏠 Detail: {{ order.shipping_address }}
                    </p>
                </div>
                <div
                    v-else
                    class="mt-2 space-y-2"
                >
                    <div class="text-xs text-emerald-300 bg-emerald-950/40 p-2.5 rounded-xl border border-emerald-500/30">
                        🏪 Pelanggan akan mengambil sendiri pesanan langsung ke lokasi toko {{ order.store }}.
                    </div>
                    <div
                        v-if="order.status === 'processed'"
                        class="text-xs text-indigo-200 bg-indigo-950/60 p-2.5 rounded-xl border border-indigo-500/40 flex items-start gap-2"
                    >
                        <span class="text-sm">👨‍🍳</span>
                        <div>
                            <strong class="text-indigo-300 block mb-0.5">Sedang Diproses Toko</strong>
                            Jika barang sudah selesai disiapkan dan siap diambil pelanggan, klik tombol <strong>"✓ Tandai Siap Dijemput"</strong> di atas.
                        </div>
                    </div>
                    <div
                        v-else-if="order.status === 'ready_for_pickup'"
                        class="text-xs text-teal-200 bg-teal-950/60 p-3 rounded-xl border border-teal-500/40 flex items-start gap-2"
                    >
                        <span class="text-base">🔔</span>
                        <div>
                            <strong class="text-teal-300 block mb-0.5">Pesanan Siap Dijemput</strong>
                            Pesanan telah selesai diproses. Saat pelanggan datang mengambil barang di toko, silakan klik tombol <strong>"✓ Tandai Selesai"</strong> di atas.
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

                <p
                    v-if="order.delivery?.courier_name"
                    class="mt-2.5 text-xs font-semibold text-emerald-400 bg-emerald-950/40 p-2 rounded-lg border border-emerald-500/20"
                >
                    🛵 Kurir Bertugas:
                    <strong>{{ order.delivery.courier_name }}</strong>
                </p>
                <p
                    v-if="order.note"
                    class="mt-2 text-xs italic text-amber-300/80 bg-amber-500/10 p-2 rounded-lg border border-amber-500/20"
                >
                    Catatan: {{ order.note }}
                </p>
            </div>

            <!-- RIWAYAT STATUS TIMELINE -->
            <div
                class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-5 shadow-lg text-[#f3f2e7]"
            >
                <h2
                    class="mb-3 text-sm font-bold uppercase tracking-wider text-[#0d685b]"
                >
                    Riwayat Status & Catatan
                </h2>
                <ol class="space-y-2.5 text-xs text-[#f3f2e7]">
                    <li
                        v-for="(t, i) in order.timeline"
                        :key="i"
                        class="border-l-2 border-[#0d685b] pl-3 py-0.5"
                    >
                        <span class="font-bold text-[#f3f2e7]">{{
                            t.note ?? t.status
                        }}</span>
                        <span class="text-[#f3f2e7]/50">
                            · oleh {{ t.by ?? "sistem" }} ·
                            {{ fmtDate(t.at) }}</span
                        >
                    </li>
                </ol>
            </div>
        </div>
    </div>
</template>

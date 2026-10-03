<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import DeliveryMap from '../../Components/DeliveryMap.vue';
import { fmtDate, rupiah, statusClass } from '../../utils/format';

defineOptions({ layout: AdminLayout });

const props = defineProps({ order: Object, actions: Array });

const form = useForm({ status: '', note: '' });

const apply = (a) => {
    const msg = a.value === 'cancelled'
        ? 'Batalkan pesanan? Saldo pelanggan akan dikembalikan dan stok dipulihkan.'
        : `Ubah status menjadi "${a.label}"?`;
    if (!confirm(msg)) return;
    form.status = a.value;
    form.patch(`/admin/pesanan/${props.order.invoice_number}/status`, { preserveScroll: true, onSuccess: () => form.reset('note') });
};
</script>

<template>
    <Head :title="order.invoice_number" />
    <Link href="/admin/pesanan" class="inline-flex items-center gap-1 text-xs font-semibold text-[#f3f2e7]/70 hover:text-[#f3f2e7] transition">
        ← Kembali ke Daftar Pesanan
    </Link>

    <div class="mt-3 flex flex-wrap items-center gap-3">
        <h1 class="text-2xl font-black text-[#f3f2e7] tracking-tight">{{ order.invoice_number }}</h1>
        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="statusClass(order.status)">{{ order.status_label }}</span>
    </div>
    <p class="text-xs text-[#f3f2e7]/60 mt-1">🏪 {{ order.store }} · 👤 {{ order.customer }} · 🕒 {{ fmtDate(order.created_at) }}</p>

    <!-- FORM TINDAKAN ADMIN -->
    <div v-if="actions.length" class="mt-5 rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-5 shadow-lg">
        <h3 class="text-xs font-bold uppercase tracking-wider text-[#0d685b] mb-2">Tindakan Admin</h3>
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
                :class="a.value === 'cancelled' ? 'bg-rose-700/80 hover:bg-rose-600 border border-rose-500/30' : 'bg-[#0d685b] hover:bg-[#0d685b]/90 shadow-[#0d685b]/30'"
                @click="apply(a)"
            >
                {{ a.value === 'cancelled' ? '⚠️ Batalkan & Refund' : `✓ Tandai ${a.label}` }}
            </button>
        </div>
        <p v-if="form.errors.status" class="mt-2 text-xs text-rose-400">{{ form.errors.status }}</p>
    </div>

    <div class="mt-6 grid gap-6 md:grid-cols-2">
        <!-- DETAIL ITEM PESANAN -->
        <div class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-5 shadow-lg text-[#f3f2e7]">
            <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-[#0d685b]">Daftar Item Pesanan</h2>
            <ul class="divide-y divide-[#0d685b]/20 text-sm">
                <li v-for="(d, i) in order.details" :key="i" class="flex justify-between py-2.5">
                    <span>📦 {{ d.product_name }} <strong class="text-[#f3f2e7]">× {{ d.quantity }}</strong></span>
                    <span class="font-semibold text-[#f3f2e7]">{{ rupiah(d.subtotal) }}</span>
                </li>
            </ul>
            <div class="mt-4 border-t border-[#0d685b]/30 pt-3 flex justify-between text-base font-bold">
                <span>Total Dibayar:</span>
                <span class="text-emerald-400">{{ rupiah(order.final_amount) }}</span>
            </div>
        </div>

        <div class="space-y-6">
            <!-- Pelacakan Kurir Real-Time & Peta -->
            <div v-if="order.delivery" class="space-y-4">
                <div class="rounded-2xl border border-[#0d685b]/30 overflow-hidden shadow-lg">
                    <DeliveryMap
                        v-if="order.status === 'shipping' || order.status === 'completed'"
                        :courier-lat="order.delivery.current_lat"
                        :courier-lng="order.delivery.current_lng"
                        :dest-lat="order.shipping_latitude"
                        :dest-lng="order.shipping_longitude"
                        :recipient-name="order.recipient_name"
                        :store-name="order.store"
                    />
                </div>

                <div v-if="order.delivery.photos?.length" class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-5 shadow-lg text-[#f3f2e7]">
                    <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-[#0d685b]">Foto Validasi Kurir</h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div v-for="p in order.delivery.photos" :key="p.type" class="space-y-1">
                            <span class="text-xs font-semibold text-[#f3f2e7]/70">
                                {{ p.type === 'pickup' ? '📸 Toko (Pickup)' : '✅ Pelanggan (Dropoff)' }}
                            </span>
                            <a :href="p.url" target="_blank" class="block overflow-hidden rounded-xl border border-[#0d685b]/30 bg-black hover:opacity-90 transition">
                                <img :src="p.url" class="h-32 w-full object-cover" />
                            </a>
                            <p class="text-[11px] text-[#f3f2e7]/50">{{ fmtDate(p.taken_at) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- INFORMASI PENGIRIMAN -->
            <div class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-5 shadow-lg text-[#f3f2e7]">
                <h2 class="mb-2 text-sm font-bold uppercase tracking-wider text-[#0d685b]">Informasi Pengiriman</h2>
                <p class="text-sm font-bold text-[#f3f2e7]">👤 {{ order.recipient_name }} <span class="text-xs font-normal text-[#f3f2e7]/60">({{ order.recipient_phone }})</span></p>
                <p class="mt-1.5 whitespace-pre-line text-xs text-[#f3f2e7]/80 leading-relaxed">📍 {{ order.shipping_address }}</p>
                <p v-if="order.delivery?.courier_name" class="mt-2.5 text-xs font-semibold text-emerald-400 bg-emerald-950/40 p-2 rounded-lg border border-emerald-500/20">
                    🛵 Kurir Bertugas: <strong>{{ order.delivery.courier_name }}</strong>
                </p>
                <p v-if="order.note" class="mt-2 text-xs italic text-amber-300/80 bg-amber-500/10 p-2 rounded-lg border border-amber-500/20">
                    Catatan: {{ order.note }}
                </p>
            </div>

            <!-- RIWAYAT STATUS TIMELINE -->
            <div class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-5 shadow-lg text-[#f3f2e7]">
                <h2 class="mb-3 text-sm font-bold uppercase tracking-wider text-[#0d685b]">Riwayat Status & Catatan</h2>
                <ol class="space-y-2.5 text-xs text-[#f3f2e7]">
                    <li v-for="(t, i) in order.timeline" :key="i" class="border-l-2 border-[#0d685b] pl-3 py-0.5">
                        <span class="font-bold text-[#f3f2e7]">{{ t.note ?? t.status }}</span>
                        <span class="text-[#f3f2e7]/50"> · oleh {{ t.by ?? 'sistem' }} · {{ fmtDate(t.at) }}</span>
                    </li>
                </ol>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { fmtDate, rupiah, statusClass } from '../../utils/format';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    orders: Object,
    stores: Array,
    statuses: Array,
    filters: Object,
    stats: {
        type: Object,
        default: () => ({
            total_orders: 0,
            today_orders: 0,
            paid_orders: 0,
            ready_orders: 0,
            shipping_orders: 0,
            completed_orders: 0,
        }),
    },
});

const f = reactive({
    q: props.filters.q ?? '',
    store: props.filters.store ?? '',
    status: props.filters.status ?? '',
    delivery_type: props.filters.delivery_type ?? '',
    today: !!props.filters.today,
    sort: props.filters.sort ?? 'created_at',
    dir: props.filters.dir ?? 'desc',
});

const apply = () => router.get('/admin/pesanan', {
    q: f.q || undefined,
    store: f.store || undefined,
    status: f.status || undefined,
    delivery_type: f.delivery_type || undefined,
    today: f.today ? 1 : undefined,
    sort: f.sort,
    dir: f.dir,
}, { preserveState: true, replace: true });

const resetFilters = () => {
    f.q = '';
    f.store = '';
    f.status = '';
    f.delivery_type = '';
    f.today = false;
    f.sort = 'created_at';
    f.dir = 'desc';
    apply();
};

const hasActiveFilters = computed(() => {
    return Boolean(f.q || f.store || f.status || f.delivery_type || f.today || f.sort !== 'created_at');
});

const filterByStatus = (statusValue) => {
    f.status = statusValue;
    f.today = false;
    apply();
};

const sortBy = (col) => {
    f.dir = f.sort === col && f.dir === 'desc' ? 'asc' : 'desc';
    f.sort = col;
    apply();
};
const arrow = (col) => (f.sort === col ? (f.dir === 'asc' ? ' ▲' : ' ▼') : ' ↕');
const input = 'rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2 text-sm text-[#f3f2e7] focus:outline-none focus:border-[#0d685b] placeholder:text-[#f3f2e7]/40';
</script>

<template>
    <Head title="Pesanan" />
    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-2xl">📦</span>
                <h1 class="text-2xl font-black text-[#f3f2e7] tracking-tight">Manajemen Pesanan</h1>
            </div>
            <p class="text-xs text-[#f3f2e7]/60">Pantau, proses, dan kelola seluruh transaksi pesanan RAMELA secara real-time.</p>
        </div>
    </div>

    <!-- Quick KPI Stat Badges -->
    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        <button
            type="button"
            @click="resetFilters"
            class="text-left rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-3 shadow-md hover:border-[#0d685b] transition cursor-pointer group"
        >
            <div class="text-[11px] font-medium text-[#f3f2e7]/60">Total Pesanan</div>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="text-xl font-black text-[#f3f2e7] group-hover:text-emerald-400 transition">{{ stats.total_orders }}</span>
                <span class="text-[10px] text-[#f3f2e7]/40">Semua</span>
            </div>
        </button>

        <button
            type="button"
            @click="f.today = true; f.status = ''; apply();"
            class="text-left rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-3 shadow-md hover:border-[#0d685b] transition cursor-pointer group"
        >
            <div class="text-[11px] font-medium text-[#f3f2e7]/60">Hari Ini</div>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="text-xl font-black text-emerald-400 group-hover:underline transition">{{ stats.today_orders }}</span>
                <span class="text-[10px] text-emerald-400/60">Order</span>
            </div>
        </button>

        <button
            type="button"
            @click="filterByStatus('paid')"
            class="text-left rounded-2xl border border-indigo-500/30 bg-[#1c2a25] p-3 shadow-md hover:border-indigo-400 transition cursor-pointer group"
        >
            <div class="text-[11px] font-medium text-indigo-300">Perlu Diproses</div>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="text-xl font-black text-indigo-400 group-hover:scale-105 transition">{{ stats.paid_orders }}</span>
                <span class="text-[10px] text-indigo-300/60">Toko</span>
            </div>
        </button>

        <button
            type="button"
            @click="filterByStatus('ready_to_ship')"
            class="text-left rounded-2xl border border-amber-500/30 bg-[#1c2a25] p-3 shadow-md hover:border-amber-400 transition cursor-pointer group"
        >
            <div class="text-[11px] font-medium text-amber-300">Siap Kirim / Ambil</div>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="text-xl font-black text-amber-400 group-hover:scale-105 transition">{{ stats.ready_orders }}</span>
                <span class="text-[10px] text-amber-300/60">Siap</span>
            </div>
        </button>

        <button
            type="button"
            @click="filterByStatus('shipping')"
            class="text-left rounded-2xl border border-teal-500/30 bg-[#1c2a25] p-3 shadow-md hover:border-teal-400 transition cursor-pointer group"
        >
            <div class="text-[11px] font-medium text-teal-300">Sedang Diantar</div>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="text-xl font-black text-teal-400 group-hover:scale-105 transition">{{ stats.shipping_orders }}</span>
                <span class="text-[10px] text-teal-300/60">En Route</span>
            </div>
        </button>

        <button
            type="button"
            @click="filterByStatus('completed')"
            class="text-left rounded-2xl border border-emerald-500/30 bg-[#1c2a25] p-3 shadow-md hover:border-emerald-400 transition cursor-pointer group"
        >
            <div class="text-[11px] font-medium text-emerald-300">Selesai</div>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="text-xl font-black text-emerald-400 group-hover:scale-105 transition">{{ stats.completed_orders }}</span>
                <span class="text-[10px] text-emerald-300/60">Berhasil</span>
            </div>
        </button>
    </div>

    <!-- Filter Control Bar -->
    <form class="mt-4 flex flex-wrap items-center gap-2.5 rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-3.5 shadow-lg" @submit.prevent="apply">
        <div class="relative flex-1 min-w-[200px]">
            <input v-model="f.q" placeholder="Cari invoice / username..." :class="[input, 'w-full pr-7']" />
            <button
                v-if="f.q"
                type="button"
                @click="f.q = ''; apply();"
                class="absolute right-2.5 top-2.5 text-xs text-[#f3f2e7]/50 hover:text-white"
            >
                ✕
            </button>
        </div>
        <select v-model="f.store" :class="[input, 'w-full sm:w-auto']" @change="apply">
            <option value="">Semua Toko</option>
            <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
        <select v-model="f.status" :class="[input, 'w-full sm:w-auto']" @change="apply">
            <option value="">Semua Status Pesanan</option>
            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
        </select>
        <select v-model="f.delivery_type" :class="[input, 'w-full sm:w-auto']" @change="apply">
            <option value="">Semua Metode Kirim</option>
            <option value="courier">🚚 Kurir Ekspedisi</option>
            <option value="pickup">🏪 Ambil Sendiri (Pickup)</option>
        </select>
        <label class="flex items-center gap-2 text-xs font-semibold text-[#f3f2e7]/80 px-2 cursor-pointer select-none">
            <input v-model="f.today" type="checkbox" class="rounded accent-[#0d685b]" @change="apply" />
            Hari ini
        </label>
        <div class="flex items-center gap-2 ml-auto">
            <button
                v-if="hasActiveFilters"
                type="button"
                @click="resetFilters"
                class="rounded-xl border border-rose-500/30 bg-rose-950/20 px-3 py-2 text-xs font-semibold text-rose-300 hover:bg-rose-900/30 transition cursor-pointer"
            >
                Reset Filter
            </button>
            <button type="submit" class="rounded-xl bg-[#0d685b] px-4 py-2 text-xs font-bold text-[#f3f2e7] shadow-sm hover:bg-[#0d685b]/90 transition cursor-pointer">
                🔍 Filter Data
            </button>
        </div>
    </form>

    <div class="mt-4 overflow-x-auto rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-lg">
        <table class="w-full text-left text-sm text-[#f3f2e7]">
            <thead class="border-b border-[#0d685b]/30 bg-[#131d1a] text-xs font-bold uppercase tracking-wider text-[#f3f2e7]/70">
                <tr>
                    <th class="w-12 px-3 py-3.5 text-center">#</th>
                    <th class="px-4 py-3.5">Invoice & Pengiriman</th>
                    <th class="px-4 py-3.5">Toko & Pembeli</th>
                    <th class="px-3 py-3.5 text-center">Total Berat</th>
                    <th class="px-4 py-3.5 cursor-pointer select-none" @click="sortBy('final_amount')">
                        Rincian Biaya & Promo{{ arrow('final_amount') }}
                    </th>
                    <th class="px-4 py-3.5 text-center">Status</th>
                    <th class="px-4 py-3.5 cursor-pointer select-none" @click="sortBy('created_at')">Waktu{{ arrow('created_at') }}</th>
                    <th class="px-4 py-3.5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#0d685b]/20">
                <tr v-for="(o, idx) in orders.data" :key="o.invoice_number" class="hover:bg-[#131d1a]/50 transition">
                    <td class="px-3 py-3.5 text-center font-bold text-xs text-[#f3f2e7]/50">
                        {{ (orders.from || 1) + idx }}
                    </td>
                    <td class="px-4 py-3.5">
                        <Link :href="`/admin/pesanan/${o.invoice_number}`" class="font-bold text-[#f3f2e7] hover:text-emerald-400 underline transition block">
                            {{ o.invoice_number }}
                        </Link>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                            <span class="rounded px-1.5 py-0.2 text-[10px] font-bold" :class="o.delivery_type === 'pickup' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-[#0d685b]/30 text-emerald-300 border border-[#0d685b]/40'">
                                {{ o.delivery_type === 'pickup' ? '🛵 Pickup Toko' : '🚚 Kurir Ekspedisi' }}
                            </span>
                            <span v-if="o.shipping_city" class="text-[11px] text-[#f3f2e7]/60">
                                📍 {{ o.shipping_city }}
                            </span>
                        </div>
                    </td>
                    <td class="px-4 py-3.5">
                        <span class="font-bold text-xs text-[#f3f2e7] block">{{ o.store }}</span>
                        <div class="text-[11px] text-[#f3f2e7]/70 mt-0.5">
                            <span>@{{ o.username }}</span>
                            <span v-if="o.recipient_name" class="text-[#f3f2e7]/50 block">Penerima: {{ o.recipient_name }}</span>
                        </div>
                    </td>
                    <td class="px-3 py-3.5 text-center">
                        <span class="inline-block rounded-md bg-[#131d1a] border border-[#0d685b]/30 px-2 py-1 text-xs font-semibold text-[#f3f2e7]">
                            ⚖️ {{ Number(o.total_weight) >= 1000 ? (Number(o.total_weight) / 1000).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + ' kg' : (o.total_weight || 0) + ' g' }}
                        </span>
                    </td>
                    <td class="px-4 py-3.5 text-xs">
                        <div class="space-y-0.5 text-[#f3f2e7]/70">
                            <div class="flex items-center justify-between gap-3">
                                <span>Produk:</span>
                                <span>{{ rupiah(o.total_amount) }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span>Ongkir:</span>
                                <span>{{ Number(o.shipping_cost) > 0 ? rupiah(o.shipping_cost) : 'Rp 0' }}</span>
                            </div>
                            <div v-if="Number(o.discount_amount) > 0" class="flex items-center justify-between gap-3 text-emerald-400 font-semibold">
                                <span class="flex items-center gap-1">
                                    <span>Diskon</span>
                                    <span v-if="o.promo_code" class="rounded bg-emerald-500/20 px-1 py-0.2 text-[9px] font-bold border border-emerald-500/30">
                                        {{ o.promo_code }}
                                    </span>
                                </span>
                                <span>−{{ rupiah(o.discount_amount) }}</span>
                            </div>
                        </div>
                        <div class="mt-1.5 border-t border-[#0d685b]/20 pt-1 flex items-center justify-between font-bold text-sm">
                            <span class="text-[11px] text-[#f3f2e7]/60">Total:</span>
                            <span class="font-black text-emerald-400">{{ rupiah(o.final_amount) }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3.5 text-center">
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap" :class="statusClass(o.status)">
                            {{ o.status_label }}
                        </span>
                    </td>
                    <td class="px-4 py-3.5 text-xs text-[#f3f2e7]/60 whitespace-nowrap">
                        {{ fmtDate(o.created_at) }}
                    </td>
                    <td class="px-4 py-3.5 text-right whitespace-nowrap">
                        <Link
                            :href="`/admin/pesanan/${o.invoice_number}`"
                            class="inline-flex items-center gap-1 rounded-lg border border-[#0d685b]/40 bg-[#131d1a] px-2.5 py-1.5 text-xs font-bold text-emerald-300 hover:bg-[#0d685b]/30 transition"
                        >
                            <span>Detail</span>
                            <span>→</span>
                        </Link>
                    </td>
                </tr>
                <tr v-if="!orders.data.length">
                    <td colspan="8" class="px-4 py-8 text-center text-sm text-[#f3f2e7]/60">🍃 Tidak ada pesanan yang sesuai filter.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <nav v-if="orders.last_page > 1" class="mt-4 flex flex-wrap gap-1.5">
        <template v-for="l in orders.links" :key="l.label">
            <Link
                v-if="l.url"
                :href="l.url"
                class="rounded-xl px-3.5 py-1.5 text-xs font-bold border transition"
                :class="l.active ? 'bg-[#0d685b] border-[#0d685b] text-[#f3f2e7]' : 'bg-[#1c2a25] border-[#0d685b]/30 text-[#f3f2e7]/70 hover:bg-[#131d1a]'"
                v-html="l.label"
            />
        </template>
    </nav>
</template>

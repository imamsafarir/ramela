<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { fmtDate, rupiah, statusClass } from '../../utils/format';

defineOptions({ layout: AdminLayout });

const props = defineProps({ orders: Object, stores: Array, statuses: Array, filters: Object });

const f = reactive({
    q: props.filters.q ?? '', store: props.filters.store ?? '', status: props.filters.status ?? '',
    today: !!props.filters.today, sort: props.filters.sort ?? 'created_at', dir: props.filters.dir ?? 'desc',
});

const apply = () => router.get('/admin/pesanan', {
    q: f.q || undefined, store: f.store || undefined, status: f.status || undefined,
    today: f.today ? 1 : undefined, sort: f.sort, dir: f.dir,
}, { preserveState: true, replace: true });

const sortBy = (col) => {
    f.dir = f.sort === col && f.dir === 'desc' ? 'asc' : 'desc';
    f.sort = col;
    apply();
};
const arrow = (col) => (f.sort === col ? (f.dir === 'asc' ? ' ▲' : ' ▼') : '');
const input = 'rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2 text-sm text-[#f3f2e7] focus:outline-none focus:border-[#0d685b] placeholder:text-[#f3f2e7]/40';
</script>

<template>
    <Head title="Pesanan" />
    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-black text-[#f3f2e7] tracking-tight">Manajemen Pesanan</h1>
            <p class="text-xs text-[#f3f2e7]/60">Pantau dan kelola seluruh transaksi pesanan RAMELA.</p>
        </div>
    </div>

    <form class="mt-5 flex flex-wrap items-center gap-2.5 rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-3 shadow-lg" @submit.prevent="apply">
        <input v-model="f.q" placeholder="Invoice / username..." :class="input" />
        <select v-model="f.store" :class="input" @change="apply">
            <option value="">Semua toko</option>
            <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
        <select v-model="f.status" :class="input" @change="apply">
            <option value="">Semua status</option>
            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
        </select>
        <label class="flex items-center gap-2 text-xs font-semibold text-[#f3f2e7]/80 px-2 cursor-pointer">
            <input v-model="f.today" type="checkbox" class="rounded accent-[#0d685b]" @change="apply" />
            Hari ini
        </label>
        <button class="rounded-xl bg-[#0d685b] px-4 py-2 text-xs font-bold text-[#f3f2e7] shadow-sm hover:bg-[#0d685b]/90 transition">
            Filter Data
        </button>
    </form>

    <div class="mt-4 overflow-x-auto rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-lg">
        <table class="w-full text-left text-sm text-[#f3f2e7]">
            <thead class="border-b border-[#0d685b]/30 bg-[#131d1a] text-xs font-bold uppercase tracking-wider text-[#f3f2e7]/70">
                <tr>
                    <th class="px-4 py-3">Invoice</th>
                    <th class="px-4 py-3">Toko</th>
                    <th class="px-4 py-3">Pelanggan</th>
                    <th class="px-4 py-3 cursor-pointer select-none" @click="sortBy('final_amount')">Total{{ arrow('final_amount') }}</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 cursor-pointer select-none" @click="sortBy('created_at')">Waktu{{ arrow('created_at') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#0d685b]/20">
                <tr v-for="o in orders.data" :key="o.invoice_number" class="hover:bg-[#131d1a]/50 transition">
                    <td class="px-4 py-3">
                        <Link :href="`/admin/pesanan/${o.invoice_number}`" class="font-bold text-[#f3f2e7] hover:text-emerald-400 underline transition">
                            {{ o.invoice_number }}
                        </Link>
                    </td>
                    <td class="px-4 py-3 text-xs text-[#f3f2e7]/80">{{ o.store }}</td>
                    <td class="px-4 py-3 text-xs font-medium text-[#f3f2e7]">{{ o.username }}</td>
                    <td class="px-4 py-3 font-semibold text-[#f3f2e7]">{{ rupiah(o.final_amount) }}</td>
                    <td class="px-4 py-3">
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="statusClass(o.status)">
                            {{ o.status_label }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-[#f3f2e7]/60">{{ fmtDate(o.created_at) }}</td>
                </tr>
                <tr v-if="!orders.data.length">
                    <td colspan="6" class="px-4 py-8 text-center text-sm text-[#f3f2e7]/60">🍃 Tidak ada pesanan yang sesuai filter.</td>
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

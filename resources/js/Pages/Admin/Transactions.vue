<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { fmtDate, rupiah, statusClass } from '../../utils/format';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    transactions: Object,
    stores: Array,
    statuses: Array,
    filters: Object,
    urls: Object,
});

const search = ref(props.filters.q ?? '');
const storeFilter = ref(props.filters.store ?? '');
const statusFilter = ref(props.filters.status ?? '');

const applyFilter = () => {
    router.get('/admin/transaksi', {
        q: search.value || undefined,
        store: storeFilter.value || undefined,
        status: statusFilter.value || undefined,
    }, { preserveState: true });
};

// Modal Bypass
const selectedTx = ref(null);
const bypassForm = useForm({
    status: '',
    note: '',
});

const openBypassModal = (t) => {
    selectedTx.value = t;
    bypassForm.status = t.status;
    bypassForm.note = '';
};

const closeBypassModal = () => {
    selectedTx.value = null;
    bypassForm.reset();
};

const submitBypass = () => {
    if (!confirm(`Yakin ingin MEMAKSA status invoice #${selectedTx.value.invoice_number} menjadi "${bypassForm.status}"? Perubahan ini akan membypass aturan alur order standar.`)) {
        return;
    }

    bypassForm.post(`/admin/transaksi/${selectedTx.value.invoice_number}/status`, {
        onSuccess: () => closeBypassModal(),
    });
};

const inputClass = 'w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2 text-xs text-[#f3f2e7] shadow-sm placeholder:text-[#f3f2e7]/40 focus:border-[#0d685b] focus:outline-none';
</script>

<template>
    <Head title="Bypass Transaksi - Admin" />

    <div class="space-y-6">
        <!-- HEADER -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-[#f3f2e7] sm:text-3xl">
                    Bypass & Koreksi Transaksi
                </h1>
                <p class="mt-1 text-xs text-[#f3f2e7]/60 sm:text-sm">
                    Fitur khusus administrator untuk mengubah status pesanan secara paksa pada kondisi darurat (refund paksa, bayar paksa, batalkan pesanan bermasalah).
                </p>
            </div>
            <span class="inline-flex items-center gap-1.5 self-start rounded-full bg-purple-950/60 border border-purple-500/40 px-3 py-1 text-xs font-bold text-purple-300 sm:self-auto">
                ⚡ Mode Otoritas Penuh
            </span>
        </div>

        <!-- FILTER & PENCARIAN -->
        <div class="flex flex-wrap items-center gap-2.5 rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-3.5 shadow-lg">
            <div class="flex-1 min-w-[200px]">
                <input
                    v-model="search"
                    placeholder="Cari nomor invoice atau username..."
                    :class="inputClass"
                    @keydown.enter.prevent="applyFilter"
                />
            </div>
            <div class="w-full sm:w-40">
                <select v-model="storeFilter" :class="inputClass" @change="applyFilter">
                    <option value="">Semua Toko</option>
                    <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
            <div class="w-full sm:w-40">
                <select v-model="statusFilter" :class="inputClass" @change="applyFilter">
                    <option value="">Semua Status</option>
                    <option v-for="st in statuses" :key="st.value" :value="st.value">{{ st.label }}</option>
                </select>
            </div>
            <button
                class="rounded-xl bg-[#0d685b] px-4 py-2 text-xs font-bold text-[#f3f2e7] shadow-sm transition hover:bg-[#0d685b]/90"
                @click="applyFilter"
            >
                Terapkan
            </button>
            <button
                v-if="search || storeFilter || statusFilter"
                class="rounded-xl border border-[#0d685b]/30 bg-[#131d1a] px-3 py-2 text-xs font-semibold text-[#f3f2e7]/70 hover:bg-[#17231f]"
                @click="search = ''; storeFilter = ''; statusFilter = ''; applyFilter();"
            >
                Reset
            </button>
        </div>

        <!-- TABEL TRANSAKSI -->
        <div class="overflow-hidden rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-lg text-[#f3f2e7]">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead class="border-b border-[#0d685b]/30 bg-[#131d1a] text-[11px] font-bold uppercase tracking-wider text-[#f3f2e7]/70">
                        <tr>
                            <th class="w-12 px-3 py-3.5 text-center">#</th>
                            <th class="px-5 py-3.5">Invoice & Waktu</th>
                            <th class="px-4 py-3.5">Toko & Pembeli</th>
                            <th class="px-4 py-3.5">Total Belanja</th>
                            <th class="px-4 py-3.5">Status Saat Ini</th>
                            <th class="px-5 py-3.5 text-right">Tindakan Khusus</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#0d685b]/20">
                        <tr v-for="(t, idx) in transactions.data" :key="t.id" class="transition hover:bg-[#131d1a]/50">
                            <td class="px-3 py-4 text-center font-bold text-xs text-[#f3f2e7]/50">
                                {{ (transactions.from || 1) + idx }}
                            </td>
                            <td class="px-5 py-4">
                                <Link :href="`/admin/pesanan/${t.invoice_number}`" class="font-bold text-emerald-400 hover:underline">
                                    {{ t.invoice_number }}
                                </Link>
                                <p class="text-xs text-[#f3f2e7]/50 mt-0.5">{{ t.created_at }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <span class="font-semibold text-[#f3f2e7]">{{ t.store }}</span>
                                <p class="text-xs text-[#f3f2e7]/60">@{{ t.username }}</p>
                            </td>
                            <td class="px-4 py-4 font-bold text-emerald-400">
                                {{ rupiah(t.final_amount) }}
                            </td>
                            <td class="px-4 py-4">
                                <span class="inline-block rounded-lg px-2.5 py-0.5 text-[11px] font-bold" :class="statusClass(t.status)">
                                    {{ t.status_label }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <button
                                    class="inline-flex items-center gap-1 rounded-xl bg-purple-700/80 border border-purple-500/40 px-3 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-purple-600 transition"
                                    @click="openBypassModal(t)"
                                >
                                    <span>⚡</span>
                                    <span>Bypass Paksa</span>
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!transactions.data?.length">
                            <td colspan="6" class="px-5 py-10 text-center text-xs text-[#f3f2e7]/50">
                                🍃 Tidak ada data transaksi yang ditemukan.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <div v-if="transactions.last_page > 1" class="border-t border-[#0d685b]/20 px-5 py-3 bg-[#131d1a]">
                <nav class="flex justify-center gap-1">
                    <template v-for="l in transactions.links" :key="l.label">
                        <Link
                            v-if="l.url"
                            :href="l.url"
                            class="rounded-xl px-3 py-1.5 text-xs font-bold border transition"
                            :class="l.active ? 'bg-[#0d685b] border-[#0d685b] text-[#f3f2e7]' : 'border-[#0d685b]/30 bg-[#1c2a25] text-[#f3f2e7]/70 hover:bg-[#17231f]'"
                            v-html="l.label"
                        />
                    </template>
                </nav>
            </div>
        </div>

        <!-- MODAL BYPASS STATUS -->
        <div v-if="selectedTx" class="fixed inset-0 z-50 flex items-center justify-center bg-[#17231f]/85 backdrop-blur-md p-4">
            <div class="w-full max-w-md rounded-2xl bg-[#1c2a25] p-6 shadow-2xl border border-purple-500/40 text-[#f3f2e7]">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-950/60 border border-purple-500/30 text-xl text-purple-300">
                        ⚡
                    </span>
                    <div>
                        <h3 class="text-base font-black text-[#f3f2e7]">Bypass Status Transaksi</h3>
                        <p class="text-xs text-[#f3f2e7]/60">Invoice: <strong class="text-emerald-400">{{ selectedTx.invoice_number }}</strong></p>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-amber-500/40 bg-amber-950/40 p-3 text-xs text-amber-200">
                    ⚠️ <strong>Peringatan Keamanan:</strong> Aksi ini mengabaikan validasi alur status normal. Jika mengubah ke <code>cancelled</code>, saldo dompet pelanggan akan otomatis dikembalikan (refund) jika pesanan sudah dibayar.
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="submitBypass">
                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase tracking-wider text-[#0d685b]">Paksa Status Menjadi</label>
                        <select v-model="bypassForm.status" :class="inputClass">
                            <option v-for="st in statuses" :key="st.value" :value="st.value">
                                {{ st.label }} ({{ st.value }})
                            </option>
                        </select>
                        <p v-if="bypassForm.errors.status" class="mt-1 text-xs text-rose-400">{{ bypassForm.errors.status }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase tracking-wider text-[#0d685b]">Alasan / Catatan Bypass (Wajib)</label>
                        <input
                            v-model="bypassForm.note"
                            placeholder="Contoh: Permintaan pembatalan darurat pelanggan / barang rusak"
                            :class="inputClass"
                        />
                        <p v-if="bypassForm.errors.note" class="mt-1 text-xs text-rose-400">{{ bypassForm.errors.note }}</p>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-[#0d685b]/20">
                        <button type="button" class="rounded-xl px-3.5 py-2 text-xs font-semibold text-[#f3f2e7]/60 hover:bg-[#131d1a]" @click="closeBypassModal">
                            Batal
                        </button>
                        <button
                            :disabled="bypassForm.processing"
                            class="rounded-xl bg-purple-700/90 border border-purple-500/40 px-4 py-2 text-xs font-bold text-white shadow-lg hover:bg-purple-600 disabled:opacity-50"
                        >
                            Terapkan Status Paksa
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

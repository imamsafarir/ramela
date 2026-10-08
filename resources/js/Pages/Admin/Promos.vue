<script setup>
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import AdminLayout from "../../Layouts/AdminLayout.vue";
import { rupiah } from "../../utils/format";

defineOptions({ layout: AdminLayout });
const props = defineProps({ promos: Array, stores: Array });

const showForm = ref(false);
const searchQuery = ref("");
const statusFilter = ref("");
const storeFilter = ref("");

const form = useForm({
    code: "",
    discount_type: "percent",
    discount_value: "",
    max_discount_amount: "",
    min_purchase: "",
    quota: "",
    per_user_limit: "",
    starts_at: "",
    valid_until: "",
    is_active: true,
    store_ids: [],
});

const submit = () => form.post("/admin/promo", {
    onSuccess: () => {
        form.reset();
        showForm.value = false;
    },
});

const toggle = (p) => router.patch(`/admin/promo/${p.id}/toggle`, {}, { preserveScroll: true });
const remove = (p) => {
    if (confirm(`Hapus kode promo "${p.code}"?`)) {
        router.delete(`/admin/promo/${p.id}`, { preserveScroll: true });
    }
};

const filteredPromos = computed(() => {
    return props.promos.filter((p) => {
        const matchesSearch = !searchQuery.value.trim() || p.code.toLowerCase().includes(searchQuery.value.toLowerCase().trim());
        const matchesStatus = !statusFilter.value || (statusFilter.value === 'active' ? p.is_active : !p.is_active);
        const matchesStore = !storeFilter.value || (p.scope && p.scope.toLowerCase().includes(storeFilter.value.toLowerCase()));
        return matchesSearch && matchesStatus && matchesStore;
    });
});

const input = "w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2 text-xs text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:outline-none focus:border-[#0d685b]";
const labelClass = "block text-[11px] font-bold uppercase tracking-wider text-[#0d685b] mb-1";
</script>

<template>
    <Head title="Promo & Kupon" />
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-2xl">🎟️</span>
                <h1 class="text-2xl font-black text-[#f3f2e7] tracking-tight">Kupon & Promo Diskon</h1>
            </div>
            <p class="text-xs text-[#f3f2e7]/60">Kelola kupon potongan harga, batas pemakaian per pengguna, dan periode diskon.</p>
        </div>
        <button
            type="button"
            @click="showForm = !showForm"
            class="inline-flex items-center gap-1.5 self-start sm:self-auto rounded-xl bg-[#0d685b] px-4 py-2.5 text-xs font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 hover:bg-[#0d685b]/90 transition cursor-pointer"
        >
            <span>{{ showForm ? '✕ Tutup Form' : '✨ + Buat Kupon Baru' }}</span>
        </button>
    </div>

    <!-- KPI MINI SUMMARY -->
    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-3.5 shadow-md">
            <span class="text-[11px] text-[#f3f2e7]/60 font-medium">Total Kupon</span>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="text-2xl font-black text-[#f3f2e7]">{{ promos.length }}</span>
                <span class="text-xs text-[#f3f2e7]/40">Voucher</span>
            </div>
        </div>
        <div class="rounded-2xl border border-emerald-500/30 bg-[#1c2a25] p-3.5 shadow-md">
            <span class="text-[11px] text-emerald-300 font-medium">Kupon Aktif</span>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="text-2xl font-black text-emerald-400">{{ promos.filter(p => p.is_active).length }}</span>
                <span class="text-xs text-emerald-400/60">Tersedia</span>
            </div>
        </div>
        <div class="rounded-2xl border border-rose-500/30 bg-[#1c2a25] p-3.5 shadow-md">
            <span class="text-[11px] text-rose-300 font-medium">Kupon Nonaktif</span>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="text-2xl font-black text-rose-400">{{ promos.filter(p => !p.is_active).length }}</span>
                <span class="text-xs text-rose-400/60">Ditutup</span>
            </div>
        </div>
        <div class="rounded-2xl border border-teal-500/30 bg-[#1c2a25] p-3.5 shadow-md">
            <span class="text-[11px] text-teal-300 font-medium">Total Terpakai</span>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="text-2xl font-black text-teal-300">{{ promos.reduce((acc, p) => acc + (p.used_count || 0), 0) }}</span>
                <span class="text-xs text-teal-300/60">Kali Klaim</span>
            </div>
        </div>
    </div>

    <!-- FORM BUAT PROMO (COLLAPSIBLE / DRAWER) -->
    <div v-show="showForm" class="mt-5 rounded-2xl border border-[#0d685b]/40 bg-[#1c2a25] p-5 shadow-xl text-[#f3f2e7] transition-all">
        <div class="flex items-center justify-between border-b border-[#0d685b]/20 pb-3 mb-4">
            <div class="flex items-center gap-2">
                <span class="text-lg">➕</span>
                <h3 class="text-sm font-black text-[#f3f2e7]">Formulir Penerbitan Kupon Diskon Baru</h3>
            </div>
            <button type="button" @click="showForm = false" class="text-xs text-[#f3f2e7]/50 hover:text-white">✕</button>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div class="grid gap-3.5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label :class="labelClass">Kode Kupon / Voucher <span class="text-rose-400">*</span></label>
                    <input v-model="form.code" placeholder="Misal: RAMELAHEMAT50" :class="input" autofocus />
                    <p v-if="form.errors.code" class="mt-1 text-xs text-rose-400">{{ form.errors.code }}</p>
                </div>

                <div>
                    <label :class="labelClass">Tipe Potongan Diskon <span class="text-rose-400">*</span></label>
                    <select v-model="form.discount_type" :class="input">
                        <option value="percent">Persentase (%)</option>
                        <option value="nominal">Nominal Tetap (Rp)</option>
                    </select>
                </div>

                <div>
                    <label :class="labelClass">Besaran Nilai Diskon <span class="text-rose-400">*</span></label>
                    <input
                        v-model="form.discount_value"
                        type="number"
                        min="1"
                        :placeholder="form.discount_type === 'percent' ? 'Contoh: 10 (artinya 10%)' : 'Contoh: 25000 (artinya Rp 25.000)'"
                        :class="input"
                    />
                    <p v-if="form.errors.discount_value" class="mt-1 text-xs text-rose-400">{{ form.errors.discount_value }}</p>
                </div>

                <div>
                    <label :class="labelClass">Maksimal Diskon (Rp)</label>
                    <input
                        v-model="form.max_discount_amount"
                        type="number"
                        placeholder="Batas maks potongan diskon persen (opsional)"
                        :class="input"
                    />
                    <p v-if="form.errors.max_discount_amount" class="mt-1 text-xs text-rose-400">{{ form.errors.max_discount_amount }}</p>
                </div>

                <div>
                    <label :class="labelClass">Minimal Belanja (Rp)</label>
                    <input
                        v-model="form.min_purchase"
                        type="number"
                        placeholder="Contoh: 50000 (0 = tanpa batas)"
                        :class="input"
                    />
                    <p v-if="form.errors.min_purchase" class="mt-1 text-xs text-rose-400">{{ form.errors.min_purchase }}</p>
                </div>

                <div>
                    <label :class="labelClass">Batas Total Kuota</label>
                    <input
                        v-model="form.quota"
                        type="number"
                        placeholder="Total kuota voucher (kosongkan jika tanpa kuota)"
                        :class="input"
                    />
                    <p v-if="form.errors.quota" class="mt-1 text-xs text-rose-400">{{ form.errors.quota }}</p>
                </div>

                <div>
                    <label :class="labelClass">Batas Pemakaian / User</label>
                    <input
                        v-model="form.per_user_limit"
                        type="number"
                        placeholder="Contoh: 1 (satu kali pakai per akun)"
                        :class="input"
                    />
                    <p v-if="form.errors.per_user_limit" class="mt-1 text-xs text-rose-400">{{ form.errors.per_user_limit }}</p>
                </div>

                <div>
                    <label :class="labelClass">Mulai Berlaku (Opsional)</label>
                    <input v-model="form.starts_at" type="datetime-local" :class="input" />
                    <p v-if="form.errors.starts_at" class="mt-1 text-xs text-rose-400">{{ form.errors.starts_at }}</p>
                </div>

                <div>
                    <label :class="labelClass">Kedaluwarsa Sampai (Opsional)</label>
                    <input v-model="form.valid_until" type="datetime-local" :class="input" />
                    <p v-if="form.errors.valid_until" class="mt-1 text-xs text-rose-400">{{ form.errors.valid_until }}</p>
                </div>
            </div>

            <!-- Cakupan Toko & Sakelar Aktif -->
            <div class="rounded-xl border border-[#0d685b]/20 bg-[#131d1a] p-3.5 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3 text-xs">
                    <div>
                        <span class="font-bold text-[#0d685b] uppercase block text-[11px] mb-1.5">Berlaku pada Pilar Toko:</span>
                        <div class="flex flex-wrap items-center gap-3">
                            <label v-for="s in stores" :key="s.id" class="flex items-center gap-1.5 cursor-pointer text-[#f3f2e7]/80 hover:text-white">
                                <input v-model="form.store_ids" type="checkbox" :value="s.id" class="rounded accent-[#0d685b]" />
                                <span>{{ s.name }}</span>
                            </label>
                            <span v-if="!form.store_ids.length" class="text-[11px] text-emerald-400/80 italic font-medium">
                                (Semua toko / Global jika tidak ada yang dicentang)
                            </span>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer font-bold text-emerald-400 select-none">
                        <input v-model="form.is_active" type="checkbox" class="rounded accent-[#0d685b]" />
                        <span>Langsung Aktifkan</span>
                    </label>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2.5 pt-2">
                <button
                    type="button"
                    @click="showForm = false"
                    class="rounded-xl border border-[#0d685b]/30 px-4 py-2 text-xs font-semibold text-[#f3f2e7]/70 hover:bg-[#131d1a]"
                >
                    Batal
                </button>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-xl bg-[#0d685b] px-6 py-2 text-xs font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 hover:bg-[#0d685b]/90 transition disabled:opacity-50"
                >
                    {{ form.processing ? 'Menyimpan...' : 'Terbitkan Kode Promo' }}
                </button>
            </div>
        </form>
    </div>

    <!-- FILTER & SEARCH BAR -->
    <div class="mt-5 flex flex-wrap items-center justify-between gap-2.5 rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-3 shadow-lg">
        <div class="flex flex-wrap items-center gap-2 flex-1 min-w-[240px]">
            <input
                v-model="searchQuery"
                placeholder="Cari kode promo..."
                class="rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-1.5 text-xs text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:outline-none focus:border-[#0d685b] w-48"
            />
            <select
                v-model="statusFilter"
                class="rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3 py-1.5 text-xs text-[#f3f2e7] focus:outline-none focus:border-[#0d685b]"
            >
                <option value="">Semua Status</option>
                <option value="active">Hanya Aktif</option>
                <option value="inactive">Hanya Nonaktif</option>
            </select>
            <select
                v-model="storeFilter"
                class="rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3 py-1.5 text-xs text-[#f3f2e7] focus:outline-none focus:border-[#0d685b]"
            >
                <option value="">Semua Cakupan Toko</option>
                <option value="global">Global</option>
                <option v-for="s in stores" :key="s.id" :value="s.name">{{ s.name }}</option>
            </select>
            <button
                v-if="searchQuery || statusFilter || storeFilter"
                type="button"
                @click="searchQuery = ''; statusFilter = ''; storeFilter = '';"
                class="text-xs text-rose-300 hover:underline"
            >
                Reset
            </button>
        </div>
        <div class="text-xs text-[#f3f2e7]/60">
            Menampilkan {{ filteredPromos.length }} dari {{ promos.length }} kupon
        </div>
    </div>

    <!-- TABEL DAFTAR PROMO -->
    <div class="mt-4 overflow-x-auto rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-lg">
        <table class="w-full text-left text-sm text-[#f3f2e7]">
            <thead class="border-b border-[#0d685b]/30 bg-[#131d1a] text-xs font-bold uppercase tracking-wider text-[#f3f2e7]/70">
                <tr>
                    <th class="w-12 px-3 py-3.5 text-center">#</th>
                    <th class="px-4 py-3.5">Kode Kupon</th>
                    <th class="px-4 py-3.5">Besaran Diskon</th>
                    <th class="px-4 py-3.5">Cakupan Scope</th>
                    <th class="px-4 py-3.5 text-center">Pemakaian / Kuota</th>
                    <th class="px-4 py-3.5">Masa Berlaku</th>
                    <th class="px-4 py-3.5 text-center">Status</th>
                    <th class="px-4 py-3.5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#0d685b]/20">
                <tr v-for="(p, idx) in filteredPromos" :key="p.id" class="hover:bg-[#131d1a]/50 transition">
                    <td class="px-3 py-3.5 text-center font-bold text-xs text-[#f3f2e7]/50">{{ idx + 1 }}</td>
                    <td class="px-4 py-3.5 font-bold text-[#f3f2e7]">
                        <span class="inline-flex items-center gap-1.5 bg-[#131d1a] border border-[#0d685b]/30 px-2.5 py-1 rounded-lg font-mono text-emerald-300">
                            🎟️ {{ p.code }}
                        </span>
                    </td>
                    <td class="px-4 py-3.5 text-emerald-400 font-semibold whitespace-nowrap">
                        {{ p.discount_type === 'percent' ? Number(p.discount_value) + '%' : rupiah(p.discount_value) }}
                    </td>
                    <td class="px-4 py-3.5 text-xs text-[#f3f2e7]/80">
                        <span class="inline-block rounded-md bg-[#131d1a] border border-[#0d685b]/20 px-2 py-0.5">
                            🏬 {{ p.scope }}
                        </span>
                    </td>
                    <td class="px-4 py-3.5 text-xs text-center font-medium whitespace-nowrap">
                        <span class="inline-flex items-center gap-1 rounded-full bg-[#131d1a] border border-[#0d685b]/30 px-2.5 py-0.5">
                            <span>👥</span>
                            <span>{{ p.used_count }}{{ p.quota ? ' / ' + p.quota : ' (Tanpa batas)' }}</span>
                        </span>
                    </td>
                    <td class="px-4 py-3.5 text-xs text-[#f3f2e7]/60 whitespace-nowrap">
                        {{ p.valid_until ?? 'Tanpa Batas Waktu' }}
                    </td>
                    <td class="px-4 py-3.5 text-center whitespace-nowrap">
                        <span
                            class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="p.is_active ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-rose-950/40 text-rose-300 border border-rose-500/30'"
                        >
                            {{ p.is_active ? '● Aktif' : '○ Nonaktif' }}
                        </span>
                    </td>
                    <td class="space-x-2.5 px-4 py-3.5 text-right text-xs whitespace-nowrap">
                        <Link :href="`/admin/promo/${p.id}/log`" class="font-bold text-emerald-400 hover:text-emerald-300 underline">
                            Audit Log
                        </Link>
                        <button
                            type="button"
                            class="font-bold underline cursor-pointer"
                            :class="p.is_active ? 'text-amber-400 hover:text-amber-300' : 'text-emerald-400 hover:text-emerald-300'"
                            @click="toggle(p)"
                        >
                            {{ p.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                        </button>
                        <button
                            type="button"
                            class="font-bold text-rose-400 hover:text-rose-300 underline cursor-pointer"
                            @click="remove(p)"
                        >
                            Hapus
                        </button>
                    </td>
                </tr>
                <tr v-if="!filteredPromos.length">
                    <td colspan="8" class="px-4 py-10 text-center text-sm text-[#f3f2e7]/60">
                        🍃 Tidak ada kupon promo yang cocok dengan kriteria filter.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>


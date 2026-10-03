<script setup>
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";

defineOptions({ layout: AdminLayout });
defineProps({ promos: Array, stores: Array });

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
const submit = () => form.post("/admin/promo", { onSuccess: () => form.reset() });
const toggle = (p) => router.patch(`/admin/promo/${p.id}/toggle`);
const remove = (p) => confirm(`Hapus promo ${p.code}?`) && router.delete(`/admin/promo/${p.id}`);
const input = "w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2 text-sm text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:outline-none focus:border-[#0d685b]";
</script>

<template>
    <Head title="Promo & Kupon" />
    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-black text-[#f3f2e7] tracking-tight">Kupon & Promo</h1>
            <p class="text-xs text-[#f3f2e7]/60">Buat voucher diskon belanja, kuota pemakaian, dan potongan harga khusus.</p>
        </div>
    </div>

    <!-- FORM BUAT PROMO -->
    <form class="mt-5 grid gap-3 rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-5 shadow-lg text-[#f3f2e7] md:grid-cols-3" @submit.prevent="submit">
        <div>
            <input v-model="form.code" placeholder="KODE KUPON (MISAL: DISKON50)" :class="input" />
            <p v-if="form.errors.code" class="mt-1 text-xs text-rose-400">{{ form.errors.code }}</p>
        </div>
        <select v-model="form.discount_type" :class="input">
            <option value="percent">Tipe: Persentase (%)</option>
            <option value="nominal">Tipe: Nominal Tetap (Rp)</option>
        </select>
        <div>
            <input v-model="form.discount_value" type="number" placeholder="Nilai diskon (cth: 10 atau 20000)" :class="input" />
            <p v-if="form.errors.discount_value" class="mt-1 text-xs text-rose-400">{{ form.errors.discount_value }}</p>
        </div>
        <input v-model="form.max_discount_amount" type="number" placeholder="Maks. potongan Rp (opsional)" :class="input" />
        <input v-model="form.min_purchase" type="number" placeholder="Min. belanja Rp" :class="input" />
        <input v-model="form.quota" type="number" placeholder="Kuota total kupon" :class="input" />
        <input v-model="form.per_user_limit" type="number" placeholder="Batas pakai per user" :class="input" />
        <input v-model="form.starts_at" type="datetime-local" :class="input" title="Mulai" />
        <div>
            <input v-model="form.valid_until" type="datetime-local" :class="input" title="Berlaku sampai" />
            <p v-if="form.errors.valid_until" class="mt-1 text-xs text-rose-400">{{ form.errors.valid_until }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-3 text-xs md:col-span-3 border-t border-[#0d685b]/20 pt-3">
            <span class="font-bold text-[#0d685b] uppercase">Berlaku di:</span>
            <label v-for="s in stores" :key="s.id" class="flex items-center gap-1.5 cursor-pointer">
                <input v-model="form.store_ids" type="checkbox" :value="s.id" class="rounded accent-[#0d685b]" />
                <span>{{ s.name }}</span>
            </label>
            <label class="ml-auto flex items-center gap-1.5 cursor-pointer font-bold text-emerald-400">
                <input v-model="form.is_active" type="checkbox" class="rounded accent-[#0d685b]" />
                <span>Aktifkan langsung</span>
            </label>
        </div>
        <button
            :disabled="form.processing"
            class="rounded-xl bg-[#0d685b] py-2.5 text-xs font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 hover:bg-[#0d685b]/90 transition md:col-span-3 disabled:opacity-50"
        >
            + Terbitkan Kode Promo Baru
        </button>
    </form>

    <!-- TABEL DAFTAR PROMO -->
    <div class="mt-6 overflow-x-auto rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-lg">
        <table class="w-full text-left text-sm text-[#f3f2e7]">
            <thead class="border-b border-[#0d685b]/30 bg-[#131d1a] text-xs font-bold uppercase tracking-wider text-[#f3f2e7]/70">
                <tr>
                    <th class="p-3.5">Kode Promo</th>
                    <th class="p-3.5">Besaran Diskon</th>
                    <th class="p-3.5">Cakupan Scope</th>
                    <th class="p-3.5">Terpakai</th>
                    <th class="p-3.5">Berlaku Sampai</th>
                    <th class="p-3.5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#0d685b]/20">
                <tr v-for="p in promos" :key="p.id" class="hover:bg-[#131d1a]/50 transition">
                    <td class="p-3.5 font-bold text-[#f3f2e7]">
                        {{ p.code }}
                        <span v-if="!p.is_active" class="ml-2 rounded bg-rose-950/40 border border-rose-500/30 px-1.5 py-0.5 text-[10px] text-rose-300">
                            Nonaktif
                        </span>
                    </td>
                    <td class="p-3.5 text-emerald-400 font-semibold">
                        {{ p.discount_type === 'percent' ? Number(p.discount_value) + '%' : 'Rp ' + Number(p.discount_value).toLocaleString('id-ID') }}
                    </td>
                    <td class="p-3.5 text-xs text-[#f3f2e7]/70">{{ p.scope }}</td>
                    <td class="p-3.5 text-xs font-medium">{{ p.used_count }}{{ p.quota ? ' / ' + p.quota : '' }}</td>
                    <td class="p-3.5 text-xs text-[#f3f2e7]/60">{{ p.valid_until ?? 'Tanpa Batas' }}</td>
                    <td class="space-x-3 p-3.5 text-right text-xs">
                        <Link :href="`/admin/promo/${p.id}/log`" class="font-bold text-emerald-400 hover:text-emerald-300 underline">
                            Riwayat Log
                        </Link>
                        <button class="font-bold text-[#f3f2e7]/80 hover:text-[#f3f2e7] underline" @click="toggle(p)">
                            {{ p.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                        </button>
                        <button class="font-bold text-rose-400 hover:text-rose-300 underline" @click="remove(p)">
                            Hapus
                        </button>
                    </td>
                </tr>
                <tr v-if="!promos.length">
                    <td colspan="6" class="p-6 text-center text-sm text-[#f3f2e7]/60">🍃 Belum ada kupon promo yang dibuat.</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

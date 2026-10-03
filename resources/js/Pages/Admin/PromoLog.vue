<script setup>
import { Head, Link } from "@inertiajs/vue3";
import AdminLayout from "../../Layouts/AdminLayout.vue";
import { rupiah } from "../../utils/format";

defineOptions({ layout: AdminLayout });
defineProps({ promo: Object, logs: Array });
</script>

<template>
    <Head :title="`Log ${promo.code}`" />
    <Link href="/admin/promo" class="inline-flex items-center gap-1 text-xs font-semibold text-[#f3f2e7]/70 hover:text-[#f3f2e7] transition">
        ← Kembali ke Kupon & Promo
    </Link>
    <div class="mt-2 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-black text-[#f3f2e7] tracking-tight">Log Pemakaian Kupon {{ promo.code }}</h1>
            <p class="text-xs text-[#f3f2e7]/60">Catatan audit seluruh pelanggan yang telah menukarkan kupon ini.</p>
        </div>
    </div>

    <div class="mt-5 overflow-x-auto rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-lg">
        <table class="w-full text-left text-sm text-[#f3f2e7]">
            <thead class="border-b border-[#0d685b]/30 bg-[#131d1a] text-xs font-bold uppercase tracking-wider text-[#f3f2e7]/70">
                <tr>
                    <th class="p-3.5">Username</th>
                    <th class="p-3.5">Invoice Transaksi</th>
                    <th class="p-3.5">Nilai Diskon</th>
                    <th class="p-3.5">Waktu Penukaran</th>
                    <th class="p-3.5">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#0d685b]/20">
                <tr v-for="l in logs" :key="l.id" class="hover:bg-[#131d1a]/50 transition">
                    <td class="p-3.5 font-bold text-[#f3f2e7]">{{ l.username }}</td>
                    <td class="p-3.5">
                        <Link :href="`/admin/pesanan/${l.invoice}`" class="font-bold text-emerald-400 hover:text-emerald-300 underline">
                            {{ l.invoice }}
                        </Link>
                    </td>
                    <td class="p-3.5 text-emerald-400 font-semibold">{{ rupiah(l.discount) }}</td>
                    <td class="p-3.5 text-xs text-[#f3f2e7]/60">{{ l.used_at }}</td>
                    <td class="p-3.5">
                        <span
                            class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="l.cancelled ? 'bg-rose-950/40 text-rose-300 border border-rose-500/40' : 'bg-emerald-950/40 text-emerald-300 border border-emerald-500/40'"
                        >
                            {{ l.cancelled ? 'Dibatalkan' : 'Terpakai' }}
                        </span>
                    </td>
                </tr>
                <tr v-if="!logs.length">
                    <td colspan="5" class="p-6 text-center text-sm text-[#f3f2e7]/60">🍃 Belum ada riwayat pemakaian untuk kupon ini.</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

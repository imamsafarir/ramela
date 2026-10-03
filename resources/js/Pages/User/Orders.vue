<script setup>
import { Head, Link } from "@inertiajs/vue3";
import UserLayout from "../../Layouts/UserLayout.vue";
import { fmtDate, rupiah, statusClass } from "../../utils/format";

defineOptions({ layout: UserLayout });

defineProps({ orders: Object });
</script>

<template>
    <Head title="Pesanan" />

    <div class="mb-4">
        <Link
            href="/dashboard"
            class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#f3f2e7]/70 hover:text-[#f3f2e7] transition"
        >
            <span>←</span>
            <span>Kembali ke Dashboard</span>
        </Link>
    </div>

    <h1 class="text-2xl font-black text-[#f3f2e7] tracking-tight">
        Pesanan Saya
    </h1>

    <div
        v-if="!orders.data.length"
        class="mt-6 rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-10 text-center text-sm text-[#f3f2e7]/60 shadow-xl"
    >
        <p class="text-4xl mb-2">🛍️</p>
        <p class="font-bold text-[#f3f2e7] text-base mb-1">
            Belum ada transaksi pesanan
        </p>
        <p class="text-xs text-[#f3f2e7]/60 mb-4">
            Pesanan belanja Anda di toko RAMELA akan tampil di sini.
        </p>
        <Link
            href="/dashboard"
            class="inline-flex items-center gap-2 rounded-xl bg-[#0d685b] hover:bg-[#117c6d] px-5 py-2.5 text-xs font-bold text-[#f3f2e7] transition shadow-md shadow-[#0d685b]/30"
        >
            Mulai Belanja Sekarang →
        </Link>
    </div>

    <ul
        v-else
        class="mt-6 divide-y divide-[#0d685b]/20 overflow-hidden rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-xl"
    >
        <li v-for="o in orders.data" :key="o.invoice_number">
            <Link
                :href="`/pesanan/${o.invoice_number}`"
                class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 transition hover:bg-[#131d1a]/50"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-sm text-[#f3f2e7]">{{
                            o.invoice_number
                        }}</span>
                        <span
                            class="rounded-full px-2.5 py-0.5 text-[10px] font-bold"
                            :class="statusClass(o.status)"
                        >
                            {{ o.status_label }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-[#f3f2e7]/60">
                        {{ o.store }} · {{ fmtDate(o.created_at) }}
                    </p>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <span class="font-black text-sm text-emerald-400">
                        {{ rupiah(o.final_amount) }}
                    </span>
                    <span class="text-xs font-bold text-[#f3f2e7]/70">
                        Lihat Detail →
                    </span>
                </div>
            </Link>
        </li>
    </ul>

    <nav v-if="orders.last_page > 1" class="mt-6 flex flex-wrap gap-1.5">
        <template v-for="l in orders.links" :key="l.label">
            <Link
                v-if="l.url"
                :href="l.url"
                class="rounded-lg px-3.5 py-1.5 text-xs font-semibold border transition"
                :class="
                    l.active
                        ? 'bg-[#0d685b] border-[#0d685b] text-[#f3f2e7]'
                        : 'bg-[#131d1a] border-[#0d685b]/30 text-[#f3f2e7]/80 hover:bg-[#0d685b]/20 hover:text-[#f3f2e7]'
                "
                v-html="l.label"
            />
        </template>
    </nav>
</template>

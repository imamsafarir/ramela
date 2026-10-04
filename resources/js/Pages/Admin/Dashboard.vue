<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { rupiah } from '../../utils/format';

defineOptions({ layout: AdminLayout });

defineProps({
    stats: Object,
    perStore: Array,
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const isSuperAdmin = computed(() => !!user.value?.is_super_admin);
const superadminPath = computed(() => user.value?.superadmin_path || 'dewa-panel');
</script>

<template>
    <Head title="Dashboard Admin" />

    <!-- HERO HEADER -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-[#f3f2e7] sm:text-3xl">
                Dashboard Manajemen
            </h1>
            <p class="mt-1 text-sm text-[#f3f2e7]/60">
                Ringkasan operasional harian seluruh toko dan inventaris RAMELA.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <Link
                href="/admin/produk"
                class="rounded-xl border border-[#0d685b]/40 bg-[#1c2a25] px-3.5 py-2 text-xs font-bold text-[#f3f2e7] shadow-sm hover:border-[#0d685b] hover:bg-[#131d1a] transition"
            >
                + Kelola Produk
            </Link>
            <Link
                href="/admin/pesanan"
                class="rounded-xl bg-[#0d685b] px-3.5 py-2 text-xs font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 hover:bg-[#0d685b]/90 transition"
            >
                Lihat Semua Pesanan →
            </Link>
        </div>
    </div>

    <!-- PUSAT KONTROL CEPAT ADMINISTRATOR -->
    <div class="mt-6 overflow-hidden rounded-2xl border border-[#0d685b]/40 bg-[#1c2a25] p-4 text-[#f3f2e7] shadow-xl sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-[#0d685b]/30 px-2.5 py-0.5 text-xs font-bold tracking-wide text-[#f3f2e7] uppercase ring-1 ring-[#0d685b]/50">
                        ⚡ Pintasan Sistem
                    </span>
                    <span class="text-xs text-[#f3f2e7]/60">Akses Cepat Pengelolaan</span>
                </div>
                <h2 class="mt-2 text-xl font-black text-[#f3f2e7] sm:text-2xl">
                    Pusat Manajemen & Kontrol RAMELA
                </h2>
                <p class="mt-1 text-xs text-[#f3f2e7]/70 sm:text-sm">
                    Akses terpadu untuk pengantaran kurir, manajemen akun pelanggan, pengaturan pembayaran Midtrans, dan bypass darurat.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Link
                    href="/admin/kurir"
                    class="rounded-xl border border-[#0d685b]/30 bg-[#131d1a] px-3.5 py-2 text-xs font-bold text-[#f3f2e7] transition hover:bg-[#17231f] hover:border-[#0d685b]"
                >
                    🛵 Kurir & Tracking
                </Link>
                <Link
                    href="/admin/kurir?tab=rates"
                    class="rounded-xl border border-[#0d685b]/30 bg-[#131d1a] px-3.5 py-2 text-xs font-bold text-[#f3f2e7] transition hover:bg-[#17231f] hover:border-[#0d685b]"
                >
                    📍 Tarif Ongkir (Kab/Kota)
                </Link>
                <Link
                    href="/admin/users"
                    class="rounded-xl border border-[#0d685b]/30 bg-[#131d1a] px-3.5 py-2 text-xs font-bold text-[#f3f2e7] transition hover:bg-[#17231f] hover:border-[#0d685b]"
                >
                    👥 Pengguna & Saldo
                </Link>
                <Link
                    v-if="isSuperAdmin"
                    href="/admin/pengaturan"
                    class="rounded-xl bg-[#0d685b] px-3.5 py-2 text-xs font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 transition hover:bg-[#0d685b]/90"
                >
                    ⚙️ Gateway & Fitur
                </Link>
                <Link
                    v-if="isSuperAdmin"
                    href="/admin/transaksi"
                    class="rounded-xl bg-purple-700/80 border border-purple-500/40 px-3.5 py-2 text-xs font-bold text-white shadow-lg transition hover:bg-purple-600"
                >
                    ⚡ Bypass Status
                </Link>
            </div>
        </div>
    </div>

    <!-- METRIK UTAMA OPERASIONAL -->
    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6 sm:gap-4">
        <!-- Pesanan Hari Ini -->
        <div class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4.5 shadow-lg">
            <div class="flex items-center justify-between">
                <p class="text-xs font-bold text-[#f3f2e7]/60 uppercase tracking-wider">Pesanan Hari Ini</p>
                <span class="rounded-lg bg-[#0d685b]/30 p-1.5 text-sm text-[#f3f2e7]">📦</span>
            </div>
            <p class="mt-3 text-2xl font-black text-[#f3f2e7] sm:text-3xl">{{ stats.orders_today }}</p>
            <p class="mt-1 text-xs text-[#f3f2e7]/50">Total order masuk</p>
        </div>

        <!-- Omzet Hari Ini -->
        <div class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4.5 shadow-lg">
            <div class="flex items-center justify-between">
                <p class="text-xs font-bold text-[#f3f2e7]/60 uppercase tracking-wider">Omzet Hari Ini</p>
                <span class="rounded-lg bg-emerald-500/20 p-1.5 text-sm text-emerald-400">💰</span>
            </div>
            <p class="mt-3 text-lg font-black text-[#f3f2e7] sm:text-2xl">{{ rupiah(stats.revenue_today) }}</p>
            <p class="mt-1 text-xs text-[#f3f2e7]/50">Tidak termasuk dibatalkan</p>
        </div>

        <!-- Perlu Diproses -->
        <Link
            href="/admin/pesanan?status=paid"
            class="group rounded-2xl border border-emerald-500/30 bg-[#1c2a25] p-4.5 shadow-lg transition hover:border-emerald-400 hover:bg-[#131d1a]"
        >
            <div class="flex items-center justify-between">
                <p class="text-xs font-bold text-emerald-400 uppercase tracking-wider">Perlu Diproses</p>
                <span class="rounded-lg bg-emerald-500/20 p-1.5 text-sm text-emerald-300">⏳</span>
            </div>
            <p class="mt-3 text-2xl font-black text-emerald-300 sm:text-3xl">{{ stats.to_process }}</p>
            <p class="mt-1 text-xs font-semibold text-emerald-400 group-hover:underline">Buka Pesanan Masuk →</p>
        </Link>

        <!-- Siap Dijemput (Pickup) -->
        <Link
            href="/admin/pesanan?status=ready_for_pickup"
            class="group rounded-2xl border border-teal-500/30 bg-[#1c2a25] p-4.5 shadow-lg transition hover:border-teal-400 hover:bg-[#131d1a]"
        >
            <div class="flex items-center justify-between">
                <p class="text-xs font-bold text-teal-400 uppercase tracking-wider">Siap Jemput</p>
                <span class="rounded-lg bg-teal-500/20 p-1.5 text-sm text-teal-300">🏪</span>
            </div>
            <p class="mt-3 text-2xl font-black text-teal-300 sm:text-3xl">{{ stats.to_pickup || 0 }}</p>
            <p class="mt-1 text-xs font-semibold text-teal-400 group-hover:underline">Pesanan Pickup →</p>
        </Link>

        <!-- Menunggu Kurir -->
        <Link
            href="/admin/pesanan?status=ready_to_ship"
            class="group rounded-2xl border border-amber-500/30 bg-[#1c2a25] p-4.5 shadow-lg transition hover:border-amber-400 hover:bg-[#131d1a]"
        >
            <div class="flex items-center justify-between">
                <p class="text-xs font-bold text-amber-400 uppercase tracking-wider">Siap Kirim</p>
                <span class="rounded-lg bg-amber-500/20 p-1.5 text-sm text-amber-300">🛵</span>
            </div>
            <p class="mt-3 text-2xl font-black text-amber-300 sm:text-3xl">{{ stats.to_ship }}</p>
            <p class="mt-1 text-xs font-semibold text-amber-400 group-hover:underline">Tugaskan Kurir →</p>
        </Link>

        <!-- Stok Menipis -->
        <Link
            href="/admin/produk"
            class="group col-span-2 sm:col-span-1 rounded-2xl border border-rose-500/30 bg-[#1c2a25] p-4.5 shadow-lg transition hover:border-rose-400 hover:bg-[#131d1a]"
        >
            <div class="flex items-center justify-between">
                <p class="text-xs font-bold text-rose-400 uppercase tracking-wider">Stok Tipis (≤5)</p>
                <span class="rounded-lg bg-rose-500/20 p-1.5 text-sm text-rose-300">⚠️</span>
            </div>
            <p class="mt-3 text-2xl font-black text-rose-300 sm:text-3xl">{{ stats.low_stock }}</p>
            <p class="mt-1 text-xs font-semibold text-rose-400 group-hover:underline">Update Stok Sekarang →</p>
        </Link>
    </div>

    <!-- PERFORMA PER TOKO -->
    <div class="mt-10 mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-black text-[#f3f2e7] sm:text-xl">Ringkasan Tiap Toko</h2>
                <p class="text-xs text-[#f3f2e7]/60">Aktivitas dan beban kerja di masing-masing pilar bisnis RAMELA.</p>
            </div>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-3">
            <div
                v-for="s in perStore"
                :key="s.name"
                class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-5 shadow-lg hover:border-[#0d685b]/60 transition"
            >
                <div class="flex items-center justify-between">
                    <span class="text-2xl">
                        {{ s.name.includes('EATS') ? '🍽️' : (s.name.includes('HAMPERS') ? '🎁' : '🏗️') }}
                    </span>
                    <span class="rounded-full bg-[#131d1a] border border-[#0d685b]/30 px-2.5 py-0.5 text-[11px] font-bold text-[#f3f2e7]">
                        {{ s.name }}
                    </span>
                </div>
                <h3 class="mt-3 font-bold text-[#f3f2e7]">{{ s.name }}</h3>

                <div class="mt-4 space-y-2 border-t border-[#0d685b]/20 pt-3 text-xs">
                    <div class="flex items-center justify-between text-[#f3f2e7]/70">
                        <span>Order Hari Ini</span>
                        <span class="font-bold text-[#f3f2e7]">{{ s.orders_today }}</span>
                    </div>
                    <div class="flex items-center justify-between text-[#f3f2e7]/70">
                        <span>Perlu Diproses</span>
                        <span class="font-bold text-emerald-400">{{ s.to_process }}</span>
                    </div>
                </div>

                <div class="mt-4 pt-2">
                    <Link
                        href="/admin/pesanan"
                        class="flex items-center justify-between text-xs font-bold text-[#0d685b] hover:text-emerald-300 transition"
                    >
                        <span>Kelola Pesanan Toko</span>
                        <span>→</span>
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>

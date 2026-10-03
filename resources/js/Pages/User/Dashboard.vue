<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import UserLayout from '../../Layouts/UserLayout.vue';
import { fmtDate, rupiah, statusClass } from '../../utils/format';

defineOptions({ layout: UserLayout });

defineProps({
    lastActive: String,
    profileComplete: Boolean,
    stores: Array,
    recentWallet: Array,
    recentOrders: Array,
    orderStats: Object,
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const isSuperAdmin = computed(() => !!user.value?.is_super_admin);
const isStaff = computed(() => ['admin', 'super_admin'].includes(user.value?.role));
const superadminPath = computed(() => user.value?.superadmin_path || 'dewa-panel');
</script>

<template>
    <Head title="Dashboard" />

    <!-- BANNER PERINGATAN PROFIL (JIKA BELUM LENGKAP) -->
    <div
        v-if="!profileComplete"
        class="mb-6 flex flex-col gap-2 rounded-2xl bg-amber-950/40 border border-amber-500/40 p-4 text-xs sm:text-sm text-amber-200 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="flex items-center gap-2">
            <span class="text-base">⚠️</span>
            <span>Profil Anda belum lengkap (nama lengkap atau nomor WhatsApp).</span>
        </div>
        <Link href="/profile" class="inline-flex items-center font-bold text-amber-300 underline hover:text-amber-100">
            Lengkapi Profil Sekarang →
        </Link>
    </div>

    <!-- HERO PROFILE & WALLET CARD -->
    <div class="grid gap-4 lg:grid-cols-3">
        <!-- Kartu Sapaan & Status Akun (2 Kolom di Desktop) -->
        <div class="relative overflow-hidden rounded-3xl bg-[#1c2a25] p-6 shadow-xl border border-[#0d685b]/30 lg:col-span-2 flex flex-col justify-between">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-tr from-[#0d685b] to-emerald-600 text-lg font-black text-[#f3f2e7] shadow-md shadow-[#0d685b]/30">
                        {{ (user.name || user.username).charAt(0).toUpperCase() }}
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-xl font-black tracking-tight text-[#f3f2e7] sm:text-2xl">
                                Halo, {{ user.name || user.username }}!
                            </h1>
                            <span class="rounded-full bg-[#0d685b]/30 border border-[#0d685b]/50 px-2.5 py-0.5 text-[11px] font-bold text-emerald-300 capitalize">
                                {{ user.role }}
                            </span>
                        </div>
                        <p class="text-xs text-[#f3f2e7]/60 mt-0.5">
                            Online terakhir: <span class="font-medium text-[#f3f2e7]/80">{{ fmtDate(lastActive) }}</span>
                        </p>
                    </div>
                </div>

                <!-- Shortcut Tombol -->
                <div class="flex items-center gap-2">
                    <Link
                        v-if="isStaff"
                        href="/admin"
                        class="rounded-xl border border-purple-500/30 bg-purple-950/40 px-3 py-1.5 text-xs font-bold text-purple-200 hover:bg-purple-900/50 transition"
                    >
                        👑 Panel Admin
                    </Link>
                    <Link
                        href="/profile"
                        class="rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3 py-1.5 text-xs font-semibold text-[#f3f2e7] hover:bg-[#0d685b]/20 transition"
                    >
                        ⚙️ Pengaturan
                    </Link>
                </div>
            </div>

            <!-- Quick Stats Bar -->
            <div class="mt-6 grid grid-cols-3 gap-2 border-t border-[#0d685b]/20 pt-4">
                <div class="text-center sm:text-left">
                    <span class="block text-[11px] font-bold text-[#f3f2e7]/60 uppercase tracking-wider">Total Belanja</span>
                    <span class="text-lg font-black text-[#f3f2e7]">{{ orderStats?.total ?? 0 }}</span>
                </div>
                <div class="text-center sm:text-left">
                    <span class="block text-[11px] font-bold text-[#f3f2e7]/60 uppercase tracking-wider">Diantar</span>
                    <span class="text-lg font-black text-cyan-400">{{ orderStats?.shipping ?? 0 }}</span>
                </div>
                <div class="text-center sm:text-left">
                    <span class="block text-[11px] font-bold text-[#f3f2e7]/60 uppercase tracking-wider">Selesai</span>
                    <span class="text-lg font-black text-emerald-400">{{ orderStats?.completed ?? 0 }}</span>
                </div>
            </div>
        </div>

        <!-- Kartu Saldo Digital Wallet (Mewah & Modern) -->
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#131d1a] via-[#1a2d26] to-[#0d685b]/40 p-6 text-[#f3f2e7] shadow-xl border border-[#0d685b]/40 flex flex-col justify-between">
            <!-- Background Glow Circle -->
            <div class="absolute -right-12 -top-12 h-40 w-40 rounded-full bg-emerald-500/10 blur-2xl"></div>

            <div class="relative z-10">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-widest text-emerald-200">Dompet Digital RAMELA</span>
                    <span class="rounded-full bg-[#0d685b]/30 border border-[#0d685b]/40 px-2 py-0.5 text-[10px] font-bold text-emerald-300 backdrop-blur-xs">
                        Aktif
                    </span>
                </div>
                <div class="mt-4">
                    <p class="text-xs text-[#f3f2e7]/70">Saldo Tersedia</p>
                    <p class="text-2xl font-black tracking-tight text-[#f3f2e7] sm:text-3xl">
                        {{ rupiah(user.saldo) }}
                    </p>
                </div>
            </div>

            <div class="relative z-10 mt-6 flex items-center gap-2">
                <Link
                    href="/topup"
                    class="flex-1 rounded-xl bg-[#0d685b] py-2.5 text-center text-xs font-bold text-[#f3f2e7] shadow-md shadow-[#0d685b]/30 transition hover:bg-[#117c6d]"
                >
                    + Isi Saldo
                </Link>
                <Link
                    href="/pesanan"
                    class="flex-1 rounded-xl bg-[#131d1a] border border-[#0d685b]/40 py-2.5 text-center text-xs font-bold text-[#f3f2e7] transition hover:bg-[#0d685b]/20"
                >
                    Riwayat
                </Link>
            </div>
        </div>
    </div>

    <!-- WIDGET PANEL KONTROL ADMIN (INTEGRASI JIKA LOGIN SEBAGAI ADMIN / SUPER_ADMIN) -->
    <div
        v-if="isStaff"
        class="mt-6 overflow-hidden rounded-3xl bg-gradient-to-r from-[#131d1a] via-[#1c2a25] to-[#131d1a] p-6 text-[#f3f2e7] shadow-xl border border-[#0d685b]/40"
    >
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-[#0d685b]/30 px-2.5 py-0.5 text-[11px] font-black uppercase tracking-wider text-emerald-300 ring-1 ring-[#0d685b]/40">
                        ⚡ Otoritas Manajemen
                    </span>
                    <span class="text-xs text-[#f3f2e7]/70 font-medium">Panel Admin Terpadu</span>
                </div>
                <h2 class="mt-2 text-xl font-black text-[#f3f2e7]">
                    Pusat Kontrol & Manajemen RAMELA
                </h2>
                <p class="mt-1 text-xs text-[#f3f2e7]/70">
                    Akses kontrol langsung untuk pesanan, kurir, akun pengguna, dan pengaturan sistem pembayaran.
                </p>
            </div>

            <!-- Pintasan Panel Admin -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                <Link
                    href="/admin"
                    class="flex items-center justify-center gap-1.5 rounded-xl bg-[#131d1a] px-3.5 py-2.5 text-xs font-bold text-[#f3f2e7] transition hover:bg-[#0d685b]/30 border border-[#0d685b]/40"
                >
                    <span>📊</span>
                    <span>Dashboard</span>
                </Link>
                <Link
                    href="/admin/kurir"
                    class="flex items-center justify-center gap-1.5 rounded-xl bg-[#131d1a] px-3.5 py-2.5 text-xs font-bold text-[#f3f2e7] transition hover:bg-[#0d685b]/30 border border-[#0d685b]/40"
                >
                    <span>🛵</span>
                    <span>Kurir</span>
                </Link>
                <Link
                    href="/admin/users"
                    class="flex items-center justify-center gap-1.5 rounded-xl bg-[#131d1a] px-3.5 py-2.5 text-xs font-bold text-[#f3f2e7] transition hover:bg-[#0d685b]/30 border border-[#0d685b]/40"
                >
                    <span>👥</span>
                    <span>Pengguna</span>
                </Link>
                <Link
                    href="/admin/pengaturan"
                    class="flex items-center justify-center gap-1.5 rounded-xl bg-[#0d685b] px-3.5 py-2.5 text-xs font-bold text-[#f3f2e7] transition hover:bg-[#117c6d] shadow-md shadow-[#0d685b]/30"
                >
                    <span>⚙️</span>
                    <span>Setting</span>
                </Link>
            </div>
        </div>
    </div>

    <!-- 3 TOMBOL SEDERHANA TOKO RAMELA -->
    <div class="mt-8">
        <div class="mb-3">
            <h2 class="text-base font-black text-[#f3f2e7] sm:text-lg">Toko RAMELA</h2>
            <p class="text-xs text-[#f3f2e7]/70">Pilih unit toko untuk langsung mulai transaksi belanja:</p>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <!-- RAMELA EATS -->
            <Link
                href="/toko/eats"
                class="group relative flex items-center justify-between rounded-2xl border border-orange-500/40 bg-gradient-to-r from-orange-600 to-amber-600 p-4 text-white shadow-lg shadow-orange-900/20 transition hover:shadow-orange-750/30 hover:-translate-y-0.5 active:scale-98"
            >
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/20 text-2xl backdrop-blur-xs group-hover:scale-110 transition">
                        🍽️
                    </span>
                    <div>
                        <span class="block text-sm font-black tracking-wide">RAMELA EATS</span>
                        <span class="text-[11px] font-medium text-orange-100">Klik untuk mulai transaksi</span>
                    </div>
                </div>
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-sm font-bold backdrop-blur-xs group-hover:translate-x-1 transition">
                    →
                </span>
            </Link>

            <!-- RAMELA HAMPERS -->
            <Link
                href="/toko/hampers"
                class="group relative flex items-center justify-between rounded-2xl border border-purple-500/40 bg-gradient-to-r from-purple-700 to-pink-600 p-4 text-white shadow-lg shadow-purple-900/20 transition hover:shadow-purple-750/30 hover:-translate-y-0.5 active:scale-98"
            >
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/20 text-2xl backdrop-blur-xs group-hover:scale-110 transition">
                        🎁
                    </span>
                    <div>
                        <span class="block text-sm font-black tracking-wide">RAMELA HAMPERS</span>
                        <span class="text-[11px] font-medium text-purple-100">Klik untuk mulai transaksi</span>
                    </div>
                </div>
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-sm font-bold backdrop-blur-xs group-hover:translate-x-1 transition">
                    →
                </span>
            </Link>

            <!-- RAMELA BETON -->
            <Link
                href="/toko/beton"
                class="group relative flex items-center justify-between rounded-2xl border border-[#0d685b] bg-gradient-to-r from-[#0d685b] to-teal-700 p-4 text-[#f3f2e7] shadow-lg shadow-teal-950/20 transition hover:shadow-teal-900/30 hover:-translate-y-0.5 active:scale-98"
            >
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/20 text-2xl backdrop-blur-xs group-hover:scale-110 transition">
                        🏗️
                    </span>
                    <div>
                        <span class="block text-sm font-black tracking-wide">RAMELA BETON</span>
                        <span class="text-[11px] font-medium text-emerald-100">Klik untuk mulai transaksi</span>
                    </div>
                </div>
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-sm font-bold backdrop-blur-xs group-hover:translate-x-1 transition">
                    →
                </span>
            </Link>
        </div>
    </div>

    <!-- PESANAN TERKINI & RIWAYAT SALDO -->
    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <!-- Pesanan Terkini -->
        <div>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-black text-[#f3f2e7]">Pesanan Terkini</h2>
                    <p class="text-xs text-[#f3f2e7]/60">Status pesanan belanja terbaru Anda.</p>
                </div>
                <Link href="/pesanan" class="text-xs font-bold text-emerald-400 hover:text-emerald-300 hover:underline">
                    Semua Pesanan →
                </Link>
            </div>

            <div v-if="recentOrders?.length" class="mt-3 overflow-hidden rounded-3xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-xl">
                <ul class="divide-y divide-[#0d685b]/20">
                    <li v-for="o in recentOrders" :key="o.invoice_number">
                        <Link
                            :href="`/pesanan/${o.invoice_number}`"
                            class="flex items-center justify-between p-4 transition hover:bg-[#131d1a]/50"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-[#f3f2e7] text-xs sm:text-sm">{{ o.invoice_number }}</span>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                                        :class="statusClass(o.status)"
                                    >
                                        {{ o.status_label }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-[#f3f2e7]/60">
                                    {{ o.store }} · {{ fmtDate(o.created_at) }}
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="block font-black text-[#f3f2e7] text-xs sm:text-sm">{{ rupiah(o.final_amount) }}</span>
                                <span v-if="o.status === 'shipping'" class="inline-flex items-center gap-1 text-[11px] font-bold text-cyan-400">
                                    <span class="h-1.5 w-1.5 animate-ping rounded-full bg-cyan-400"></span>
                                    Lacak Kurir
                                </span>
                            </div>
                        </Link>
                    </li>
                </ul>
            </div>
            <div v-else class="mt-3 rounded-3xl border border-[#0d685b]/30 bg-[#1c2a25] p-8 text-center text-xs sm:text-sm text-[#f3f2e7]/60">
                <p class="text-2xl mb-2">🛍️</p>
                Belum ada transaksi pesanan.
            </div>
        </div>

        <!-- Riwayat Mutasi Saldo -->
        <div>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-black text-[#f3f2e7]">Riwayat Mutasi Saldo</h2>
                    <p class="text-xs text-[#f3f2e7]/60">Catatan keluar masuk saldo dompet Anda.</p>
                </div>
                <Link href="/topup" class="text-xs font-bold text-emerald-400 hover:text-emerald-300 hover:underline">
                    Isi Saldo →
                </Link>
            </div>

            <div v-if="recentWallet?.length" class="mt-3 overflow-hidden rounded-3xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-xl">
                <ul class="divide-y divide-[#0d685b]/20">
                    <li v-for="(w, i) in recentWallet" :key="i" class="flex items-center justify-between p-4 hover:bg-[#131d1a]/50 transition">
                        <div>
                            <span class="font-bold text-xs sm:text-sm text-[#f3f2e7] capitalize">{{ w.type }}</span>
                            <p class="text-xs text-[#f3f2e7]/60">{{ w.note || w.description || '-' }}</p>
                            <span class="text-[10px] text-[#f3f2e7]/40">{{ fmtDate(w.created_at) }}</span>
                        </div>
                        <span
                            class="text-xs sm:text-sm font-black"
                            :class="Number(w.amount) < 0 ? 'text-rose-400' : 'text-emerald-400'"
                        >
                            {{ Number(w.amount) < 0 ? '' : '+' }}{{ rupiah(w.amount) }}
                        </span>
                    </li>
                </ul>
            </div>
            <div v-else class="mt-3 rounded-3xl border border-[#0d685b]/30 bg-[#1c2a25] p-8 text-center text-xs sm:text-sm text-[#f3f2e7]/60">
                <p class="text-2xl mb-2">💳</p>
                Belum ada catatan transaksi saldo dompet.
            </div>
        </div>
    </div>
</template>

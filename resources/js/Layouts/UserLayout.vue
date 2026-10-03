<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const flash = computed(() => page.props.flash?.success);
const isStaffOrSuper = computed(() => ['admin', 'super_admin'].includes(user.value?.role));

const rupiah = (v) => 'Rp ' + Number(v ?? 0).toLocaleString('id-ID');
const logout = () => router.post('/logout');

const navItems = [
    { label: 'Dashboard', href: '/dashboard', icon: 'dashboard' },
    { label: 'Pesanan', href: '/pesanan', icon: 'orders' },
    { label: 'Top-Up', href: '/topup', icon: 'topup' },
    { label: 'Profil', href: '/profile', icon: 'profile' },
];

const active = (href) => (href === '/dashboard' ? page.url === '/dashboard' : page.url.startsWith(href));
</script>

<template>
    <div class="min-h-screen bg-[#17231f] text-[#f3f2e7] pb-20 md:pb-10">
        <!-- HEADER TOP BAR -->
        <header class="sticky top-0 z-30 border-b border-[#0d685b]/30 bg-[#17231f]/95 backdrop-blur-md">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3 sm:px-6">
                <!-- Brand & Desktop Nav -->
                <div class="flex items-center gap-6">
                    <Link href="/dashboard" class="flex items-center gap-2 group">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#0d685b] text-sm font-black text-[#f3f2e7] shadow-sm group-hover:scale-105 transition transform">
                            R
                        </span>
                        <div class="flex flex-col">
                            <span class="text-base font-black tracking-tight text-[#f3f2e7] group-hover:text-emerald-400 transition">{{ page.props.appName }}</span>
                            <span class="hidden sm:inline-block text-[10px] font-semibold text-[#f3f2e7]/60">Pusat Belanja & Layanan</span>
                        </div>
                    </Link>

                    <!-- Desktop Nav Links -->
                    <nav class="hidden md:flex md:items-center md:gap-1">
                        <Link
                            v-for="item in navItems"
                            :key="item.href"
                            :href="item.href"
                            class="rounded-xl px-3 py-1.5 text-xs font-bold transition"
                            :class="active(item.href)
                                ? 'bg-[#0d685b] text-[#f3f2e7] shadow-xs'
                                : 'text-[#f3f2e7]/75 hover:bg-[#1c2a25] hover:text-[#f3f2e7]'"
                        >
                            {{ item.label }}
                        </Link>
                    </nav>
                </div>

                <!-- Right Elements: Saldo Pill, Cart, User Profile & Actions -->
                <div class="flex items-center gap-2 sm:gap-4">
                    <!-- Quick Saldo Button / Pill -->
                    <Link
                        href="/topup"
                        class="flex items-center gap-1.5 rounded-full border border-[#0d685b] bg-[#0d685b]/30 px-3 py-1.5 text-xs font-bold text-[#f3f2e7] transition hover:bg-[#0d685b]/50"
                        title="Isi Saldo Dompet"
                    >
                        <span>💳</span>
                        <span>{{ rupiah(user?.saldo) }}</span>
                    </Link>

                    <!-- Keranjang -->
                    <Link
                        href="/keranjang"
                        class="relative rounded-full p-2 text-[#f3f2e7]/80 transition hover:bg-[#1c2a25] hover:text-[#f3f2e7]"
                        title="Keranjang Belanja"
                    >
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                        </svg>
                        <span
                            v-if="page.props.cartCount"
                            class="absolute -top-1 -right-1 flex h-4.5 min-w-[18px] items-center justify-center rounded-full bg-amber-500 px-1 text-[10px] font-black text-slate-900 ring-2 ring-[#17231f]"
                        >
                            {{ page.props.cartCount }}
                        </span>
                    </Link>

                    <!-- Staff/Admin Switcher Button -->
                    <Link
                        v-if="isStaffOrSuper"
                        href="/admin"
                        class="inline-flex items-center gap-1 rounded-xl border border-amber-500/40 bg-amber-500/20 px-2.5 py-1 text-xs font-bold text-amber-300 hover:bg-amber-500/30 shadow-xs transition active:scale-95"
                    >
                        <span>👑 Mode Admin</span>
                    </Link>

                    <!-- User Menu / Logout -->
                    <div class="hidden sm:flex items-center gap-2 pl-2 border-l border-[#0d685b]/30">
                        <span class="text-xs font-bold text-[#f3f2e7]">{{ user?.name || user?.username }}</span>
                        <button
                            class="rounded-lg p-1.5 text-[#f3f2e7]/50 hover:bg-rose-500/20 hover:text-rose-400 transition"
                            title="Keluar"
                            @click="logout"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- MAIN CONTENT CONTAINER -->
        <main class="mx-auto max-w-6xl px-4 py-6 sm:px-6">
            <div
                v-if="flash"
                class="mb-6 flex items-center justify-between rounded-2xl border border-[#0d685b] bg-[#0d685b]/30 px-4 py-3 text-sm font-semibold text-[#f3f2e7]"
            >
                <div class="flex items-center gap-2">
                    <span>✅</span>
                    <span>{{ flash }}</span>
                </div>
            </div>
            <slot />
        </main>

        <!-- MOBILE BOTTOM NAVIGATION BAR -->
        <nav class="fixed bottom-0 inset-x-0 z-40 flex items-center justify-around border-t border-[#0d685b]/30 bg-[#121c19]/95 px-1 py-2 backdrop-blur-md md:hidden shadow-lg">
            <Link
                href="/dashboard"
                class="flex flex-col items-center gap-0.5 px-2 py-1 text-xs font-medium transition"
                :class="page.url === '/dashboard' ? 'font-bold text-emerald-400' : 'text-[#f3f2e7]/60 hover:text-[#f3f2e7]'"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span class="text-[10px]">Beranda</span>
            </Link>

            <Link
                href="/pesanan"
                class="flex flex-col items-center gap-0.5 px-2 py-1 text-xs font-medium transition"
                :class="page.url.startsWith('/pesanan') ? 'font-bold text-emerald-400' : 'text-[#f3f2e7]/60 hover:text-[#f3f2e7]'"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <span class="text-[10px]">Pesanan</span>
            </Link>

            <Link
                href="/topup"
                class="flex flex-col items-center gap-0.5 px-2 py-1 text-xs font-medium transition"
                :class="page.url.startsWith('/topup') ? 'font-bold text-emerald-400' : 'text-[#f3f2e7]/60 hover:text-[#f3f2e7]'"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                <span class="text-[10px]">Top-Up</span>
            </Link>

            <Link
                href="/keranjang"
                class="relative flex flex-col items-center gap-0.5 px-2 py-1 text-xs font-medium transition"
                :class="page.url.startsWith('/keranjang') ? 'font-bold text-emerald-400' : 'text-[#f3f2e7]/60 hover:text-[#f3f2e7]'"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                <span
                    v-if="page.props.cartCount"
                    class="absolute top-0 right-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-amber-500 text-[9px] font-bold text-slate-900"
                >
                    {{ page.props.cartCount }}
                </span>
                <span class="text-[10px]">Keranjang</span>
            </Link>

            <Link
                href="/profile"
                class="flex flex-col items-center gap-0.5 px-2 py-1 text-xs font-medium transition"
                :class="page.url.startsWith('/profile') ? 'font-bold text-emerald-400' : 'text-[#f3f2e7]/60 hover:text-[#f3f2e7]'"
            >
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span class="text-[10px]">Profil</span>
            </Link>

            <!-- Shortcut Mode Admin khusus Staff pada Bottom Bar Mobile -->
            <Link
                v-if="isStaffOrSuper"
                href="/admin"
                class="flex flex-col items-center gap-0.5 px-2 py-1 text-xs font-bold text-amber-300 hover:text-amber-200 transition"
            >
                <span class="text-base leading-none">👑</span>
                <span class="text-[10px]">Admin</span>
            </Link>
        </nav>
    </div>
</template>

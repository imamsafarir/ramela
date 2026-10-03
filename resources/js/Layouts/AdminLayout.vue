<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const flash = computed(() => page.props.flash?.success);
const user = computed(() => page.props.auth?.user);
const isSuperAdmin = computed(() => !!user.value?.is_super_admin);
const superadminPath = computed(() => user.value?.superadmin_path || 'dewa-panel');

const mobileMenuOpen = ref(false);

const navItems = computed(() => {
    const items = [
        { label: 'Dashboard', href: '/admin' },
        { label: 'Pesanan', href: '/admin/pesanan' },
        { label: 'Produk', href: '/admin/produk' },
        { label: 'Kategori', href: '/admin/kategori' },
        { label: 'Kurir', href: '/admin/kurir' },
        { label: 'Pengguna', href: '/admin/users' },
        { label: 'Promo', href: '/admin/promo' },
        { label: 'Blog', href: '/admin/blog' },
        { label: 'FAQ', href: '/admin/faq' },
    ];

    if (isSuperAdmin.value) {
        items.push(
            { label: 'Pengaturan', href: '/admin/pengaturan' },
            { label: 'Bypass', href: '/admin/transaksi' },
        );
    }

    return items;
});

const active = (href) => (href === '/admin' ? page.url === '/admin' : page.url.startsWith(href));
</script>

<template>
    <div class="min-h-screen bg-[#17231f] text-[#f3f2e7]">
        <!-- HEADER ADMIN -->
        <header class="sticky top-0 z-30 border-b border-[#0d685b]/30 bg-[#17231f]/95 backdrop-blur-md header-safe">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 lg:px-8">
                <!-- Brand & Desktop Nav -->
                <div class="flex items-center gap-5">
                    <Link href="/admin" class="flex items-center gap-2 group">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#0d685b] text-sm font-black text-[#f3f2e7] shadow-sm group-hover:scale-105 transition transform">
                            R
                        </span>
                        <div>
                            <span class="block text-base font-black tracking-tight text-[#f3f2e7]">{{ page.props.appName }}</span>
                            <span class="block text-[10px] font-bold tracking-wider uppercase text-amber-300">
                                {{ isSuperAdmin ? '👑 Super Admin' : '🛡️ Admin Panel' }}
                            </span>
                        </div>
                    </Link>

                    <!-- Desktop Nav Links -->
                    <nav class="hidden xl:flex xl:items-center xl:gap-1">
                        <Link
                            v-for="item in navItems"
                            :key="item.href"
                            :href="item.href"
                            class="rounded-xl px-2.5 py-1.5 text-xs font-semibold transition"
                            :class="active(item.href)
                                ? 'bg-[#0d685b] text-[#f3f2e7] shadow-sm'
                                : 'text-[#f3f2e7]/75 hover:bg-[#1c2a25] hover:text-[#f3f2e7]'"
                        >
                            {{ item.label }}
                        </Link>
                    </nav>
                </div>

                <!-- Right Actions: Switcher ke Mode Belanja, User info, Logout & Mobile Toggle -->
                <div class="flex items-center gap-1.5 sm:gap-3">
                    <!-- Tombol Cepat Beralih ke Mode Belanja (Dashboard Pengguna) - Desktop Only, di HP ada di bottom nav -->
                    <Link
                        href="/dashboard"
                        class="hidden sm:inline-flex items-center gap-1.5 rounded-xl border border-emerald-500/40 bg-emerald-500/20 px-3 py-1.5 text-xs font-bold text-emerald-300 hover:bg-emerald-500/30 transition shadow-xs active:scale-95"
                        title="Beralih ke Tampilan Belanja Pengguna"
                    >
                        <span>🛍️ Mode Belanja</span>
                    </Link>

                    <div class="flex items-center gap-1.5 sm:gap-2 pl-1.5 sm:pl-2 border-l border-[#0d685b]/30">
                        <div class="text-right">
                            <span class="block text-xs font-bold text-[#f3f2e7] max-w-[80px] sm:max-w-none truncate">@{{ user?.username || user?.name }}</span>
                            <span class="hidden sm:block text-[10px] uppercase font-semibold text-emerald-300/80">{{ user?.role }}</span>
                        </div>
                        <button
                            class="rounded-lg p-1.5 text-[#f3f2e7]/50 hover:bg-rose-500/20 hover:text-rose-400 transition"
                            title="Keluar"
                            @click="router.post('/logout')"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </button>
                    </div>

                    <!-- Mobile Drawer Menu Toggle -->
                    <button
                        type="button"
                        class="rounded-xl border border-[#0d685b]/40 bg-[#131d1a] p-2 text-[#f3f2e7] transition hover:bg-[#0d685b]/20 xl:hidden"
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        aria-label="Toggle menu"
                    >
                        <svg v-if="!mobileMenuOpen" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg v-else class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile Drawer Menu Dropdown -->
            <div v-show="mobileMenuOpen" class="border-t border-[#0d685b]/30 bg-[#121c19] px-4 py-4 xl:hidden shadow-2xl">
                <div class="mb-3 flex items-center justify-between border-b border-[#0d685b]/20 pb-3">
                    <div>
                        <p class="text-sm font-bold text-[#f3f2e7]">{{ user?.name || user?.username }}</p>
                        <p class="text-xs text-amber-300 capitalize">{{ user?.role }}</p>
                    </div>
                    <button
                        class="rounded-xl border border-rose-500/40 bg-rose-500/10 px-3 py-1.5 text-xs font-bold text-rose-300 hover:bg-rose-500/20"
                        @click="router.post('/logout')"
                    >
                        Keluar
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-1.5">
                    <Link
                        v-for="item in navItems"
                        :key="item.href"
                        :href="item.href"
                        class="flex items-center rounded-xl px-3 py-2 text-xs font-semibold transition"
                        :class="active(item.href)
                            ? 'bg-[#0d685b] text-[#f3f2e7]'
                            : 'bg-[#17231f] text-[#f3f2e7]/80 hover:bg-[#1c2a25] border border-[#0d685b]/20'"
                        @click="mobileMenuOpen = false"
                    >
                        {{ item.label }}
                    </Link>
                </div>

                <div class="mt-3 pt-3 border-t border-[#0d685b]/20">
                    <Link
                        href="/dashboard"
                        class="flex items-center justify-center gap-2 rounded-xl bg-emerald-600/30 border border-emerald-500/40 px-3 py-2.5 text-xs font-bold text-emerald-300"
                        @click="mobileMenuOpen = false"
                    >
                        <span>🛍️</span>
                        <span>Buka Mode Transaksi Pengguna</span>
                    </Link>
                </div>
            </div>
        </header>

        <!-- MAIN BODY -->
        <main class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6 content-bottom-safe xl:pb-10">
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

        <!-- MOBILE BOTTOM NAVIGATION BAR (ADMIN) -->
        <nav class="fixed bottom-0 inset-x-0 z-40 flex items-center justify-around border-t border-[#0d685b]/30 bg-[#121c19]/95 px-2 pt-2 bottom-nav-safe backdrop-blur-md xl:hidden shadow-lg">
            <Link
                href="/admin"
                class="flex flex-col items-center gap-0.5 px-2 py-1 text-xs font-medium transition"
                :class="page.url === '/admin' ? 'font-bold text-emerald-400' : 'text-[#f3f2e7]/60 hover:text-[#f3f2e7]'"
            >
                <span class="text-base leading-none">📊</span>
                <span class="text-[10px]">Dashboard</span>
            </Link>

            <Link
                href="/admin/pesanan"
                class="flex flex-col items-center gap-0.5 px-2 py-1 text-xs font-medium transition"
                :class="page.url.startsWith('/admin/pesanan') ? 'font-bold text-emerald-400' : 'text-[#f3f2e7]/60 hover:text-[#f3f2e7]'"
            >
                <span class="text-base leading-none">📦</span>
                <span class="text-[10px]">Pesanan</span>
            </Link>

            <Link
                href="/admin/produk"
                class="flex flex-col items-center gap-0.5 px-2 py-1 text-xs font-medium transition"
                :class="page.url.startsWith('/admin/produk') ? 'font-bold text-emerald-400' : 'text-[#f3f2e7]/60 hover:text-[#f3f2e7]'"
            >
                <span class="text-base leading-none">🏷️</span>
                <span class="text-[10px]">Produk</span>
            </Link>

            <Link
                href="/admin/users"
                class="flex flex-col items-center gap-0.5 px-2 py-1 text-xs font-medium transition"
                :class="page.url.startsWith('/admin/users') ? 'font-bold text-emerald-400' : 'text-[#f3f2e7]/60 hover:text-[#f3f2e7]'"
            >
                <span class="text-base leading-none">👥</span>
                <span class="text-[10px]">Pengguna</span>
            </Link>

            <!-- Mode Belanja Toggle -->
            <Link
                href="/dashboard"
                class="flex flex-col items-center gap-0.5 px-2 py-1 text-xs font-bold text-emerald-300 hover:text-emerald-200 transition"
            >
                <span class="text-base leading-none">🛍️</span>
                <span class="text-[10px]">Belanja</span>
            </Link>

            <!-- More / Menu Drawer Toggle -->
            <button
                type="button"
                class="flex flex-col items-center gap-0.5 px-2 py-1 text-xs font-medium text-[#f3f2e7]/60 hover:text-[#f3f2e7] transition"
                @click="mobileMenuOpen = !mobileMenuOpen"
            >
                <span class="text-base leading-none">☰</span>
                <span class="text-[10px]">Menu</span>
            </button>
        </nav>
    </div>
</template>

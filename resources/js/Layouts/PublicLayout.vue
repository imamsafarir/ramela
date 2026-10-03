<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const features = computed(() => page.props.features ?? { blog: true, faq: true });
</script>

<template>
    <div class="min-h-screen bg-[#17231f] text-[#f3f2e7]">
        <!-- HEADER / NAVIGATION -->
        <header class="sticky top-0 z-40 border-b border-[#0d685b]/30 bg-[#17231f]/95 backdrop-blur-md header-safe">
            <nav class="mx-auto flex max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center gap-8">
                    <Link href="/" class="text-2xl font-black tracking-tight text-[#f3f2e7]">
                        RAMELA<span class="text-emerald-400">.</span>
                    </Link>
                    <div class="hidden items-center gap-6 md:flex">
                        <Link href="/#layanan" class="text-sm font-medium text-[#f3f2e7]/80 hover:text-[#f3f2e7] transition">Layanan</Link>
                        <Link v-if="features.blog" href="/blog" class="text-sm font-medium text-[#f3f2e7]/80 hover:text-[#f3f2e7] transition">Blog</Link>
                        <Link v-if="features.faq" href="/faq" class="text-sm font-medium text-[#f3f2e7]/80 hover:text-[#f3f2e7] transition">FAQ</Link>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <template v-if="user">
                        <Link
                            href="/dashboard"
                            class="rounded-xl bg-[#0d685b] px-4 py-2 text-sm font-bold text-[#f3f2e7] shadow-sm hover:bg-[#117c6d] active:scale-95 transition"
                        >
                            Dashboard ({{ user.username }})
                        </Link>
                    </template>
                    <template v-else>
                        <Link
                            href="/login"
                            class="text-sm font-bold text-[#f3f2e7]/90 hover:text-[#f3f2e7] transition px-3 py-2"
                        >
                            Masuk
                        </Link>
                        <Link
                            href="/register"
                            class="rounded-xl bg-[#0d685b] px-4 py-2 text-sm font-bold text-[#f3f2e7] shadow-sm hover:bg-[#117c6d] active:scale-95 transition"
                        >
                            Daftar Sekarang
                        </Link>
                    </template>
                </div>
            </nav>
        </header>

        <slot />

        <!-- FOOTER -->
        <footer class="border-t border-[#0d685b]/30 bg-[#121c19] text-[#f3f2e7] py-12 content-bottom-safe md:pb-12">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-8 md:grid-cols-4">
                    <div class="md:col-span-2">
                        <span class="text-xl font-black tracking-tight text-[#f3f2e7]">RAMELA</span>
                        <p class="mt-2 max-w-sm text-sm text-[#f3f2e7]/70">
                            Platform ekosistem terpadu: Kuliner lezat RAMELA EATS, parsel istimewa RAMELA HAMPERS, dan konstruksi kokoh RAMELA BETON dengan satu aplikasi single sign-on.
                        </p>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-400">Navigasi</h4>
                        <ul class="mt-3 space-y-2 text-sm text-[#f3f2e7]/70">
                            <li><Link href="/#layanan" class="hover:text-white transition">Layanan RAMELA</Link></li>
                            <li v-if="features.blog"><Link href="/blog" class="hover:text-white transition">Blog & Artikel</Link></li>
                            <li v-if="features.faq"><Link href="/faq" class="hover:text-white transition">Tanya Jawab (FAQ)</Link></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-400">Akun & Layanan</h4>
                        <ul class="mt-3 space-y-2 text-sm text-[#f3f2e7]/70">
                            <li><Link href="/login" class="hover:text-white transition">Masuk Akun</Link></li>
                            <li><Link href="/register" class="hover:text-white transition">Pendaftaran Pengguna</Link></li>
                            <li><span class="text-xs text-[#f3f2e7]/40">Dukungan Midtrans & Realtime GPS</span></li>
                        </ul>
                    </div>
                </div>
                <div class="mt-8 border-t border-[#0d685b]/30 pt-6 text-center text-xs text-[#f3f2e7]/40">
                    &copy; {{ new Date().getFullYear() }} RAMELA. Hak Cipta Dilindungi.
                </div>
            </div>
        </footer>

        <!-- MOBILE BOTTOM NAVIGATION (PUBLIC) -->
        <nav class="fixed bottom-0 inset-x-0 z-40 flex items-center justify-around border-t border-[#0d685b]/30 bg-[#121c19]/95 px-2 pt-2 bottom-nav-safe backdrop-blur-md md:hidden shadow-lg">
            <Link
                href="/"
                class="flex flex-col items-center gap-0.5 px-3 py-1 text-xs font-medium transition"
                :class="page.url === '/' ? 'font-bold text-emerald-400' : 'text-[#f3f2e7]/60 hover:text-[#f3f2e7]'"
            >
                <span class="text-base leading-none">🏠</span>
                <span class="text-[10px]">Beranda</span>
            </Link>

            <a
                href="/#layanan"
                class="flex flex-col items-center gap-0.5 px-3 py-1 text-xs font-medium text-[#f3f2e7]/60 hover:text-[#f3f2e7] transition"
            >
                <span class="text-base leading-none">🛍️</span>
                <span class="text-[10px]">Layanan</span>
            </a>

            <Link
                v-if="features.blog"
                href="/blog"
                class="flex flex-col items-center gap-0.5 px-3 py-1 text-xs font-medium transition"
                :class="page.url.startsWith('/blog') ? 'font-bold text-emerald-400' : 'text-[#f3f2e7]/60 hover:text-[#f3f2e7]'"
            >
                <span class="text-base leading-none">📰</span>
                <span class="text-[10px]">Blog</span>
            </Link>

            <Link
                v-if="features.faq"
                href="/faq"
                class="flex flex-col items-center gap-0.5 px-3 py-1 text-xs font-medium transition"
                :class="page.url.startsWith('/faq') ? 'font-bold text-emerald-400' : 'text-[#f3f2e7]/60 hover:text-[#f3f2e7]'"
            >
                <span class="text-base leading-none">❓</span>
                <span class="text-[10px]">FAQ</span>
            </Link>

            <Link
                :href="user ? '/dashboard' : '/login'"
                class="flex flex-col items-center gap-0.5 px-3 py-1 text-xs font-bold transition"
                :class="user ? 'text-amber-300' : 'text-[#f3f2e7]'"
            >
                <span class="text-base leading-none">{{ user ? '👤' : '🔑' }}</span>
                <span class="text-[10px]">{{ user ? 'Akun' : 'Masuk' }}</span>
            </Link>
        </nav>
    </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '../Layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

defineProps({
    stores: Array,
    recentBlogs: Array,
    faqs: Array,
});
</script>

<template>
    <Head title="Platform Transaksi Terpadu: Ramela Eats, Hampers & Beton">
        <meta
            name="description"
            content="Platform transaksi melalui sistem single sign on, jelajahi Ramela Eats, Ramela Hampers, Ramela Beton, dengan satu aplikasi dilengkapi layanan dompet digital dan pelacakan kurir terpadu."
        />
    </Head>

    <div class="bg-[#17231f] text-[#f3f2e7]">
        <!-- HERO SECTION -->
        <section class="relative overflow-hidden py-20 md:py-28">
            <!-- Background Orbs Glow -->
            <div class="pointer-events-none absolute -left-20 top-1/4 h-80 w-80 rounded-full bg-[#0d685b]/25 blur-3xl"></div>
            <div class="pointer-events-none absolute -right-20 top-1/3 h-96 w-96 rounded-full bg-emerald-500/10 blur-3xl"></div>

            <div class="relative mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 text-center">
                <!-- Badge SSO -->
                <div class="inline-flex items-center gap-2 rounded-full border border-[#0d685b] bg-[#0d685b]/20 px-4 py-1.5 text-xs font-bold text-[#f3f2e7] shadow-sm backdrop-blur-xs">
                    <span class="flex h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Platform Transaksi Single Sign-On (SSO)</span>
                </div>

                <!-- Headline Utama Sesuai Permintaan -->
                <h1 class="mx-auto mt-6 max-w-4xl text-3xl font-black tracking-tight text-[#f3f2e7] sm:text-5xl md:text-6xl leading-tight">
                    Platform transaksi melalui sistem single sign on, jelajahi
                    <span class="text-emerald-400">Ramela Eats</span>,
                    <span class="text-amber-200">Ramela Hampers</span>,
                    <span class="text-teal-300">Ramela Beton</span>
                </h1>

                <!-- Deskripsi Pendukung -->
                <p class="mx-auto mt-6 max-w-2xl text-base sm:text-lg text-[#f3f2e7]/80 leading-relaxed">
                    Dengan satu aplikasi dilengkapi layanan dompet digital dan pelacakan kurir terpadu.
                </p>

                <!-- Label Layanan RAMELA -->
                <div class="mt-4 inline-block">
                    <p class="text-xs sm:text-sm font-extrabold tracking-widest uppercase text-emerald-300/90">
                        Layanan RAMELA: RAMELA EATS · RAMELA HAMPERS · RAMELA BETON
                    </p>
                </div>

                <!-- Tombol Aksi Utama (#0d685b dan #f3f2e7) -->
                <div class="mt-10 flex flex-wrap justify-center gap-4">
                    <a
                        href="#layanan"
                        class="inline-flex items-center gap-2 rounded-2xl bg-[#0d685b] px-7 py-3.5 text-sm font-black text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 hover:bg-[#117c6d] active:scale-95 transition"
                    >
                        <span>Jelajahi Layanan RAMELA</span>
                        <span>↓</span>
                    </a>
                    <Link
                        href="/register"
                        class="inline-flex items-center gap-2 rounded-2xl bg-[#f3f2e7] px-7 py-3.5 text-sm font-black text-[#17231f] shadow-lg hover:bg-white active:scale-95 transition"
                    >
                        <span>Mulai Transaksi Sekarang</span>
                        <span>→</span>
                    </Link>
                </div>
            </div>
        </section>

        <!-- 3 FITUR UTAMA DALAM SATU APLIKASI -->
        <section class="border-y border-[#0d685b]/30 bg-[#131d1a]/80 py-12">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-6 sm:grid-cols-3">
                    <div class="rounded-2xl border border-[#0d685b]/40 bg-[#1c2a25]/60 p-5 backdrop-blur-xs">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#0d685b] text-lg text-white">
                            🔐
                        </div>
                        <h3 class="mt-3 text-sm font-bold text-[#f3f2e7]">Single Sign-On (SSO)</h3>
                        <p class="mt-1 text-xs text-[#f3f2e7]/70 leading-relaxed">
                            Cukup satu akun untuk berbelanja kuliner, memesan bingkisan, maupun order material beton.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-[#0d685b]/40 bg-[#1c2a25]/60 p-5 backdrop-blur-xs">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#0d685b] text-lg text-white">
                            💳
                        </div>
                        <h3 class="mt-3 text-sm font-bold text-[#f3f2e7]">Dompet Digital Terpadu</h3>
                        <p class="mt-1 text-xs text-[#f3f2e7]/70 leading-relaxed">
                            Isi saldo instan lewat QRIS, Virtual Account, dan E-Wallet Midtrans untuk seluruh transaksi.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-[#0d685b]/40 bg-[#1c2a25]/60 p-5 backdrop-blur-xs">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#0d685b] text-lg text-white">
                            🛵
                        </div>
                        <h3 class="mt-3 text-sm font-bold text-[#f3f2e7]">Pelacakan Kurir Terpadu</h3>
                        <p class="mt-1 text-xs text-[#f3f2e7]/70 leading-relaxed">
                            Pantau rute jalan raya dan posisi kurir secara realtime dengan foto bukti validasi penerimaan.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECTION 3 LAYANAN UTAMA RAMELA -->
        <section id="layanan" class="py-20 md:py-28">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <span class="rounded-full border border-[#0d685b] bg-[#0d685b]/20 px-3.5 py-1 text-xs font-bold uppercase tracking-wider text-emerald-300">
                        Ekosistem Terintegrasi
                    </span>
                    <h2 class="mt-3 text-3xl font-black text-[#f3f2e7] sm:text-4xl">
                        Layanan RAMELA
                    </h2>
                    <p class="mt-2 text-sm sm:text-base font-bold text-emerald-400">
                        RAMELA EATS · RAMELA HAMPERS · RAMELA BETON
                    </p>
                    <p class="mx-auto mt-2 max-w-xl text-xs sm:text-sm text-[#f3f2e7]/70">
                        Pilih sektor toko yang Anda butuhkan untuk langsung mulai bertransaksi:
                    </p>
                </div>

                <div class="mt-12 grid gap-6 md:grid-cols-3">
                    <!-- 1. RAMELA EATS -->
                    <div class="group flex flex-col justify-between rounded-3xl border border-[#0d685b]/40 bg-[#1c2a25] p-7 shadow-xl hover:border-emerald-400/60 hover:-translate-y-1 transition duration-200">
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-orange-500/20 text-3xl group-hover:scale-110 transition">
                                    🍽️
                                </span>
                                <span class="rounded-full bg-orange-500/20 px-2.5 py-0.5 text-[10px] font-bold text-orange-300 uppercase tracking-wider">
                                    Kuliner
                                </span>
                            </div>
                            <h3 class="mt-5 text-xl font-black text-[#f3f2e7] group-hover:text-emerald-400 transition">
                                RAMELA EATS
                            </h3>
                            <p class="mt-1 text-xs font-semibold text-emerald-400">Kuliner & Santapan Lezat</p>
                            <p class="mt-3 text-xs sm:text-sm text-[#f3f2e7]/70 leading-relaxed">
                                Pilihan aneka kuliner lezat, makanan berat, dan hidangan siap santap nusantara yang higienis serta siap diantar cepat.
                            </p>
                        </div>

                        <div class="mt-8 pt-4 border-t border-[#0d685b]/30">
                            <Link
                                href="/toko/eats"
                                class="flex items-center justify-between rounded-xl bg-[#0d685b] px-4 py-3 text-xs sm:text-sm font-black text-[#f3f2e7] hover:bg-[#117c6d] active:scale-95 transition shadow-md"
                            >
                                <span>Mulai Transaksi EATS</span>
                                <span>→</span>
                            </Link>
                        </div>
                    </div>

                    <!-- 2. RAMELA HAMPERS -->
                    <div class="group flex flex-col justify-between rounded-3xl border border-[#0d685b]/40 bg-[#1c2a25] p-7 shadow-xl hover:border-pink-400/60 hover:-translate-y-1 transition duration-200">
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-pink-500/20 text-3xl group-hover:scale-110 transition">
                                    🎁
                                </span>
                                <span class="rounded-full bg-pink-500/20 px-2.5 py-0.5 text-[10px] font-bold text-pink-300 uppercase tracking-wider">
                                    Bingkisan
                                </span>
                            </div>
                            <h3 class="mt-5 text-xl font-black text-[#f3f2e7] group-hover:text-pink-300 transition">
                                RAMELA HAMPERS
                            </h3>
                            <p class="mt-1 text-xs font-semibold text-pink-300">Parsel & Hadiah Spesial</p>
                            <p class="mt-3 text-xs sm:text-sm text-[#f3f2e7]/70 leading-relaxed">
                                Paket bingkisan hari raya, gift set eksklusif, dan souvenir elegan untuk orang terkasih maupun relasi bisnis terpercaya.
                            </p>
                        </div>

                        <div class="mt-8 pt-4 border-t border-[#0d685b]/30">
                            <Link
                                href="/toko/hampers"
                                class="flex items-center justify-between rounded-xl bg-[#0d685b] px-4 py-3 text-xs sm:text-sm font-black text-[#f3f2e7] hover:bg-[#117c6d] active:scale-95 transition shadow-md"
                            >
                                <span>Mulai Transaksi HAMPERS</span>
                                <span>→</span>
                            </Link>
                        </div>
                    </div>

                    <!-- 3. RAMELA BETON -->
                    <div class="group flex flex-col justify-between rounded-3xl border border-[#0d685b]/40 bg-[#1c2a25] p-7 shadow-xl hover:border-cyan-400/60 hover:-translate-y-1 transition duration-200">
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-teal-500/20 text-3xl group-hover:scale-110 transition">
                                    🏗️
                                </span>
                                <span class="rounded-full bg-teal-500/20 px-2.5 py-0.5 text-[10px] font-bold text-teal-300 uppercase tracking-wider">
                                    Konstruksi
                                </span>
                            </div>
                            <h3 class="mt-5 text-xl font-black text-[#f3f2e7] group-hover:text-teal-300 transition">
                                RAMELA BETON
                            </h3>
                            <p class="mt-1 text-xs font-semibold text-teal-300">Readymix & Material Bangunan</p>
                            <p class="mt-3 text-xs sm:text-sm text-[#f3f2e7]/70 leading-relaxed">
                                Solusi suplai beton cor siap pakai, semen readymix, dan material konstruksi kokoh berstandar tinggi untuk proyek Anda.
                            </p>
                        </div>

                        <div class="mt-8 pt-4 border-t border-[#0d685b]/30">
                            <Link
                                href="/toko/beton"
                                class="flex items-center justify-between rounded-xl bg-[#0d685b] px-4 py-3 text-xs sm:text-sm font-black text-[#f3f2e7] hover:bg-[#117c6d] active:scale-95 transition shadow-md"
                            >
                                <span>Mulai Transaksi BETON</span>
                                <span>→</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- BANNER CALL TO ACTION BAWAH -->
        <section class="py-12">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="relative overflow-hidden rounded-3xl border border-[#0d685b]/50 bg-gradient-to-r from-[#0d685b] to-[#14231f] p-6 sm:p-12 text-center shadow-2xl">
                    <h2 class="text-2xl font-black text-[#f3f2e7] sm:text-3xl">
                        Mulai Belanja dengan Satu Akun Terpadu
                    </h2>
                    <p class="mx-auto mt-3 max-w-xl text-xs sm:text-sm text-[#f3f2e7]/80">
                        Nikmati kemudahan bertransaksi di Ramela Eats, Ramela Hampers, dan Ramela Beton dengan dompet digital serta pemantauan kurir langsung.
                    </p>
                    <div class="mt-6 flex flex-wrap justify-center gap-3">
                        <Link
                            href="/register"
                            class="rounded-xl bg-[#f3f2e7] px-6 py-3 text-xs sm:text-sm font-black text-[#17231f] shadow-md hover:bg-white active:scale-95 transition"
                        >
                            Daftar Akun Sekarang
                        </Link>
                        <Link
                            href="/login"
                            class="rounded-xl border border-[#f3f2e7]/30 bg-white/10 px-6 py-3 text-xs sm:text-sm font-bold text-[#f3f2e7] backdrop-blur-xs hover:bg-white/20 active:scale-95 transition"
                        >
                            Masuk Akun
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- BLOG PREVIEW -->
        <section v-if="recentBlogs?.length" class="border-t border-[#0d685b]/30 bg-[#131d1a]/60 py-16">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-black text-[#f3f2e7] sm:text-2xl">Kabar & Artikel Terbaru</h2>
                        <p class="mt-1 text-xs text-[#f3f2e7]/60">Inspirasi dan informasi seputar ekosistem RAMELA.</p>
                    </div>
                    <Link href="/blog" class="text-xs font-bold text-emerald-400 hover:underline">
                        Semua Artikel →
                    </Link>
                </div>

                <div class="mt-8 grid gap-6 md:grid-cols-3">
                    <article
                        v-for="b in recentBlogs"
                        :key="b.slug"
                        class="flex flex-col overflow-hidden rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-sm transition hover:border-emerald-400/50 hover:shadow-md"
                    >
                        <img v-if="b.thumbnail" :src="b.thumbnail" class="h-44 w-full object-cover" />
                        <div v-else class="flex h-44 w-full items-center justify-center bg-[#131d1a] text-xs text-[#f3f2e7]/40 font-bold">
                            RAMELA Blog
                        </div>
                        <div class="flex flex-1 flex-col justify-between p-5">
                            <div>
                                <span v-if="b.store" class="rounded bg-[#0d685b]/40 px-2 py-0.5 text-[10px] font-bold text-emerald-300 uppercase">
                                    {{ b.store }}
                                </span>
                                <h3 class="mt-2 text-sm font-bold text-[#f3f2e7] hover:text-emerald-400 transition">
                                    <Link :href="`/blog/${b.slug}`">{{ b.title }}</Link>
                                </h3>
                                <p class="mt-2 line-clamp-2 text-xs text-[#f3f2e7]/70">{{ b.excerpt }}</p>
                            </div>
                            <p class="mt-4 text-[10px] text-[#f3f2e7]/40">{{ b.published_at }}</p>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <!-- FAQ PREVIEW -->
        <section v-if="faqs?.length" class="py-16 md:py-20">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <h2 class="text-xl font-black text-[#f3f2e7] sm:text-2xl">Pertanyaan yang Sering Diajukan</h2>
                    <p class="mt-1 text-xs text-[#f3f2e7]/60">Temukan informasi seputar transaksi, dompet digital, dan pengiriman.</p>
                </div>

                <div class="mt-8 space-y-3">
                    <div
                        v-for="f in faqs"
                        :key="f.id"
                        class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-5 shadow-sm"
                    >
                        <h3 class="text-sm font-bold text-[#f3f2e7]">{{ f.question }}</h3>
                        <p class="mt-2 whitespace-pre-line text-xs text-[#f3f2e7]/75 leading-relaxed">{{ f.answer }}</p>
                    </div>
                </div>

                <div class="mt-8 text-center">
                    <Link
                        href="/faq"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-[#0d685b] bg-[#0d685b]/20 px-4 py-2 text-xs font-bold text-[#f3f2e7] hover:bg-[#0d685b] transition"
                    >
                        <span>Lihat Semua FAQ Lengkap</span>
                        <span>→</span>
                    </Link>
                </div>
            </div>
        </section>
    </div>
</template>

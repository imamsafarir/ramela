<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import PublicLayout from '../../Layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

defineProps({
    faqs: Array,
});

const openIndex = ref(null);

const toggle = (idx) => {
    openIndex.value = openIndex.value === idx ? null : idx;
};
</script>

<template>
    <Head title="Pusat Bantuan & FAQ">
        <meta name="description" content="Temukan jawaban atas pertanyaan umum terkait pemesanan, pembayaran Midtrans, saldo, dan pengantaran kurir di RAMELA." />
    </Head>

    <main class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-12">
        <div class="text-center">
            <h1 class="text-3xl font-black tracking-tight text-[#f3f2e7] sm:text-4xl">Pertanyaan yang Sering Diajukan</h1>
            <p class="mt-2 text-[#f3f2e7]/70">Punya pertanyaan seputar layanan RAMELA? Temukan jawabannya di bawah ini.</p>
        </div>

        <div v-if="faqs.length" class="mt-10 space-y-4">
            <div
                v-for="(f, idx) in faqs"
                :key="f.id"
                class="overflow-hidden rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] transition shadow-sm hover:border-emerald-400/40"
            >
                <button
                    class="flex w-full items-center justify-between p-5 text-left font-bold text-[#f3f2e7]"
                    @click="toggle(idx)"
                >
                    <span>{{ f.question }}</span>
                    <span class="text-lg text-emerald-400">{{ openIndex === idx ? '−' : '+' }}</span>
                </button>
                <div v-show="openIndex === idx || true" class="border-t border-[#0d685b]/20 bg-[#14221d] p-5 text-sm leading-relaxed text-[#f3f2e7]/80">
                    <p class="whitespace-pre-line">{{ f.answer }}</p>
                </div>
            </div>
        </div>

        <div v-else class="mt-12 rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] py-16 text-center text-sm text-[#f3f2e7]/60">
            Belum ada daftar FAQ yang dipublikasikan.
        </div>

        <div class="mt-16 rounded-3xl border border-[#0d685b]/50 bg-gradient-to-r from-[#0d685b] to-[#14231f] p-8 text-center shadow-xl">
            <h2 class="text-lg font-black text-[#f3f2e7]">Masih membutuhkan bantuan?</h2>
            <p class="mt-1 text-sm text-[#f3f2e7]/80">Tim kami siap membantu kebutuhan belanja kuliner, bingkisan, maupun proyek konstruksi Anda.</p>
            <Link href="/register" class="mt-5 inline-block rounded-xl bg-[#f3f2e7] px-6 py-2.5 text-sm font-black text-[#17231f] shadow-md hover:bg-white active:scale-95 transition">
                Hubungi Kami / Buat Akun
            </Link>
        </div>
    </main>
</template>

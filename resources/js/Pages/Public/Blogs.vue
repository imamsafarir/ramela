<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps({
    blogs: Object,
    stores: Array,
    currentStore: String,
});

const filterStore = (slug) => {
    router.get('/blog', slug ? { toko: slug } : {}, { preserveState: true });
};
</script>

<template>
    <Head title="Blog & Berita Edukasi">
        <meta name="description" content="Kumpulan artikel edukatif, info promo, dan kabar terbaru seputar RAMELA EATS, RAMELA HAMPERS, dan RAMELA BETON." />
    </Head>

    <main class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-12">
        <div class="text-center">
            <h1 class="text-3xl font-black tracking-tight text-[#f3f2e7] sm:text-4xl">Blog & Wawasan RAMELA</h1>
            <p class="mt-2 text-[#f3f2e7]/70">Berita promo, tips bermanfaat, dan edukasi seputar layanan kami.</p>

            <!-- Filter Kategori Toko -->
            <div class="mt-6 flex flex-wrap justify-center gap-2">
                <button
                    class="rounded-full px-4 py-1.5 text-xs font-bold transition shadow-xs"
                    :class="!currentStore ? 'bg-[#0d685b] text-[#f3f2e7]' : 'border border-[#0d685b]/40 bg-[#1c2a25] text-[#f3f2e7]/80 hover:bg-[#0d685b]/30'"
                    @click="filterStore('')"
                >
                    Semua Kategori
                </button>
                <button
                    v-for="s in stores"
                    :key="s.slug"
                    class="rounded-full px-4 py-1.5 text-xs font-bold transition shadow-xs"
                    :class="currentStore === s.slug ? 'bg-[#0d685b] text-[#f3f2e7]' : 'border border-[#0d685b]/40 bg-[#1c2a25] text-[#f3f2e7]/80 hover:bg-[#0d685b]/30'"
                    @click="filterStore(s.slug)"
                >
                    {{ s.name }}
                </button>
            </div>
        </div>

        <!-- Daftar Artikel Grid -->
        <div v-if="blogs.data?.length" class="mt-10 grid gap-8 md:grid-cols-3">
            <article v-for="b in blogs.data" :key="b.slug" class="flex flex-col overflow-hidden rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-sm transition hover:border-emerald-400/50 hover:shadow-md">
                <img v-if="b.thumbnail" :src="b.thumbnail" class="h-48 w-full object-cover" />
                <div v-else class="flex h-48 w-full items-center justify-center bg-[#131d1a] text-sm text-[#f3f2e7]/40 font-bold">
                    RAMELA
                </div>
                <div class="flex flex-1 flex-col justify-between p-6">
                    <div>
                        <span v-if="b.store" class="rounded bg-[#0d685b]/40 px-2 py-0.5 text-xs font-bold text-emerald-300 uppercase">{{ b.store }}</span>
                        <h2 class="mt-2 text-lg font-bold text-[#f3f2e7] hover:text-emerald-400 transition">
                            <Link :href="`/blog/${b.slug}`">{{ b.title }}</Link>
                        </h2>
                        <p class="mt-2 line-clamp-3 text-xs text-[#f3f2e7]/70">{{ b.excerpt }}</p>
                    </div>
                    <div class="mt-6 flex items-center justify-between border-t border-[#0d685b]/20 pt-3 text-xs text-[#f3f2e7]/40">
                        <span>Oleh {{ b.author }}</span>
                        <span>{{ b.published_at }}</span>
                    </div>
                </div>
            </article>
        </div>

        <div v-else class="mt-12 rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] py-16 text-center text-sm text-[#f3f2e7]/60">
            Belum ada artikel untuk kategori ini.
        </div>

        <!-- Pagination -->
        <nav v-if="blogs.last_page > 1" class="mt-8 flex justify-center gap-1">
            <template v-for="l in blogs.links" :key="l.label">
                <Link
                    v-if="l.url"
                    :href="l.url"
                    class="rounded-xl px-3 py-1.5 text-sm font-semibold transition"
                    :class="l.active ? 'bg-[#0d685b] text-[#f3f2e7]' : 'border border-[#0d685b]/30 bg-[#1c2a25] text-[#f3f2e7]/80 hover:bg-[#0d685b]/20'"
                    v-html="l.label"
                />
            </template>
        </nav>
    </main>
</template>

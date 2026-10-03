<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '../../Layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

defineProps({
    blog: Object,
});
</script>

<template>
    <Head :title="blog.meta_title || blog.title">
        <meta name="description" :content="blog.meta_description || blog.excerpt || blog.title" />
        <meta property="og:title" :content="blog.meta_title || blog.title" />
        <meta property="og:description" :content="blog.meta_description || blog.excerpt || blog.title" />
        <meta v-if="blog.thumbnail" property="og:image" :content="blog.thumbnail" />
    </Head>

    <main class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 py-12">
        <Link href="/blog" class="text-sm font-bold text-emerald-400 hover:underline">← Kembali ke Semua Artikel</Link>

        <header class="mt-4">
            <span v-if="blog.store" class="rounded bg-[#0d685b]/40 px-2.5 py-1 text-xs font-bold text-emerald-300 uppercase">{{ blog.store }}</span>
            <h1 class="mt-3 text-3xl font-black tracking-tight text-[#f3f2e7] sm:text-4xl">{{ blog.title }}</h1>
            <div class="mt-4 flex items-center gap-3 text-xs text-[#f3f2e7]/60">
                <span>Ditulis oleh <strong class="text-[#f3f2e7]">{{ blog.author }}</strong></span>
                <span>•</span>
                <span>{{ blog.published_at }}</span>
            </div>
        </header>

        <img v-if="blog.thumbnail" :src="blog.thumbnail" class="mt-8 h-80 w-full rounded-2xl object-cover shadow-sm border border-[#0d685b]/30" />

        <article class="mt-8 max-w-none text-base leading-relaxed text-[#f3f2e7]/85">
            <div class="whitespace-pre-line leading-relaxed">{{ blog.content }}</div>
        </article>

        <div class="mt-12 border-t border-[#0d685b]/30 pt-6">
            <Link href="/blog" class="rounded-xl bg-[#0d685b] px-5 py-2.5 text-sm font-bold text-[#f3f2e7] shadow-sm hover:bg-[#117c6d] active:scale-95 transition">
                ← Kembali ke Blog
            </Link>
        </div>
    </main>
</template>

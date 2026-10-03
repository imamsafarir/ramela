<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import { ref } from "vue";
import UserLayout from "../../Layouts/UserLayout.vue";
import { rupiah } from "../../utils/format";

defineOptions({ layout: UserLayout });

const props = defineProps({
    store: Object,
    categories: Array,
    products: Object,
    filters: Object,
});

const q = ref(props.filters.q ?? "");
const category = ref(props.filters.category ?? "");

const search = () =>
    router.get(
        `/toko/${props.store.slug}`,
        { q: q.value || undefined, category: category.value || undefined },
        { preserveState: true, replace: true },
    );

const add = (product) =>
    router.post(
        "/keranjang",
        { product_id: product.id, quantity: 1 },
        { preserveScroll: true },
    );
</script>

<template>
    <Head :title="store.name" />

    <div class="mb-4">
        <Link href="/dashboard" class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#f3f2e7]/70 hover:text-[#f3f2e7] transition">
            <span>←</span>
            <span>Kembali ke Dashboard</span>
        </Link>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-[#f3f2e7] tracking-tight">{{ store.name }}</h1>
            <p class="text-sm text-emerald-100/70 mt-0.5">{{ store.tagline }}</p>
        </div>
        <Link
            href="/keranjang"
            class="inline-flex items-center gap-2 rounded-xl bg-[#0d685b] px-4 py-2.5 text-xs font-bold text-[#f3f2e7] hover:bg-[#117c6d] transition shadow-md shadow-[#0d685b]/30"
        >
            <span>🛒</span>
            <span>Lihat Keranjang</span>
        </Link>
    </div>

    <form class="mt-6 flex flex-wrap gap-2.5" @submit.prevent="search">
        <input
            v-model="q"
            placeholder="Cari produk..."
            class="w-full sm:w-auto flex-1 rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-4 py-2.5 text-sm text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:border-emerald-400 focus:outline-none focus:ring-1 focus:ring-emerald-400"
        />
        <select
            v-model="category"
            class="w-full sm:w-auto rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-4 py-2.5 text-sm text-[#f3f2e7] focus:border-emerald-400 focus:outline-none focus:ring-1 focus:ring-emerald-400"
            @change="search"
        >
            <option value="" class="bg-[#131d1a] text-[#f3f2e7]">Semua kategori</option>
            <option v-for="c in categories" :key="c.id" :value="c.id" class="bg-[#131d1a] text-[#f3f2e7]">
                {{ c.name }}
            </option>
        </select>
        <button class="w-full sm:w-auto rounded-xl bg-[#0d685b] hover:bg-[#117c6d] px-5 py-2.5 text-sm font-bold text-[#f3f2e7] transition shadow-md shadow-[#0d685b]/20">
            Cari
        </button>
    </form>

    <div v-if="!products.data.length" class="mt-8 rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-8 text-center text-sm text-[#f3f2e7]/60 shadow-xl">
        <p class="text-3xl mb-2">📦</p>
        Belum ada produk untuk kategori atau kata kunci ini.
    </div>

    <div v-else class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div
            v-for="p in products.data"
            :key="p.id"
            class="flex flex-col justify-between rounded-2xl bg-[#1c2a25] p-4 border border-[#0d685b]/30 shadow-xl hover:border-[#0d685b]/60 transition"
        >
            <div>
                <img
                    v-if="p.image"
                    :src="p.image"
                    :alt="p.name"
                    class="mb-3 h-40 w-full rounded-xl object-cover border border-[#0d685b]/20"
                />
                <div
                    v-else
                    class="mb-3 flex h-40 items-center justify-center rounded-xl bg-[#131d1a] border border-[#0d685b]/20 text-sm text-[#f3f2e7]/40"
                >
                    Tanpa foto
                </div>
                <p class="font-bold text-[#f3f2e7] text-base">{{ p.name }}</p>
                <p class="mt-1 line-clamp-2 text-xs text-[#f3f2e7]/70 leading-relaxed">
                    {{ p.description }}
                </p>
            </div>
            <div class="mt-4 pt-3 border-t border-[#0d685b]/20">
                <div class="flex items-baseline justify-between">
                    <p class="text-base font-black text-emerald-400">
                        {{ rupiah(p.price) }}
                        <span v-if="p.unit" class="text-xs font-normal text-[#f3f2e7]/60">
                            / {{ p.unit }}
                        </span>
                    </p>
                    <span class="text-xs font-medium text-[#f3f2e7]/60">Stok: {{ p.stock }}</span>
                </div>
                <button
                    :disabled="p.stock < 1"
                    class="mt-3 w-full rounded-xl bg-[#0d685b] hover:bg-[#117c6d] py-2.5 text-xs font-bold text-[#f3f2e7] shadow-md shadow-[#0d685b]/20 transition disabled:bg-slate-800 disabled:text-slate-500 disabled:cursor-not-allowed"
                    @click="add(p)"
                >
                    {{ p.stock < 1 ? "Habis" : "+ Tambah ke Keranjang" }}
                </button>
            </div>
        </div>
    </div>

    <nav v-if="products.last_page > 1" class="mt-6 flex flex-wrap gap-1.5">
        <template v-for="l in products.links" :key="l.label">
            <Link
                v-if="l.url"
                :href="l.url"
                preserve-scroll
                class="rounded-lg px-3.5 py-1.5 text-xs font-semibold border transition"
                :class="l.active ? 'bg-[#0d685b] border-[#0d685b] text-[#f3f2e7]' : 'bg-[#131d1a] border-[#0d685b]/30 text-[#f3f2e7]/80 hover:bg-[#0d685b]/20 hover:text-[#f3f2e7]'"
                v-html="l.label"
            />
        </template>
    </nav>
</template>

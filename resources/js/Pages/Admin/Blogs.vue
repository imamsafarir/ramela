<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    blogs: { type: Array, default: () => [] },
    faqs: { type: Array, default: () => [] },
    stores: { type: Array, default: () => [] },
    stats: {
        type: Object,
        default: () => ({
            total_blogs: 0,
            published_blogs: 0,
            draft_blogs: 0,
            total_faqs: 0,
            active_faqs: 0,
            inactive_faqs: 0,
        }),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

// Active Tab ('blogs' | 'faqs')
const currentTab = ref(props.filters.tab || 'blogs');

// Blog Filters & Sorting
const blogQ = ref(props.filters.q ?? '');
const blogStore = ref(props.filters.store ?? '');
const blogStatus = ref(props.filters.status ?? '');
const blogSort = ref(props.filters.sort ?? 'created_at');
const blogDir = ref(props.filters.dir ?? 'desc');

// FAQ Filters & Sorting
const faqQ = ref(props.filters.faq_q ?? '');
const faqStatus = ref(props.filters.faq_status ?? '');
const faqSort = ref(props.filters.faq_sort ?? 'sort_order');
const faqDir = ref(props.filters.faq_dir ?? 'asc');

// Modal & Form Toggles
const showBlogModal = ref(false);
const showFaqModal = ref(false);

// Blog Form
const blogForm = useForm({
    title: '',
    store_id: '',
    excerpt: '',
    content: '',
    meta_title: '',
    meta_description: '',
    thumbnail: null,
    is_published: true,
});

const submitBlog = () => {
    blogForm.post('/admin/blog', {
        preserveScroll: true,
        onSuccess: () => {
            blogForm.reset();
            showBlogModal.value = false;
        },
    });
};

const toggleBlog = (b) => router.patch(`/admin/blog/${b.id}/toggle`, {}, { preserveScroll: true });
const removeBlog = (b) => {
    if (confirm(`Hapus artikel "${b.title}"?`)) {
        router.delete(`/admin/blog/${b.id}`, { preserveScroll: true });
    }
};

// FAQ Form
const faqForm = useForm({
    question: '',
    answer: '',
    sort_order: 0,
    is_active: true,
});

const submitFaq = () => {
    faqForm.post('/admin/faq', {
        preserveScroll: true,
        onSuccess: () => {
            faqForm.reset({ question: '', answer: '', sort_order: 0, is_active: true });
            showFaqModal.value = false;
        },
    });
};

const toggleFaq = (f) => router.patch(`/admin/faq/${f.id}/toggle`, {}, { preserveScroll: true });
const removeFaq = (f) => {
    if (confirm(`Hapus FAQ "${f.question}"?`)) {
        router.delete(`/admin/faq/${f.id}`, { preserveScroll: true });
    }
};

// Navigation & Tab Switching
const switchTab = (tab) => {
    currentTab.value = tab;
    applyAllFilters();
};

const applyAllFilters = () => {
    router.get(
        '/admin/blog',
        {
            tab: currentTab.value,
            q: blogQ.value || undefined,
            store: blogStore.value || undefined,
            status: blogStatus.value || undefined,
            sort: blogSort.value !== 'created_at' ? blogSort.value : undefined,
            dir: blogDir.value !== 'desc' ? blogDir.value : undefined,
            faq_q: faqQ.value || undefined,
            faq_status: faqStatus.value || undefined,
            faq_sort: faqSort.value !== 'sort_order' ? faqSort.value : undefined,
            faq_dir: faqDir.value !== 'asc' ? faqDir.value : undefined,
        },
        { preserveState: true, replace: true }
    );
};

const resetBlogFilters = () => {
    blogQ.value = '';
    blogStore.value = '';
    blogStatus.value = '';
    blogSort.value = 'created_at';
    blogDir.value = 'desc';
    applyAllFilters();
};

const resetFaqFilters = () => {
    faqQ.value = '';
    faqStatus.value = '';
    faqSort.value = 'sort_order';
    faqDir.value = 'asc';
    applyAllFilters();
};

const sortBlogBy = (column) => {
    if (blogSort.value === column) {
        blogDir.value = blogDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        blogSort.value = column;
        blogDir.value = column === 'title' ? 'asc' : 'desc';
    }
    applyAllFilters();
};

const sortFaqBy = (column) => {
    if (faqSort.value === column) {
        faqDir.value = faqDir.value === 'asc' ? 'desc' : 'asc';
    } else {
        faqSort.value = column;
        faqDir.value = 'asc';
    }
    applyAllFilters();
};

const filterByQuickStat = (targetTab, filterKey, filterVal) => {
    currentTab.value = targetTab;
    if (targetTab === 'blogs') {
        if (filterKey === 'all') {
            resetBlogFilters();
            return;
        }
        if (filterKey === 'status') blogStatus.value = filterVal;
    } else if (targetTab === 'faqs') {
        if (filterKey === 'all') {
            resetFaqFilters();
            return;
        }
        if (filterKey === 'faq_status') faqStatus.value = filterVal;
    }
    applyAllFilters();
};

const hasActiveBlogFilters = computed(() => {
    return Boolean(blogQ.value || blogStore.value || blogStatus.value || blogSort.value !== 'created_at');
});

const hasActiveFaqFilters = computed(() => {
    return Boolean(faqQ.value || faqStatus.value || faqSort.value !== 'sort_order' || faqDir.value !== 'asc');
});

const inputClass = 'w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2.5 text-sm text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:outline-none focus:border-[#0d685b]';
</script>

<template>
    <Head title="Manajemen Konten (Blog & FAQ)" />

    <!-- Page Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-2xl">📝</span>
                <h1 class="text-2xl font-black text-[#f3f2e7] tracking-tight">Manajemen Konten & FAQ</h1>
            </div>
            <p class="mt-1 text-xs text-[#f3f2e7]/70">
                Pusat penulisan artikel berita, edukasi layanan 3 pilar toko, serta pusat tanya jawab umum (FAQ) bagi pelanggan.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <button
                type="button"
                @click="showFaqModal = true"
                class="inline-flex items-center gap-1.5 rounded-xl border border-[#0d685b] bg-[#1c2a25] px-4 py-2.5 text-xs font-bold text-[#f3f2e7] shadow-sm hover:bg-[#131d1a] hover:border-emerald-400/50 transition cursor-pointer"
            >
                <span>❓</span> + Tambah FAQ Baru
            </button>
            <button
                type="button"
                @click="showBlogModal = true"
                class="inline-flex items-center gap-1.5 rounded-xl bg-[#0d685b] px-4 py-2.5 text-xs font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 hover:bg-[#0d685b]/90 transition cursor-pointer"
            >
                <span>✍️</span> + Tulis Artikel Baru
            </button>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <button
            type="button"
            @click="filterByQuickStat('blogs', 'all')"
            class="text-left rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-3.5 shadow-md hover:border-[#0d685b] transition group cursor-pointer"
        >
            <div class="text-[11px] font-medium text-[#f3f2e7]/60">Total Artikel Blog</div>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="text-2xl font-black text-[#f3f2e7] group-hover:text-emerald-400 transition">{{ stats.total_blogs }}</span>
                <span class="text-xs text-[#f3f2e7]/40">Artikel</span>
            </div>
        </button>

        <button
            type="button"
            @click="filterByQuickStat('blogs', 'status', 'published')"
            class="text-left rounded-2xl border border-emerald-500/30 bg-[#1c2a25] p-3.5 shadow-md hover:border-emerald-400 transition group cursor-pointer"
        >
            <div class="text-[11px] font-medium text-emerald-300/80">Artikel Publik</div>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="text-2xl font-black text-emerald-400 group-hover:scale-105 transition">{{ stats.published_blogs }}</span>
                <span class="text-xs text-emerald-400/60">Tayang</span>
            </div>
        </button>

        <button
            type="button"
            @click="filterByQuickStat('faqs', 'all')"
            class="text-left rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-3.5 shadow-md hover:border-[#0d685b] transition group cursor-pointer"
        >
            <div class="text-[11px] font-medium text-[#f3f2e7]/60">Total FAQ</div>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="text-2xl font-black text-[#f3f2e7] group-hover:text-emerald-400 transition">{{ stats.total_faqs }}</span>
                <span class="text-xs text-[#f3f2e7]/40">Pertanyaan</span>
            </div>
        </button>

        <button
            type="button"
            @click="filterByQuickStat('faqs', 'faq_status', 'active')"
            class="text-left rounded-2xl border border-teal-500/30 bg-[#1c2a25] p-3.5 shadow-md hover:border-teal-400 transition group cursor-pointer"
        >
            <div class="text-[11px] font-medium text-teal-300/80">FAQ Aktif</div>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="text-2xl font-black text-teal-400 group-hover:scale-105 transition">{{ stats.active_faqs }}</span>
                <span class="text-xs text-teal-400/60">Ditampilkan</span>
            </div>
        </button>
    </div>

    <!-- Tab Buttons -->
    <div class="mt-6 flex border-b border-[#0d685b]/30">
        <button
            type="button"
            @click="switchTab('blogs')"
            class="inline-flex items-center gap-2 border-b-2 px-5 py-3 text-sm font-bold transition cursor-pointer"
            :class="currentTab === 'blogs' ? 'border-emerald-400 text-emerald-300 bg-[#131d1a]/60 rounded-t-xl' : 'border-transparent text-[#f3f2e7]/60 hover:text-[#f3f2e7]'"
        >
            <span>📰</span> Artikel & Berita Blog
            <span class="rounded-full bg-[#131d1a] px-2 py-0.5 text-xs text-[#f3f2e7]/70 border border-[#0d685b]/30">
                {{ stats.total_blogs }}
            </span>
        </button>
        <button
            type="button"
            @click="switchTab('faqs')"
            class="inline-flex items-center gap-2 border-b-2 px-5 py-3 text-sm font-bold transition cursor-pointer"
            :class="currentTab === 'faqs' ? 'border-emerald-400 text-emerald-300 bg-[#131d1a]/60 rounded-t-xl' : 'border-transparent text-[#f3f2e7]/60 hover:text-[#f3f2e7]'"
        >
            <span>❓</span> Tanya Jawab (FAQ)
            <span class="rounded-full bg-[#131d1a] px-2 py-0.5 text-xs text-[#f3f2e7]/70 border border-[#0d685b]/30">
                {{ stats.total_faqs }}
            </span>
        </button>
    </div>

    <!-- TAB 1: ARTIKEL & BERITA BLOG -->
    <div v-if="currentTab === 'blogs'" class="mt-4 space-y-4">
        <!-- Filter & Sorting Bar for Blogs -->
        <div class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 shadow-lg">
            <form class="space-y-3" @submit.prevent="applyAllFilters">
                <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-5">
                    <!-- Search Input -->
                    <div class="sm:col-span-2 lg:col-span-2 relative">
                        <input
                            v-model="blogQ"
                            type="text"
                            placeholder="Cari judul, ringkasan, atau isi artikel..."
                            class="w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2 text-sm text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:border-[#0d685b] focus:outline-none"
                        />
                        <button
                            v-if="blogQ"
                            type="button"
                            @click="blogQ = ''; applyAllFilters();"
                            class="absolute right-3 top-2.5 text-xs text-[#f3f2e7]/50 hover:text-[#f3f2e7]"
                        >
                            ✕
                        </button>
                    </div>

                    <!-- Store Filter -->
                    <div>
                        <select
                            v-model="blogStore"
                            @change="applyAllFilters"
                            class="w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3 py-2 text-sm text-[#f3f2e7] focus:border-[#0d685b] focus:outline-none"
                        >
                            <option value="">Semua Pilar Toko</option>
                            <option value="general">Umum / Tanpa Toko</option>
                            <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <select
                            v-model="blogStatus"
                            @change="applyAllFilters"
                            class="w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3 py-2 text-sm text-[#f3f2e7] focus:border-[#0d685b] focus:outline-none"
                        >
                            <option value="">Semua Status</option>
                            <option value="published">Publik (Tayang)</option>
                            <option value="draft">Draft (Tertutup)</option>
                        </select>
                    </div>

                    <!-- Sort By -->
                    <div>
                        <select
                            v-model="blogSort"
                            @change="applyAllFilters"
                            class="w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3 py-2 text-sm text-[#f3f2e7] focus:border-[#0d685b] focus:outline-none"
                        >
                            <option value="created_at">Waktu Dibuat</option>
                            <option value="title">Judul Artikel</option>
                            <option value="published_at">Tanggal Terbit</option>
                            <option value="status">Status Publikasi</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2.5 pt-1 border-t border-[#0d685b]/20">
                    <div class="flex items-center gap-2">
                        <!-- Direction Toggle -->
                        <button
                            type="button"
                            @click="blogDir = blogDir === 'asc' ? 'desc' : 'asc'; applyAllFilters();"
                            class="inline-flex items-center gap-1 rounded-lg border border-[#0d685b]/40 bg-[#131d1a] px-2.5 py-1.5 text-xs font-semibold text-[#f3f2e7] hover:border-emerald-400 transition"
                        >
                            <span>{{ blogDir === 'asc' ? '▲ Urut Naik (A-Z)' : '▼ Urut Turun (Z-A)' }}</span>
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            v-if="hasActiveBlogFilters"
                            type="button"
                            @click="resetBlogFilters"
                            class="rounded-xl border border-rose-500/30 bg-rose-950/20 px-3 py-1.5 text-xs font-semibold text-rose-300 hover:bg-rose-900/30 transition cursor-pointer"
                        >
                            Reset Filter
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-[#0d685b] px-4 py-1.5 text-xs font-bold text-[#f3f2e7] shadow-sm hover:bg-[#0d685b]/90 transition"
                        >
                            🔍 Cari Artikel
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Blogs Table -->
        <div class="overflow-x-auto rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-lg">
            <table class="w-full text-left text-sm text-[#f3f2e7]">
                <thead class="border-b border-[#0d685b]/30 bg-[#131d1a] text-xs font-bold uppercase tracking-wider text-[#f3f2e7]/70">
                    <tr>
                        <th class="w-12 px-3 py-3.5 text-center">#</th>
                        <th class="px-4 py-3.5 cursor-pointer select-none hover:text-white" @click="sortBlogBy('title')">
                            <div class="flex items-center gap-1">
                                <span>Artikel</span>
                                <span class="text-[10px]">{{ blogSort === 'title' ? (blogDir === 'asc' ? '▲' : '▼') : '↕' }}</span>
                            </div>
                        </th>
                        <th class="px-4 py-3.5">Pilar Layanan</th>
                        <th class="px-4 py-3.5">Penulis</th>
                        <th class="px-4 py-3.5 cursor-pointer select-none hover:text-white" @click="sortBlogBy('status')">
                            <div class="flex items-center gap-1">
                                <span>Status</span>
                                <span class="text-[10px]">{{ blogSort === 'status' ? (blogDir === 'asc' ? '▲' : '▼') : '↕' }}</span>
                            </div>
                        </th>
                        <th class="px-4 py-3.5 cursor-pointer select-none hover:text-white" @click="sortBlogBy('published_at')">
                            <div class="flex items-center gap-1">
                                <span>Tanggal Terbit</span>
                                <span class="text-[10px]">{{ blogSort === 'published_at' ? (blogDir === 'asc' ? '▲' : '▼') : '↕' }}</span>
                            </div>
                        </th>
                        <th class="px-4 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#0d685b]/20">
                    <tr v-for="(b, idx) in blogs" :key="b.id" class="hover:bg-[#131d1a]/50 transition">
                        <td class="px-3 py-3.5 text-center font-bold text-xs text-[#f3f2e7]/50">{{ idx + 1 }}</td>
                        <td class="px-4 py-3.5">
                            <div class="flex items-center gap-3">
                                <img v-if="b.thumbnail" :src="b.thumbnail" class="h-10 w-12 rounded-lg object-cover border border-[#0d685b]/30 shrink-0" />
                                <div v-else class="flex h-10 w-12 items-center justify-center rounded-lg bg-[#131d1a] border border-[#0d685b]/20 text-xs text-[#f3f2e7]/40 shrink-0">No Img</div>
                                <div class="min-w-0">
                                    <p class="font-bold text-[#f3f2e7] truncate max-w-sm">{{ b.title }}</p>
                                    <span class="text-xs text-[#f3f2e7]/50">/blog/{{ b.slug }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="rounded-full bg-[#131d1a] border border-[#0d685b]/30 px-2.5 py-0.5 text-xs text-[#f3f2e7]/80 whitespace-nowrap">
                                🏬 {{ b.store }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-xs text-[#f3f2e7]/70 whitespace-nowrap">👤 {{ b.author }}</td>
                        <td class="px-4 py-3.5 whitespace-nowrap">
                            <span
                                class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="b.is_published ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-amber-500/20 text-amber-300 border border-amber-500/40'"
                            >
                                {{ b.is_published ? 'Publik' : 'Draft' }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-xs text-[#f3f2e7]/60 whitespace-nowrap">{{ b.published_at ?? '-' }}</td>
                        <td class="space-x-3 px-4 py-3.5 text-right text-xs whitespace-nowrap">
                            <a v-if="b.is_published" :href="`/blog/${b.slug}`" target="_blank" class="font-bold text-emerald-400 hover:text-emerald-300 underline">
                                Lihat
                            </a>
                            <button
                                type="button"
                                class="font-bold text-[#f3f2e7]/80 hover:text-[#f3f2e7] underline cursor-pointer"
                                @click="toggleBlog(b)"
                            >
                                {{ b.is_published ? 'Jadikan Draft' : 'Publikasikan' }}
                            </button>
                            <button
                                type="button"
                                class="font-bold text-rose-400 hover:text-rose-300 underline cursor-pointer"
                                @click="removeBlog(b)"
                            >
                                Hapus
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!blogs.length">
                        <td colspan="7" class="px-4 py-12 text-center text-sm text-[#f3f2e7]/60">
                            <div class="space-y-2">
                                <div class="text-2xl">🍃</div>
                                <div>Tidak ada artikel blog yang sesuai dengan filter atau kata kunci.</div>
                                <button
                                    v-if="hasActiveBlogFilters"
                                    type="button"
                                    @click="resetBlogFilters"
                                    class="text-xs text-emerald-400 hover:underline font-bold"
                                >
                                    Bersihkan Filter Pencarian
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 2: TANYA JAWAB (FAQ) -->
    <div v-else-if="currentTab === 'faqs'" class="mt-4 space-y-4">
        <!-- Filter & Sorting Bar for FAQs -->
        <div class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 shadow-lg">
            <form class="space-y-3" @submit.prevent="applyAllFilters">
                <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 md:grid-cols-4">
                    <!-- Search Input -->
                    <div class="sm:col-span-2 relative">
                        <input
                            v-model="faqQ"
                            type="text"
                            placeholder="Cari pertanyaan atau jawaban..."
                            class="w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2 text-sm text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:border-[#0d685b] focus:outline-none"
                        />
                        <button
                            v-if="faqQ"
                            type="button"
                            @click="faqQ = ''; applyAllFilters();"
                            class="absolute right-3 top-2.5 text-xs text-[#f3f2e7]/50 hover:text-[#f3f2e7]"
                        >
                            ✕
                        </button>
                    </div>

                    <!-- Status Filter -->
                    <div>
                        <select
                            v-model="faqStatus"
                            @change="applyAllFilters"
                            class="w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3 py-2 text-sm text-[#f3f2e7] focus:border-[#0d685b] focus:outline-none"
                        >
                            <option value="">Semua Status FAQ</option>
                            <option value="active">Hanya Aktif</option>
                            <option value="inactive">Hanya Nonaktif</option>
                        </select>
                    </div>

                    <!-- Sort By -->
                    <div>
                        <select
                            v-model="faqSort"
                            @change="applyAllFilters"
                            class="w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3 py-2 text-sm text-[#f3f2e7] focus:border-[#0d685b] focus:outline-none"
                        >
                            <option value="sort_order">Urutan Tampil (Sort Order)</option>
                            <option value="question">Pertanyaan (A-Z)</option>
                            <option value="status">Status Aktif</option>
                            <option value="created_at">Waktu Dibuat</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2.5 pt-1 border-t border-[#0d685b]/20">
                    <div class="flex items-center gap-2">
                        <!-- Direction Toggle -->
                        <button
                            type="button"
                            @click="faqDir = faqDir === 'asc' ? 'desc' : 'asc'; applyAllFilters();"
                            class="inline-flex items-center gap-1 rounded-lg border border-[#0d685b]/40 bg-[#131d1a] px-2.5 py-1.5 text-xs font-semibold text-[#f3f2e7] hover:border-emerald-400 transition"
                        >
                            <span>{{ faqDir === 'asc' ? '▲ Urut Naik (Asc)' : '▼ Urut Turun (Desc)' }}</span>
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            v-if="hasActiveFaqFilters"
                            type="button"
                            @click="resetFaqFilters"
                            class="rounded-xl border border-rose-500/30 bg-rose-950/20 px-3 py-1.5 text-xs font-semibold text-rose-300 hover:bg-rose-900/30 transition cursor-pointer"
                        >
                            Reset Filter
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-[#0d685b] px-4 py-1.5 text-xs font-bold text-[#f3f2e7] shadow-sm hover:bg-[#0d685b]/90 transition"
                        >
                            🔍 Cari FAQ
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- FAQs Table -->
        <div class="overflow-x-auto rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-lg">
            <table class="w-full text-left text-sm text-[#f3f2e7]">
                <thead class="border-b border-[#0d685b]/30 bg-[#131d1a] text-xs font-bold uppercase tracking-wider text-[#f3f2e7]/70">
                    <tr>
                        <th class="w-12 px-3 py-3.5 text-center">#</th>
                        <th class="w-20 px-4 py-3.5 text-center cursor-pointer select-none hover:text-white" @click="sortFaqBy('sort_order')">
                            <div class="flex items-center justify-center gap-1">
                                <span>Urutan</span>
                                <span class="text-[10px]">{{ faqSort === 'sort_order' ? (faqDir === 'asc' ? '▲' : '▼') : '↕' }}</span>
                            </div>
                        </th>
                        <th class="py-3.5 cursor-pointer select-none hover:text-white" @click="sortFaqBy('question')">
                            <div class="flex items-center gap-1">
                                <span>Pertanyaan & Jawaban</span>
                                <span class="text-[10px]">{{ faqSort === 'question' ? (faqDir === 'asc' ? '▲' : '▼') : '↕' }}</span>
                            </div>
                        </th>
                        <th class="w-28 py-3.5 cursor-pointer select-none hover:text-white" @click="sortFaqBy('status')">
                            <div class="flex items-center gap-1">
                                <span>Status</span>
                                <span class="text-[10px]">{{ faqSort === 'status' ? (faqDir === 'asc' ? '▲' : '▼') : '↕' }}</span>
                            </div>
                        </th>
                        <th class="w-36 px-4 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#0d685b]/20">
                    <tr v-for="(f, idx) in faqs" :key="f.id" class="hover:bg-[#131d1a]/50 transition">
                        <td class="px-3 py-3.5 text-center font-bold text-xs text-[#f3f2e7]/50">{{ idx + 1 }}</td>
                        <td class="px-4 py-3.5 text-center">
                            <span class="rounded-lg bg-[#131d1a] border border-[#0d685b]/30 px-2 py-1 text-xs font-bold text-[#f3f2e7]">
                                {{ f.sort_order }}
                            </span>
                        </td>
                        <td class="py-3.5 pr-4">
                            <p class="font-bold text-[#f3f2e7] flex items-center gap-2">
                                <span class="text-emerald-400">Q:</span> {{ f.question }}
                            </p>
                            <p class="mt-1 whitespace-pre-line text-xs text-[#f3f2e7]/70 leading-relaxed bg-[#131d1a]/40 p-2.5 rounded-xl border border-[#0d685b]/10">
                                <span class="text-teal-400 font-semibold">A:</span> {{ f.answer }}
                            </p>
                        </td>
                        <td class="py-3.5 whitespace-nowrap">
                            <span
                                class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="f.is_active ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-slate-700/40 text-slate-400 border border-slate-600/40'"
                            >
                                {{ f.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="space-x-3 px-4 py-3.5 text-right text-xs whitespace-nowrap">
                            <button
                                type="button"
                                class="font-bold text-[#f3f2e7]/80 hover:text-[#f3f2e7] underline cursor-pointer"
                                @click="toggleFaq(f)"
                            >
                                {{ f.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </button>
                            <button
                                type="button"
                                class="font-bold text-rose-400 hover:text-rose-300 underline cursor-pointer"
                                @click="removeFaq(f)"
                            >
                                Hapus
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!faqs.length">
                        <td colspan="5" class="px-4 py-12 text-center text-sm text-[#f3f2e7]/60">
                            <div class="space-y-2">
                                <div class="text-2xl">🍃</div>
                                <div>Tidak ada daftar FAQ yang sesuai dengan filter atau kata kunci.</div>
                                <button
                                    v-if="hasActiveFaqFilters"
                                    type="button"
                                    @click="resetFaqFilters"
                                    class="text-xs text-emerald-400 hover:underline font-bold"
                                >
                                    Bersihkan Filter Pencarian
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL: TULIS ARTIKEL BARU -->
    <div
        v-if="showBlogModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm overflow-y-auto"
        @click.self="showBlogModal = false"
    >
        <div class="w-full max-w-2xl my-8 rounded-2xl border border-[#0d685b]/40 bg-[#1c2a25] p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-[#0d685b]/30 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-xl">✍️</span>
                    <h3 class="text-base font-black text-[#f3f2e7]">Tulis Artikel Blog Baru</h3>
                </div>
                <button
                    type="button"
                    @click="showBlogModal = false"
                    class="rounded-lg p-1 text-[#f3f2e7]/60 hover:text-white hover:bg-[#131d1a]"
                >
                    ✕
                </button>
            </div>

            <form class="space-y-4" @submit.prevent="submitBlog">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-bold text-[#f3f2e7]/80">Judul Artikel</label>
                        <input
                            v-model="blogForm.title"
                            placeholder="Contoh: 5 Tips Memilih Beton Siap Pakai Berkualitas"
                            :class="inputClass"
                            autofocus
                        />
                        <p v-if="blogForm.errors.title" class="mt-1 text-xs text-rose-400">{{ blogForm.errors.title }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-bold text-[#f3f2e7]/80">Pilar Toko (Opsional)</label>
                        <select v-model="blogForm.store_id" :class="inputClass">
                            <option value="">Umum / Semua Toko</option>
                            <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                        <p v-if="blogForm.errors.store_id" class="mt-1 text-xs text-rose-400">{{ blogForm.errors.store_id }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold text-[#f3f2e7]/80">Ringkasan / Excerpt</label>
                    <textarea
                        v-model="blogForm.excerpt"
                        rows="2"
                        placeholder="Ringkasan singkat untuk tampilan preview dan SEO..."
                        :class="inputClass"
                    ></textarea>
                    <p v-if="blogForm.errors.excerpt" class="mt-1 text-xs text-rose-400">{{ blogForm.errors.excerpt }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold text-[#f3f2e7]/80">Isi Artikel Lengkap</label>
                    <textarea
                        v-model="blogForm.content"
                        rows="7"
                        placeholder="Tuliskan isi artikel secara mendalam..."
                        :class="inputClass"
                    ></textarea>
                    <p v-if="blogForm.errors.content" class="mt-1 text-xs text-rose-400">{{ blogForm.errors.content }}</p>
                </div>

                <!-- SEO Settings -->
                <div class="rounded-xl border border-[#0d685b]/30 bg-[#131d1a] p-4 text-[#f3f2e7] space-y-3">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-400">Pengaturan SEO Google (Opsional)</h4>
                    <div class="grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-[#f3f2e7]/70">Meta Title</label>
                            <input v-model="blogForm.meta_title" placeholder="Kosongkan jika sama dengan judul" :class="inputClass" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-[#f3f2e7]/70">Meta Description</label>
                            <input v-model="blogForm.meta_description" placeholder="Deskripsi untuk snippet hasil Google" :class="inputClass" />
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2 items-center">
                    <div>
                        <label class="mb-1 block text-xs font-bold text-[#f3f2e7]/80">Foto Thumbnail (maks 3MB)</label>
                        <input
                            type="file"
                            accept="image/*"
                            class="text-xs text-[#f3f2e7]/80 file:mr-3 file:rounded-xl file:border-0 file:bg-[#0d685b] file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-[#f3f2e7]"
                            @change="(e) => blogForm.thumbnail = e.target.files[0]"
                        />
                        <p v-if="blogForm.errors.thumbnail" class="mt-1 text-xs text-rose-400">{{ blogForm.errors.thumbnail }}</p>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-sm font-semibold text-[#f3f2e7] cursor-pointer">
                            <input v-model="blogForm.is_published" type="checkbox" class="rounded accent-[#0d685b]" />
                            <span>Langsung Publikasikan Artikel</span>
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-[#0d685b]/30">
                    <button
                        type="button"
                        @click="showBlogModal = false"
                        class="rounded-xl border border-[#0d685b]/30 px-4 py-2 text-xs font-semibold text-[#f3f2e7]/70 hover:bg-[#131d1a]"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="blogForm.processing"
                        class="rounded-xl bg-[#0d685b] px-5 py-2 text-xs font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 hover:bg-[#0d685b]/90 transition disabled:opacity-50"
                    >
                        Simpan Artikel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: TAMBAH FAQ BARU -->
    <div
        v-if="showFaqModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
        @click.self="showFaqModal = false"
    >
        <div class="w-full max-w-lg rounded-2xl border border-[#0d685b]/40 bg-[#1c2a25] p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-[#0d685b]/30 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-xl">❓</span>
                    <h3 class="text-base font-black text-[#f3f2e7]">Tambah FAQ Baru</h3>
                </div>
                <button
                    type="button"
                    @click="showFaqModal = false"
                    class="rounded-lg p-1 text-[#f3f2e7]/60 hover:text-white hover:bg-[#131d1a]"
                >
                    ✕
                </button>
            </div>

            <form class="space-y-4" @submit.prevent="submitFaq">
                <div>
                    <label class="mb-1 block text-xs font-bold text-[#f3f2e7]/80">Pertanyaan</label>
                    <input
                        v-model="faqForm.question"
                        placeholder="Contoh: Bagaimana cara top-up saldo akun RAMELA?"
                        :class="inputClass"
                        autofocus
                    />
                    <p v-if="faqForm.errors.question" class="mt-1 text-xs text-rose-400">{{ faqForm.errors.question }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-bold text-[#f3f2e7]/80">Jawaban Lengkap</label>
                    <textarea
                        v-model="faqForm.answer"
                        rows="4"
                        placeholder="Tuliskan jawaban yang informatif dan ramah..."
                        :class="inputClass"
                    ></textarea>
                    <p v-if="faqForm.errors.answer" class="mt-1 text-xs text-rose-400">{{ faqForm.errors.answer }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4 items-center">
                    <div>
                        <label class="mb-1 block text-xs font-bold text-[#f3f2e7]/80">Urutan Tampil (Sort Order)</label>
                        <input
                            v-model="faqForm.sort_order"
                            type="number"
                            min="0"
                            :class="inputClass"
                        />
                        <p v-if="faqForm.errors.sort_order" class="mt-1 text-xs text-rose-400">{{ faqForm.errors.sort_order }}</p>
                    </div>
                    <div class="pt-5">
                        <label class="flex items-center gap-2 text-sm font-semibold text-[#f3f2e7] cursor-pointer">
                            <input v-model="faqForm.is_active" type="checkbox" class="rounded accent-[#0d685b]" />
                            <span>Aktif (Tampilkan)</span>
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-[#0d685b]/30">
                    <button
                        type="button"
                        @click="showFaqModal = false"
                        class="rounded-xl border border-[#0d685b]/30 px-4 py-2 text-xs font-semibold text-[#f3f2e7]/70 hover:bg-[#131d1a]"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="faqForm.processing"
                        class="rounded-xl bg-[#0d685b] px-5 py-2 text-xs font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 hover:bg-[#0d685b]/90 transition disabled:opacity-50"
                    >
                        Simpan FAQ
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

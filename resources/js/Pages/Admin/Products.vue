<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { rupiah } from '../../utils/format';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    products: Object,
    categories: { type: Array, default: () => [] },
    stores: { type: Array, default: () => [] },
    stats: {
        type: Object,
        default: () => ({
            total_products: 0,
            total_categories: 0,
            low_stock: 0,
            inactive: 0,
        }),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

// Active Tab ('products' | 'categories')
const currentTab = ref(props.filters.tab || 'products');

// Product Filters & Sorting
const q = ref(props.filters.q ?? '');
const store = ref(props.filters.store ?? '');
const category = ref(props.filters.category ?? '');
const status = ref(props.filters.status ?? '');
const stock_status = ref(props.filters.stock_status ?? '');
const sort = ref(props.filters.sort ?? 'created_at');
const dir = ref(props.filters.dir ?? 'desc');

// Categories filtered for the product filter dropdown (based on selected store)
const availableCategoriesForFilter = computed(() => {
    if (!store.value) return props.categories;
    return props.categories.filter((c) => String(c.store_id) === String(store.value));
});

// If selected store changes and selected category doesn't belong to it, reset category filter
watch(store, (newStore) => {
    if (newStore && category.value) {
        const match = props.categories.find(
            (c) => String(c.id) === String(category.value) && String(c.store_id) === String(newStore)
        );
        if (!match) category.value = '';
    }
});

const applyFilters = () => {
    router.get(
        '/admin/produk',
        {
            tab: currentTab.value,
            q: q.value || undefined,
            store: store.value || undefined,
            category: category.value || undefined,
            status: status.value || undefined,
            stock_status: stock_status.value || undefined,
            sort: sort.value !== 'created_at' ? sort.value : undefined,
            dir: dir.value !== 'desc' ? dir.value : undefined,
        },
        { preserveState: true, replace: true }
    );
};

const switchTab = (tab) => {
    currentTab.value = tab;
    router.get(
        '/admin/produk',
        {
            tab,
            q: tab === 'products' ? q.value || undefined : undefined,
            store: tab === 'products' ? store.value || undefined : undefined,
            category: tab === 'products' ? category.value || undefined : undefined,
            status: tab === 'products' ? status.value || undefined : undefined,
            stock_status: tab === 'products' ? stock_status.value || undefined : undefined,
            sort: tab === 'products' && sort.value !== 'created_at' ? sort.value : undefined,
            dir: tab === 'products' && dir.value !== 'desc' ? dir.value : undefined,
        },
        { preserveState: true, replace: true }
    );
};

const resetFilters = () => {
    q.value = '';
    store.value = '';
    category.value = '';
    status.value = '';
    stock_status.value = '';
    sort.value = 'created_at';
    dir.value = 'desc';
    router.get('/admin/produk', { tab: currentTab.value }, { preserveState: true, replace: true });
};

const sortBy = (column) => {
    if (sort.value === column) {
        dir.value = dir.value === 'asc' ? 'desc' : 'asc';
    } else {
        sort.value = column;
        dir.value = column === 'name' ? 'asc' : 'desc';
    }
    applyFilters();
};

const filterByQuickStat = (type) => {
    currentTab.value = 'products';
    if (type === 'all') {
        resetFilters();
    } else if (type === 'low_stock') {
        stock_status.value = 'low';
        status.value = 'active';
        applyFilters();
    } else if (type === 'inactive') {
        status.value = 'inactive';
        stock_status.value = '';
        applyFilters();
    }
};

const removeProduct = (p) => {
    if (confirm(`Hapus produk "${p.name}"? Data penjualan terkait akan tetap tersimpan.`)) {
        router.delete(`/admin/produk/${p.id}`, { preserveScroll: true });
    }
};

// Category Management
const categorySearch = ref('');
const categoryStoreFilter = ref('');
const showCategoryModal = ref(false);

const categoryForm = useForm({
    store_id: props.stores[0]?.id ?? '',
    name: '',
});

const submitCategory = () => {
    categoryForm.post('/admin/kategori', {
        preserveScroll: true,
        onSuccess: () => {
            categoryForm.reset('name');
            showCategoryModal.value = false;
        },
    });
};

const removeCategory = (c) => {
    if (confirm(`Hapus kategori "${c.name}"? Produk yang terkait akan menjadi tanpa kategori.`)) {
        router.delete(`/admin/kategori/${c.id}`, { preserveScroll: true });
    }
};

const filterProductsByCategory = (c) => {
    currentTab.value = 'products';
    store.value = String(c.store_id);
    category.value = String(c.id);
    applyFilters();
};

const displayedCategories = computed(() => {
    return props.categories.filter((c) => {
        const matchesStore = !categoryStoreFilter.value || String(c.store_id) === String(categoryStoreFilter.value);
        const matchesSearch = !categorySearch.value || c.name.toLowerCase().includes(categorySearch.value.toLowerCase());
        return matchesStore && matchesSearch;
    });
});

const formatWeight = (w) => {
    const num = Number(w || 1000);
    return num >= 1000
        ? (num / 1000).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + ' kg'
        : num.toLocaleString('id-ID') + ' g';
};

const hasActiveProductFilters = computed(() => {
    return Boolean(q.value || store.value || category.value || status.value || stock_status.value || sort.value !== 'created_at');
});
</script>

<template>
    <Head title="Katalog Produk & Kategori" />

    <!-- Page Header -->
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="text-2xl">📦</span>
                <h1 class="text-2xl font-black text-[#f3f2e7] tracking-tight">Katalog Produk & Kategori</h1>
            </div>
            <p class="mt-1 text-xs text-[#f3f2e7]/70">
                Pusat manajemen terpadu inventaris produk, penyesuaian bobot paket ekspedisi, serta klasifikasi kategori toko.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            <button
                type="button"
                @click="showCategoryModal = true"
                class="inline-flex items-center gap-1.5 rounded-xl border border-[#0d685b] bg-[#1c2a25] px-4 py-2.5 text-xs font-bold text-[#f3f2e7] shadow-sm hover:bg-[#131d1a] hover:border-emerald-400/50 transition cursor-pointer"
            >
                <span>🏷️</span> + Tambah Kategori
            </button>
            <Link
                href="/admin/produk/create"
                class="inline-flex items-center gap-1.5 rounded-xl bg-[#0d685b] px-4 py-2.5 text-xs font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 hover:bg-[#0d685b]/90 transition"
            >
                <span>✨</span> + Tambah Produk Baru
            </Link>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <button
            type="button"
            @click="filterByQuickStat('all')"
            class="text-left rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-3.5 shadow-md hover:border-[#0d685b] transition group cursor-pointer"
        >
            <div class="text-[11px] font-medium text-[#f3f2e7]/60">Total Produk</div>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="text-2xl font-black text-[#f3f2e7] group-hover:text-emerald-400 transition">{{ stats.total_products }}</span>
                <span class="text-xs text-[#f3f2e7]/40">Item</span>
            </div>
        </button>

        <button
            type="button"
            @click="switchTab('categories')"
            class="text-left rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-3.5 shadow-md hover:border-[#0d685b] transition group cursor-pointer"
        >
            <div class="text-[11px] font-medium text-[#f3f2e7]/60">Total Kategori</div>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="text-2xl font-black text-emerald-400 group-hover:underline transition">{{ stats.total_categories }}</span>
                <span class="text-xs text-[#f3f2e7]/40">Kategori</span>
            </div>
        </button>

        <button
            type="button"
            @click="filterByQuickStat('low_stock')"
            class="text-left rounded-2xl border border-amber-500/30 bg-[#1c2a25] p-3.5 shadow-md hover:border-amber-400 transition group cursor-pointer"
        >
            <div class="text-[11px] font-medium text-amber-300/80">Stok Menipis (≤ 5)</div>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="text-2xl font-black text-amber-400 group-hover:scale-105 transition">{{ stats.low_stock }}</span>
                <span class="text-xs text-amber-400/60">Perlu restock</span>
            </div>
        </button>

        <button
            type="button"
            @click="filterByQuickStat('inactive')"
            class="text-left rounded-2xl border border-slate-600/40 bg-[#1c2a25] p-3.5 shadow-md hover:border-slate-500 transition group cursor-pointer"
        >
            <div class="text-[11px] font-medium text-slate-400">Produk Nonaktif</div>
            <div class="mt-1 flex items-baseline justify-between">
                <span class="text-2xl font-black text-slate-300 group-hover:text-white transition">{{ stats.inactive }}</span>
                <span class="text-xs text-slate-400">Disembunyikan</span>
            </div>
        </button>
    </div>

    <!-- Tab Buttons -->
    <div class="mt-6 flex border-b border-[#0d685b]/30">
        <button
            type="button"
            @click="switchTab('products')"
            class="inline-flex items-center gap-2 border-b-2 px-5 py-3 text-sm font-bold transition cursor-pointer"
            :class="currentTab === 'products' ? 'border-emerald-400 text-emerald-300 bg-[#131d1a]/60 rounded-t-xl' : 'border-transparent text-[#f3f2e7]/60 hover:text-[#f3f2e7]'"
        >
            <span>📦</span> Katalog Produk
            <span class="rounded-full bg-[#131d1a] px-2 py-0.5 text-xs text-[#f3f2e7]/70 border border-[#0d685b]/30">
                {{ stats.total_products }}
            </span>
        </button>
        <button
            type="button"
            @click="switchTab('categories')"
            class="inline-flex items-center gap-2 border-b-2 px-5 py-3 text-sm font-bold transition cursor-pointer"
            :class="currentTab === 'categories' ? 'border-emerald-400 text-emerald-300 bg-[#131d1a]/60 rounded-t-xl' : 'border-transparent text-[#f3f2e7]/60 hover:text-[#f3f2e7]'"
        >
            <span>🏷️</span> Kategori Toko
            <span class="rounded-full bg-[#131d1a] px-2 py-0.5 text-xs text-[#f3f2e7]/70 border border-[#0d685b]/30">
                {{ stats.total_categories }}
            </span>
        </button>
    </div>

    <!-- TAB 1: KATALOG PRODUK -->
    <div v-if="currentTab === 'products'" class="mt-4 space-y-4">
        <!-- Filter & Sorting Bar -->
        <div class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 shadow-lg">
            <form class="space-y-3" @submit.prevent="applyFilters">
                <!-- Search & Dropdowns Row -->
                <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-5">
                    <!-- Search Input -->
                    <div class="sm:col-span-2 lg:col-span-2 relative">
                        <input
                            v-model="q"
                            type="text"
                            placeholder="Cari nama atau deskripsi produk..."
                            class="w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2 text-sm text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:border-[#0d685b] focus:outline-none"
                        />
                        <button
                            v-if="q"
                            type="button"
                            @click="q = ''; applyFilters();"
                            class="absolute right-3 top-2.5 text-xs text-[#f3f2e7]/50 hover:text-[#f3f2e7]"
                        >
                            ✕
                        </button>
                    </div>

                    <!-- Filter Toko -->
                    <div>
                        <select
                            v-model="store"
                            @change="applyFilters"
                            class="w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3 py-2 text-sm text-[#f3f2e7] focus:border-[#0d685b] focus:outline-none"
                        >
                            <option value="">Semua Toko</option>
                            <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>

                    <!-- Filter Kategori -->
                    <div>
                        <select
                            v-model="category"
                            @change="applyFilters"
                            class="w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3 py-2 text-sm text-[#f3f2e7] focus:border-[#0d685b] focus:outline-none"
                        >
                            <option value="">Semua Kategori</option>
                            <option v-for="c in availableCategoriesForFilter" :key="c.id" :value="c.id">
                                {{ c.name }} ({{ c.store }})
                            </option>
                        </select>
                    </div>

                    <!-- Filter Status -->
                    <div>
                        <select
                            v-model="status"
                            @change="applyFilters"
                            class="w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3 py-2 text-sm text-[#f3f2e7] focus:border-[#0d685b] focus:outline-none"
                        >
                            <option value="">Semua Status</option>
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                    </div>
                </div>

                <!-- Secondary Sorting & Actions Row -->
                <div class="flex flex-wrap items-center justify-between gap-2.5 pt-1 border-t border-[#0d685b]/20">
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Filter Kondisi Stok -->
                        <div class="flex items-center gap-1.5 text-xs text-[#f3f2e7]/70">
                            <span>Stok:</span>
                            <select
                                v-model="stock_status"
                                @change="applyFilters"
                                class="rounded-lg border border-[#0d685b]/40 bg-[#131d1a] px-2.5 py-1.5 text-xs text-[#f3f2e7] focus:border-[#0d685b] focus:outline-none"
                            >
                                <option value="">Semua Stok</option>
                                <option value="available">Tersedia (> 5)</option>
                                <option value="low">Menipis (1 - 5)</option>
                                <option value="empty">Habis (0)</option>
                            </select>
                        </div>

                        <!-- Urutan (Sort By) -->
                        <div class="flex items-center gap-1.5 text-xs text-[#f3f2e7]/70">
                            <span>Urut:</span>
                            <select
                                v-model="sort"
                                @change="applyFilters"
                                class="rounded-lg border border-[#0d685b]/40 bg-[#131d1a] px-2.5 py-1.5 text-xs text-[#f3f2e7] focus:border-[#0d685b] focus:outline-none"
                            >
                                <option value="created_at">Waktu Dibuat</option>
                                <option value="name">Nama Produk</option>
                                <option value="price">Harga</option>
                                <option value="stock">Jumlah Stok</option>
                                <option value="weight">Bobot / Berat</option>
                            </select>
                        </div>

                        <!-- Direction Toggle (Asc / Desc) -->
                        <button
                            type="button"
                            @click="dir = dir === 'asc' ? 'desc' : 'asc'; applyFilters();"
                            class="inline-flex items-center gap-1 rounded-lg border border-[#0d685b]/40 bg-[#131d1a] px-2.5 py-1.5 text-xs font-semibold text-[#f3f2e7] hover:border-emerald-400 transition"
                            :title="dir === 'asc' ? 'Urutan Naik (A-Z / Rendah ke Tinggi)' : 'Urutan Turun (Z-A / Tinggi ke Rendah)'"
                        >
                            <span>{{ dir === 'asc' ? '▲ Naik (Asc)' : '▼ Turun (Desc)' }}</span>
                        </button>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center gap-2">
                        <button
                            v-if="hasActiveProductFilters"
                            type="button"
                            @click="resetFilters"
                            class="rounded-xl border border-rose-500/30 bg-rose-950/20 px-3 py-1.5 text-xs font-semibold text-rose-300 hover:bg-rose-900/30 transition"
                        >
                            Reset Filter
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-[#0d685b] px-4 py-1.5 text-xs font-bold text-[#f3f2e7] shadow-sm hover:bg-[#0d685b]/90 transition"
                        >
                            🔍 Terapkan Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Products Table -->
        <div class="overflow-x-auto rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-lg">
            <table class="w-full text-left text-sm text-[#f3f2e7]">
                <thead class="border-b border-[#0d685b]/30 bg-[#131d1a] text-xs font-bold uppercase tracking-wider text-[#f3f2e7]/70">
                    <tr>
                        <th class="w-12 px-3 py-3 text-center">#</th>
                        <th class="px-4 py-3 cursor-pointer select-none hover:text-white" @click="sortBy('name')">
                            <div class="flex items-center gap-1">
                                <span>Nama Produk</span>
                                <span class="text-[10px]">{{ sort === 'name' ? (dir === 'asc' ? '▲' : '▼') : '↕' }}</span>
                            </div>
                        </th>
                        <th class="px-4 py-3 cursor-pointer select-none hover:text-white" @click="sortBy('weight')">
                            <div class="flex items-center gap-1">
                                <span>Berat</span>
                                <span class="text-[10px]">{{ sort === 'weight' ? (dir === 'asc' ? '▲' : '▼') : '↕' }}</span>
                            </div>
                        </th>
                        <th class="px-4 py-3">Toko</th>
                        <th class="px-4 py-3">Kategori</th>
                        <th class="px-4 py-3 cursor-pointer select-none hover:text-white" @click="sortBy('price')">
                            <div class="flex items-center gap-1">
                                <span>Harga</span>
                                <span class="text-[10px]">{{ sort === 'price' ? (dir === 'asc' ? '▲' : '▼') : '↕' }}</span>
                            </div>
                        </th>
                        <th class="px-4 py-3 cursor-pointer select-none hover:text-white" @click="sortBy('stock')">
                            <div class="flex items-center gap-1">
                                <span>Stok</span>
                                <span class="text-[10px]">{{ sort === 'stock' ? (dir === 'asc' ? '▲' : '▼') : '↕' }}</span>
                            </div>
                        </th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#0d685b]/20">
                    <tr v-for="(p, idx) in products.data" :key="p.id" class="hover:bg-[#131d1a]/50 transition">
                        <td class="px-3 py-3 text-center font-bold text-xs text-[#f3f2e7]/50">
                            {{ (products.from || 1) + idx }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-bold text-[#f3f2e7]">{{ p.name }}</div>
                            <div v-if="p.description" class="text-[11px] text-[#f3f2e7]/40 truncate max-w-xs">
                                {{ p.description }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-xs font-medium text-[#f3f2e7] whitespace-nowrap">
                            <span class="inline-flex items-center gap-1 bg-[#131d1a] px-2.5 py-1 rounded-lg border border-[#0d685b]/30">
                                ⚖️ {{ formatWeight(p.weight) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-[#f3f2e7]/80">
                            <span class="inline-block rounded-md bg-[#131d1a] px-2 py-0.5 border border-[#0d685b]/20">
                                {{ p.store }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-xs text-[#f3f2e7]/70">
                            <button
                                v-if="p.category"
                                type="button"
                                @click="filterProductsByCategory({ id: p.category_id, store_id: p.store_id, name: p.category })"
                                class="inline-flex items-center gap-1 rounded-md bg-[#131d1a] px-2 py-0.5 border border-emerald-500/20 text-emerald-300 hover:border-emerald-400 hover:underline transition"
                                title="Klik untuk memfilter kategori ini"
                            >
                                🏷️ {{ p.category }}
                            </button>
                            <span v-else class="text-[#f3f2e7]/40">-</span>
                        </td>
                        <td class="px-4 py-3 font-semibold text-[#f3f2e7] whitespace-nowrap">
                            {{ rupiah(p.price) }}
                            <span v-if="p.unit" class="text-xs text-[#f3f2e7]/50 font-normal"> /{{ p.unit }}</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span
                                v-if="p.stock === 0"
                                class="inline-flex items-center gap-1 font-bold text-rose-400 bg-rose-950/40 px-2 py-0.5 rounded border border-rose-500/40 text-xs"
                            >
                                ⚠️ Habis (0)
                            </span>
                            <span
                                v-else-if="p.stock <= 5"
                                class="inline-flex items-center gap-1 font-bold text-amber-300 bg-amber-950/40 px-2 py-0.5 rounded border border-amber-500/40 text-xs"
                            >
                                ⚡ Menipis ({{ p.stock }})
                            </span>
                            <span v-else class="text-[#f3f2e7] text-xs font-medium">
                                {{ p.stock }} unit
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap"
                                :class="p.is_active ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-slate-700/40 text-slate-400 border border-slate-600/40'"
                            >
                                {{ p.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="space-x-3 px-4 py-3 text-right text-xs whitespace-nowrap">
                            <Link :href="`/admin/produk/${p.id}/edit`" class="font-bold text-emerald-400 hover:text-emerald-300 transition underline">
                                Ubah
                            </Link>
                            <button type="button" class="font-bold text-rose-400 hover:text-rose-300 transition underline cursor-pointer" @click="removeProduct(p)">
                                Hapus
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!products.data.length">
                        <td colspan="9" class="px-4 py-12 text-center text-sm text-[#f3f2e7]/60">
                            <div class="space-y-2">
                                <div class="text-2xl">🍃</div>
                                <div>Tidak ada produk yang cocok dengan kriteria pencarian/filter.</div>
                                <button
                                    v-if="hasActiveProductFilters"
                                    type="button"
                                    @click="resetFilters"
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

        <!-- Pagination -->
        <nav v-if="products.last_page > 1" class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <div class="text-xs text-[#f3f2e7]/60">
                Menampilkan halaman {{ products.current_page }} dari {{ products.last_page }} (total {{ products.total }} produk)
            </div>
            <div class="flex flex-wrap gap-1.5">
                <template v-for="l in products.links" :key="l.label">
                    <Link
                        v-if="l.url"
                        :href="l.url"
                        class="rounded-xl px-3.5 py-1.5 text-xs font-bold border transition"
                        :class="l.active ? 'bg-[#0d685b] border-[#0d685b] text-[#f3f2e7]' : 'bg-[#1c2a25] border-[#0d685b]/30 text-[#f3f2e7]/70 hover:bg-[#131d1a]'"
                        v-html="l.label"
                    />
                </template>
            </div>
        </nav>
    </div>

    <!-- TAB 2: KATEGORI TOKO -->
    <div v-else-if="currentTab === 'categories'" class="mt-4 space-y-5">
        <!-- Quick Add Category Inline Card -->
        <div class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-5 shadow-lg">
            <h3 class="text-sm font-bold text-[#f3f2e7] flex items-center gap-2">
                <span>➕</span> Tambah Kategori Baru
            </h3>
            <p class="mt-0.5 text-xs text-[#f3f2e7]/60">
                Tambahkan klasifikasi kategori untuk mengelompokkan produk pada pilar toko masing-masing.
            </p>

            <form class="mt-4 flex flex-wrap items-start gap-3" @submit.prevent="submitCategory">
                <div class="w-full sm:w-auto">
                    <label class="block text-[11px] font-semibold text-[#f3f2e7]/70 mb-1">Pilar Toko</label>
                    <select
                        v-model="categoryForm.store_id"
                        class="w-full sm:w-auto min-w-[180px] rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2 text-sm text-[#f3f2e7] focus:outline-none focus:border-[#0d685b]"
                    >
                        <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <p v-if="categoryForm.errors.store_id" class="mt-1 text-xs text-rose-400">{{ categoryForm.errors.store_id }}</p>
                </div>

                <div class="w-full sm:flex-1">
                    <label class="block text-[11px] font-semibold text-[#f3f2e7]/70 mb-1">Nama Kategori</label>
                    <input
                        v-model="categoryForm.name"
                        type="text"
                        placeholder="Contoh: Makanan Berat, Hampers Lebaran, Material Cor..."
                        class="w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2 text-sm text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:outline-none focus:border-[#0d685b]"
                    />
                    <p v-if="categoryForm.errors.name" class="mt-1 text-xs text-rose-400">{{ categoryForm.errors.name }}</p>
                </div>

                <div class="w-full sm:w-auto pt-5">
                    <button
                        type="submit"
                        :disabled="categoryForm.processing"
                        class="w-full sm:w-auto rounded-xl bg-[#0d685b] px-5 py-2.5 text-xs font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 hover:bg-[#0d685b]/90 transition disabled:opacity-50 cursor-pointer"
                    >
                        + Simpan Kategori
                    </button>
                </div>
            </form>
        </div>

        <!-- Category Search & Filter -->
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-3 shadow-md">
            <div class="flex flex-wrap items-center gap-2.5 w-full sm:w-auto flex-1">
                <input
                    v-model="categorySearch"
                    type="text"
                    placeholder="Filter nama kategori..."
                    class="w-full sm:w-64 rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-1.5 text-xs text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:outline-none focus:border-[#0d685b]"
                />
                <select
                    v-model="categoryStoreFilter"
                    class="w-full sm:w-auto rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3 py-1.5 text-xs text-[#f3f2e7] focus:outline-none focus:border-[#0d685b]"
                >
                    <option value="">Semua Toko</option>
                    <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <button
                    v-if="categorySearch || categoryStoreFilter"
                    type="button"
                    @click="categorySearch = ''; categoryStoreFilter = '';"
                    class="text-xs text-rose-300 hover:underline"
                >
                    Reset
                </button>
            </div>
            <div class="text-xs text-[#f3f2e7]/60">
                Menampilkan {{ displayedCategories.length }} dari {{ categories.length }} kategori
            </div>
        </div>

        <!-- Categories Table -->
        <div class="overflow-x-auto rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-lg">
            <table class="w-full text-left text-sm text-[#f3f2e7]">
                <thead class="border-b border-[#0d685b]/30 bg-[#131d1a] text-xs font-bold uppercase tracking-wider text-[#f3f2e7]/70">
                    <tr>
                        <th class="w-12 px-4 py-3 text-center">#</th>
                        <th class="px-5 py-3">Nama Kategori</th>
                        <th class="px-5 py-3">Pilar Toko</th>
                        <th class="px-5 py-3">Jumlah Produk Terdaftar</th>
                        <th class="px-5 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#0d685b]/20">
                    <tr v-for="(c, idx) in displayedCategories" :key="c.id" class="hover:bg-[#131d1a]/50 transition">
                        <td class="px-4 py-3.5 text-center font-bold text-xs text-[#f3f2e7]/50">
                            {{ idx + 1 }}
                        </td>
                        <td class="px-5 py-3.5 font-bold text-[#f3f2e7]">
                            {{ c.name }}
                        </td>
                        <td class="px-5 py-3.5 text-xs">
                            <span class="inline-block rounded-lg bg-[#131d1a] border border-[#0d685b]/30 px-2.5 py-1 text-[#f3f2e7]/80">
                                🏬 {{ c.store }}
                            </span>
                        </td>
                        <td class="px-5 py-3.5">
                            <button
                                type="button"
                                @click="filterProductsByCategory(c)"
                                class="inline-flex items-center gap-1.5 rounded-full bg-[#131d1a] border border-emerald-500/30 px-3 py-1 text-xs font-semibold text-emerald-300 hover:bg-emerald-950/40 hover:border-emerald-400 transition cursor-pointer"
                                title="Lihat produk dalam kategori ini"
                            >
                                <span>📦</span> {{ c.products_count }} produk terdaftar
                                <span class="text-[10px] text-emerald-400/70">→ Lihat</span>
                            </button>
                        </td>
                        <td class="px-5 py-3.5 text-right text-xs">
                            <button
                                type="button"
                                class="font-bold text-rose-400 hover:text-rose-300 underline transition cursor-pointer"
                                @click="removeCategory(c)"
                            >
                                Hapus
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!displayedCategories.length">
                        <td colspan="5" class="px-5 py-10 text-center text-sm text-[#f3f2e7]/60">
                            🍃 Tidak ada kategori yang sesuai dengan filter.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quick Add Category Modal (Accessible from any tab via top header button) -->
    <div
        v-if="showCategoryModal"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
        @click.self="showCategoryModal = false"
    >
        <div class="w-full max-w-md rounded-2xl border border-[#0d685b]/40 bg-[#1c2a25] p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-[#0d685b]/30 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-xl">🏷️</span>
                    <h3 class="text-base font-black text-[#f3f2e7]">Tambah Kategori Toko</h3>
                </div>
                <button
                    type="button"
                    @click="showCategoryModal = false"
                    class="rounded-lg p-1 text-[#f3f2e7]/60 hover:text-white hover:bg-[#131d1a]"
                >
                    ✕
                </button>
            </div>

            <form class="space-y-4" @submit.prevent="submitCategory">
                <div>
                    <label class="block text-xs font-bold text-[#f3f2e7]/80 mb-1">Pilih Pilar Toko</label>
                    <select
                        v-model="categoryForm.store_id"
                        class="w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2.5 text-sm text-[#f3f2e7] focus:outline-none focus:border-[#0d685b]"
                    >
                        <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <p v-if="categoryForm.errors.store_id" class="mt-1 text-xs text-rose-400">{{ categoryForm.errors.store_id }}</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-[#f3f2e7]/80 mb-1">Nama Kategori</label>
                    <input
                        v-model="categoryForm.name"
                        type="text"
                        placeholder="Contoh: Paket Parcel, Semen Instan, Dimsum..."
                        class="w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2.5 text-sm text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:outline-none focus:border-[#0d685b]"
                        autofocus
                    />
                    <p v-if="categoryForm.errors.name" class="mt-1 text-xs text-rose-400">{{ categoryForm.errors.name }}</p>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <button
                        type="button"
                        @click="showCategoryModal = false"
                        class="rounded-xl border border-[#0d685b]/30 px-4 py-2 text-xs font-semibold text-[#f3f2e7]/70 hover:bg-[#131d1a]"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="categoryForm.processing"
                        class="rounded-xl bg-[#0d685b] px-4 py-2 text-xs font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 hover:bg-[#0d685b]/90 transition disabled:opacity-50"
                    >
                        Simpan Kategori
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

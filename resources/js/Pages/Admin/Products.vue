<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { rupiah } from '../../utils/format';

defineOptions({ layout: AdminLayout });

const props = defineProps({ products: Object, stores: Array, filters: Object });

const q = ref(props.filters.q ?? '');
const store = ref(props.filters.store ?? '');

const search = () => router.get('/admin/produk',
    { q: q.value || undefined, store: store.value || undefined }, { preserveState: true, replace: true });

const remove = (p) => {
    if (confirm(`Hapus produk "${p.name}"?`)) router.delete(`/admin/produk/${p.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Produk" />
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-black text-[#f3f2e7] tracking-tight">Katalog Produk</h1>
            <p class="text-xs text-[#f3f2e7]/60">Kelola daftar item, persediaan stok, dan varian barang seluruh toko.</p>
        </div>
        <Link
            href="/admin/produk/create"
            class="rounded-xl bg-[#0d685b] px-4 py-2.5 text-xs font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 hover:bg-[#0d685b]/90 transition"
        >
            + Tambah Produk Baru
        </Link>
    </div>

    <form class="mt-5 flex flex-wrap items-center gap-2.5 rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-3 shadow-lg" @submit.prevent="search">
        <input
            v-model="q"
            placeholder="Cari nama produk..."
            class="w-full sm:w-auto rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2 text-sm text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:outline-none focus:border-[#0d685b]"
        />
        <select
            v-model="store"
            class="w-full sm:w-auto rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2 text-sm text-[#f3f2e7] focus:outline-none focus:border-[#0d685b]"
            @change="search"
        >
            <option value="">Semua toko</option>
            <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
        <button class="rounded-xl bg-[#0d685b] px-4 py-2 text-xs font-bold text-[#f3f2e7] shadow-sm hover:bg-[#0d685b]/90 transition">
            Cari Produk
        </button>
    </form>

    <div class="mt-4 overflow-x-auto rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-lg">
        <table class="w-full text-left text-sm text-[#f3f2e7]">
            <thead class="border-b border-[#0d685b]/30 bg-[#131d1a] text-xs font-bold uppercase tracking-wider text-[#f3f2e7]/70">
                <tr>
                    <th class="w-12 px-3 py-3 text-center">#</th>
                    <th class="px-4 py-3">Nama Produk</th>
                    <th class="px-4 py-3">Berat</th>
                    <th class="px-4 py-3">Toko</th>
                    <th class="px-4 py-3">Kategori</th>
                    <th class="px-4 py-3">Harga</th>
                    <th class="px-4 py-3">Stok</th>
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
                            ⚖️ {{ Number(p.weight) >= 1000 ? (Number(p.weight) / 1000).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + ' kg' : (p.weight || 1000) + ' g' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-xs text-[#f3f2e7]/80">{{ p.store }}</td>
                    <td class="px-4 py-3 text-xs text-[#f3f2e7]/70">{{ p.category ?? '-' }}</td>
                    <td class="px-4 py-3 font-semibold text-[#f3f2e7]">
                        {{ rupiah(p.price) }}<span v-if="p.unit" class="text-xs text-[#f3f2e7]/50 font-normal"> /{{ p.unit }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <span :class="p.stock <= 5 ? 'font-bold text-rose-400 bg-rose-950/40 px-2 py-0.5 rounded border border-rose-500/30' : 'text-[#f3f2e7]'">
                            {{ p.stock }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <span
                            class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="p.is_active ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-slate-700/40 text-slate-400 border border-slate-600/40'"
                        >
                            {{ p.is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </td>
                    <td class="space-x-3 px-4 py-3 text-right text-xs">
                        <Link :href="`/admin/produk/${p.id}/edit`" class="font-bold text-emerald-400 hover:text-emerald-300 transition underline">
                            Ubah
                        </Link>
                        <button class="font-bold text-rose-400 hover:text-rose-300 transition underline" @click="remove(p)">
                            Hapus
                        </button>
                    </td>
                </tr>
                <tr v-if="!products.data.length">
                    <td colspan="9" class="px-4 py-8 text-center text-sm text-[#f3f2e7]/60">🍃 Tidak ada produk ditemukan.</td>
                </tr>
            </tbody>
        </table>
    </div>

    <nav v-if="products.last_page > 1" class="mt-4 flex flex-wrap gap-1.5">
        <template v-for="l in products.links" :key="l.label">
            <Link
                v-if="l.url"
                :href="l.url"
                class="rounded-xl px-3.5 py-1.5 text-xs font-bold border transition"
                :class="l.active ? 'bg-[#0d685b] border-[#0d685b] text-[#f3f2e7]' : 'bg-[#1c2a25] border-[#0d685b]/30 text-[#f3f2e7]/70 hover:bg-[#131d1a]'"
                v-html="l.label"
            />
        </template>
    </nav>
</template>

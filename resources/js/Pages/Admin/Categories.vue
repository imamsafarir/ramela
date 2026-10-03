<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({ stores: Array, categories: Array });

const form = useForm({ store_id: props.stores[0]?.id ?? '', name: '' });
const add = () => form.post('/admin/kategori', { preserveScroll: true, onSuccess: () => form.reset('name') });
const remove = (c) => {
    if (confirm(`Hapus kategori "${c.name}"? Produk di dalamnya menjadi tanpa kategori.`)) {
        router.delete(`/admin/kategori/${c.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Kategori" />
    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-black text-[#f3f2e7] tracking-tight">Kategori Produk</h1>
            <p class="text-xs text-[#f3f2e7]/60">Kelola kelompok klasifikasi menu dan item untuk setiap pilar toko.</p>
        </div>
    </div>

    <form class="mt-5 flex flex-wrap items-start gap-2.5 rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 shadow-lg" @submit.prevent="add">
        <select v-model="form.store_id" class="rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2 text-sm text-[#f3f2e7] focus:outline-none focus:border-[#0d685b]">
            <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
        <div>
            <input
                v-model="form.name"
                placeholder="Nama kategori baru..."
                class="rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2 text-sm text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:outline-none focus:border-[#0d685b]"
            />
            <p v-if="form.errors.name" class="mt-1 text-xs text-rose-400">{{ form.errors.name }}</p>
        </div>
        <button
            :disabled="form.processing"
            class="rounded-xl bg-[#0d685b] px-4 py-2 text-xs font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 hover:bg-[#0d685b]/90 transition disabled:opacity-50"
        >
            + Tambah Kategori
        </button>
    </form>

    <div class="mt-6 overflow-hidden rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-lg">
        <div class="border-b border-[#0d685b]/30 bg-[#131d1a] px-5 py-3 text-xs font-bold uppercase tracking-wider text-[#f3f2e7]/70">
            Daftar Kategori Aktif
        </div>
        <ul class="divide-y divide-[#0d685b]/20">
            <li v-for="c in categories" :key="c.id" class="flex items-center justify-between px-5 py-3.5 text-sm hover:bg-[#131d1a]/50 transition">
                <div class="flex items-center gap-3">
                    <span class="font-bold text-[#f3f2e7]">{{ c.name }}</span>
                    <span class="rounded-full bg-[#131d1a] border border-[#0d685b]/30 px-2.5 py-0.5 text-xs text-[#f3f2e7]/70">
                        {{ c.store }} · {{ c.products_count }} produk
                    </span>
                </div>
                <button class="text-xs font-bold text-rose-400 hover:text-rose-300 underline transition" @click="remove(c)">
                    Hapus
                </button>
            </li>
            <li v-if="!categories.length" class="px-5 py-8 text-center text-sm text-[#f3f2e7]/60">
                🍃 Belum ada kategori yang dibuat.
            </li>
        </ul>
    </div>
</template>

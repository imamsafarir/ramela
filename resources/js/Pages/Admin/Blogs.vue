<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });
defineProps({ blogs: Array, stores: Array });

const showCreate = ref(false);

const form = useForm({
    title: '',
    store_id: '',
    excerpt: '',
    content: '',
    meta_title: '',
    meta_description: '',
    thumbnail: null,
    is_published: true,
});

const submit = () => {
    form.post('/admin/blog', {
        onSuccess: () => {
            form.reset();
            showCreate.value = false;
        },
    });
};

const toggle = (b) => router.patch(`/admin/blog/${b.id}/toggle`);
const remove = (b) => confirm(`Hapus artikel "${b.title}"?`) && router.delete(`/admin/blog/${b.id}`);
const input = 'w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2.5 text-sm text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:outline-none focus:border-[#0d685b]';
</script>

<template>
    <Head title="Manajemen Blog" />
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-black text-[#f3f2e7] tracking-tight">Artikel & Berita Blog</h1>
            <p class="text-xs text-[#f3f2e7]/60">Tulis artikel berita, edukasi, dan tips untuk 3 layanan RAMELA.</p>
        </div>
        <button
            class="rounded-xl bg-[#0d685b] px-4 py-2.5 text-xs font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 hover:bg-[#0d685b]/90 transition"
            @click="showCreate = !showCreate"
        >
            {{ showCreate ? '✕ Tutup Form' : '+ Tulis Artikel Baru' }}
        </button>
    </div>

    <!-- Form Buat Artikel -->
    <form
        v-if="showCreate"
        class="mt-5 space-y-4 rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4.5 sm:p-6 shadow-xl text-[#f3f2e7]"
        @submit.prevent="submit"
    >
        <h2 class="text-base font-bold text-[#f3f2e7]">Tulis Artikel Baru</h2>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#0d685b]">Judul Artikel</label>
                <input v-model="form.title" placeholder="Contoh: 5 Tips Memilih Beton Siap Pakai Berkualitas" :class="input" />
                <p v-if="form.errors.title" class="mt-1 text-xs text-rose-400">{{ form.errors.title }}</p>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#0d685b]">Kategori Pilar Toko (opsional)</label>
                <select v-model="form.store_id" :class="input">
                    <option value="">Umum / Semua Toko</option>
                    <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
        </div>

        <div>
            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#0d685b]">Ringkasan / Excerpt</label>
            <textarea v-model="form.excerpt" rows="2" placeholder="Ringkasan singkat untuk tampilan preview dan SEO..." :class="input"></textarea>
        </div>

        <div>
            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#0d685b]">Isi Artikel Lengkap</label>
            <textarea v-model="form.content" rows="8" placeholder="Tuliskan isi artikel secara mendalam..." :class="input"></textarea>
            <p v-if="form.errors.content" class="mt-1 text-xs text-rose-400">{{ form.errors.content }}</p>
        </div>

        <!-- SEO SETTINGS -->
        <div class="rounded-xl border border-[#0d685b]/20 bg-[#131d1a] p-4 text-[#f3f2e7]">
            <h3 class="text-xs font-bold uppercase tracking-wider text-[#0d685b]">Pengaturan SEO Google</h3>
            <div class="mt-3 grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-[#f3f2e7]/70">Meta Title (judul di search engine)</label>
                    <input v-model="form.meta_title" placeholder="Kosongkan jika sama dengan judul artikel" :class="input" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-[#f3f2e7]/70">Meta Description</label>
                    <input v-model="form.meta_description" placeholder="Deskripsi untuk snippet hasil Google" :class="input" />
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#0d685b]">Foto Thumbnail (maks 3MB)</label>
                <input
                    type="file"
                    accept="image/*"
                    class="text-xs text-[#f3f2e7]/80 file:mr-3 file:rounded-xl file:border-0 file:bg-[#0d685b] file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-[#f3f2e7]"
                    @change="(e) => form.thumbnail = e.target.files[0]"
                />
            </div>
            <div class="flex items-center">
                <label class="flex items-center gap-2 text-sm font-semibold text-[#f3f2e7] cursor-pointer">
                    <input v-model="form.is_published" type="checkbox" class="rounded accent-[#0d685b]" />
                    Langsung Publikasikan Artikel
                </label>
            </div>
        </div>

        <div class="flex justify-end gap-2 border-t border-[#0d685b]/20 pt-3">
            <button type="button" class="rounded-xl px-4 py-2 text-xs font-semibold text-[#f3f2e7]/60 hover:bg-[#131d1a]" @click="showCreate = false">
                Batal
            </button>
            <button :disabled="form.processing" class="rounded-xl bg-[#0d685b] px-5 py-2 text-xs font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 hover:bg-[#0d685b]/90 disabled:opacity-50 transition">
                Simpan Artikel
            </button>
        </div>
    </form>

    <!-- Tabel Daftar Artikel -->
    <div class="mt-6 overflow-x-auto rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-lg">
        <table class="w-full text-left text-sm text-[#f3f2e7]">
            <thead class="border-b border-[#0d685b]/30 bg-[#131d1a] text-xs font-bold uppercase tracking-wider text-[#f3f2e7]/70">
                <tr>
                    <th class="w-12 px-3 py-3.5 text-center">#</th>
                    <th class="px-4 py-3.5">Artikel</th>
                    <th class="px-4 py-3.5">Pilar Layanan</th>
                    <th class="px-4 py-3.5">Penulis</th>
                    <th class="px-4 py-3.5">Status</th>
                    <th class="px-4 py-3.5">Tanggal Terbit</th>
                    <th class="px-4 py-3.5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#0d685b]/20">
                <tr v-for="(b, idx) in blogs" :key="b.id" class="hover:bg-[#131d1a]/50 transition">
                    <td class="px-3 py-3.5 text-center font-bold text-xs text-[#f3f2e7]/50">{{ idx + 1 }}</td>
                    <td class="px-4 py-3.5">
                        <div class="flex items-center gap-3">
                            <img v-if="b.thumbnail" :src="b.thumbnail" class="h-10 w-12 rounded-lg object-cover border border-[#0d685b]/30" />
                            <div v-else class="flex h-10 w-12 items-center justify-center rounded-lg bg-[#131d1a] border border-[#0d685b]/20 text-xs text-[#f3f2e7]/40">No Img</div>
                            <div>
                                <p class="font-bold text-[#f3f2e7]">{{ b.title }}</p>
                                <span class="text-xs text-[#f3f2e7]/50">/blog/{{ b.slug }}</span>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3.5">
                        <span class="rounded-full bg-[#131d1a] border border-[#0d685b]/30 px-2.5 py-0.5 text-xs text-[#f3f2e7]/80">
                            {{ b.store }}
                        </span>
                    </td>
                    <td class="px-4 py-3.5 text-xs text-[#f3f2e7]/70">{{ b.author }}</td>
                    <td class="px-4 py-3.5">
                        <span
                            class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="b.is_published ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-amber-500/20 text-amber-300 border border-amber-500/40'"
                        >
                            {{ b.is_published ? 'Publik' : 'Draft' }}
                        </span>
                    </td>
                    <td class="px-4 py-3.5 text-xs text-[#f3f2e7]/60">{{ b.published_at ?? '-' }}</td>
                    <td class="space-x-3 px-4 py-3.5 text-right text-xs">
                        <a v-if="b.is_published" :href="`/blog/${b.slug}`" target="_blank" class="font-bold text-emerald-400 hover:text-emerald-300 underline">Lihat</a>
                        <button class="font-bold text-[#f3f2e7]/80 hover:text-[#f3f2e7] underline" @click="toggle(b)">
                            {{ b.is_published ? 'Jadikan Draft' : 'Publikasikan' }}
                        </button>
                        <button class="font-bold text-rose-400 hover:text-rose-300 underline" @click="remove(b)">Hapus</button>
                    </td>
                </tr>
                <tr v-if="!blogs.length">
                    <td colspan="7" class="px-4 py-8 text-center text-sm text-[#f3f2e7]/60">
                        🍃 Belum ada artikel blog yang ditulis.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });
defineProps({ faqs: Array });

const showCreate = ref(false);

const form = useForm({
    question: '',
    answer: '',
    sort_order: 0,
    is_active: true,
});

const submit = () => {
    form.post('/admin/faq', {
        onSuccess: () => {
            form.reset({ question: '', answer: '', sort_order: 0, is_active: true });
            showCreate.value = false;
        },
    });
};

const toggle = (f) => router.patch(`/admin/faq/${f.id}/toggle`);
const remove = (f) => confirm(`Hapus FAQ "${f.question}"?`) && router.delete(`/admin/faq/${f.id}`);
const input = 'w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2.5 text-sm text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:outline-none focus:border-[#0d685b]';
</script>

<template>
    <Head title="Manajemen FAQ" />
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-black text-[#f3f2e7] tracking-tight">Tanya Jawab (FAQ)</h1>
            <p class="text-xs text-[#f3f2e7]/60">Kelola daftar pertanyaan umum dan jawaban untuk memandu pelanggan.</p>
        </div>
        <button
            class="rounded-xl bg-[#0d685b] px-4 py-2.5 text-xs font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 hover:bg-[#0d685b]/90 transition"
            @click="showCreate = !showCreate"
        >
            {{ showCreate ? '✕ Tutup Form' : '+ Tambah FAQ Baru' }}
        </button>
    </div>

    <!-- Form Tambah FAQ -->
    <form
        v-if="showCreate"
        class="mt-5 space-y-4 rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-6 shadow-xl text-[#f3f2e7]"
        @submit.prevent="submit"
    >
        <h2 class="text-base font-bold text-[#f3f2e7]">Tambah Pertanyaan & Jawaban Baru</h2>
        <div>
            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#0d685b]">Pertanyaan</label>
            <input v-model="form.question" placeholder="Contoh: Bagaimana cara top-up saldo akun RAMELA?" :class="input" />
            <p v-if="form.errors.question" class="mt-1 text-xs text-rose-400">{{ form.errors.question }}</p>
        </div>

        <div>
            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#0d685b]">Jawaban Lengkap</label>
            <textarea v-model="form.answer" rows="4" placeholder="Tulis jawaban lengkap dan informatif..." :class="input"></textarea>
            <p v-if="form.errors.answer" class="mt-1 text-xs text-rose-400">{{ form.errors.answer }}</p>
        </div>

        <div class="flex items-center gap-6">
            <div class="w-36">
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#0d685b]">Urutan Tampil</label>
                <input v-model="form.sort_order" type="number" :class="input" />
            </div>
            <label class="flex items-center gap-2 pt-6 text-sm font-semibold text-[#f3f2e7] cursor-pointer">
                <input v-model="form.is_active" type="checkbox" class="rounded accent-[#0d685b]" />
                <span>Aktif (Tampilkan ke Publik)</span>
            </label>
        </div>

        <div class="flex justify-end gap-2 border-t border-[#0d685b]/20 pt-3">
            <button type="button" class="rounded-xl px-4 py-2 text-xs font-semibold text-[#f3f2e7]/60 hover:bg-[#131d1a]" @click="showCreate = false">
                Batal
            </button>
            <button :disabled="form.processing" class="rounded-xl bg-[#0d685b] px-5 py-2 text-xs font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 hover:bg-[#0d685b]/90 disabled:opacity-50 transition">
                Simpan FAQ
            </button>
        </div>
    </form>

    <!-- Daftar FAQ -->
    <div class="mt-6 overflow-x-auto rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-lg">
        <table class="w-full text-left text-sm text-[#f3f2e7]">
            <thead class="border-b border-[#0d685b]/30 bg-[#131d1a] text-xs font-bold uppercase tracking-wider text-[#f3f2e7]/70">
                <tr>
                    <th class="w-16 px-4 py-3.5 text-center">Urutan</th>
                    <th class="py-3.5">Pertanyaan & Jawaban</th>
                    <th class="py-3.5">Status</th>
                    <th class="px-4 py-3.5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#0d685b]/20">
                <tr v-for="f in faqs" :key="f.id" class="hover:bg-[#131d1a]/50 transition">
                    <td class="px-4 py-3.5 text-center font-bold text-[#f3f2e7]/50">{{ f.sort_order }}</td>
                    <td class="py-3.5 pr-4">
                        <p class="font-bold text-[#f3f2e7]">{{ f.question }}</p>
                        <p class="mt-1 whitespace-pre-line text-xs text-[#f3f2e7]/70 leading-relaxed">{{ f.answer }}</p>
                    </td>
                    <td class="py-3.5">
                        <span
                            class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="f.is_active ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-slate-700/40 text-slate-400 border border-slate-600/40'"
                        >
                            {{ f.is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </td>
                    <td class="space-x-3 px-4 py-3.5 text-right text-xs">
                        <button class="font-bold text-[#f3f2e7]/80 hover:text-[#f3f2e7] underline" @click="toggle(f)">
                            {{ f.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                        </button>
                        <button class="font-bold text-rose-400 hover:text-rose-300 underline" @click="remove(f)">Hapus</button>
                    </td>
                </tr>
                <tr v-if="!faqs.length">
                    <td colspan="4" class="px-4 py-8 text-center text-sm text-[#f3f2e7]/60">
                        🍃 Belum ada daftar FAQ yang ditambahkan.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

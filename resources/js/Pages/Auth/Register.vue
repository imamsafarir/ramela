<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';

defineOptions({ layout: GuestLayout });

const form = useForm({ username: '', password: '', password_confirmation: '' });
const submit = () => form.post('/register', {
    onFinish: () => form.reset('password', 'password_confirmation'),
});
</script>

<template>
    <Head title="Daftar Akun Baru" />
    <h1 class="text-2xl font-black tracking-tight text-[#f3f2e7]">Daftar Akun Baru</h1>
    <p class="text-xs text-[#f3f2e7]/70 mt-1 mb-5">Bergabung dengan ekosistem terpadu RAMELA.</p>

    <form class="space-y-4" @submit.prevent="submit">
        <div>
            <label class="mb-1.5 block text-xs font-bold text-[#f3f2e7]/80" for="username">Username</label>
            <input id="username" v-model="form.username" type="text" autocomplete="username" autofocus
                placeholder="Pilih username unik"
                class="w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-4 py-2.5 text-sm text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:border-emerald-400 focus:outline-none focus:ring-1 focus:ring-emerald-400" />
            <p class="mt-1 text-[11px] text-[#f3f2e7]/60">4–50 karakter: huruf, angka, strip, garis bawah.</p>
            <p v-if="form.errors.username" class="mt-1 text-xs text-rose-400 font-semibold">{{ form.errors.username }}</p>
        </div>
        <div>
            <label class="mb-1.5 block text-xs font-bold text-[#f3f2e7]/80" for="password">Password</label>
            <input id="password" v-model="form.password" type="password" autocomplete="new-password"
                placeholder="Minimal 8 karakter"
                class="w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-4 py-2.5 text-sm text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:border-emerald-400 focus:outline-none focus:ring-1 focus:ring-emerald-400" />
            <p v-if="form.errors.password" class="mt-1 text-xs text-rose-400 font-semibold">{{ form.errors.password }}</p>
        </div>
        <div>
            <label class="mb-1.5 block text-xs font-bold text-[#f3f2e7]/80" for="password_confirmation">Ulangi Password</label>
            <input id="password_confirmation" v-model="form.password_confirmation" type="password"
                placeholder="Ketik ulang password"
                autocomplete="new-password" class="w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-4 py-2.5 text-sm text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:border-emerald-400 focus:outline-none focus:ring-1 focus:ring-emerald-400" />
        </div>
        <button :disabled="form.processing"
            class="w-full rounded-xl bg-[#0d685b] hover:bg-[#117c6d] py-3 text-sm font-bold text-[#f3f2e7] shadow-md shadow-[#0d685b]/30 transition disabled:opacity-50">
            {{ form.processing ? 'Mendaftarkan Akun...' : 'Daftar Sekarang →' }}
        </button>
    </form>
    <p class="mt-6 text-center text-xs text-[#f3f2e7]/70">
        Sudah punya akun? <Link href="/login" class="font-bold text-emerald-400 hover:text-emerald-300 underline">Masuk di Sini</Link>
    </p>
</template>

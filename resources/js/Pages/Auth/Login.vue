<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';

defineOptions({ layout: GuestLayout });

const form = useForm({ username: '', password: '', remember: false });
const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
</script>

<template>
    <Head title="Masuk ke Akun" />
    <h1 class="text-2xl font-black tracking-tight text-[#f3f2e7]">Masuk ke Akun</h1>
    <p class="text-xs text-[#f3f2e7]/70 mt-1 mb-5">Single Sign-On untuk seluruh layanan RAMELA.</p>

    <form class="space-y-4" @submit.prevent="submit">
        <div>
            <label class="mb-1.5 block text-xs font-bold text-[#f3f2e7]/80" for="username">Username</label>
            <input id="username" v-model="form.username" type="text" autocomplete="username" autofocus
                placeholder="Masukkan username Anda"
                class="w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-4 py-2.5 text-sm text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:border-emerald-400 focus:outline-none focus:ring-1 focus:ring-emerald-400" />
            <p v-if="form.errors.username" class="mt-1 text-xs text-rose-400 font-semibold">{{ form.errors.username }}</p>
        </div>
        <div>
            <label class="mb-1.5 block text-xs font-bold text-[#f3f2e7]/80" for="password">Password</label>
            <input id="password" v-model="form.password" type="password" autocomplete="current-password"
                placeholder="••••••••"
                class="w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-4 py-2.5 text-sm text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:border-emerald-400 focus:outline-none focus:ring-1 focus:ring-emerald-400" />
            <p v-if="form.errors.password" class="mt-1 text-xs text-rose-400 font-semibold">{{ form.errors.password }}</p>
        </div>
        <div class="flex items-center justify-between text-xs text-[#f3f2e7]/80">
            <label class="flex items-center gap-2 cursor-pointer">
                <input v-model="form.remember" type="checkbox" class="rounded accent-[#0d685b]" /> Ingat saya
            </label>
        </div>
        <button :disabled="form.processing"
            class="w-full rounded-xl bg-[#0d685b] hover:bg-[#117c6d] py-3 text-sm font-bold text-[#f3f2e7] shadow-md shadow-[#0d685b]/30 transition disabled:opacity-50">
            {{ form.processing ? 'Memproses...' : 'Masuk Sekarang →' }}
        </button>
    </form>
    <p class="mt-6 text-center text-xs text-[#f3f2e7]/70">
        Belum punya akun? <Link href="/register" class="font-bold text-emerald-400 hover:text-emerald-300 underline">Daftar Akun Baru</Link>
    </p>
</template>

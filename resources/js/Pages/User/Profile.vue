<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import UserLayout from '../../Layouts/UserLayout.vue';

defineOptions({ layout: UserLayout });

const props = defineProps({ profile: Object });

const profileForm = useForm({
    name: props.profile.name ?? '',
    email: props.profile.email ?? '',
    phone: props.profile.phone ?? '',
});

const passwordForm = useForm({ current_password: '', password: '', password_confirmation: '' });

const saveProfile = () => profileForm.patch('/profile', { preserveScroll: true });
const savePassword = () => passwordForm.put('/profile/password', {
    preserveScroll: true,
    onSuccess: () => passwordForm.reset(),
});

const input = 'w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-4 py-2.5 text-sm text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:border-emerald-400 focus:outline-none focus:ring-1 focus:ring-emerald-400';
</script>

<template>
    <Head title="Profil Pengguna" />

    <div class="mb-4">
        <Link href="/dashboard" class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#f3f2e7]/70 hover:text-[#f3f2e7] transition">
            <span>←</span>
            <span>Kembali ke Dashboard</span>
        </Link>
    </div>

    <h1 class="text-2xl font-black text-[#f3f2e7] tracking-tight">Pengaturan Profil</h1>

    <div class="mt-6 grid gap-6 md:grid-cols-2">
        <form class="space-y-4 rounded-2xl bg-[#1c2a25] p-6 border border-[#0d685b]/30 shadow-xl" @submit.prevent="saveProfile">
            <h2 class="text-base font-bold text-[#f3f2e7] border-b border-[#0d685b]/20 pb-3">Data Diri</h2>
            <div>
                <label class="mb-1.5 block text-xs font-bold text-[#f3f2e7]/80">Username</label>
                <input :value="profile.username" disabled :class="input + ' opacity-60 cursor-not-allowed'" />
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-bold text-[#f3f2e7]/80" for="name">Nama Lengkap</label>
                <input id="name" v-model="profileForm.name" :class="input" placeholder="Masukkan nama lengkap" />
                <p v-if="profileForm.errors.name" class="mt-1 text-xs text-rose-400 font-semibold">{{ profileForm.errors.name }}</p>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-bold text-[#f3f2e7]/80" for="email">Alamat Email</label>
                <input id="email" v-model="profileForm.email" type="email" :class="input" placeholder="email@contoh.com" />
                <p v-if="profileForm.errors.email" class="mt-1 text-xs text-rose-400 font-semibold">{{ profileForm.errors.email }}</p>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-bold text-[#f3f2e7]/80" for="phone">Nomor Telepon / WhatsApp</label>
                <input id="phone" v-model="profileForm.phone" :class="input" placeholder="08xxxxxxxxxx" />
                <p v-if="profileForm.errors.phone" class="mt-1 text-xs text-rose-400 font-semibold">{{ profileForm.errors.phone }}</p>
            </div>
            <button :disabled="profileForm.processing"
                class="rounded-xl bg-[#0d685b] hover:bg-[#117c6d] px-6 py-2.5 text-xs font-bold text-[#f3f2e7] shadow-md shadow-[#0d685b]/30 transition disabled:opacity-50">
                Simpan Perubahan
            </button>
        </form>

        <form class="space-y-4 rounded-2xl bg-[#1c2a25] p-6 border border-[#0d685b]/30 shadow-xl" @submit.prevent="savePassword">
            <h2 class="text-base font-bold text-[#f3f2e7] border-b border-[#0d685b]/20 pb-3">Ganti Kata Sandi</h2>
            <div>
                <label class="mb-1.5 block text-xs font-bold text-[#f3f2e7]/80" for="current_password">Password Saat Ini</label>
                <input id="current_password" v-model="passwordForm.current_password" type="password"
                    autocomplete="current-password" :class="input" placeholder="••••••••" />
                <p v-if="passwordForm.errors.current_password" class="mt-1 text-xs text-rose-400 font-semibold">
                    {{ passwordForm.errors.current_password }}
                </p>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-bold text-[#f3f2e7]/80" for="new_password">Password Baru</label>
                <input id="new_password" v-model="passwordForm.password" type="password"
                    autocomplete="new-password" :class="input" placeholder="Minimal 8 karakter" />
                <p v-if="passwordForm.errors.password" class="mt-1 text-xs text-rose-400 font-semibold">
                    {{ passwordForm.errors.password }}
                </p>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-bold text-[#f3f2e7]/80" for="confirm_password">Ulangi Password Baru</label>
                <input id="confirm_password" v-model="passwordForm.password_confirmation" type="password"
                    autocomplete="new-password" :class="input" placeholder="Ketik ulang password baru" />
            </div>
            <button :disabled="passwordForm.processing"
                class="rounded-xl bg-[#0d685b] hover:bg-[#117c6d] px-6 py-2.5 text-xs font-bold text-[#f3f2e7] shadow-md shadow-[#0d685b]/30 transition disabled:opacity-50">
                Perbarui Password
            </button>
        </form>
    </div>
</template>

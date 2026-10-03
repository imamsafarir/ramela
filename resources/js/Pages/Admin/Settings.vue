<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    midtrans: Object,
    features: Object,
    webhookUrl: String,
    urls: Object,
});

const form = useForm({
    is_production: props.midtrans.is_production,
    merchant_id: '',
    client_key: '',
    server_key: '',
    feature_blog: props.features.blog,
    feature_faq: props.features.faq,
});

const copied = ref(false);
const copyWebhook = () => {
    navigator.clipboard.writeText(props.webhookUrl);
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 2000);
};

const submit = () => {
    form.put('/admin/pengaturan', {
        preserveScroll: true,
        onSuccess: () => form.reset('merchant_id', 'client_key', 'server_key'),
    });
};

const inputClass = 'w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2.5 text-xs text-[#f3f2e7] shadow-sm placeholder:text-[#f3f2e7]/40 focus:border-[#0d685b] focus:outline-none';
const secrets = [
    ['merchant_id', 'Merchant ID'],
    ['client_key', 'Client Key'],
    ['server_key', 'Server Key'],
];
</script>

<template>
    <Head title="Pengaturan Website & Midtrans - Admin" />

    <div class="mx-auto max-w-4xl space-y-6">
        <!-- HEADER -->
        <div>
            <h1 class="text-2xl font-black tracking-tight text-[#f3f2e7] sm:text-3xl">
                Pengaturan Sistem & Gateway
            </h1>
            <p class="mt-1 text-xs text-[#f3f2e7]/60 sm:text-sm">
                Konfigurasi payment gateway Midtrans untuk isi saldo otomatis serta sakelar fitur publik website.
            </p>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <!-- SECTION PAYMENT GATEWAY MIDTRANS -->
            <div class="overflow-hidden rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-6 shadow-xl text-[#f3f2e7]">
                <div class="flex items-center justify-between border-b border-[#0d685b]/20 pb-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#0d685b]/30 text-xl text-[#f3f2e7]">
                            💳
                        </span>
                        <div>
                            <h2 class="text-base font-black text-[#f3f2e7]">Payment Gateway Midtrans</h2>
                            <p class="text-xs text-[#f3f2e7]/60">Kunci API disimpan secara terenkripsi aman di database server.</p>
                        </div>
                    </div>
                    <span
                        class="rounded-full px-3 py-1 text-[11px] font-bold"
                        :class="form.is_production ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-amber-500/20 text-amber-300 border border-amber-500/40'"
                    >
                        {{ form.is_production ? '● Mode Production (Live)' : '● Mode Sandbox (Testing)' }}
                    </span>
                </div>

                <div class="mt-5 space-y-5">
                    <!-- Environment Mode Switch -->
                    <label class="flex items-start gap-3 rounded-xl border border-[#0d685b]/20 bg-[#131d1a] p-3.5 cursor-pointer">
                        <input
                            v-model="form.is_production"
                            type="checkbox"
                            class="mt-0.5 h-4 w-4 rounded accent-[#0d685b]"
                        />
                        <div>
                            <span class="block text-xs font-bold text-[#f3f2e7]">Aktifkan Mode Production</span>
                            <span class="block text-[11px] text-[#f3f2e7]/60">
                                Centang untuk menerima pembayaran uang asli melalui Midtrans. Hilangkan centang jika ingin menggunakan simulasi Sandbox simulator.
                            </span>
                        </div>
                    </label>

                    <!-- API Keys -->
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div v-for="[name, title] in secrets" :key="name">
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#0d685b]">{{ title }}</label>
                            <input
                                v-model="form[name]"
                                type="password"
                                autocomplete="off"
                                :class="inputClass"
                                :placeholder="midtrans.secrets[name] ? `Tersimpan (${midtrans.secrets[name]})` : 'Belum dikonfigurasi'"
                            />
                            <p v-if="form.errors[name]" class="mt-1 text-xs text-rose-400">{{ form.errors[name] }}</p>
                        </div>
                    </div>

                    <!-- Webhook Notification URL -->
                    <div class="rounded-xl border border-[#0d685b]/30 bg-[#131d1a] p-4">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <p class="text-xs font-bold text-[#f3f2e7]">URL Notifikasi Webhook (Payment Notification URL)</p>
                                <p class="text-[11px] text-[#f3f2e7]/60">Salin URL ini dan tempelkan ke Dashboard Midtrans → Settings → Configuration.</p>
                            </div>
                            <button
                                type="button"
                                class="shrink-0 rounded-xl bg-[#0d685b] px-3.5 py-1.5 text-xs font-bold text-[#f3f2e7] shadow-sm hover:bg-[#0d685b]/90 transition"
                                @click="copyWebhook"
                            >
                                {{ copied ? 'Tersalin! ✓' : 'Salin URL' }}
                            </button>
                        </div>
                        <div class="mt-2.5 rounded-xl bg-[#17231f] p-2.5 text-xs font-mono text-emerald-300 break-all select-all border border-[#0d685b]/30">
                            {{ webhookUrl }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTION FITUR PUBLIK -->
            <div class="overflow-hidden rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-6 shadow-xl text-[#f3f2e7]">
                <div class="flex items-center gap-3 border-b border-[#0d685b]/20 pb-4">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#0d685b]/30 text-xl text-[#f3f2e7]">
                        ⚡
                    </span>
                    <div>
                        <h2 class="text-base font-black text-[#f3f2e7]">Sakelar Fitur Website</h2>
                        <p class="text-xs text-[#f3f2e7]/60">Kontrol ketersediaan modul halaman publik di website.</p>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label class="flex items-center gap-3 rounded-xl border border-[#0d685b]/20 bg-[#131d1a] p-3.5 cursor-pointer hover:border-[#0d685b]/50 transition">
                        <input
                            v-model="form.feature_blog"
                            type="checkbox"
                            class="h-4 w-4 rounded accent-[#0d685b]"
                        />
                        <div>
                            <span class="block text-xs font-bold text-[#f3f2e7]">Aktifkan Halaman Blog & Berita</span>
                            <span class="block text-[11px] text-[#f3f2e7]/60">Tampilkan artikel berita di menu publik toko</span>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 rounded-xl border border-[#0d685b]/20 bg-[#131d1a] p-3.5 cursor-pointer hover:border-[#0d685b]/50 transition">
                        <input
                            v-model="form.feature_faq"
                            type="checkbox"
                            class="h-4 w-4 rounded accent-[#0d685b]"
                        />
                        <div>
                            <span class="block text-xs font-bold text-[#f3f2e7]">Aktifkan Tanya Jawab (FAQ)</span>
                            <span class="block text-[11px] text-[#f3f2e7]/60">Tampilkan pertanyaan umum pelanggan di landing page</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- TOMBOL SIMPAN -->
            <div class="flex justify-end gap-3">
                <button
                    :disabled="form.processing"
                    class="rounded-xl bg-[#0d685b] px-6 py-2.5 text-xs font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 transition hover:bg-[#0d685b]/90 disabled:opacity-50"
                >
                    {{ form.processing ? 'Menyimpan...' : 'Simpan Semua Pengaturan' }}
                </button>
            </div>
        </form>
    </div>
</template>

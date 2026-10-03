<script setup>
import { Head, Link, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import UserLayout from "../../Layouts/UserLayout.vue";
import { rupiah } from "../../utils/format";

defineOptions({ layout: UserLayout });

const props = defineProps({
    store: Object,
    items: Array,
    total: String,
    defaults: Object,
    promo: Object,
    promoError: String,
});

const page = usePage();
const saldo = computed(() => page.props.auth.user.saldo);
const final = computed(() =>
    props.promo ? Number(props.total) - Number(props.promo.discount) : Number(props.total),
);
const kurang = computed(() => Number(saldo.value) < final.value);

const promoInput = ref(props.promo?.code ?? "");
const applyPromo = () =>
    router.get(
        `/checkout/${props.store.slug}`,
        promoInput.value ? { promo: promoInput.value } : {},
        { preserveState: true, preserveScroll: true, only: ["promo", "promoError"] },
    );

const form = useForm({
    recipient_name: props.defaults.recipient_name,
    recipient_phone: props.defaults.recipient_phone,
    shipping_address: "",
    shipping_latitude: null,
    shipping_longitude: null,
    note: "",
});

const detectLocation = () => {
    if (!navigator.geolocation) {
        alert('Browser tidak mendukung Geolocation.');
        return;
    }
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            form.shipping_latitude = pos.coords.latitude;
            form.shipping_longitude = pos.coords.longitude;
        },
        (err) => alert('Gagal mendeteksi lokasi GPS: ' + err.message),
        { enableHighAccuracy: true }
    );
};

const submit = () =>
    form
        .transform((d) => ({ ...d, promo_code: props.promo?.code ?? null }))
        .post(`/checkout/${props.store.slug}`);
const input = "w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-4 py-2.5 text-sm text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:border-emerald-400 focus:outline-none focus:ring-1 focus:ring-emerald-400";
</script>

<template>
    <Head title="Checkout" />

    <div class="mb-4">
        <Link href="/keranjang" class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#f3f2e7]/70 hover:text-[#f3f2e7] transition">
            <span>←</span>
            <span>Kembali ke Keranjang</span>
        </Link>
    </div>

    <h1 class="text-2xl font-black text-[#f3f2e7] tracking-tight">Checkout · {{ store.name }}</h1>

    <div class="mt-6 grid gap-6 md:grid-cols-2">
        <form
            class="space-y-4 rounded-2xl bg-[#1c2a25] p-6 border border-[#0d685b]/30 shadow-xl"
            @submit.prevent="submit"
        >
            <h2 class="text-base font-bold text-[#f3f2e7] border-b border-[#0d685b]/20 pb-3">Tujuan Pengiriman</h2>
            <div>
                <label class="mb-1.5 block text-xs font-bold text-[#f3f2e7]/80">Nama Penerima</label>
                <input v-model="form.recipient_name" :class="input" placeholder="Nama penerima paket" />
                <p
                    v-if="form.errors.recipient_name"
                    class="mt-1 text-xs text-rose-400 font-semibold"
                >
                    {{ form.errors.recipient_name }}
                </p>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-bold text-[#f3f2e7]/80">Nomor Telepon / WhatsApp</label>
                <input v-model="form.recipient_phone" :class="input" placeholder="08xxxxxxxxxx" />
                <p
                    v-if="form.errors.recipient_phone"
                    class="mt-1 text-xs text-rose-400 font-semibold"
                >
                    {{ form.errors.recipient_phone }}
                </p>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-bold text-[#f3f2e7]/80">Alamat Lengkap</label>
                <textarea
                    v-model="form.shipping_address"
                    rows="3"
                    :class="input"
                    placeholder="Nama jalan, nomor rumah, RT/RW, patokan..."
                />
                <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs">
                    <button
                        type="button"
                        class="flex items-center gap-1 font-bold text-emerald-400 hover:text-emerald-300 transition"
                        @click="detectLocation"
                    >
                        📍 Pin Titik Koordinat GPS Saat Ini
                    </button>
                    <span v-if="form.shipping_latitude && form.shipping_longitude" class="font-semibold text-emerald-300">
                        ✓ Terdeteksi ({{ Number(form.shipping_latitude).toFixed(4) }}, {{ Number(form.shipping_longitude).toFixed(4) }})
                    </span>
                </div>
                <p
                    v-if="form.errors.shipping_address"
                    class="mt-1 text-xs text-rose-400 font-semibold"
                >
                    {{ form.errors.shipping_address }}
                </p>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-bold text-[#f3f2e7]/80">Catatan Khusus (Opsional)</label>
                <input v-model="form.note" :class="input" placeholder="Misal: titip di pos satpam..." />
            </div>

            <p
                v-if="form.errors.checkout"
                class="rounded-xl bg-rose-950/40 border border-rose-500/40 px-4 py-2.5 text-xs text-rose-200"
            >
                {{ form.errors.checkout }}
            </p>

            <button
                :disabled="form.processing || kurang"
                class="w-full rounded-xl bg-[#0d685b] hover:bg-[#117c6d] py-3 text-sm font-bold text-[#f3f2e7] shadow-md shadow-[#0d685b]/30 transition disabled:bg-slate-800 disabled:text-slate-500 disabled:cursor-not-allowed"
            >
                Bayar {{ rupiah(final) }} dengan Saldo Dompet
            </button>
            <p v-if="kurang" class="text-xs font-semibold text-rose-400">
                ⚠️ Saldo tidak cukup (saldo {{ rupiah(saldo) }}). Silakan isi saldo terlebih dahulu.
            </p>
        </form>

        <div class="h-fit rounded-2xl bg-[#1c2a25] p-6 border border-[#0d685b]/30 shadow-xl">
            <h2 class="mb-4 text-base font-bold text-[#f3f2e7] border-b border-[#0d685b]/20 pb-3">Ringkasan Pesanan</h2>
            <ul class="divide-y divide-[#0d685b]/20 text-sm">
                <li
                    v-for="(i, idx) in items"
                    :key="idx"
                    class="flex justify-between py-2.5 text-[#f3f2e7]"
                >
                    <span>{{ i.name }} × {{ i.quantity }}</span>
                    <span class="font-bold">{{ rupiah(i.subtotal) }}</span>
                </li>
            </ul>
            <div class="mt-4 flex gap-2">
                <input
                    v-model="promoInput"
                    placeholder="Kode promo..."
                    class="w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2 text-sm text-[#f3f2e7] uppercase placeholder:text-[#f3f2e7]/40 focus:border-emerald-400 focus:outline-none"
                    @keydown.enter.prevent="applyPromo"
                />
                <button
                    type="button"
                    class="rounded-xl bg-[#f3f2e7] hover:bg-white text-[#17231f] px-4 py-2 text-xs font-bold transition shadow-xs"
                    @click="applyPromo"
                >
                    Pakai
                </button>
            </div>
            <p v-if="promoError" class="mt-1.5 text-xs text-rose-400 font-semibold">{{ promoError }}</p>
            <p v-if="promo" class="mt-3 flex justify-between text-sm text-emerald-400 font-semibold">
                <span>Promo ({{ promo.code }})</span><span>−{{ rupiah(promo.discount) }}</span>
            </p>
            <div class="mt-4 border-t border-[#0d685b]/20 pt-3 flex justify-between items-baseline">
                <span class="text-sm font-semibold text-[#f3f2e7]/80">Total Pembayaran</span>
                <span class="text-xl font-black text-emerald-400">{{ rupiah(final) }}</span>
            </div>
            <div class="mt-4 text-center">
                <Link
                    href="/keranjang"
                    class="text-xs text-[#f3f2e7]/70 underline hover:text-[#f3f2e7] transition"
                >
                    Ubah isi keranjang
                </Link>
            </div>
        </div>
    </div>
</template>

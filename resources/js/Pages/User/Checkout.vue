<script setup>
import { Head, Link, router, useForm, usePage } from "@inertiajs/vue3";
import { computed, ref, watch } from "vue";
import UserLayout from "../../Layouts/UserLayout.vue";
import LocationPicker from "../../Components/LocationPicker.vue";
import { rupiah } from "../../utils/format";

defineOptions({ layout: UserLayout });

const props = defineProps({
    store: Object,
    items: Array,
    total: String,
    totalWeight: {
        type: [Number, String],
        default: 0,
    },
    defaults: Object,
    promo: Object,
    promoError: String,
    shippingRates: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const saldo = computed(() => page.props.auth.user.saldo);

const form = useForm({
    delivery_type: "courier", // 'courier' | 'pickup'
    recipient_name: props.defaults.recipient_name,
    recipient_phone: props.defaults.recipient_phone,
    shipping_rate_id: props.shippingRates.length ? props.shippingRates[0].id : "",
    shipping_district: "",
    shipping_postal_code: "",
    shipping_address: "",
    shipping_latitude: null,
    shipping_longitude: null,
    note: "",
});

const selectedRate = computed(() => {
    if (form.delivery_type !== "courier") return null;
    return props.shippingRates.find((r) => Number(r.id) === Number(form.shipping_rate_id)) || null;
});

const shippingCost = computed(() => {
    if (form.delivery_type !== "courier" || !selectedRate.value) return 0;
    const baseCost = Number(selectedRate.value.shipping_cost);
    if (selectedRate.value.pricing_type === "flat") {
        return baseCost;
    }
    const weightInGrams = Number(props.totalWeight) || 0;
    const billedKg = Math.max(1, Math.ceil(weightInGrams / 1000));
    return baseCost * billedKg;
});

const discountAmount = computed(() => (props.promo ? Number(props.promo.discount) : 0));
const productSubtotal = computed(() => Number(props.total));
const final = computed(() => {
    const discountedProducts = Math.max(0, productSubtotal.value - discountAmount.value);
    return discountedProducts + shippingCost.value;
});

const kurang = computed(() => Number(saldo.value) < final.value);

const promoInput = ref(props.promo?.code ?? "");
const applyPromo = () =>
    router.get(
        `/checkout/${props.store.slug}`,
        promoInput.value ? { promo: promoInput.value } : {},
        { preserveState: true, preserveScroll: true, only: ["promo", "promoError"] },
    );

const defaultStoreCenter = computed(() => {
    const lat = Number(props.store?.latitude);
    const lng = Number(props.store?.longitude);
    if (!isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0) {
        return { lat, lng };
    }
    return { lat: -6.989720, lng: 110.421930 };
});

const onLocationSelected = (data) => {
    form.shipping_latitude = data.latitude;
    form.shipping_longitude = data.longitude;
    if (data.formatted_address || data.street_name) {
        form.shipping_address = data.formatted_address || data.street_name;
    }
    if (data.district) {
        form.shipping_district = data.district;
    }
    if (data.postal_code) {
        form.shipping_postal_code = data.postal_code;
    }
};

const submit = () =>
    form
        .transform((d) => ({
            ...d,
            promo_code: props.promo?.code ?? null,
        }))
        .post(`/checkout/${props.store.slug}`);

const input =
    "w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-4 py-2.5 text-sm text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:border-emerald-400 focus:outline-none focus:ring-1 focus:ring-emerald-400";
</script>

<template>
    <Head title="Checkout" />

    <div class="mb-4">
        <Link
            href="/keranjang"
            class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#f3f2e7]/70 hover:text-[#f3f2e7] transition"
        >
            <span>←</span>
            <span>Kembali ke Keranjang</span>
        </Link>
    </div>

    <h1 class="text-2xl font-black text-[#f3f2e7] tracking-tight">Checkout · {{ store.name }}</h1>

    <div class="mt-6 grid gap-6 lg:grid-cols-12">
        <!-- FORM PENGIRIMAN & PENERIMA -->
        <form
            id="checkout-form"
            class="space-y-5 rounded-2xl bg-[#1c2a25] p-4.5 sm:p-6 border border-[#0d685b]/30 shadow-xl lg:col-span-7"
            @submit.prevent="submit"
        >
            <!-- PILIHAN METODE PENGIRIMAN -->
            <div>
                <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-[#0d685b]">
                    Metode Transaksi & Pengambilan
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        class="flex flex-col items-center justify-center gap-1.5 rounded-xl border p-3.5 text-center transition"
                        :class="
                            form.delivery_type === 'courier'
                                ? 'border-emerald-400 bg-emerald-500/20 text-emerald-300 font-bold shadow-sm shadow-emerald-500/20'
                                : 'border-[#0d685b]/40 bg-[#131d1a] text-[#f3f2e7]/70 hover:bg-[#182722]'
                        "
                        @click="form.delivery_type = 'courier'"
                    >
                        <span class="text-2xl">🛵</span>
                        <span class="text-xs font-bold">Kirim via Kurir</span>
                        <span class="text-[10px] text-[#f3f2e7]/60">Diantar sampai alamat</span>
                    </button>

                    <button
                        type="button"
                        class="flex flex-col items-center justify-center gap-1.5 rounded-xl border p-3.5 text-center transition"
                        :class="
                            form.delivery_type === 'pickup'
                                ? 'border-emerald-400 bg-emerald-500/20 text-emerald-300 font-bold shadow-sm shadow-emerald-500/20'
                                : 'border-[#0d685b]/40 bg-[#131d1a] text-[#f3f2e7]/70 hover:bg-[#182722]'
                        "
                        @click="form.delivery_type = 'pickup'"
                    >
                        <span class="text-2xl">🏪</span>
                        <span class="text-xs font-bold">Ambil Sendiri (Pickup)</span>
                        <span class="text-[10px] text-emerald-400 font-semibold">Gratis Ongkir (Rp 0)</span>
                    </button>
                </div>
                <p v-if="form.errors.delivery_type" class="mt-1 text-xs text-rose-400 font-semibold">
                    {{ form.errors.delivery_type }}
                </p>
            </div>

            <!-- BANNER INFO JIKA PICKUP -->
            <div
                v-if="form.delivery_type === 'pickup'"
                class="rounded-xl border border-emerald-500/30 bg-emerald-950/40 p-4 text-xs text-emerald-200 space-y-1"
            >
                <div class="flex items-center gap-2 font-bold text-emerald-300">
                    <span>🏪</span>
                    <span>Ambil Langsung di Alamat Toko {{ store.name }}</span>
                </div>
                <p class="text-[#f3f2e7]/80 text-[11px] leading-relaxed">
                    Pesanan Anda akan disiapkan oleh toko. Anda tidak dikenakan biaya pengiriman kurir. Silakan datang ke toko setelah status pesanan berubah menjadi <strong>Siap Diambil</strong>.
                </p>
            </div>

            <h2 class="text-base font-bold text-[#f3f2e7] border-b border-[#0d685b]/20 pb-2 pt-2">
                {{ form.delivery_type === 'courier' ? 'Informasi Penerima & Alamat Tujuan' : 'Data Pemesan / Pengambil' }}
            </h2>

            <!-- DATA PENERIMA / PENGAMBIL -->
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-xs font-bold text-[#f3f2e7]/80">
                        {{ form.delivery_type === 'courier' ? 'Nama Penerima' : 'Nama Pengambil' }}
                    </label>
                    <input
                        v-model="form.recipient_name"
                        :class="input"
                        placeholder="Nama lengkap"
                    />
                    <p v-if="form.errors.recipient_name" class="mt-1 text-xs text-rose-400 font-semibold">
                        {{ form.errors.recipient_name }}
                    </p>
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-bold text-[#f3f2e7]/80">Nomor WhatsApp / Telepon</label>
                    <input
                        v-model="form.recipient_phone"
                        :class="input"
                        placeholder="08xxxxxxxxxx"
                    />
                    <p v-if="form.errors.recipient_phone" class="mt-1 text-xs text-rose-400 font-semibold">
                        {{ form.errors.recipient_phone }}
                    </p>
                </div>
            </div>

            <!-- FORM ALAMAT LENGKAP KHUSUS KURIR -->
            <div v-if="form.delivery_type === 'courier'" class="space-y-4 pt-1">
                <!-- Dropdown Kab/Kota (Tarif Ditentukan Admin) -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold text-[#f3f2e7]/80">
                            Kabupaten / Kota (Tarif Kurir Ditentukan Admin)
                        </label>
                        <span v-if="selectedRate" class="text-xs font-bold text-emerald-400">
                            + {{ rupiah(shippingCost) }}
                        </span>
                    </div>
                    <select v-model="form.shipping_rate_id" :class="input">
                        <option value="" disabled>-- Pilih Kabupaten / Kota Tujuan --</option>
                        <option v-for="r in shippingRates" :key="r.id" :value="r.id">
                            {{ r.city_name }} — {{ rupiah(r.shipping_cost) }}{{ r.pricing_type === 'flat' ? ' (Flat)' : ' / kg' }} (Estimasi: {{ r.estimated_delivery || '1 Hari' }})
                        </option>
                    </select>
                    <p v-if="!shippingRates.length" class="mt-1 text-xs text-amber-300">
                        ⚠️ Belum ada tarif wilayah yang ditentukan oleh Admin. Hubungi Admin.
                    </p>
                    <p v-if="form.errors.shipping_rate_id" class="mt-1 text-xs text-rose-400 font-semibold">
                        {{ form.errors.shipping_rate_id }}
                    </p>
                </div>

                <!-- Baris Kecamatan/Kelurahan & Kode Pos -->
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-bold text-[#f3f2e7]/80">Kecamatan / Kelurahan (Kec/Kel)</label>
                        <input
                            v-model="form.shipping_district"
                            :class="input"
                            placeholder="Contoh: Kec. Tarakan Barat, Kel. Karang Anyar"
                        />
                        <p v-if="form.errors.shipping_district" class="mt-1 text-xs text-rose-400 font-semibold">
                            {{ form.errors.shipping_district }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-[#f3f2e7]/80">Kode Pos</label>
                        <input
                            v-model="form.shipping_postal_code"
                            :class="input"
                            placeholder="Contoh: 77111"
                        />
                        <p v-if="form.errors.shipping_postal_code" class="mt-1 text-xs text-rose-400 font-semibold">
                            {{ form.errors.shipping_postal_code }}
                        </p>
                    </div>
                </div>

                <!-- PETA INTERAKTIF OPENSTREETMAP & AMBIL NAMA JALAN GOOGLE -->
                <div>
                    <LocationPicker
                        v-model:latitude="form.shipping_latitude"
                        v-model:longitude="form.shipping_longitude"
                        v-model:address="form.shipping_address"
                        v-model:district="form.shipping_district"
                        v-model:postalCode="form.shipping_postal_code"
                        :default-center="defaultStoreCenter"
                        label="Pin Titik Lokasi Penerima (OpenStreetMap)"
                        @location-selected="onLocationSelected"
                    />
                    <p v-if="form.errors.shipping_latitude || form.errors.shipping_longitude" class="mt-1 text-xs text-rose-400 font-semibold">
                        {{ form.errors.shipping_latitude || form.errors.shipping_longitude }}
                    </p>
                </div>

                <!-- Detail Alamat Lengkap -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-bold text-[#f3f2e7]/80">
                            Detail Alamat Lengkap (Nama Jalan, No. Rumah, Patokan)
                        </label>
                        <span class="text-[10px] text-[#f3f2e7]/50">
                            Terisi otomatis dari pin lokasi & dapat diedit
                        </span>
                    </div>
                    <textarea
                        v-model="form.shipping_address"
                        rows="3"
                        :class="input"
                        placeholder="Nama jalan, nomor rumah, RT/RW, gang, blok, patokan lokasi..."
                    />
                    <p v-if="form.errors.shipping_address" class="mt-1 text-xs text-rose-400 font-semibold">
                        {{ form.errors.shipping_address }}
                    </p>
                </div>
            </div>

            <!-- Catatan Khusus -->
            <div>
                <label class="mb-1.5 block text-xs font-bold text-[#f3f2e7]/80">
                    {{ form.delivery_type === 'courier' ? 'Catatan untuk Kurir (Opsional)' : 'Catatan Pengambilan (Opsional)' }}
                </label>
                <input
                    v-model="form.note"
                    :class="input"
                    :placeholder="form.delivery_type === 'courier' ? 'Misal: titip di pos satpam, pagar hitam...' : 'Misal: akan diambil pukul 14.00 oleh adik saya...'"
                />
            </div>
        </form>

        <!-- RINGKASAN PESANAN -->
        <div class="h-fit rounded-2xl bg-[#1c2a25] p-4.5 sm:p-6 border border-[#0d685b]/30 shadow-xl lg:col-span-5">
            <h2 class="mb-4 text-base font-bold text-[#f3f2e7] border-b border-[#0d685b]/20 pb-3">Ringkasan Pesanan</h2>
            <ul class="divide-y divide-[#0d685b]/20 text-sm">
                <li
                    v-for="(i, idx) in items"
                    :key="idx"
                    class="flex justify-between py-2.5 text-[#f3f2e7]"
                >
                    <div>
                        <span class="block font-medium">{{ i.name }}</span>
                        <span class="text-xs text-[#f3f2e7]/60">{{ i.quantity }} × {{ rupiah(i.price) }}</span>
                    </div>
                    <span class="font-bold">{{ rupiah(i.subtotal) }}</span>
                </li>
            </ul>

            <!-- VOUCHER PROMO -->
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
                <span>Promo ({{ promo.code }})</span>
                <span>−{{ rupiah(promo.discount) }}</span>
            </p>

            <!-- RINCIAN KALKULASI HARGA -->
            <div class="mt-4 border-t border-[#0d685b]/20 pt-3 space-y-2 text-xs text-[#f3f2e7]/80">
                <div class="flex justify-between">
                    <span>Total Berat</span>
                    <span class="font-semibold text-[#f3f2e7]">
                        {{ Number(totalWeight) >= 1000 ? (Number(totalWeight) / 1000).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + ' kg' : Number(totalWeight) + ' g' }}
                    </span>
                </div>

                <div class="flex justify-between">
                    <span>Subtotal Produk</span>
                    <span class="font-semibold text-[#f3f2e7]">{{ rupiah(productSubtotal) }}</span>
                </div>

                <div class="flex justify-between items-center">
                    <span class="flex items-center gap-1">
                        <span>Ongkos Kirim:</span>
                        <span class="font-bold text-emerald-400">
                            {{ form.delivery_type === 'courier' ? (selectedRate ? selectedRate.city_name : 'Kurir') : 'Pickup Toko' }}
                        </span>
                    </span>
                    <span class="font-semibold" :class="form.delivery_type === 'pickup' ? 'text-emerald-400' : 'text-[#f3f2e7]'">
                        {{ form.delivery_type === 'pickup' ? 'Gratis (Rp 0)' : (selectedRate ? rupiah(shippingCost) : 'Pilih Wilayah') }}
                    </span>
                </div>

                <div v-if="promo" class="flex justify-between text-emerald-400">
                    <span>Diskon Promo</span>
                    <span>−{{ rupiah(promo.discount) }}</span>
                </div>
            </div>

            <!-- TOTAL PEMBAYARAN -->
            <div class="mt-4 border-t border-[#0d685b]/20 pt-3 flex justify-between items-baseline">
                <span class="text-sm font-semibold text-[#f3f2e7]/80">Total Pembayaran</span>
                <span class="text-xl font-black text-emerald-400">{{ rupiah(final) }}</span>
            </div>

            <!-- Saldo Info -->
            <div class="mt-3 flex items-center justify-between rounded-xl bg-[#131d1a] border border-[#0d685b]/30 p-2.5 text-xs">
                <span class="text-[#f3f2e7]/60">Saldo Anda:</span>
                <span class="font-bold" :class="kurang ? 'text-rose-400' : 'text-emerald-300'">{{ rupiah(saldo) }}</span>
            </div>

            <!-- Pesan Error / Validasi -->
            <p
                v-if="form.errors.checkout"
                class="mt-3 rounded-xl bg-rose-950/40 border border-rose-500/40 px-3.5 py-2 text-xs text-rose-200"
            >
                {{ form.errors.checkout }}
            </p>

            <div v-if="kurang" class="mt-3 rounded-xl bg-rose-950/40 border border-rose-500/40 p-3 text-xs text-rose-200 space-y-1.5">
                <p class="font-semibold">⚠️ Saldo dompet Anda tidak cukup.</p>
                <p class="text-[11px] text-rose-300/80">
                    Dibutuhkan <strong class="text-white">{{ rupiah(final) }}</strong>, saldo saat ini <strong class="text-white">{{ rupiah(saldo) }}</strong>.
                </p>
                <div class="pt-1">
                    <Link
                        href="/topup"
                        class="inline-flex items-center gap-1 font-bold text-emerald-400 hover:text-emerald-300 underline"
                    >
                        💳 Isi Saldo Dompet Sekarang →
                    </Link>
                </div>
            </div>

            <!-- TOMBOL BAYAR SEKARANG -->
            <div class="mt-4 pt-2">
                <button
                    type="submit"
                    form="checkout-form"
                    :disabled="form.processing || kurang || (form.delivery_type === 'courier' && !form.shipping_rate_id)"
                    class="w-full rounded-xl bg-[#0d685b] hover:bg-[#117c6d] py-3.5 px-4 text-sm font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 transition disabled:bg-slate-800 disabled:text-slate-500 disabled:cursor-not-allowed cursor-pointer active:scale-[0.99] flex items-center justify-center gap-2"
                >
                    <span v-if="form.processing" class="animate-spin">🔄</span>
                    <span>Bayar {{ rupiah(final) }} dengan Saldo</span>
                </button>
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

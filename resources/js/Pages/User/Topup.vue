<script setup>
import { Head, Link, router, useForm, usePage } from "@inertiajs/vue3";
import axios from "axios";
import { computed, onMounted, ref } from "vue";
import UserLayout from "../../Layouts/UserLayout.vue";
import { fmtDate, rupiah } from "../../utils/format";

defineOptions({ layout: UserLayout });

const props = defineProps({
    ready: Boolean,
    min: Number,
    max: Number,
    clientKey: {
        type: String,
        default: null,
    },
    snapJsUrl: {
        type: String,
        default: null,
    },
    history: {
        type: Array,
        default: () => [],
    },
    walletHistory: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

const form = useForm({ amount: 50000 });
const presets = [25000, 50000, 100000, 250000, 500000, 1000000];

const isSnapLoaded = ref(false);
const isPaying = ref(false);
const paymentError = ref("");

const loadSnapScript = () => {
    return new Promise((resolve) => {
        if (typeof window !== "undefined" && window.snap) {
            isSnapLoaded.value = true;
            resolve(window.snap);
            return;
        }

        const scriptId = "midtrans-snap-script";
        let script = document.getElementById(scriptId);

        if (script) {
            if (window.snap) {
                isSnapLoaded.value = true;
                resolve(window.snap);
                return;
            }
            script.addEventListener("load", () => {
                isSnapLoaded.value = true;
                resolve(window.snap);
            });
            script.addEventListener("error", () => resolve(null));
            return;
        }

        if (!props.snapJsUrl) {
            resolve(null);
            return;
        }

        script = document.createElement("script");
        script.id = scriptId;
        script.src = props.snapJsUrl;
        if (props.clientKey) {
            script.setAttribute("data-client-key", props.clientKey);
        }
        script.async = true;
        script.onload = () => {
            isSnapLoaded.value = true;
            resolve(window.snap);
        };
        script.onerror = () => {
            resolve(null);
        };
        document.body.appendChild(script);
    });
};

onMounted(() => {
    if (props.ready && props.snapJsUrl) {
        loadSnapScript();
    }
});

const submit = async () => {
    if (isPaying.value || !props.ready) return;
    paymentError.value = "";
    form.clearErrors();

    if (!form.amount || form.amount < props.min || form.amount > props.max) {
        form.setError("amount", `Nominal harus antara ${rupiah(props.min)} dan ${rupiah(props.max)}`);
        return;
    }

    isPaying.value = true;

    try {
        await loadSnapScript();

        const response = await axios.post(
            "/topup",
            { amount: form.amount },
            { headers: { Accept: "application/json" } }
        );

        const { token, redirect_url, order_id } = response.data;

        if (window.snap && token) {
            window.snap.pay(token, {
                onSuccess: (result) => {
                    isPaying.value = false;
                    const finalOrderId = result?.order_id || order_id;
                    router.visit(`/topup?order_id=${encodeURIComponent(finalOrderId)}`, {
                        preserveScroll: true,
                    });
                },
                onPending: (result) => {
                    isPaying.value = false;
                    const finalOrderId = result?.order_id || order_id;
                    router.visit(`/topup?order_id=${encodeURIComponent(finalOrderId)}`, {
                        preserveScroll: true,
                    });
                },
                onError: (result) => {
                    isPaying.value = false;
                    paymentError.value = result?.status_message || "Pembayaran dibatalkan atau gagal diproses.";
                    router.reload({ preserveScroll: true });
                },
                onClose: () => {
                    isPaying.value = false;
                    router.reload({ preserveScroll: true });
                },
            });
        } else if (redirect_url) {
            // Fallback bila snap.js diblokir
            window.location.href = redirect_url;
        } else {
            isPaying.value = false;
            paymentError.value = "Gagal memproses sesi pembayaran Midtrans.";
        }
    } catch (err) {
        isPaying.value = false;
        if (err.response?.data?.errors?.amount) {
            form.setError("amount", err.response.data.errors.amount[0]);
        } else if (err.response?.data?.message) {
            paymentError.value = err.response.data.message;
        } else {
            paymentError.value = "Terjadi kesalahan koneksi saat memproses top-up.";
        }
    }
};

const syncingId = ref(null);
const sync = (orderId) => {
    syncingId.value = orderId;
    router.post(
        `/topup/${orderId}/sync`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                syncingId.value = null;
            },
        }
    );
};

// State Tab Riwayat: 'wallet' (Mutasi Saldo) | 'topup' (Riwayat Top-up Midtrans)
const activeTab = ref("wallet");

const badge = {
    pending: "bg-amber-950/40 text-amber-200 border border-amber-500/40",
    success: "bg-[#0d685b]/40 text-emerald-300 border border-[#0d685b]/60",
    failed: "bg-rose-950/40 text-rose-200 border border-rose-500/40",
    expired: "bg-[#131d1a] text-[#f3f2e7]/60 border border-[#0d685b]/30",
};

const label = {
    pending: "Menunggu Pembayaran",
    success: "Berhasil",
    failed: "Gagal",
    expired: "Kedaluwarsa",
};

const walletTypeBadge = (type) => {
    switch (type) {
        case "topup":
            return "bg-[#0d685b]/40 text-emerald-300 border border-[#0d685b]/50";
        case "checkout":
        case "purchase":
            return "bg-rose-950/40 text-rose-300 border border-rose-500/40";
        case "adjustment":
            return "bg-cyan-950/40 text-cyan-300 border border-cyan-500/40";
        case "refund":
            return "bg-purple-950/40 text-purple-300 border border-purple-500/40";
        default:
            return "bg-[#131d1a] text-[#f3f2e7]/70 border border-[#0d685b]/30";
    }
};

const walletTypeLabel = (type) => {
    switch (type) {
        case "topup":
            return "Top-Up Masuk";
        case "checkout":
        case "purchase":
            return "Belanja Toko";
        case "adjustment":
            return "Koreksi Saldo";
        case "refund":
            return "Pengembalian";
        default:
            return type || "Mutasi Saldo";
    }
};
</script>

<template>
    <Head title="Top-Up Saldo" />

    <div class="mb-4">
        <Link
            href="/dashboard"
            class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#f3f2e7]/70 hover:text-[#f3f2e7] transition"
        >
            <span>←</span>
            <span>Kembali ke Dashboard</span>
        </Link>
    </div>

    <!-- HEADER & CARD SALDO AKTIF -->
    <div class="grid gap-6 md:grid-cols-3">
        <!-- Kartu Saldo Saat Ini -->
        <div
            class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-[#131d1a] via-[#1a2d26] to-[#0d685b]/40 p-4.5 sm:p-6 text-[#f3f2e7] shadow-xl border border-[#0d685b]/40 flex flex-col justify-between md:col-span-1"
        >
            <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-emerald-500/10 blur-xl"></div>
            <div class="relative z-10">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-black uppercase tracking-widest text-emerald-200">
                        Dompet RAMELA
                    </span>
                    <span class="rounded-full bg-[#0d685b]/30 border border-[#0d685b]/40 px-2 py-0.5 text-[10px] font-bold text-emerald-300 backdrop-blur-xs">
                        Aktif
                    </span>
                </div>
                <div class="mt-5">
                    <p class="text-xs text-[#f3f2e7]/70">Saldo Dompet Anda</p>
                    <p class="mt-1 text-2xl font-black tracking-tight text-[#f3f2e7] sm:text-3xl">
                        {{ rupiah(user?.saldo) }}
                    </p>
                </div>
            </div>

            <div class="relative z-10 mt-6 border-t border-[#0d685b]/20 pt-4 text-[11px] text-[#f3f2e7]/70">
                <p>
                    Saldo dapat digunakan langsung untuk belanja di
                    <strong class="text-[#f3f2e7]">Ramela Eats, Hampers, & Beton</strong>.
                </p>
            </div>
        </div>

        <!-- Form Top-Up Nominal (2 Kolom di Desktop) -->
        <div class="rounded-3xl bg-[#1c2a25] p-4.5 sm:p-6 shadow-xl border border-[#0d685b]/30 md:col-span-2">
            <h1 class="text-lg font-black text-[#f3f2e7] sm:text-xl">Isi Saldo Dompet</h1>
            <p class="text-xs text-[#f3f2e7]/70 mt-0.5">
                Pilih nominal atau masukkan jumlah isi saldo yang Anda inginkan.
            </p>

            <div
                v-if="!ready"
                class="mt-4 rounded-2xl bg-amber-950/40 border border-amber-500/40 p-4 text-xs text-amber-200"
            >
                ⚠️ Gateway pembayaran belum siap saat ini. Silakan hubungi admin atau gunakan saldo yang ada.
            </div>

            <div
                v-if="paymentError"
                class="mt-4 flex items-start justify-between gap-3 rounded-2xl border border-rose-500/40 bg-rose-950/40 p-4 text-xs text-rose-200"
            >
                <div class="flex items-center gap-2">
                    <span class="text-base">⚠️</span>
                    <span>{{ paymentError }}</span>
                </div>
                <button
                    type="button"
                    class="text-rose-300 hover:text-white font-bold px-1 transition"
                    @click="paymentError = ''"
                >
                    ✕
                </button>
            </div>

            <form class="mt-5 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1.5 block text-xs font-bold text-[#f3f2e7]/80" for="amount">
                        Nominal Isi Saldo (Rp)
                    </label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-2.5 text-xs font-bold text-[#f3f2e7]/50">Rp</span>
                        <input
                            id="amount"
                            v-model.number="form.amount"
                            type="number"
                            :min="min"
                            :max="max"
                            step="1000"
                            class="w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] pl-10 pr-4 py-2.5 text-sm font-bold text-[#f3f2e7] shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-1 focus:ring-emerald-400 placeholder:text-[#f3f2e7]/40"
                            placeholder="50000"
                        />
                    </div>
                    <div class="mt-1.5 flex items-center justify-between text-[11px] text-[#f3f2e7]/60">
                        <span>Min. {{ rupiah(min) }}</span>
                        <span>Maks. {{ rupiah(max) }}</span>
                    </div>
                    <p v-if="form.errors.amount" class="mt-1 text-xs font-semibold text-rose-400">
                        {{ form.errors.amount }}
                    </p>
                </div>

                <!-- Tombol Pilihan Preset Nominal -->
                <div>
                    <span class="mb-2 block text-xs font-semibold text-[#f3f2e7]/70">Pilihan Cepat Nominal:</span>
                    <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                        <button
                            v-for="p in presets"
                            :key="p"
                            type="button"
                            class="rounded-xl border py-2 text-xs font-bold transition active:scale-95"
                            :class="
                                form.amount === p
                                    ? 'border-[#0d685b] bg-[#0d685b] text-[#f3f2e7] shadow-md shadow-[#0d685b]/30'
                                    : 'border-[#0d685b]/40 bg-[#131d1a] text-[#f3f2e7]/80 hover:bg-[#0d685b]/20 hover:text-[#f3f2e7]'
                            "
                            @click="form.amount = p"
                        >
                            {{ rupiah(p) }}
                        </button>
                    </div>
                </div>

                <button
                    :disabled="isPaying || form.processing || !ready"
                    type="submit"
                    class="w-full rounded-xl bg-[#0d685b] hover:bg-[#117c6d] py-3 text-center text-sm font-black text-[#f3f2e7] shadow-md shadow-[#0d685b]/30 transition disabled:cursor-not-allowed disabled:bg-slate-800 disabled:text-slate-500 active:scale-98"
                >
                    <span v-if="isPaying || form.processing">Menyiapkan Pembayaran Midtrans...</span>
                    <span v-else>💳 Bayar Sekarang via Midtrans (QRIS / VA / E-Wallet)</span>
                </button>
            </form>
        </div>
    </div>

    <!-- BAGIAN RIWAYAT (MUTASI SALDO & TOP-UP) -->
    <div class="mt-8 rounded-3xl bg-[#1c2a25] p-4.5 sm:p-6 shadow-xl border border-[#0d685b]/30">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-[#0d685b]/20 pb-4">
            <div>
                <h2 class="text-base font-black text-[#f3f2e7] sm:text-lg">Riwayat Aktivitas & Transaksi</h2>
                <p class="text-xs text-[#f3f2e7]/70">
                    Pantau mutasi saldo keluar-masuk serta riwayat pembayaran top-up Anda.
                </p>
            </div>

            <!-- Tab Switcher -->
            <div class="flex rounded-xl bg-[#131d1a] border border-[#0d685b]/40 p-1">
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-bold transition"
                    :class="
                        activeTab === 'wallet'
                            ? 'bg-[#0d685b] text-[#f3f2e7] shadow-sm'
                            : 'text-[#f3f2e7]/70 hover:text-[#f3f2e7]'
                    "
                    @click="activeTab = 'wallet'"
                >
                    <span>💳 Mutasi Saldo Dompet</span>
                    <span class="rounded-full bg-[#1c2a25] border border-[#0d685b]/30 px-1.5 py-0.2 text-[10px] text-[#f3f2e7]">
                        {{ walletHistory?.length || 0 }}
                    </span>
                </button>
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-lg px-3.5 py-1.5 text-xs font-bold transition"
                    :class="
                        activeTab === 'topup'
                            ? 'bg-[#0d685b] text-[#f3f2e7] shadow-sm'
                            : 'text-[#f3f2e7]/70 hover:text-[#f3f2e7]'
                    "
                    @click="activeTab = 'topup'"
                >
                    <span>⚡ Pembayaran Top-Up</span>
                    <span class="rounded-full bg-[#1c2a25] border border-[#0d685b]/30 px-1.5 py-0.2 text-[10px] text-[#f3f2e7]">
                        {{ history?.length || 0 }}
                    </span>
                </button>
            </div>
        </div>

        <!-- TAB 1: RIWAYAT MUTASI SALDO DOMPET -->
        <div v-if="activeTab === 'wallet'" class="mt-4">
            <div v-if="!walletHistory?.length" class="py-12 text-center text-[#f3f2e7]/60">
                <span class="text-3xl">💳</span>
                <p class="mt-2 text-xs sm:text-sm font-medium">Belum ada riwayat mutasi saldo dompet.</p>
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-[#0d685b]/20 text-[#f3f2e7]/60 uppercase tracking-wider text-[10px]">
                            <th class="w-10 py-3 px-2 text-center font-bold">#</th>
                            <th class="py-3 px-2 font-bold">Jenis Mutasi</th>
                            <th class="py-3 px-2 font-bold">Keterangan</th>
                            <th class="py-3 px-2 font-bold">Waktu</th>
                            <th class="py-3 px-2 font-bold text-right">Nominal</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#0d685b]/20">
                        <tr
                            v-for="(w, i) in walletHistory"
                            :key="w.id || i"
                            class="hover:bg-[#131d1a]/50 transition"
                        >
                            <td class="py-3 px-2 text-center font-bold text-[#f3f2e7]/50 text-xs">
                                {{ i + 1 }}
                            </td>
                            <td class="py-3 px-2">
                                <span
                                    class="inline-block rounded-md px-2 py-0.5 text-[11px] font-bold capitalize"
                                    :class="walletTypeBadge(w.type)"
                                >
                                    {{ walletTypeLabel(w.type) }}
                                </span>
                            </td>
                            <td class="py-3 px-2 font-medium text-[#f3f2e7]">
                                {{ w.note || '-' }}
                            </td>
                            <td class="py-3 px-2 text-[#f3f2e7]/60 whitespace-nowrap">
                                {{ fmtDate(w.created_at) }}
                            </td>
                            <td class="py-3 px-2 text-right font-black whitespace-nowrap">
                                <span
                                    :class="
                                        Number(w.amount) < 0
                                            ? 'text-rose-400'
                                            : 'text-emerald-400'
                                    "
                                >
                                    {{ Number(w.amount) < 0 ? '' : '+' }}{{ rupiah(w.amount) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 2: RIWAYAT PEMBAYARAN TOP-UP MIDTRANS -->
        <div v-if="activeTab === 'topup'" class="mt-4">
            <div v-if="!history?.length" class="py-12 text-center text-[#f3f2e7]/60">
                <span class="text-3xl">⚡</span>
                <p class="mt-2 text-xs sm:text-sm font-medium">Belum ada transaksi top-up Midtrans.</p>
            </div>
            <ul v-else class="divide-y divide-[#0d685b]/20">
                <li
                    v-for="h in history"
                    :key="h.midtrans_order_id"
                    class="flex flex-wrap items-center justify-between gap-3 py-3.5 px-2 hover:bg-[#131d1a]/50 transition rounded-xl"
                >
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-black text-[#f3f2e7]">{{ rupiah(h.amount) }}</span>
                            <span
                                v-if="h.payment_type"
                                class="rounded bg-[#131d1a] border border-[#0d685b]/30 px-1.5 py-0.5 text-[10px] font-bold text-[#f3f2e7]/70 uppercase"
                            >
                                {{ h.payment_type }}
                            </span>
                        </div>
                        <p class="mt-0.5 text-xs text-[#f3f2e7]/60">
                            {{ h.midtrans_order_id }} · {{ fmtDate(h.created_at) }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2.5">
                        <span
                            class="rounded-full px-2.5 py-0.5 text-xs font-bold"
                            :class="badge[h.status] || 'bg-[#131d1a] border border-[#0d685b]/30 text-[#f3f2e7]/70'"
                        >
                            {{ label[h.status] || h.status }}
                        </span>

                        <button
                            v-if="h.status === 'pending'"
                            type="button"
                            :disabled="syncingId === h.midtrans_order_id"
                            class="inline-flex items-center gap-1 rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-2.5 py-1 text-xs font-bold text-[#f3f2e7] shadow-sm hover:bg-[#0d685b]/20 transition active:scale-95 disabled:opacity-50"
                            @click="sync(h.midtrans_order_id)"
                        >
                            <span :class="{ 'animate-spin': syncingId === h.midtrans_order_id }">🔄</span>
                            <span>{{ syncingId === h.midtrans_order_id ? 'Memeriksa...' : 'Cek Status' }}</span>
                        </button>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>

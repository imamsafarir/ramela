<script setup>
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { computed, ref } from "vue";
import AdminLayout from "../../Layouts/AdminLayout.vue";
import { rupiah } from "../../utils/format";

defineOptions({ layout: AdminLayout });

const props = defineProps({
    stats: Object,
    couriers: Array,
    deliveries: Object,
    unassignedOrders: Array,
    shippingRates: {
        type: Array,
        default: () => [],
    },
    filters: Object,
});

const search = ref(props.filters?.q ?? "");
const statusFilter = ref(props.filters?.status ?? "");

const applyFilter = () => {
    router.get(
        "/admin/kurir",
        {
            q: search.value || undefined,
            status: statusFilter.value || undefined,
            tab: activeTab.value === "rates" ? "rates" : undefined,
        },
        { preserveState: true },
    );
};

// State Tab: 'monitoring' (Default) | 'rates' (Tarif Ongkir Kab/Kota)
const urlParams = typeof window !== "undefined" ? new URLSearchParams(window.location.search) : null;
const activeTab = ref(urlParams?.get("tab") === "rates" ? "rates" : "monitoring");

const setTab = (tab) => {
    activeTab.value = tab;
    if (typeof window !== "undefined") {
        const url = new URL(window.location.href);
        if (tab === "rates") {
            url.searchParams.set("tab", "rates");
        } else {
            url.searchParams.delete("tab");
        }
        window.history.replaceState({}, "", url);
    }
};

// Penugasan Kurir per Invoice
const selectedCouriers = ref({});
const isAssigning = ref(false);

const submitAssign = (invoice) => {
    const courierId = selectedCouriers.value[invoice];
    if (!courierId) return;
    isAssigning.value = true;
    router.post(
        "/admin/kurir/assign",
        {
            invoice_number: invoice,
            courier_id: courierId,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                delete selectedCouriers.value[invoice];
            },
            onFinish: () => {
                isAssigning.value = false;
            },
        }
    );
};

// Modal Preview Foto Bukti
const activePhoto = ref(null);
const openPhotoModal = (photo) => {
    activePhoto.value = photo;
};
const closePhotoModal = () => {
    activePhoto.value = null;
};

// Manajemen Tarif Ongkir Kab/Kota
const rateSearch = ref("");
const filteredRates = computed(() => {
    if (!rateSearch.value.trim()) return props.shippingRates;
    const q = rateSearch.value.toLowerCase().trim();
    return props.shippingRates.filter(
        (r) =>
            r.city_name?.toLowerCase().includes(q) ||
            (r.estimated_delivery && r.estimated_delivery.toLowerCase().includes(q)),
    );
});

const isRateModalOpen = ref(false);
const editingRate = ref(null);

const rateForm = useForm({
    city_name: "",
    shipping_cost: "",
    pricing_type: "per_kg",
    estimated_delivery: "",
    is_active: true,
});

const openCreateRateModal = () => {
    editingRate.value = null;
    rateForm.reset();
    rateForm.clearErrors();
    rateForm.city_name = "";
    rateForm.shipping_cost = "";
    rateForm.pricing_type = "per_kg";
    rateForm.estimated_delivery = "1-2 Jam";
    rateForm.is_active = true;
    isRateModalOpen.value = true;
};

const openEditRateModal = (rate) => {
    editingRate.value = rate;
    rateForm.clearErrors();
    rateForm.city_name = rate.city_name;
    rateForm.shipping_cost = rate.shipping_cost;
    rateForm.pricing_type = rate.pricing_type || "per_kg";
    rateForm.estimated_delivery = rate.estimated_delivery || "";
    rateForm.is_active = Boolean(rate.is_active);
    isRateModalOpen.value = true;
};

const closeRateModal = () => {
    isRateModalOpen.value = false;
    editingRate.value = null;
    rateForm.reset();
};

const submitRateForm = () => {
    if (editingRate.value) {
        rateForm.put(`/admin/kurir/tarif/${editingRate.value.id}`, {
            preserveScroll: true,
            onSuccess: () => closeRateModal(),
        });
    } else {
        rateForm.post("/admin/kurir/tarif", {
            preserveScroll: true,
            onSuccess: () => closeRateModal(),
        });
    }
};

const toggleRateStatus = (rate) => {
    router.put(
        `/admin/kurir/tarif/${rate.id}`,
        {
            city_name: rate.city_name,
            shipping_cost: rate.shipping_cost,
            pricing_type: rate.pricing_type || "per_kg",
            estimated_delivery: rate.estimated_delivery,
            is_active: !rate.is_active,
        },
        { preserveScroll: true },
    );
};

const deleteRate = (rate) => {
    if (
        confirm(
            `Hapus tarif pengiriman untuk "${rate.city_name}"? Wilayah ini tidak akan muncul lagi di opsi checkout.`,
        )
    ) {
        router.delete(`/admin/kurir/tarif/${rate.id}`, {
            preserveScroll: true,
        });
    }
};

const inputClass =
    "w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2.5 text-xs text-[#f3f2e7] shadow-sm placeholder:text-[#f3f2e7]/40 focus:border-emerald-400 focus:outline-none focus:ring-1 focus:ring-emerald-400";

const deliveryStatusBadge = (status) => {
    switch (status) {
        case "waiting_pickup":
            return {
                label: "Menunggu Pickup Toko",
                class: "bg-amber-500/20 text-amber-300 border border-amber-500/40",
            };
        case "en_route":
            return {
                label: "Sedang Diantar (En Route)",
                class: "bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 animate-pulse",
            };
        case "delivered":
            return {
                label: "Terkirim (Selesai)",
                class: "bg-[#0d685b]/30 text-[#f3f2e7] border border-[#0d685b]/50",
            };
        default:
            return {
                label: status,
                class: "bg-[#131d1a] text-[#f3f2e7]/70 border border-[#0d685b]/20",
            };
    }
};
</script>

<template>
    <Head title="Kurir & Pemantauan Pengiriman - Admin" />

    <div class="space-y-6">
        <!-- HEADER -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-[#f3f2e7] sm:text-3xl">
                    Kurir & Tarif Pengiriman
                </h1>
                <p class="mt-1 text-xs text-[#f3f2e7]/60 sm:text-sm">
                    Manajemen personil kurir internal, pelacakan GPS live, dan pengaturan tarif ongkir Kabupaten/Kota.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button
                    v-if="activeTab === 'rates'"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-[#0d685b] hover:bg-[#117c6d] px-3.5 py-2 text-xs font-bold text-[#f3f2e7] shadow-sm transition active:scale-95"
                    @click="openCreateRateModal"
                >
                    <span>➕</span>
                    <span>Tambah Tarif Kab/Kota</span>
                </button>
                <Link
                    href="/admin/users"
                    class="inline-flex items-center gap-1.5 self-start rounded-xl border border-[#0d685b]/40 bg-[#1c2a25] px-3.5 py-2 text-xs font-bold text-[#f3f2e7] shadow-sm hover:bg-[#131d1a] sm:self-auto transition"
                >
                    <span>👥</span>
                    <span>Tambah / Atur Role Kurir</span>
                </Link>
            </div>
        </div>

        <!-- TAB SWITCHER: Monitoring vs Tarif Pengiriman -->
        <div class="flex flex-wrap items-center gap-2 border-b border-[#0d685b]/30 pb-3">
            <button
                type="button"
                class="flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition"
                :class="
                    activeTab === 'monitoring'
                        ? 'bg-[#0d685b] text-[#f3f2e7] shadow-sm'
                        : 'bg-[#1c2a25] text-[#f3f2e7]/70 hover:bg-[#131d1a] hover:text-[#f3f2e7] border border-[#0d685b]/30'
                "
                @click="setTab('monitoring')"
            >
                <span>🛵 Monitoring & Personil Kurir</span>
                <span
                    v-if="unassignedOrders.length"
                    class="rounded-full bg-amber-500/30 text-amber-300 border border-amber-500/40 px-1.5 py-0.2 text-[10px]"
                >
                    {{ unassignedOrders.length }} siap kirim
                </span>
            </button>

            <button
                type="button"
                class="flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition"
                :class="
                    activeTab === 'rates'
                        ? 'bg-[#0d685b] text-[#f3f2e7] shadow-sm'
                        : 'bg-[#1c2a25] text-[#f3f2e7]/70 hover:bg-[#131d1a] hover:text-[#f3f2e7] border border-[#0d685b]/30'
                "
                @click="setTab('rates')"
            >
                <span>📍 Pengaturan Tarif Ongkir (Kab/Kota)</span>
                <span class="rounded-full bg-[#131d1a] border border-[#0d685b]/40 px-1.5 py-0.2 text-[10px] text-emerald-300 font-bold">
                    {{ shippingRates.length }} wilayah
                </span>
            </button>
        </div>

        <!-- TAB 1: MONITORING & PERSONIL KURIR -->
        <div v-show="activeTab === 'monitoring'" class="space-y-6">

        <!-- METRIK RINGKASAN -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
            <div
                class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 shadow-lg text-[#f3f2e7]"
            >
                <div class="flex items-center justify-between">
                    <p
                        class="text-[11px] font-bold uppercase tracking-wider text-[#f3f2e7]/60"
                    >
                        Total Personil
                    </p>
                    <span
                        class="rounded-lg bg-[#0d685b]/30 p-1.5 text-sm text-[#f3f2e7]"
                        >🛵</span
                    >
                </div>
                <p class="mt-2 text-2xl font-black text-[#f3f2e7] sm:text-3xl">
                    {{ stats.total_couriers }}
                </p>
                <p class="mt-0.5 text-[11px] text-[#f3f2e7]/50">
                    {{ stats.idle_couriers }} bebas tugas ·
                    {{ stats.busy_couriers }} bertugas
                </p>
            </div>

            <div
                class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 shadow-lg text-[#f3f2e7]"
            >
                <div class="flex items-center justify-between">
                    <p
                        class="text-[11px] font-bold uppercase tracking-wider text-[#f3f2e7]/60"
                    >
                        Pengiriman Aktif
                    </p>
                    <span
                        class="rounded-lg bg-amber-500/20 p-1.5 text-sm text-amber-300"
                        >📦</span
                    >
                </div>
                <p class="mt-2 text-2xl font-black text-amber-300 sm:text-3xl">
                    {{ stats.active_deliveries }}
                </p>
                <p class="mt-0.5 text-[11px] text-[#f3f2e7]/50">
                    Pickup & sedang di jalan
                </p>
            </div>

            <div
                class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 shadow-lg text-[#f3f2e7]"
            >
                <div class="flex items-center justify-between">
                    <p
                        class="text-[11px] font-bold uppercase tracking-wider text-[#f3f2e7]/60"
                    >
                        Selesai Hari Ini
                    </p>
                    <span
                        class="rounded-lg bg-emerald-500/20 p-1.5 text-sm text-emerald-400"
                        >✅</span
                    >
                </div>
                <p
                    class="mt-2 text-2xl font-black text-emerald-400 sm:text-3xl"
                >
                    {{ stats.delivered_today }}
                </p>
                <p class="mt-0.5 text-[11px] text-[#f3f2e7]/50">
                    Pesanan berhasil diantar
                </p>
            </div>

            <div
                class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 shadow-lg text-[#f3f2e7]"
            >
                <div class="flex items-center justify-between">
                    <p
                        class="text-[11px] font-bold uppercase tracking-wider text-[#f3f2e7]/60"
                    >
                        Siap Dikirim
                    </p>
                    <span
                        class="rounded-lg bg-rose-500/20 p-1.5 text-sm text-rose-300"
                        >⏳</span
                    >
                </div>
                <p class="mt-2 text-2xl font-black text-[#f3f2e7] sm:text-3xl">
                    {{ unassignedOrders.length }}
                </p>
                <p class="mt-0.5 text-[11px] text-[#f3f2e7]/50">
                    Menunggu penugasan kurir
                </p>
            </div>
        </div>

        <!-- PESANAN SIAP KIRIM (BELUM ADA KURIR) -->
        <div
            v-if="unassignedOrders.length"
            class="overflow-hidden rounded-2xl border border-amber-500/40 bg-amber-950/30 p-4 sm:p-5 shadow-lg text-[#f3f2e7]"
        >
            <div class="flex items-center gap-2 mb-3">
                <span class="text-base">⚠️</span>
                <h3 class="text-sm font-black text-amber-300">
                    Pesanan Siap Kirim Menunggu Penugasan Kurir ({{
                        unassignedOrders.length
                    }})
                </h3>
            </div>
            <div class="space-y-3">
                <div
                    v-for="order in unassignedOrders"
                    :key="order.invoice_number"
                    class="flex flex-col gap-3 rounded-xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 sm:flex-row sm:items-center sm:justify-between shadow-sm"
                >
                    <div>
                        <div class="flex items-center gap-2">
                            <Link
                                :href="`/admin/pesanan/${order.invoice_number}`"
                                class="text-xs font-black text-emerald-400 hover:underline"
                            >
                                #{{ order.invoice_number }}
                            </Link>
                            <span
                                class="rounded-md bg-[#131d1a] border border-[#0d685b]/30 px-2 py-0.5 text-[10px] font-bold text-[#f3f2e7]/80"
                            >
                                {{ order.store }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-[#f3f2e7]/80">
                            <strong>{{ order.recipient_name }}</strong> ({{
                                order.recipient_phone
                            }}) · {{ order.shipping_address }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <select
                            v-model="selectedCouriers[order.invoice_number]"
                            class="rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3 py-1.5 text-xs text-[#f3f2e7] focus:outline-none focus:border-[#0d685b]"
                        >
                            <option value="">-- Pilih Kurir --</option>
                            <option
                                v-for="c in couriers"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.name }} ({{ c.username }})
                                {{
                                    c.is_busy ? "[Sedang Mengantar]" : "[🟢 Siap]"
                                }}
                            </option>
                        </select>
                        <button
                            type="button"
                            :disabled="
                                !selectedCouriers[order.invoice_number] || isAssigning
                            "
                            class="rounded-xl bg-[#0d685b] px-3.5 py-1.5 text-xs font-bold text-[#f3f2e7] shadow-sm hover:bg-[#0d685b]/90 disabled:opacity-40 transition cursor-pointer"
                            @click="submitAssign(order.invoice_number)"
                        >
                            Tugaskan
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- DAFTAR PERSONIL KURIR -->
        <div
            class="overflow-hidden rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 sm:p-5 shadow-lg text-[#f3f2e7]"
        >
            <h2 class="text-base font-black text-[#f3f2e7] mb-3">
                Daftar Personil Kurir Internal
            </h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="c in couriers"
                    :key="c.id"
                    class="flex flex-col justify-between rounded-xl border border-[#0d685b]/20 bg-[#131d1a] p-4 transition hover:border-[#0d685b]/60"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#0d685b]/30 font-black text-[#f3f2e7] text-sm"
                            >
                                🛵
                            </div>
                            <div>
                                <p class="font-black text-sm text-[#f3f2e7]">
                                    {{ c.name }}
                                </p>
                                <p class="text-xs text-[#f3f2e7]/50">
                                    @{{ c.username }}
                                </p>
                            </div>
                        </div>
                        <span
                            class="rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                            :class="
                                c.is_busy
                                    ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40'
                                    : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'
                            "
                        >
                            {{ c.is_busy ? "● Mengantar" : "● Siap" }}
                        </span>
                    </div>

                    <div
                        class="mt-3 border-t border-[#0d685b]/20 pt-2.5 text-xs space-y-1"
                    >
                        <div class="flex justify-between text-[#f3f2e7]/60">
                            <span>No Telepon/WA:</span>
                            <span class="font-semibold text-[#f3f2e7]">{{
                                c.phone || "Belum diisi"
                            }}</span>
                        </div>
                        <div class="flex justify-between text-[#f3f2e7]/60">
                            <span>Selesai Diantar:</span>
                            <span class="font-bold text-[#f3f2e7]"
                                >{{ c.completed_count }} pesanan</span
                            >
                        </div>
                        <div
                            v-if="c.active_task"
                            class="mt-2 rounded-lg bg-emerald-950/40 border border-emerald-500/30 p-2 text-[11px] text-emerald-300"
                        >
                            <span class="font-bold">Tugas Aktif:</span> #{{
                                c.active_task.invoice_number
                            }}
                            ({{ c.active_task.store }})
                        </div>
                    </div>
                </div>

                <div
                    v-if="!couriers.length"
                    class="col-span-full rounded-xl border border-dashed border-[#0d685b]/30 p-8 text-center text-xs text-[#f3f2e7]/50"
                >
                    Belum ada pengguna dengan role "kurir". Anda dapat
                    menambahkan akun kurir melalui menu
                    <Link
                        href="/admin/users"
                        class="text-emerald-400 underline font-bold"
                        >Kelola Pengguna</Link
                    >.
                </div>
            </div>
        </div>

        <!-- MONITORING TUGAS PENGIRIMAN REALTIME -->
        <div
            class="overflow-hidden rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-lg text-[#f3f2e7]"
        >
            <div class="border-b border-[#0d685b]/30 p-4 sm:p-5 bg-[#131d1a]">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2 class="text-base font-black text-[#f3f2e7]">
                            Monitoring Pengiriman (Live Tracking Board)
                        </h2>
                        <p class="text-xs text-[#f3f2e7]/60">
                            Pemantauan lokasi GPS kurir dan foto bukti
                            pickup/dropoff.
                        </p>
                    </div>

                    <!-- Filter & Search -->
                    <div class="flex flex-wrap items-center gap-2">
                        <input
                            v-model="search"
                            placeholder="Cari invoice / kurir / penerima..."
                            class="w-48 rounded-xl border border-[#0d685b]/40 bg-[#17231f] px-3 py-1.5 text-xs text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:outline-none focus:border-[#0d685b]"
                            @keydown.enter.prevent="applyFilter"
                        />
                        <select
                            v-model="statusFilter"
                            class="rounded-xl border border-[#0d685b]/40 bg-[#17231f] px-3 py-1.5 text-xs text-[#f3f2e7] focus:outline-none focus:border-[#0d685b]"
                            @change="applyFilter"
                        >
                            <option value="">Semua Status</option>
                            <option value="waiting_pickup">
                                Menunggu Pickup
                            </option>
                            <option value="en_route">Sedang Diantar</option>
                            <option value="delivered">Terkirim</option>
                        </select>
                        <button
                            class="rounded-xl bg-[#0d685b] px-3.5 py-1.5 text-xs font-bold text-[#f3f2e7] shadow-sm hover:bg-[#0d685b]/90 transition"
                            @click="applyFilter"
                        >
                            Cari
                        </button>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead
                        class="border-b border-[#0d685b]/30 bg-[#131d1a] text-[11px] font-bold uppercase tracking-wider text-[#f3f2e7]/70"
                    >
                        <tr>
                            <th class="w-12 px-3 py-3.5 text-center">#</th>
                            <th class="px-5 py-3.5">Pesanan & Toko</th>
                            <th class="px-4 py-3.5">Kurir Bertugas</th>
                            <th class="px-4 py-3.5">Tujuan & Penerima</th>
                            <th class="px-4 py-3.5">Status Pengantaran</th>
                            <th class="px-4 py-3.5">Lokasi GPS Terkini</th>
                            <th class="px-5 py-3.5 text-right">Foto Bukti</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#0d685b]/20">
                        <tr
                            v-for="(d, idx) in deliveries.data"
                            :key="d.id"
                            class="transition hover:bg-[#131d1a]/50"
                        >
                            <td class="px-3 py-4 text-center font-bold text-xs text-[#f3f2e7]/50">
                                {{ (deliveries.from || 1) + idx }}
                            </td>
                            <td class="px-5 py-4">
                                <Link
                                    :href="`/admin/pesanan/${d.invoice_number}`"
                                    class="font-bold text-emerald-400 hover:underline"
                                >
                                    #{{ d.invoice_number }}
                                </Link>
                                <p class="text-xs text-[#f3f2e7]/60">
                                    {{ d.store }}
                                </p>
                            </td>

                            <td class="px-4 py-4">
                                <div v-if="d.courier">
                                    <p class="font-bold text-[#f3f2e7]">
                                        {{ d.courier.name }}
                                    </p>
                                    <p class="text-xs text-[#f3f2e7]/50">
                                        @{{ d.courier.username }} ·
                                        {{ d.courier.phone || "-" }}
                                    </p>
                                </div>
                                <span
                                    v-else
                                    class="text-xs text-[#f3f2e7]/40 italic"
                                    >Belum ada kurir</span
                                >
                            </td>

                            <td class="px-4 py-4 max-w-xs">
                                <p class="font-bold text-[#f3f2e7]">
                                    {{ d.recipient_name }}
                                </p>
                                <p
                                    class="text-xs text-[#f3f2e7]/60 truncate"
                                    :title="d.shipping_address"
                                >
                                    {{ d.shipping_address }}
                                </p>
                            </td>

                            <td class="px-4 py-4">
                                <span
                                    class="inline-block rounded-lg px-2.5 py-0.5 text-[11px] font-bold"
                                    :class="deliveryStatusBadge(d.status).class"
                                >
                                    {{ deliveryStatusBadge(d.status).label }}
                                </span>
                                <p
                                    v-if="d.started_at"
                                    class="text-[10px] text-[#f3f2e7]/40 mt-0.5"
                                >
                                    Mulai: {{ d.started_at }}
                                </p>
                            </td>

                            <td class="px-4 py-4">
                                <div v-if="d.current_lat && d.current_lng">
                                    <a
                                        :href="`https://www.google.com/maps?q=${d.current_lat},${d.current_lng}`"
                                        target="_blank"
                                        class="inline-flex items-center gap-1 rounded-xl bg-[#0d685b]/30 border border-[#0d685b]/50 px-2 py-1 text-xs font-bold text-[#f3f2e7] hover:bg-[#0d685b]/50 transition"
                                    >
                                        <span>📍</span>
                                        <span>Buka di Maps</span>
                                    </a>
                                    <p
                                        class="text-[10px] text-[#f3f2e7]/40 mt-0.5"
                                    >
                                        {{ d.location_updated_at }}
                                    </p>
                                </div>
                                <span
                                    v-else
                                    class="text-xs text-[#f3f2e7]/40 italic"
                                    >Belum ada sinyal GPS</span
                                >
                            </td>

                            <td class="px-5 py-4 text-right">
                                <div
                                    v-if="d.photos?.length"
                                    class="inline-flex items-center gap-1.5 justify-end"
                                >
                                    <button
                                        v-for="(photo, idx) in d.photos"
                                        :key="idx"
                                        type="button"
                                        class="relative h-9 w-9 overflow-hidden rounded-xl border border-[#0d685b]/40 hover:ring-2 hover:ring-[#0d685b] transition"
                                        :title="`Foto ${photo.type}`"
                                        @click="openPhotoModal(photo)"
                                    >
                                        <img
                                            :src="photo.url"
                                            class="h-full w-full object-cover"
                                        />
                                        <span
                                            class="absolute bottom-0 right-0 bg-black/70 px-0.5 text-[8px] font-bold text-white uppercase"
                                        >
                                            {{
                                                photo.type === "pickup"
                                                    ? "P"
                                                    : "D"
                                            }}
                                        </span>
                                    </button>
                                </div>
                                <span
                                    v-else
                                    class="text-xs text-[#f3f2e7]/40 italic"
                                    >Belum ada foto</span
                                >
                            </td>
                        </tr>

                        <tr v-if="!deliveries.data?.length">
                            <td
                                colspan="7"
                                class="px-5 py-10 text-center text-xs text-[#f3f2e7]/50"
                            >
                                🍃 Tidak ada data pengiriman aktif atau selesai
                                yang sesuai filter.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <div
                v-if="deliveries.last_page > 1"
                class="border-t border-[#0d685b]/20 px-5 py-3"
            >
                <nav class="flex justify-center gap-1">
                    <template v-for="l in deliveries.links" :key="l.label">
                        <Link
                            v-if="l.url"
                            :href="l.url"
                            class="rounded-xl px-3 py-1.5 text-xs font-bold border transition"
                            :class="
                                l.active
                                    ? 'bg-[#0d685b] border-[#0d685b] text-[#f3f2e7]'
                                    : 'border-[#0d685b]/30 bg-[#131d1a] text-[#f3f2e7]/70 hover:bg-[#17231f]'
                            "
                            v-html="l.label"
                        />
                    </template>
                </nav>
            </div>
        </div>
        </div> <!-- END TAB 1 (MONITORING) -->

        <!-- TAB 2: PENGATURAN TARIF ONGKIR KAB/KOTA -->
        <div v-show="activeTab === 'rates'" class="space-y-6">
            <!-- Header Kartu & Statistik Tarif -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4">
                <div class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 shadow-lg text-[#f3f2e7]">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-[#f3f2e7]/60">Total Wilayah</p>
                        <span class="rounded-lg bg-[#0d685b]/30 p-1.5 text-sm text-[#f3f2e7]">📍</span>
                    </div>
                    <p class="mt-2 text-2xl font-black text-[#f3f2e7] sm:text-3xl">{{ shippingRates.length }}</p>
                    <p class="mt-0.5 text-[11px] text-[#f3f2e7]/50">Wilayah Kabupaten / Kota</p>
                </div>

                <div class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 shadow-lg text-[#f3f2e7]">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-[#f3f2e7]/60">Wilayah Aktif</p>
                        <span class="rounded-lg bg-emerald-500/20 p-1.5 text-sm text-emerald-300">✅</span>
                    </div>
                    <p class="mt-2 text-2xl font-black text-emerald-300 sm:text-3xl">
                        {{ shippingRates.filter((r) => r.is_active).length }}
                    </p>
                    <p class="mt-0.5 text-[11px] text-[#f3f2e7]/50">Tersedia untuk pembeli</p>
                </div>

                <div class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 shadow-lg text-[#f3f2e7] col-span-2 sm:col-span-1">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-[#f3f2e7]/60">Wilayah Nonaktif</p>
                        <span class="rounded-lg bg-rose-500/20 p-1.5 text-sm text-rose-300">⏸️</span>
                    </div>
                    <p class="mt-2 text-2xl font-black text-rose-300 sm:text-3xl">
                        {{ shippingRates.filter((r) => !r.is_active).length }}
                    </p>
                    <p class="mt-0.5 text-[11px] text-[#f3f2e7]/50">Sementara dinonaktifkan</p>
                </div>
            </div>

            <!-- Panel Tabel Tarif Wilayah -->
            <div class="overflow-hidden rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-lg text-[#f3f2e7]">
                <div class="border-b border-[#0d685b]/30 p-4 sm:p-5 bg-[#131d1a]">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-base font-black text-[#f3f2e7]">
                                Daftar Tarif Ongkir Kurir per Kabupaten / Kota
                            </h2>
                            <p class="text-xs text-[#f3f2e7]/60">
                                Tarif ini yang akan muncul sebagai pilihan ongkir kurir saat pembeli checkout pesanan.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <div class="relative flex-1 sm:w-64">
                                <input
                                    v-model="rateSearch"
                                    type="text"
                                    placeholder="Cari Kab/Kota atau estimasi..."
                                    class="w-full rounded-xl border border-[#0d685b]/40 bg-[#17231f] pl-8 pr-3 py-1.5 text-xs text-[#f3f2e7] focus:outline-none focus:border-emerald-400 placeholder:text-[#f3f2e7]/40"
                                />
                                <span class="absolute left-2.5 top-2 text-xs text-[#f3f2e7]/40">🔍</span>
                            </div>

                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-[#0d685b] hover:bg-[#117c6d] px-3.5 py-1.5 text-xs font-bold text-[#f3f2e7] shadow-sm transition active:scale-95"
                                @click="openCreateRateModal"
                            >
                                <span>➕</span>
                                <span>Tambah Tarif</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tampilan Desktop: Tabel -->
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-[#0d685b]/20 bg-[#17231f]/50 text-[#f3f2e7]/60 uppercase tracking-wider text-[10px]">
                                <th class="w-12 py-3 px-3 text-center font-bold">#</th>
                                <th class="py-3 px-4 font-bold">Kabupaten / Kota</th>
                                <th class="py-3 px-4 font-bold">Biaya Pengiriman (Ongkir)</th>
                                <th class="py-3 px-4 font-bold">Estimasi Pengantaran</th>
                                <th class="py-3 px-4 font-bold text-center">Status</th>
                                <th class="py-3 px-4 font-bold text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#0d685b]/20">
                            <tr
                                v-for="(rate, idx) in filteredRates"
                                :key="rate.id"
                                class="hover:bg-[#131d1a]/50 transition"
                            >
                                <td class="py-3.5 px-3 text-center font-bold text-xs text-[#f3f2e7]/50">
                                    {{ idx + 1 }}
                                </td>
                                <td class="py-3.5 px-4 font-black text-[#f3f2e7] flex items-center gap-2">
                                    <span class="text-emerald-400">📍</span>
                                    <span>{{ rate.city_name }}</span>
                                </td>
                                <td class="py-3.5 px-4 font-black text-emerald-400">
                                    <div>{{ rupiah(rate.shipping_cost) }}{{ rate.pricing_type === 'flat' ? '' : ' / kg' }}</div>
                                    <span
                                        class="inline-block mt-0.5 rounded px-1.5 py-0.2 text-[10px] font-semibold"
                                        :class="rate.pricing_type === 'flat' ? 'bg-sky-500/20 text-sky-300 border border-sky-500/30' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'"
                                    >
                                        {{ rate.pricing_type === 'flat' ? 'Tarif Flat' : 'Per Kilogram' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-[#f3f2e7]/80">
                                    <span class="inline-flex items-center gap-1 rounded-md bg-[#131d1a] border border-[#0d685b]/30 px-2 py-0.5 text-[11px] font-medium">
                                        ⏱️ {{ rate.estimated_delivery || "1-3 Hari" }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-bold transition active:scale-95"
                                        :class="
                                            rate.is_active
                                                ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 hover:bg-emerald-500/30'
                                                : 'bg-rose-500/20 text-rose-300 border border-rose-500/40 hover:bg-rose-500/30'
                                        "
                                        :title="rate.is_active ? 'Klik untuk menonaktifkan' : 'Klik untuk mengaktifkan'"
                                        @click="toggleRateStatus(rate)"
                                    >
                                        <span>{{ rate.is_active ? "● Aktif" : "○ Nonaktif" }}</span>
                                    </button>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button
                                            type="button"
                                            class="rounded-lg border border-[#0d685b]/40 bg-[#131d1a] px-2.5 py-1 text-xs font-bold text-[#f3f2e7] hover:bg-[#0d685b]/30 transition"
                                            @click="openEditRateModal(rate)"
                                        >
                                            ✏️ Edit
                                        </button>
                                        <button
                                            type="button"
                                            class="rounded-lg border border-rose-500/40 bg-rose-950/30 px-2.5 py-1 text-xs font-bold text-rose-300 hover:bg-rose-900/50 transition"
                                            @click="deleteRate(rate)"
                                        >
                                            🗑️ Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="!filteredRates.length">
                                <td colspan="6" class="py-12 text-center text-[#f3f2e7]/50 text-xs">
                                    <span class="text-3xl block mb-2">📍</span>
                                    <p class="font-bold">Tidak ada data tarif pengiriman ditemukan.</p>
                                    <p class="text-[11px] text-[#f3f2e7]/40 mt-1">
                                        {{ rateSearch ? 'Coba kata kunci pencarian lain.' : 'Klik "Tambah Tarif" untuk menambahkan wilayah baru.' }}
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Tampilan Mobile: Kartu Responsif -->
                <div class="sm:hidden divide-y divide-[#0d685b]/20 p-3 space-y-3">
                    <div
                        v-for="rate in filteredRates"
                        :key="rate.id"
                        class="rounded-xl border border-[#0d685b]/30 bg-[#131d1a] p-3.5 space-y-2.5"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h3 class="text-sm font-black text-[#f3f2e7] flex items-center gap-1.5">
                                    <span>📍</span>
                                    <span>{{ rate.city_name }}</span>
                                </h3>
                                <p class="text-xs font-black text-emerald-400 mt-0.5">
                                    {{ rupiah(rate.shipping_cost) }}{{ rate.pricing_type === 'flat' ? '' : ' / kg' }}
                                    <span
                                        class="inline-block ml-1 rounded px-1.5 py-0.2 text-[9px] font-semibold"
                                        :class="rate.pricing_type === 'flat' ? 'bg-sky-500/20 text-sky-300 border border-sky-500/30' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'"
                                    >
                                        {{ rate.pricing_type === 'flat' ? 'Flat' : 'Per Kg' }}
                                    </span>
                                </p>
                            </div>
                            <button
                                type="button"
                                class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                                :class="
                                    rate.is_active
                                        ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'
                                        : 'bg-rose-500/20 text-rose-300 border border-rose-500/40'
                                "
                                @click="toggleRateStatus(rate)"
                            >
                                {{ rate.is_active ? "● Aktif" : "○ Nonaktif" }}
                            </button>
                        </div>

                        <div class="text-[11px] text-[#f3f2e7]/70 flex items-center gap-1">
                            <span>⏱️ Estimasi:</span>
                            <span class="font-semibold text-[#f3f2e7]">{{ rate.estimated_delivery || "1-3 Hari" }}</span>
                        </div>

                        <div class="flex items-center justify-end gap-2 border-t border-[#0d685b]/20 pt-2.5">
                            <button
                                type="button"
                                class="rounded-lg border border-[#0d685b]/40 bg-[#1c2a25] px-3 py-1 text-xs font-bold text-[#f3f2e7]"
                                @click="openEditRateModal(rate)"
                            >
                                ✏️ Edit
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-rose-500/40 bg-rose-950/30 px-3 py-1 text-xs font-bold text-rose-300"
                                @click="deleteRate(rate)"
                            >
                                🗑️ Hapus
                            </button>
                        </div>
                    </div>

                    <div v-if="!filteredRates.length" class="py-8 text-center text-xs text-[#f3f2e7]/50">
                        Tidak ada wilayah ditemukan.
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL TAMBAH / EDIT TARIF ONGKIR KAB/KOTA -->
        <div
            v-if="isRateModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#17231f]/85 backdrop-blur-md p-4"
        >
            <div
                class="w-full max-w-md rounded-2xl bg-[#1c2a25] p-5 sm:p-6 shadow-2xl border border-[#0d685b]/40 text-[#f3f2e7]"
            >
                <div class="flex items-center justify-between border-b border-[#0d685b]/30 pb-3">
                    <h3 class="text-sm font-black text-[#f3f2e7]">
                        {{ editingRate ? "Edit Tarif Pengiriman" : "Tambah Tarif Pengiriman Baru" }}
                    </h3>
                    <button
                        type="button"
                        class="rounded-lg p-1 text-[#f3f2e7]/60 hover:bg-[#131d1a] hover:text-[#f3f2e7] transition"
                        @click="closeRateModal"
                    >
                        ✕
                    </button>
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="submitRateForm">
                    <div>
                        <label class="mb-1 block text-xs font-bold text-[#f3f2e7]/80">
                            Nama Kabupaten / Kota
                        </label>
                        <input
                            v-model="rateForm.city_name"
                            type="text"
                            placeholder="Contoh: Kota Tarakan, Kabupaten Bulungan"
                            :class="inputClass"
                            required
                        />
                        <p v-if="rateForm.errors.city_name" class="mt-1 text-xs font-semibold text-rose-400">
                            {{ rateForm.errors.city_name }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1.5 block text-xs font-bold text-[#f3f2e7]/80">
                            Skema Perhitungan Tarif Ongkir
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <label
                                class="flex cursor-pointer flex-col gap-1 rounded-xl border p-2.5 transition"
                                :class="
                                    rateForm.pricing_type === 'per_kg'
                                        ? 'border-emerald-400 bg-emerald-500/20 text-emerald-300 font-bold'
                                        : 'border-[#0d685b]/40 bg-[#131d1a] text-[#f3f2e7]/70 hover:bg-[#182722]'
                                "
                            >
                                <div class="flex items-center gap-1.5">
                                    <input
                                        v-model="rateForm.pricing_type"
                                        type="radio"
                                        value="per_kg"
                                        class="accent-emerald-400"
                                    />
                                    <span class="text-xs">Per Kilogram (Kg)</span>
                                </div>
                                <span class="text-[10px] text-[#f3f2e7]/60 font-normal">
                                    &lt; 1 kg = 1 kg, &gt; 1 kg kelipatan per kg.
                                </span>
                            </label>

                            <label
                                class="flex cursor-pointer flex-col gap-1 rounded-xl border p-2.5 transition"
                                :class="
                                    rateForm.pricing_type === 'flat'
                                        ? 'border-emerald-400 bg-emerald-500/20 text-emerald-300 font-bold'
                                        : 'border-[#0d685b]/40 bg-[#131d1a] text-[#f3f2e7]/70 hover:bg-[#182722]'
                                "
                            >
                                <div class="flex items-center gap-1.5">
                                    <input
                                        v-model="rateForm.pricing_type"
                                        type="radio"
                                        value="flat"
                                        class="accent-emerald-400"
                                    />
                                    <span class="text-xs">Tarif Flat</span>
                                </div>
                                <span class="text-[10px] text-[#f3f2e7]/60 font-normal">
                                    Biaya tetap tanpa terpengaruh berat.
                                </span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-bold text-[#f3f2e7]/80">
                            {{ rateForm.pricing_type === 'flat' ? 'Biaya Pengiriman Flat (Rp)' : 'Tarif Ongkir per 1 Kg (Rp)' }}
                        </label>
                        <input
                            v-model.number="rateForm.shipping_cost"
                            type="number"
                            min="0"
                            step="500"
                            :placeholder="rateForm.pricing_type === 'flat' ? 'Contoh: 15000' : 'Contoh: 10000 / kg'"
                            :class="inputClass"
                            required
                        />
                        <p v-if="rateForm.errors.shipping_cost" class="mt-1 text-xs font-semibold text-rose-400">
                            {{ rateForm.errors.shipping_cost }}
                        </p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-bold text-[#f3f2e7]/80">
                            Estimasi Waktu Pengantaran
                        </label>
                        <input
                            v-model="rateForm.estimated_delivery"
                            type="text"
                            placeholder="Contoh: 1-2 Jam, 1 Hari, 2-3 Hari"
                            :class="inputClass"
                        />
                        <p v-if="rateForm.errors.estimated_delivery" class="mt-1 text-xs font-semibold text-rose-400">
                            {{ rateForm.errors.estimated_delivery }}
                        </p>
                    </div>

                    <div class="rounded-xl border border-[#0d685b]/30 bg-[#131d1a] p-3">
                        <label class="flex items-center gap-2.5 cursor-pointer">
                            <input
                                v-model="rateForm.is_active"
                                type="checkbox"
                                class="h-4 w-4 rounded accent-[#0d685b]"
                            />
                            <div>
                                <span class="text-xs font-bold text-[#f3f2e7] block">Wilayah Aktif</span>
                                <span class="text-[11px] text-[#f3f2e7]/60 block">
                                    Tampilkan wilayah ini sebagai pilihan pengiriman kurir di checkout pembeli.
                                </span>
                            </div>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-[#0d685b]/20 pt-3">
                        <button
                            type="button"
                            class="rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-4 py-2 text-xs font-bold text-[#f3f2e7]/80 hover:text-[#f3f2e7] transition"
                            @click="closeRateModal"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="rateForm.processing"
                            class="rounded-xl bg-[#0d685b] hover:bg-[#117c6d] px-4 py-2 text-xs font-black text-[#f3f2e7] shadow-sm transition disabled:opacity-50"
                        >
                            {{ rateForm.processing ? "Menyimpan..." : "Simpan Tarif" }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL PREVIEW FOTO BUKTI -->
        <div
            v-if="activePhoto"
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#17231f]/85 backdrop-blur-md p-4"
        >
            <div
                class="w-full max-w-lg rounded-2xl bg-[#1c2a25] p-5 shadow-2xl border border-[#0d685b]/40 text-[#f3f2e7]"
            >
                <div
                    class="flex items-center justify-between border-b border-[#0d685b]/30 pb-3"
                >
                    <h3 class="text-sm font-black text-[#f3f2e7] capitalize">
                        Bukti {{ activePhoto.type }} Kurir
                    </h3>
                    <button
                        type="button"
                        class="rounded-lg p-1 text-[#f3f2e7]/60 hover:bg-[#131d1a] hover:text-[#f3f2e7] transition"
                        @click="closePhotoModal"
                    >
                        ✕
                    </button>
                </div>
                <div
                    class="mt-4 overflow-hidden rounded-xl bg-black border border-[#0d685b]/30"
                >
                    <img
                        :src="activePhoto.url"
                        class="max-h-[60vh] w-full object-contain"
                    />
                </div>
                <div
                    class="mt-3 flex items-center justify-between text-xs text-[#f3f2e7]/60"
                >
                    <span>Waktu: {{ activePhoto.taken_at }}</span>
                    <span v-if="activePhoto.notes"
                        >Catatan: {{ activePhoto.notes }}</span
                    >
                </div>
            </div>
        </div>
    </div>
</template>

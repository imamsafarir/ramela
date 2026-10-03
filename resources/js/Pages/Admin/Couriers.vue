<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    stats: Object,
    couriers: Array,
    deliveries: Object,
    unassignedOrders: Array,
    filters: Object,
});

const search = ref(props.filters.q ?? '');
const statusFilter = ref(props.filters.status ?? '');

const applyFilter = () => {
    router.get('/admin/kurir', {
        q: search.value || undefined,
        status: statusFilter.value || undefined,
    }, { preserveState: true });
};

// Form Assign Kurir
const assignForm = useForm({
    invoice_number: '',
    courier_id: '',
});

const submitAssign = (invoice) => {
    assignForm.invoice_number = invoice;
    assignForm.post('/admin/kurir/assign', {
        preserveScroll: true,
        onSuccess: () => {
            assignForm.reset();
        },
    });
};

// Modal Preview Foto Bukti
const activePhoto = ref(null);
const openPhotoModal = (photo) => {
    activePhoto.value = photo;
};
const closePhotoModal = () => {
    activePhoto.value = null;
};

const inputClass = 'w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs text-slate-800 shadow-xs focus:border-slate-900 focus:outline-none';

const deliveryStatusBadge = (status) => {
    switch (status) {
        case 'waiting_pickup':
            return { label: 'Menunggu Pickup Toko', class: 'bg-amber-500/20 text-amber-300 border border-amber-500/40' };
        case 'en_route':
            return { label: 'Sedang Diantar (En Route)', class: 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 animate-pulse' };
        case 'delivered':
            return { label: 'Terkirim (Selesai)', class: 'bg-[#0d685b]/30 text-[#f3f2e7] border border-[#0d685b]/50' };
        default:
            return { label: status, class: 'bg-[#131d1a] text-[#f3f2e7]/70 border border-[#0d685b]/20' };
    }
};
</script>

<template>
    <Head title="Kurir & Pemantauan Pengiriman - Admin" />

    <div class="space-y-6">
        <!-- HEADER -->
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-[#f3f2e7] sm:text-3xl">
                    Kurir & Pemantauan Pengiriman
                </h1>
                <p class="mt-1 text-xs text-[#f3f2e7]/60 sm:text-sm">
                    Manajemen personil kurir internal, pelacakan GPS pengantaran real-time, foto validasi, dan penugasan kurir.
                </p>
            </div>
            <Link
                href="/admin/users"
                class="inline-flex items-center gap-1.5 self-start rounded-xl border border-[#0d685b]/40 bg-[#1c2a25] px-3.5 py-2 text-xs font-bold text-[#f3f2e7] shadow-sm hover:bg-[#131d1a] sm:self-auto transition"
            >
                <span>👥</span>
                <span>Tambah / Atur Role Kurir</span>
            </Link>
        </div>

        <!-- METRIK RINGKASAN -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
            <div class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 shadow-lg text-[#f3f2e7]">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#f3f2e7]/60">Total Personil</p>
                    <span class="rounded-lg bg-[#0d685b]/30 p-1.5 text-sm text-[#f3f2e7]">🛵</span>
                </div>
                <p class="mt-2 text-2xl font-black text-[#f3f2e7] sm:text-3xl">{{ stats.total_couriers }}</p>
                <p class="mt-0.5 text-[11px] text-[#f3f2e7]/50">{{ stats.idle_couriers }} bebas tugas · {{ stats.busy_couriers }} bertugas</p>
            </div>

            <div class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 shadow-lg text-[#f3f2e7]">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#f3f2e7]/60">Pengiriman Aktif</p>
                    <span class="rounded-lg bg-amber-500/20 p-1.5 text-sm text-amber-300">📦</span>
                </div>
                <p class="mt-2 text-2xl font-black text-amber-300 sm:text-3xl">{{ stats.active_deliveries }}</p>
                <p class="mt-0.5 text-[11px] text-[#f3f2e7]/50">Pickup & sedang di jalan</p>
            </div>

            <div class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 shadow-lg text-[#f3f2e7]">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#f3f2e7]/60">Selesai Hari Ini</p>
                    <span class="rounded-lg bg-emerald-500/20 p-1.5 text-sm text-emerald-400">✅</span>
                </div>
                <p class="mt-2 text-2xl font-black text-emerald-400 sm:text-3xl">{{ stats.delivered_today }}</p>
                <p class="mt-0.5 text-[11px] text-[#f3f2e7]/50">Pesanan berhasil diantar</p>
            </div>

            <div class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 shadow-lg text-[#f3f2e7]">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[#f3f2e7]/60">Siap Dikirim</p>
                    <span class="rounded-lg bg-rose-500/20 p-1.5 text-sm text-rose-300">⏳</span>
                </div>
                <p class="mt-2 text-2xl font-black text-[#f3f2e7] sm:text-3xl">{{ unassignedOrders.length }}</p>
                <p class="mt-0.5 text-[11px] text-[#f3f2e7]/50">Menunggu penugasan kurir</p>
            </div>
        </div>

        <!-- PESANAN SIAP KIRIM (BELUM ADA KURIR) -->
        <div v-if="unassignedOrders.length" class="overflow-hidden rounded-2xl border border-amber-500/40 bg-amber-950/30 p-5 shadow-lg text-[#f3f2e7]">
            <div class="flex items-center gap-2 mb-3">
                <span class="text-base">⚠️</span>
                <h3 class="text-sm font-black text-amber-300">
                    Pesanan Siap Kirim Menunggu Penugasan Kurir ({{ unassignedOrders.length }})
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
                            <Link :href="`/admin/pesanan/${order.invoice_number}`" class="text-xs font-black text-emerald-400 hover:underline">
                                #{{ order.invoice_number }}
                            </Link>
                            <span class="rounded-md bg-[#131d1a] border border-[#0d685b]/30 px-2 py-0.5 text-[10px] font-bold text-[#f3f2e7]/80">
                                {{ order.store }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-[#f3f2e7]/80">
                            <strong>{{ order.recipient_name }}</strong> ({{ order.recipient_phone }}) · {{ order.shipping_address }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <select
                            v-model="assignForm.courier_id"
                            class="rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3 py-1.5 text-xs text-[#f3f2e7] focus:outline-none focus:border-[#0d685b]"
                        >
                            <option value="">-- Pilih Kurir --</option>
                            <option
                                v-for="c in couriers"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.username }} ({{ c.name }}) {{ c.is_busy ? '[Sedang Mengantar]' : '[Siap]' }}
                            </option>
                        </select>
                        <button
                            type="button"
                            :disabled="!assignForm.courier_id || assignForm.processing"
                            class="rounded-xl bg-[#0d685b] px-3.5 py-1.5 text-xs font-bold text-[#f3f2e7] shadow-sm hover:bg-[#0d685b]/90 disabled:opacity-40 transition"
                            @click="submitAssign(order.invoice_number)"
                        >
                            Tugaskan
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- DAFTAR PERSONIL KURIR -->
        <div class="overflow-hidden rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-5 shadow-lg text-[#f3f2e7]">
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
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#0d685b]/30 font-black text-[#f3f2e7] text-sm">
                                🛵
                            </div>
                            <div>
                                <p class="font-black text-sm text-[#f3f2e7]">{{ c.name }}</p>
                                <p class="text-xs text-[#f3f2e7]/50">@{{ c.username }}</p>
                            </div>
                        </div>
                        <span
                            class="rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                            :class="c.is_busy ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40' : 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40'"
                        >
                            {{ c.is_busy ? '● Mengantar' : '● Siap' }}
                        </span>
                    </div>

                    <div class="mt-3 border-t border-[#0d685b]/20 pt-2.5 text-xs space-y-1">
                        <div class="flex justify-between text-[#f3f2e7]/60">
                            <span>No Telepon/WA:</span>
                            <span class="font-semibold text-[#f3f2e7]">{{ c.phone || 'Belum diisi' }}</span>
                        </div>
                        <div class="flex justify-between text-[#f3f2e7]/60">
                            <span>Selesai Diantar:</span>
                            <span class="font-bold text-[#f3f2e7]">{{ c.completed_count }} pesanan</span>
                        </div>
                        <div v-if="c.active_task" class="mt-2 rounded-lg bg-emerald-950/40 border border-emerald-500/30 p-2 text-[11px] text-emerald-300">
                            <span class="font-bold">Tugas Aktif:</span> #{{ c.active_task.invoice_number }} ({{ c.active_task.store }})
                        </div>
                    </div>
                </div>

                <div v-if="!couriers.length" class="col-span-full rounded-xl border border-dashed border-[#0d685b]/30 p-8 text-center text-xs text-[#f3f2e7]/50">
                    Belum ada pengguna dengan role "kurir". Anda dapat menambahkan akun kurir melalui menu <Link href="/admin/users" class="text-emerald-400 underline font-bold">Kelola Pengguna</Link>.
                </div>
            </div>
        </div>

        <!-- MONITORING TUGAS PENGIRIMAN REALTIME -->
        <div class="overflow-hidden rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-lg text-[#f3f2e7]">
            <div class="border-b border-[#0d685b]/30 p-5 bg-[#131d1a]">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-black text-[#f3f2e7]">
                            Monitoring Pengiriman (Live Tracking Board)
                        </h2>
                        <p class="text-xs text-[#f3f2e7]/60">Pemantauan lokasi GPS kurir dan foto bukti pickup/dropoff.</p>
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
                            <option value="waiting_pickup">Menunggu Pickup</option>
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
                    <thead class="border-b border-[#0d685b]/30 bg-[#131d1a] text-[11px] font-bold uppercase tracking-wider text-[#f3f2e7]/70">
                        <tr>
                            <th class="px-5 py-3.5">Pesanan & Toko</th>
                            <th class="px-4 py-3.5">Kurir Bertugas</th>
                            <th class="px-4 py-3.5">Tujuan & Penerima</th>
                            <th class="px-4 py-3.5">Status Pengantaran</th>
                            <th class="px-4 py-3.5">Lokasi GPS Terkini</th>
                            <th class="px-5 py-3.5 text-right">Foto Bukti</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#0d685b]/20">
                        <tr v-for="d in deliveries.data" :key="d.id" class="transition hover:bg-[#131d1a]/50">
                            <td class="px-5 py-4">
                                <Link :href="`/admin/pesanan/${d.invoice_number}`" class="font-bold text-emerald-400 hover:underline">
                                    #{{ d.invoice_number }}
                                </Link>
                                <p class="text-xs text-[#f3f2e7]/60">{{ d.store }}</p>
                            </td>

                            <td class="px-4 py-4">
                                <div v-if="d.courier">
                                    <p class="font-bold text-[#f3f2e7]">{{ d.courier.name }}</p>
                                    <p class="text-xs text-[#f3f2e7]/50">@{{ d.courier.username }} · {{ d.courier.phone || '-' }}</p>
                                </div>
                                <span v-else class="text-xs text-[#f3f2e7]/40 italic">Belum ada kurir</span>
                            </td>

                            <td class="px-4 py-4 max-w-xs">
                                <p class="font-bold text-[#f3f2e7]">{{ d.recipient_name }}</p>
                                <p class="text-xs text-[#f3f2e7]/60 truncate" :title="d.shipping_address">{{ d.shipping_address }}</p>
                            </td>

                            <td class="px-4 py-4">
                                <span
                                    class="inline-block rounded-lg px-2.5 py-0.5 text-[11px] font-bold"
                                    :class="deliveryStatusBadge(d.status).class"
                                >
                                    {{ deliveryStatusBadge(d.status).label }}
                                </span>
                                <p v-if="d.started_at" class="text-[10px] text-[#f3f2e7]/40 mt-0.5">Mulai: {{ d.started_at }}</p>
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
                                    <p class="text-[10px] text-[#f3f2e7]/40 mt-0.5">{{ d.location_updated_at }}</p>
                                </div>
                                <span v-else class="text-xs text-[#f3f2e7]/40 italic">Belum ada sinyal GPS</span>
                            </td>

                            <td class="px-5 py-4 text-right">
                                <div v-if="d.photos?.length" class="inline-flex items-center gap-1.5 justify-end">
                                    <button
                                        v-for="(photo, idx) in d.photos"
                                        :key="idx"
                                        type="button"
                                        class="relative h-9 w-9 overflow-hidden rounded-xl border border-[#0d685b]/40 hover:ring-2 hover:ring-[#0d685b] transition"
                                        :title="`Foto ${photo.type}`"
                                        @click="openPhotoModal(photo)"
                                    >
                                        <img :src="photo.url" class="h-full w-full object-cover" />
                                        <span class="absolute bottom-0 right-0 bg-black/70 px-0.5 text-[8px] font-bold text-white uppercase">
                                            {{ photo.type === 'pickup' ? 'P' : 'D' }}
                                        </span>
                                    </button>
                                </div>
                                <span v-else class="text-xs text-[#f3f2e7]/40 italic">Belum ada foto</span>
                            </td>
                        </tr>

                        <tr v-if="!deliveries.data?.length">
                            <td colspan="6" class="px-5 py-10 text-center text-xs text-[#f3f2e7]/50">
                                🍃 Tidak ada data pengiriman aktif atau selesai yang sesuai filter.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <div v-if="deliveries.last_page > 1" class="border-t border-[#0d685b]/20 px-5 py-3">
                <nav class="flex justify-center gap-1">
                    <template v-for="l in deliveries.links" :key="l.label">
                        <Link
                            v-if="l.url"
                            :href="l.url"
                            class="rounded-xl px-3 py-1.5 text-xs font-bold border transition"
                            :class="l.active ? 'bg-[#0d685b] border-[#0d685b] text-[#f3f2e7]' : 'border-[#0d685b]/30 bg-[#131d1a] text-[#f3f2e7]/70 hover:bg-[#17231f]'"
                            v-html="l.label"
                        />
                    </template>
                </nav>
            </div>
        </div>

        <!-- MODAL PREVIEW FOTO BUKTI -->
        <div v-if="activePhoto" class="fixed inset-0 z-50 flex items-center justify-center bg-[#17231f]/85 backdrop-blur-md p-4">
            <div class="w-full max-w-lg rounded-2xl bg-[#1c2a25] p-5 shadow-2xl border border-[#0d685b]/40 text-[#f3f2e7]">
                <div class="flex items-center justify-between border-b border-[#0d685b]/30 pb-3">
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
                <div class="mt-4 overflow-hidden rounded-xl bg-black border border-[#0d685b]/30">
                    <img :src="activePhoto.url" class="max-h-[60vh] w-full object-contain" />
                </div>
                <div class="mt-3 flex items-center justify-between text-xs text-[#f3f2e7]/60">
                    <span>Waktu: {{ activePhoto.taken_at }}</span>
                    <span v-if="activePhoto.notes">Catatan: {{ activePhoto.notes }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

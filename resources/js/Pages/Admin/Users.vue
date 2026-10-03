<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { rupiah } from '../../utils/format';

defineOptions({ layout: AdminLayout });

const props = defineProps({
    users: Object,
    roles: Array,
    stats: Object,
    roleCounts: Object,
    filters: Object,
    isSuperAdmin: Boolean,
    urls: Object,
});

const page = usePage();
const isSuperAdmin = computed(() => props.isSuperAdmin ?? !!page.props.auth?.user?.is_super_admin);

const search = ref(props.filters.q ?? '');
const roleFilter = ref(props.filters.role ?? '');
const statusFilter = ref(props.filters.status ?? '');
const sortFilter = ref(props.filters.sort ?? 'latest');

const applyFilter = (overrideRole = null) => {
    if (overrideRole !== null) {
        roleFilter.value = overrideRole;
    }
    router.get('/admin/users', {
        q: search.value || undefined,
        role: roleFilter.value || undefined,
        status: statusFilter.value || undefined,
        sort: sortFilter.value !== 'latest' ? sortFilter.value : undefined,
    }, { preserveState: true, replace: true });
};

const resetFilter = () => {
    search.value = '';
    roleFilter.value = '';
    statusFilter.value = '';
    sortFilter.value = 'latest';
    applyFilter();
};

// Modal State & Forms
const selectedUser = ref(null);
const modalType = ref(''); // 'create' | 'edit' | 'detail' | 'role' | 'password' | 'saldo' | 'delete'

const createForm = useForm({
    username: '',
    name: '',
    email: '',
    phone: '',
    password: '',
    role: 'pengguna',
    initial_balance: '',
});

const editForm = useForm({
    username: '',
    name: '',
    email: '',
    phone: '',
});

const roleForm = useForm({ role: '' });
const passwordForm = useForm({ password: '' });
const balanceForm = useForm({ type: 'credit', amount: '', note: '' });

const openCreateModal = () => {
    if (!isSuperAdmin.value) return;
    createForm.reset();
    createForm.clearErrors();
    createForm.role = 'pengguna';
    modalType.value = 'create';
};

const openEditModal = (u) => {
    if (!isSuperAdmin.value) return;
    selectedUser.value = u;
    editForm.reset();
    editForm.clearErrors();
    editForm.username = u.username;
    editForm.name = u.name || '';
    editForm.email = u.email || '';
    editForm.phone = u.phone || '';
    modalType.value = 'edit';
};

const openDetailModal = (u) => {
    if (!isSuperAdmin.value) return;
    selectedUser.value = u;
    modalType.value = 'detail';
};

const openRoleModal = (u) => {
    if (!isSuperAdmin.value) return;
    selectedUser.value = u;
    roleForm.clearErrors();
    roleForm.role = u.role;
    modalType.value = 'role';
};

const openPasswordModal = (u) => {
    if (!isSuperAdmin.value) return;
    selectedUser.value = u;
    passwordForm.clearErrors();
    passwordForm.password = '';
    modalType.value = 'password';
};

const openBalanceModal = (u) => {
    if (!isSuperAdmin.value) return;
    selectedUser.value = u;
    balanceForm.clearErrors();
    balanceForm.reset();
    modalType.value = 'saldo';
};

const openDeleteModal = (u) => {
    if (!isSuperAdmin.value) return;
    selectedUser.value = u;
    modalType.value = 'delete';
};

const closeModal = () => {
    modalType.value = '';
    selectedUser.value = null;
};

const submitCreate = () => {
    if (!isSuperAdmin.value) return;
    createForm.post('/admin/users', {
        onSuccess: () => closeModal(),
    });
};

const submitEdit = () => {
    if (!isSuperAdmin.value) return;
    editForm.put(`/admin/users/${selectedUser.value.id}`, {
        onSuccess: () => closeModal(),
    });
};

const submitRole = () => {
    if (!isSuperAdmin.value) return;
    roleForm.patch(`/admin/users/${selectedUser.value.id}/role`, {
        onSuccess: () => closeModal(),
    });
};

const submitPassword = () => {
    if (!isSuperAdmin.value) return;
    passwordForm.put(`/admin/users/${selectedUser.value.id}/password`, {
        onSuccess: () => closeModal(),
    });
};

const submitBalance = () => {
    if (!isSuperAdmin.value) return;
    balanceForm.post(`/admin/users/${selectedUser.value.id}/saldo`, {
        onSuccess: () => closeModal(),
    });
};

const submitDelete = () => {
    if (!isSuperAdmin.value) return;
    router.delete(`/admin/users/${selectedUser.value.id}`, {
        onSuccess: () => closeModal(),
    });
};

const formatWaUrl = (phone) => {
    if (!phone) return '#';
    let clean = phone.replace(/[^0-9]/g, '');
    if (clean.startsWith('0')) {
        clean = '62' + clean.slice(1);
    }
    return `https://wa.me/${clean}`;
};

const inputClass = 'w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2 text-xs text-[#f3f2e7] shadow-sm placeholder:text-[#f3f2e7]/40 focus:border-[#0d685b] focus:ring-1 focus:ring-[#0d685b] focus:outline-none transition';
</script>

<template>
    <Head title="Kelola Pengguna - Admin" />

    <div class="space-y-6">
        <!-- HEADER & TOMBOL TAMBAH -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-black tracking-tight text-[#f3f2e7] sm:text-3xl">
                    Manajemen Pengguna
                </h1>
                <p class="mt-1 text-xs text-[#f3f2e7]/60 sm:text-sm">
                    {{ isSuperAdmin
                        ? 'Kelola akun pengguna, registrasi user baru, atur hak akses peran, edit profil, reset password, dan ledger saldo.'
                        : 'Daftar dan informasi akun pengguna sistem RAMELA (Mode Tinjauan Operasional).' }}
                </p>
            </div>
            <!-- Tombol Tambah Pengguna (Hanya tampil untuk Super Admin) -->
            <div v-if="isSuperAdmin" class="flex items-center gap-2.5">
                <button
                    class="inline-flex items-center gap-2 rounded-xl bg-[#0d685b] px-4 py-2.5 text-xs font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 transition hover:bg-[#0d685b]/90 active:scale-95"
                    @click="openCreateModal"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah Pengguna
                </button>
            </div>
        </div>

        <!-- STATS KPI CARDS -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <div class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 shadow-lg text-[#f3f2e7]">
                <div class="flex items-center gap-2 text-[#f3f2e7]/60">
                    <span class="text-sm">👥</span>
                    <span class="text-xs font-semibold">Total Akun</span>
                </div>
                <p class="mt-2 text-2xl font-black text-[#f3f2e7]">{{ stats?.total ?? users.total }}</p>
                <p class="mt-0.5 text-[11px] text-[#f3f2e7]/50">{{ isSuperAdmin ? 'Terdaftar di sistem' : 'Pengguna aktif' }}</p>
            </div>
            <div class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 shadow-lg text-[#f3f2e7]">
                <div class="flex items-center gap-2 text-[#f3f2e7]/60">
                    <span class="text-sm">🛒</span>
                    <span class="text-xs font-semibold">Pelanggan</span>
                </div>
                <p class="mt-2 text-2xl font-black text-emerald-400">{{ stats?.customers ?? 0 }}</p>
                <p class="mt-0.5 text-[11px] text-[#f3f2e7]/50">Pembeli aktif</p>
            </div>
            <div class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 shadow-lg text-[#f3f2e7]">
                <div class="flex items-center gap-2 text-[#f3f2e7]/60">
                    <span class="text-sm">🛵</span>
                    <span class="text-xs font-semibold">Kurir Armada</span>
                </div>
                <p class="mt-2 text-2xl font-black text-amber-400">{{ stats?.couriers ?? 0 }}</p>
                <p class="mt-0.5 text-[11px] text-[#f3f2e7]/50">Personil pengantar</p>
            </div>
            <div class="rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 shadow-lg text-[#f3f2e7]">
                <div class="flex items-center gap-2 text-[#f3f2e7]/60">
                    <span class="text-sm">🛡️</span>
                    <span class="text-xs font-semibold">{{ isSuperAdmin ? 'Staf & Admin' : 'Administrator' }}</span>
                </div>
                <p class="mt-2 text-2xl font-black text-purple-400">{{ stats?.admins ?? 0 }}</p>
                <p class="mt-0.5 text-[11px] text-[#f3f2e7]/50">{{ isSuperAdmin ? 'Admin & Super Admin' : 'Admin Operasional' }}</p>
            </div>
            <div class="col-span-2 rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4 shadow-lg text-[#f3f2e7] sm:col-span-1">
                <div class="flex items-center gap-2 text-[#f3f2e7]/60">
                    <span class="text-sm">💳</span>
                    <span class="text-xs font-semibold">Total Saldo</span>
                </div>
                <p class="mt-2 text-xl font-black text-emerald-400 sm:text-2xl">{{ rupiah(stats?.total_balance ?? 0) }}</p>
                <p class="mt-0.5 text-[11px] text-[#f3f2e7]/50">Akumulasi saldo user</p>
            </div>
        </div>

        <!-- QUICK ROLE FILTER TABS (Kategori Peran) -->
        <div class="flex flex-wrap items-center gap-2 border-b border-[#0d685b]/20 pb-2">
            <button
                class="inline-flex items-center gap-2 rounded-xl px-3.5 py-1.5 text-xs font-bold border transition"
                :class="!roleFilter ? 'bg-[#0d685b] border-[#0d685b] text-[#f3f2e7] shadow-sm' : 'border-[#0d685b]/20 bg-[#1c2a25] text-[#f3f2e7]/70 hover:bg-[#131d1a]'"
                @click="applyFilter('')"
            >
                Semua
                <span class="rounded-full px-2 py-0.2 text-[10px]" :class="!roleFilter ? 'bg-[#131d1a] text-[#f3f2e7]' : 'bg-[#131d1a] text-[#f3f2e7]/60'">
                    {{ roleCounts?.all ?? users.total }}
                </span>
            </button>
            <button
                class="inline-flex items-center gap-2 rounded-xl px-3.5 py-1.5 text-xs font-bold border transition"
                :class="roleFilter === 'pengguna' ? 'bg-[#0d685b] border-[#0d685b] text-[#f3f2e7] shadow-sm' : 'border-[#0d685b]/20 bg-[#1c2a25] text-[#f3f2e7]/70 hover:bg-[#131d1a]'"
                @click="applyFilter('pengguna')"
            >
                Pelanggan
                <span class="rounded-full px-2 py-0.2 text-[10px]" :class="roleFilter === 'pengguna' ? 'bg-[#131d1a] text-[#f3f2e7]' : 'bg-[#131d1a] text-[#f3f2e7]/60'">
                    {{ roleCounts?.pengguna ?? 0 }}
                </span>
            </button>
            <button
                class="inline-flex items-center gap-2 rounded-xl px-3.5 py-1.5 text-xs font-bold border transition"
                :class="roleFilter === 'kurir' ? 'bg-amber-600/80 border-amber-500 text-white shadow-sm' : 'border-[#0d685b]/20 bg-[#1c2a25] text-[#f3f2e7]/70 hover:bg-[#131d1a]'"
                @click="applyFilter('kurir')"
            >
                Kurir
                <span class="rounded-full px-2 py-0.2 text-[10px]" :class="roleFilter === 'kurir' ? 'bg-[#131d1a] text-[#f3f2e7]' : 'bg-[#131d1a] text-[#f3f2e7]/60'">
                    {{ roleCounts?.kurir ?? 0 }}
                </span>
            </button>
            <button
                class="inline-flex items-center gap-2 rounded-xl px-3.5 py-1.5 text-xs font-bold border transition"
                :class="roleFilter === 'admin' ? 'bg-[#0d685b] border-[#0d685b] text-[#f3f2e7] shadow-sm' : 'border-[#0d685b]/20 bg-[#1c2a25] text-[#f3f2e7]/70 hover:bg-[#131d1a]'"
                @click="applyFilter('admin')"
            >
                Admin
                <span class="rounded-full px-2 py-0.2 text-[10px]" :class="roleFilter === 'admin' ? 'bg-[#131d1a] text-[#f3f2e7]' : 'bg-[#131d1a] text-[#f3f2e7]/60'">
                    {{ roleCounts?.admin ?? 0 }}
                </span>
            </button>
            <!-- Tab Kategori Super Admin (Hanya tampil untuk Super Admin) -->
            <button
                v-if="isSuperAdmin && (roleCounts?.super_admin ?? 0) > 0"
                class="inline-flex items-center gap-2 rounded-xl px-3.5 py-1.5 text-xs font-bold border transition"
                :class="roleFilter === 'super_admin' ? 'bg-purple-700/80 border-purple-500 text-white shadow-sm' : 'border-[#0d685b]/20 bg-[#1c2a25] text-[#f3f2e7]/70 hover:bg-[#131d1a]'"
                @click="applyFilter('super_admin')"
            >
                Super Admin
                <span class="rounded-full px-2 py-0.2 text-[10px]" :class="roleFilter === 'super_admin' ? 'bg-[#131d1a] text-[#f3f2e7]' : 'bg-[#131d1a] text-[#f3f2e7]/60'">
                    {{ roleCounts?.super_admin ?? 0 }}
                </span>
            </button>
        </div>

        <!-- FILTER & PENCARIAN CONTROLS -->
        <div class="flex flex-wrap items-center gap-2.5 rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-3.5 shadow-lg">
            <!-- PENCARIAN -->
            <div class="min-w-[220px] flex-1">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-[#f3f2e7]/40">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </span>
                    <input
                        v-model="search"
                        placeholder="Cari username, nama, email, no. WhatsApp..."
                        :class="[inputClass, 'pl-9']"
                        @keydown.enter.prevent="applyFilter()"
                    />
                </div>
            </div>

            <!-- FILTER ROLE DROPDOWN -->
            <div class="w-full sm:w-40">
                <select v-model="roleFilter" :class="inputClass" @change="applyFilter()">
                    <option value="">Semua Role</option>
                    <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                </select>
            </div>

            <!-- FILTER STATUS AKTIVITAS -->
            <div class="w-full sm:w-44">
                <select v-model="statusFilter" :class="inputClass" @change="applyFilter()">
                    <option value="">Semua Aktivitas</option>
                    <option value="active">Pernah Login</option>
                    <option value="never">Belum Pernah Login</option>
                </select>
            </div>

            <!-- URUTKAN -->
            <div class="w-full sm:w-48">
                <select v-model="sortFilter" :class="inputClass" @change="applyFilter()">
                    <option value="latest">Terbaru Terdaftar</option>
                    <option value="oldest">Terlama Terdaftar</option>
                    <option value="balance_desc">Saldo Tertinggi</option>
                    <option value="balance_asc">Saldo Terendah</option>
                    <option value="active_desc">Terakhir Aktif</option>
                    <option value="name_asc">Username (A-Z)</option>
                    <option value="orders_desc">Pesanan Terbanyak</option>
                </select>
            </div>

            <!-- BUTTONS -->
            <button
                class="rounded-xl bg-[#0d685b] px-4 py-2 text-xs font-bold text-[#f3f2e7] shadow-sm transition hover:bg-[#0d685b]/90 active:scale-95"
                @click="applyFilter()"
            >
                Terapkan
            </button>
            <button
                v-if="search || roleFilter || statusFilter || sortFilter !== 'latest'"
                class="rounded-xl border border-[#0d685b]/30 bg-[#131d1a] px-3 py-2 text-xs font-semibold text-[#f3f2e7]/70 transition hover:bg-[#17231f]"
                @click="resetFilter"
            >
                Reset
            </button>
        </div>

        <!-- TABEL PENGGUNA -->
        <div class="overflow-hidden rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-lg text-[#f3f2e7]">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm">
                    <thead class="border-b border-[#0d685b]/30 bg-[#131d1a] text-[11px] font-bold uppercase tracking-wider text-[#f3f2e7]/70">
                        <tr>
                            <th class="px-5 py-3.5">Pengguna</th>
                            <th class="px-4 py-3.5">Role</th>
                            <th class="px-4 py-3.5">Saldo Dompet</th>
                            <th class="px-4 py-3.5">Pesanan</th>
                            <th class="px-4 py-3.5">Aktivitas</th>
                            <th class="px-4 py-3.5">Terdaftar</th>
                            <!-- Kolom Aksi HANYA tampil jika Super Admin -->
                            <th v-if="isSuperAdmin" class="px-5 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#0d685b]/20">
                        <tr v-for="u in users.data" :key="u.id" class="transition hover:bg-[#131d1a]/50">
                            <!-- PENGGUNA INFO -->
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-xs font-bold ring-1 border"
                                        :class="{
                                            'bg-purple-950/60 text-purple-300 border-purple-500/40': u.role === 'super_admin',
                                            'bg-[#0d685b]/30 text-[#f3f2e7] border-[#0d685b]/50': u.role === 'admin',
                                            'bg-amber-950/60 text-amber-300 border-amber-500/40': u.role === 'kurir',
                                            'bg-[#131d1a] text-[#f3f2e7] border-[#0d685b]/30': u.role === 'pengguna',
                                        }"
                                    >
                                        {{ (u.name || u.username).charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <p class="font-bold text-[#f3f2e7] truncate">@{{ u.username }}</p>
                                        </div>
                                        <p class="text-xs text-[#f3f2e7]/70 truncate">
                                            {{ u.name || 'Belum mengisi nama' }}
                                        </p>
                                        <div class="mt-0.5 flex flex-wrap items-center gap-2 text-[11px] text-[#f3f2e7]/50">
                                            <span v-if="u.email" class="truncate">✉️ {{ u.email }}</span>
                                            <a
                                                v-if="u.phone"
                                                :href="formatWaUrl(u.phone)"
                                                target="_blank"
                                                class="inline-flex items-center gap-1 text-emerald-400 hover:underline font-medium"
                                                title="Hubungi via WhatsApp"
                                            >
                                                <span>💬 {{ u.phone }}</span>
                                            </a>
                                            <span v-else class="text-[#f3f2e7]/30">Tanpa HP</span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- ROLE BADGE -->
                            <td class="px-4 py-4 whitespace-nowrap">
                                <span
                                    class="inline-block rounded-lg px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide border"
                                    :class="{
                                        'bg-purple-950/60 text-purple-300 border-purple-500/40': u.role === 'super_admin',
                                        'bg-[#0d685b]/30 text-[#f3f2e7] border-[#0d685b]/50': u.role === 'admin',
                                        'bg-amber-950/60 text-amber-300 border-amber-500/40': u.role === 'kurir',
                                        'bg-[#131d1a] text-[#f3f2e7]/80 border-[#0d685b]/30': u.role === 'pengguna',
                                    }"
                                >
                                    {{ u.role }}
                                </span>
                            </td>

                            <!-- SALDO DOMPET -->
                            <td class="px-4 py-4 whitespace-nowrap">
                                <span
                                    class="font-black"
                                    :class="Number(u.saldo) > 0 ? 'text-emerald-400' : 'text-[#f3f2e7]/70'"
                                >
                                    {{ rupiah(u.saldo) }}
                                </span>
                            </td>

                            <!-- PESANAN COUNT -->
                            <td class="px-4 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 rounded-full bg-[#131d1a] border border-[#0d685b]/30 px-2.5 py-0.5 text-xs font-semibold text-[#f3f2e7]/80">
                                    📦 {{ u.orders_count ?? 0 }}
                                </span>
                            </td>

                            <!-- STATUS AKTIVITAS -->
                            <td class="px-4 py-4 whitespace-nowrap text-xs">
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="h-2 w-2 rounded-full shrink-0"
                                        :class="u.is_recently_active ? 'bg-emerald-400 animate-pulse' : (u.last_active_at ? 'bg-slate-500' : 'bg-amber-400')"
                                    ></span>
                                    <span class="text-[#f3f2e7]/70">
                                        {{ u.last_active_at ? u.last_active_at : 'Belum login' }}
                                    </span>
                                </div>
                            </td>

                            <!-- TERDAFTAR -->
                            <td class="px-4 py-4 whitespace-nowrap text-xs text-[#f3f2e7]/50">
                                {{ u.created_at }}
                            </td>

                            <!-- SEMUA TOMBOL AKSI (Hanya tampil jika Super Admin) -->
                            <td v-if="isSuperAdmin" class="px-5 py-4 whitespace-nowrap text-right">
                                <div class="inline-flex items-center gap-1">
                                    <button
                                        class="rounded-lg border border-[#0d685b]/30 bg-[#131d1a] px-2.5 py-1 text-xs font-semibold text-[#f3f2e7] hover:bg-[#17231f] transition"
                                        title="Detail Pengguna"
                                        @click="openDetailModal(u)"
                                    >
                                        Detail
                                    </button>
                                    <button
                                        class="rounded-lg border border-[#0d685b]/30 bg-[#131d1a] px-2.5 py-1 text-xs font-semibold text-emerald-400 hover:bg-[#17231f] transition"
                                        title="Edit Profil"
                                        @click="openEditModal(u)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        class="rounded-lg border border-[#0d685b]/30 bg-[#131d1a] px-2.5 py-1 text-xs font-semibold text-purple-400 hover:bg-[#17231f] transition"
                                        title="Ubah Role"
                                        @click="openRoleModal(u)"
                                    >
                                        Role
                                    </button>
                                    <button
                                        class="rounded-lg border border-[#0d685b]/30 bg-[#131d1a] px-2.5 py-1 text-xs font-semibold text-amber-400 hover:bg-[#17231f] transition"
                                        title="Reset Password"
                                        @click="openPasswordModal(u)"
                                    >
                                        Sandi
                                    </button>
                                    <button
                                        class="rounded-lg border border-[#0d685b]/30 bg-[#131d1a] px-2.5 py-1 text-xs font-semibold text-teal-400 hover:bg-[#17231f] transition"
                                        title="Koreksi Saldo Dompet"
                                        @click="openBalanceModal(u)"
                                    >
                                        Saldo
                                    </button>
                                    <button
                                        class="rounded-lg border border-rose-500/30 bg-[#131d1a] px-2.5 py-1 text-xs font-semibold text-rose-400 hover:bg-[#17231f] transition"
                                        title="Hapus Akun Pengguna"
                                        @click="openDeleteModal(u)"
                                    >
                                        Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!users.data?.length">
                            <td :colspan="isSuperAdmin ? 7 : 6" class="px-5 py-12 text-center">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-[#131d1a] border border-[#0d685b]/30 text-2xl">
                                    👤
                                </div>
                                <p class="mt-3 text-sm font-bold text-[#f3f2e7]">Tidak ada pengguna ditemukan</p>
                                <p class="mt-1 text-xs text-[#f3f2e7]/50">Coba ubah kata kunci pencarian atau reset filter.</p>
                                <button
                                    class="mt-4 rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-4 py-2 text-xs font-semibold text-[#f3f2e7] hover:bg-[#17231f]"
                                    @click="resetFilter"
                                >
                                    Reset Semua Filter
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION & INFO SUMMARY -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-t border-[#0d685b]/20 px-5 py-3.5 gap-3 bg-[#131d1a]">
                <p class="text-xs text-[#f3f2e7]/60">
                    Menampilkan <span class="font-bold text-[#f3f2e7]">{{ users.from ?? 0 }}</span> sampai
                    <span class="font-bold text-[#f3f2e7]">{{ users.to ?? 0 }}</span> dari
                    <span class="font-bold text-[#f3f2e7]">{{ users.total ?? 0 }}</span> akun pengguna
                </p>
                <div v-if="users.last_page > 1">
                    <nav class="flex flex-wrap justify-center gap-1">
                        <template v-for="l in users.links" :key="l.label">
                            <Link
                                v-if="l.url"
                                :href="l.url"
                                class="rounded-xl px-3 py-1.5 text-xs font-bold border transition"
                                :class="l.active ? 'bg-[#0d685b] border-[#0d685b] text-[#f3f2e7]' : 'border-[#0d685b]/30 bg-[#1c2a25] text-[#f3f2e7]/70 hover:bg-[#17231f]'"
                                v-html="l.label"
                            />
                        </template>
                    </nav>
                </div>
            </div>
        </div>

        <!-- ================= MODAL TAMBAH PENGGUNA (Hanya Super Admin) ================= -->
        <div v-if="isSuperAdmin && modalType === 'create'" class="fixed inset-0 z-50 flex items-center justify-center bg-[#17231f]/85 backdrop-blur-md p-4">
            <div class="w-full max-w-lg rounded-2xl bg-[#1c2a25] p-6 shadow-2xl border border-[#0d685b]/40 text-[#f3f2e7] max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-[#0d685b]/30 pb-3">
                    <div>
                        <h3 class="text-base font-black text-[#f3f2e7]">Tambah Pengguna Baru</h3>
                        <p class="text-xs text-[#f3f2e7]/60">Daftarkan akun baru ke dalam sistem RAMELA.</p>
                    </div>
                    <button class="rounded-lg p-1 text-[#f3f2e7]/60 hover:bg-[#131d1a] hover:text-[#f3f2e7]" @click="closeModal">✕</button>
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="submitCreate">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <!-- USERNAME -->
                        <div>
                            <label class="mb-1 block text-xs font-bold text-[#0d685b] uppercase">Username <span class="text-rose-400">*</span></label>
                            <input v-model="createForm.username" placeholder="Contoh: budi_ramela" :class="inputClass" />
                            <p v-if="createForm.errors.username" class="mt-1 text-xs text-rose-400">{{ createForm.errors.username }}</p>
                        </div>

                        <!-- PASSWORD -->
                        <div>
                            <label class="mb-1 block text-xs font-bold text-[#0d685b] uppercase">Password <span class="text-rose-400">*</span></label>
                            <input v-model="createForm.password" type="text" placeholder="Min. 6 karakter" :class="inputClass" />
                            <p v-if="createForm.errors.password" class="mt-1 text-xs text-rose-400">{{ createForm.errors.password }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <!-- NAMA LENGKAP -->
                        <div>
                            <label class="mb-1 block text-xs font-bold text-[#0d685b] uppercase">Nama Lengkap</label>
                            <input v-model="createForm.name" placeholder="Contoh: Budi Santoso" :class="inputClass" />
                            <p v-if="createForm.errors.name" class="mt-1 text-xs text-rose-400">{{ createForm.errors.name }}</p>
                        </div>

                        <!-- EMAIL -->
                        <div>
                            <label class="mb-1 block text-xs font-bold text-[#0d685b] uppercase">Email (Opsional)</label>
                            <input v-model="createForm.email" type="email" placeholder="budi@example.com" :class="inputClass" />
                            <p v-if="createForm.errors.email" class="mt-1 text-xs text-rose-400">{{ createForm.errors.email }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                        <!-- NO TELEPON / WHATSAPP -->
                        <div>
                            <label class="mb-1 block text-xs font-bold text-[#0d685b] uppercase">No. WhatsApp / HP</label>
                            <input v-model="createForm.phone" placeholder="081234567890" :class="inputClass" />
                            <p v-if="createForm.errors.phone" class="mt-1 text-xs text-rose-400">{{ createForm.errors.phone }}</p>
                        </div>

                        <!-- ROLE -->
                        <div>
                            <label class="mb-1 block text-xs font-bold text-[#0d685b] uppercase">Peran / Hak Akses <span class="text-rose-400">*</span></label>
                            <select v-model="createForm.role" :class="inputClass">
                                <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                            </select>
                            <p v-if="createForm.errors.role" class="mt-1 text-xs text-rose-400">{{ createForm.errors.role }}</p>
                        </div>
                    </div>

                    <!-- SALDO AWAL -->
                    <div class="rounded-xl border border-[#0d685b]/30 bg-[#131d1a] p-3.5">
                        <label class="mb-1 block text-xs font-bold text-emerald-400">Deposit Saldo Awal (Opsional - Rp)</label>
                        <input
                            v-model="createForm.initial_balance"
                            type="number"
                            placeholder="Contoh: 50000 (biarkan kosong jika Rp 0)"
                            :class="inputClass"
                        />
                        <p class="mt-1 text-[11px] text-[#f3f2e7]/60">Jika diisi, saldo akan otomatis dikreditkan melalui ledger transaksi dompet.</p>
                        <p v-if="createForm.errors.initial_balance" class="mt-1 text-xs text-rose-400">{{ createForm.errors.initial_balance }}</p>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-[#0d685b]/20">
                        <button type="button" class="rounded-xl px-4 py-2 text-xs font-semibold text-[#f3f2e7]/60 hover:bg-[#131d1a]" @click="closeModal">
                            Batal
                        </button>
                        <button :disabled="createForm.processing" class="rounded-xl bg-[#0d685b] px-5 py-2 text-xs font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 hover:bg-[#0d685b]/90 disabled:opacity-50">
                            Simpan Pengguna
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================= MODAL EDIT PROFIL (Hanya Super Admin) ================= -->
        <div v-if="isSuperAdmin && modalType === 'edit'" class="fixed inset-0 z-50 flex items-center justify-center bg-[#17231f]/85 backdrop-blur-md p-4">
            <div class="w-full max-w-md rounded-2xl bg-[#1c2a25] p-6 shadow-2xl border border-[#0d685b]/40 text-[#f3f2e7]">
                <div class="flex items-center justify-between border-b border-[#0d685b]/30 pb-3">
                    <div>
                        <h3 class="text-base font-black text-[#f3f2e7]">Edit Profil Pengguna</h3>
                        <p class="text-xs text-[#f3f2e7]/60">Perbarui informasi profil pengguna.</p>
                    </div>
                    <button class="rounded-lg p-1 text-[#f3f2e7]/60 hover:bg-[#131d1a] hover:text-[#f3f2e7]" @click="closeModal">✕</button>
                </div>

                <form class="mt-4 space-y-4" @submit.prevent="submitEdit">
                    <div>
                        <label class="mb-1 block text-xs font-bold text-[#0d685b] uppercase">Username</label>
                        <input v-model="editForm.username" :class="inputClass" />
                        <p v-if="editForm.errors.username" class="mt-1 text-xs text-rose-400">{{ editForm.errors.username }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-bold text-[#0d685b] uppercase">Nama Lengkap</label>
                        <input v-model="editForm.name" placeholder="Nama lengkap pengguna..." :class="inputClass" />
                        <p v-if="editForm.errors.name" class="mt-1 text-xs text-rose-400">{{ editForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-bold text-[#0d685b] uppercase">Email</label>
                        <input v-model="editForm.email" type="email" placeholder="email@example.com" :class="inputClass" />
                        <p v-if="editForm.errors.email" class="mt-1 text-xs text-rose-400">{{ editForm.errors.email }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-bold text-[#0d685b] uppercase">Nomor WhatsApp / HP</label>
                        <input v-model="editForm.phone" placeholder="08xxxxxxxx" :class="inputClass" />
                        <p v-if="editForm.errors.phone" class="mt-1 text-xs text-rose-400">{{ editForm.errors.phone }}</p>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-[#0d685b]/20">
                        <button type="button" class="rounded-xl px-4 py-2 text-xs font-semibold text-[#f3f2e7]/60 hover:bg-[#131d1a]" @click="closeModal">
                            Batal
                        </button>
                        <button :disabled="editForm.processing" class="rounded-xl bg-[#0d685b] px-5 py-2 text-xs font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 hover:bg-[#0d685b]/90 disabled:opacity-50">
                            Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================= MODAL DETAIL PENGGUNA (Hanya Super Admin) ================= -->
        <div v-if="isSuperAdmin && modalType === 'detail'" class="fixed inset-0 z-50 flex items-center justify-center bg-[#17231f]/85 backdrop-blur-md p-4">
            <div class="w-full max-w-md rounded-2xl bg-[#1c2a25] p-6 shadow-2xl border border-[#0d685b]/40 text-[#f3f2e7]">
                <div class="flex items-center justify-between border-b border-[#0d685b]/30 pb-3">
                    <h3 class="text-base font-black text-[#f3f2e7]">Informasi Lengkap Akun</h3>
                    <button class="rounded-lg p-1 text-[#f3f2e7]/60 hover:bg-[#131d1a] hover:text-[#f3f2e7]" @click="closeModal">✕</button>
                </div>

                <div class="mt-4 space-y-4">
                    <div class="flex items-center gap-3 rounded-2xl bg-[#131d1a] p-4 border border-[#0d685b]/30">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#0d685b] text-base font-black text-[#f3f2e7]">
                            {{ (selectedUser?.name || selectedUser?.username).charAt(0).toUpperCase() }}
                        </div>
                        <div class="min-w-0">
                            <p class="font-black text-[#f3f2e7] text-sm">@{{ selectedUser?.username }}</p>
                            <p class="text-xs text-[#f3f2e7]/60">{{ selectedUser?.name || 'Belum mengisi nama lengkap' }}</p>
                            <span class="mt-1 inline-block rounded-md bg-[#17231f] border border-[#0d685b]/30 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#f3f2e7]">
                                {{ selectedUser?.role }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-xs">
                        <div class="rounded-xl border border-[#0d685b]/20 p-3 bg-[#131d1a]">
                            <span class="text-[#f3f2e7]/50 text-[11px] block">Saldo Dompet</span>
                            <span class="font-black text-sm text-emerald-400">{{ rupiah(selectedUser?.saldo) }}</span>
                        </div>
                        <div class="rounded-xl border border-[#0d685b]/20 p-3 bg-[#131d1a]">
                            <span class="text-[#f3f2e7]/50 text-[11px] block">Total Pesanan</span>
                            <span class="font-black text-sm text-[#f3f2e7]">📦 {{ selectedUser?.orders_count ?? 0 }} transaksi</span>
                        </div>
                    </div>

                    <div class="space-y-2 text-xs divide-y divide-[#0d685b]/20 text-[#f3f2e7]/80">
                        <div class="flex justify-between py-2">
                            <span class="text-[#f3f2e7]/50">Email:</span>
                            <span class="font-semibold text-[#f3f2e7]">{{ selectedUser?.email || '-' }}</span>
                        </div>
                        <div class="flex justify-between py-2">
                            <span class="text-[#f3f2e7]/50">No. WhatsApp:</span>
                            <div>
                                <a
                                    v-if="selectedUser?.phone"
                                    :href="formatWaUrl(selectedUser?.phone)"
                                    target="_blank"
                                    class="font-bold text-emerald-400 hover:underline"
                                >
                                    {{ selectedUser?.phone }} ↗
                                </a>
                                <span v-else>-</span>
                            </div>
                        </div>
                        <div class="flex justify-between py-2">
                            <span class="text-[#f3f2e7]/50">Terakhir Aktif:</span>
                            <span class="font-semibold text-[#f3f2e7]">{{ selectedUser?.last_active_at || 'Belum pernah login' }}</span>
                        </div>
                        <div class="flex justify-between py-2">
                            <span class="text-[#f3f2e7]/50">Tanggal Terdaftar:</span>
                            <span class="font-semibold text-[#f3f2e7]">{{ selectedUser?.created_at_full || selectedUser?.created_at }}</span>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2 border-t border-[#0d685b]/20">
                        <button type="button" class="rounded-xl bg-[#0d685b] px-4 py-2 text-xs font-bold text-[#f3f2e7] shadow-sm hover:bg-[#0d685b]/90 transition" @click="closeModal">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= MODAL UBAH ROLE (Hanya Super Admin) ================= -->
        <div v-if="isSuperAdmin && modalType === 'role'" class="fixed inset-0 z-50 flex items-center justify-center bg-[#17231f]/85 backdrop-blur-md p-4">
            <div class="w-full max-w-sm rounded-2xl bg-[#1c2a25] p-6 shadow-2xl border border-[#0d685b]/40 text-[#f3f2e7]">
                <h3 class="text-base font-black text-[#f3f2e7]">Ubah Hak Akses Role</h3>
                <p class="mt-1 text-xs text-[#f3f2e7]/60">Pengguna: <strong class="text-[#f3f2e7]">@{{ selectedUser?.username }}</strong></p>
                <form class="mt-4 space-y-4" @submit.prevent="submitRole">
                    <div>
                        <label class="mb-1 block text-xs font-bold text-[#0d685b] uppercase">Pilih Role Baru</label>
                        <select v-model="roleForm.role" :class="inputClass">
                            <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
                        </select>
                        <p v-if="roleForm.errors.role" class="mt-1 text-xs text-rose-400">{{ roleForm.errors.role }}</p>
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-[#0d685b]/20">
                        <button type="button" class="rounded-xl px-3.5 py-2 text-xs font-semibold text-[#f3f2e7]/60 hover:bg-[#131d1a]" @click="closeModal">Batal</button>
                        <button :disabled="roleForm.processing" class="rounded-xl bg-[#0d685b] px-4 py-2 text-xs font-bold text-[#f3f2e7] shadow-sm hover:bg-[#0d685b]/90 disabled:opacity-50">
                            Simpan Role
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================= MODAL RESET PASSWORD (Hanya Super Admin) ================= -->
        <div v-if="isSuperAdmin && modalType === 'password'" class="fixed inset-0 z-50 flex items-center justify-center bg-[#17231f]/85 backdrop-blur-md p-4">
            <div class="w-full max-w-sm rounded-2xl bg-[#1c2a25] p-6 shadow-2xl border border-[#0d685b]/40 text-[#f3f2e7]">
                <h3 class="text-base font-black text-[#f3f2e7]">Reset Kata Sandi</h3>
                <p class="mt-1 text-xs text-[#f3f2e7]/60">Pengguna: <strong class="text-[#f3f2e7]">@{{ selectedUser?.username }}</strong></p>
                <form class="mt-4 space-y-4" @submit.prevent="submitPassword">
                    <div>
                        <label class="mb-1 block text-xs font-bold text-[#0d685b] uppercase">Kata Sandi Baru (min. 6 karakter)</label>
                        <input v-model="passwordForm.password" type="text" placeholder="Masukkan password baru..." :class="inputClass" />
                        <p v-if="passwordForm.errors.password" class="mt-1 text-xs text-rose-400">{{ passwordForm.errors.password }}</p>
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-[#0d685b]/20">
                        <button type="button" class="rounded-xl px-3.5 py-2 text-xs font-semibold text-[#f3f2e7]/60 hover:bg-[#131d1a]" @click="closeModal">Batal</button>
                        <button :disabled="passwordForm.processing" class="rounded-xl bg-amber-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-amber-500 disabled:opacity-50">
                            Reset Password
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================= MODAL KOREKSI SALDO (Hanya Super Admin) ================= -->
        <div v-if="isSuperAdmin && modalType === 'saldo'" class="fixed inset-0 z-50 flex items-center justify-center bg-[#17231f]/85 backdrop-blur-md p-4">
            <div class="w-full max-w-sm rounded-2xl bg-[#1c2a25] p-6 shadow-2xl border border-[#0d685b]/40 text-[#f3f2e7]">
                <h3 class="text-base font-black text-[#f3f2e7]">Koreksi Saldo Dompet</h3>
                <p class="mt-1 text-xs text-[#f3f2e7]/60">
                    Pengguna: <strong class="text-[#f3f2e7]">@{{ selectedUser?.username }}</strong> · Saldo: <strong class="text-emerald-400">{{ rupiah(selectedUser?.saldo) }}</strong>
                </p>
                <form class="mt-4 space-y-3.5" @submit.prevent="submitBalance">
                    <div>
                        <label class="mb-1 block text-xs font-bold text-[#0d685b] uppercase">Jenis Tindakan</label>
                        <select v-model="balanceForm.type" :class="inputClass">
                            <option value="credit">Tambah Saldo (+) [Credit]</option>
                            <option value="debit">Tarik Saldo (-) [Debit]</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-bold text-[#0d685b] uppercase">Nominal (Rp)</label>
                        <input v-model="balanceForm.amount" type="number" placeholder="Contoh: 50000" :class="inputClass" />
                        <p v-if="balanceForm.errors.amount" class="mt-1 text-xs text-rose-400">{{ balanceForm.errors.amount }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-bold text-[#0d685b] uppercase">Alasan / Catatan Koreksi</label>
                        <input v-model="balanceForm.note" placeholder="Misal: Bonus / penyesuaian transfer" :class="inputClass" />
                        <p v-if="balanceForm.errors.note" class="mt-1 text-xs text-rose-400">{{ balanceForm.errors.note }}</p>
                    </div>
                    <div class="flex justify-end gap-2 pt-2 border-t border-[#0d685b]/20">
                        <button type="button" class="rounded-xl px-3.5 py-2 text-xs font-semibold text-[#f3f2e7]/60 hover:bg-[#131d1a]" @click="closeModal">Batal</button>
                        <button :disabled="balanceForm.processing" class="rounded-xl bg-[#0d685b] px-4 py-2 text-xs font-bold text-[#f3f2e7] shadow-sm hover:bg-[#0d685b]/90 disabled:opacity-50">
                            Eksekusi Koreksi
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ================= MODAL KONFIRMASI HAPUS (Hanya Super Admin) ================= -->
        <div v-if="isSuperAdmin && modalType === 'delete'" class="fixed inset-0 z-50 flex items-center justify-center bg-[#17231f]/85 backdrop-blur-md p-4">
            <div class="w-full max-w-sm rounded-2xl bg-[#1c2a25] p-6 shadow-2xl border border-rose-500/40 text-[#f3f2e7]">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-950/60 text-rose-400 text-xl border border-rose-500/30">
                    ⚠️
                </div>
                <h3 class="mt-3 text-center text-base font-black text-[#f3f2e7]">Hapus Akun Pengguna?</h3>
                <p class="mt-1 text-center text-xs text-[#f3f2e7]/70">
                    Anda akan menghapus akun <strong class="text-[#f3f2e7]">@{{ selectedUser?.username }}</strong>. Akun ini akan di-soft delete dan tidak dapat login ke sistem.
                </p>
                <div class="mt-6 flex justify-end gap-2 border-t border-[#0d685b]/20 pt-3">
                    <button type="button" class="w-full rounded-xl border border-[#0d685b]/30 px-4 py-2 text-xs font-semibold text-[#f3f2e7]/70 hover:bg-[#131d1a]" @click="closeModal">
                        Batal
                    </button>
                    <button class="w-full rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-rose-500" @click="submitDelete">
                        Ya, Hapus
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

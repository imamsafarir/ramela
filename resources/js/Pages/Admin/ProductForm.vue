<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps({ product: Object, stores: Array, categories: Array });

const editing = !!props.product;

const form = useForm({
    _method: editing ? 'put' : 'post',
    store_id: props.product?.store_id ?? props.stores[0]?.id ?? '',
    category_id: props.product?.category_id ?? '',
    name: props.product?.name ?? '',
    description: props.product?.description ?? '',
    price: props.product?.price ?? '',
    stock: props.product?.stock ?? 0,
    unit: props.product?.unit ?? '',
    weight: props.product?.weight ? (props.product.weight >= 1000 && props.product.weight % 1000 === 0 ? props.product.weight / 1000 : props.product.weight) : 1000,
    weight_unit: props.product?.weight && props.product.weight >= 1000 && props.product.weight % 1000 === 0 ? 'kg' : 'g',
    is_active: props.product?.is_active ?? true,
    images: [],
    remove_images: [],
});

const storeCategories = computed(() => props.categories.filter((c) => c.store_id === Number(form.store_id)));

const onFiles = (e) => { form.images = Array.from(e.target.files); };
const toggleRemove = (id) => {
    form.remove_images = form.remove_images.includes(id)
        ? form.remove_images.filter((i) => i !== id)
        : [...form.remove_images, id];
};

// PUT + file tidak terbaca PHP, jadi selalu POST dengan _method spoofing.
const submit = () => form.post(editing ? `/admin/produk/${props.product.id}` : '/admin/produk', { forceFormData: true });

const input = 'w-full rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3.5 py-2.5 text-sm text-[#f3f2e7] placeholder:text-[#f3f2e7]/40 focus:outline-none focus:border-[#0d685b]';
</script>

<template>
    <Head :title="editing ? 'Ubah Produk' : 'Tambah Produk'" />
    <Link href="/admin/produk" class="inline-flex items-center gap-1 text-xs font-semibold text-[#f3f2e7]/70 hover:text-[#f3f2e7] transition">
        ← Kembali ke Katalog Produk
    </Link>
    <h1 class="mt-2 text-2xl font-black text-[#f3f2e7] tracking-tight">{{ editing ? 'Ubah Informasi Produk' : 'Tambah Produk Baru' }}</h1>

    <form class="mt-6 max-w-2xl space-y-4 rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-4.5 sm:p-6 shadow-xl text-[#f3f2e7]" @submit.prevent="submit">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#0d685b]">Pilar Toko</label>
                <select v-model="form.store_id" :class="input" @change="form.category_id = ''">
                    <option v-for="s in stores" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <p v-if="form.errors.store_id" class="mt-1 text-xs text-rose-400">{{ form.errors.store_id }}</p>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#0d685b]">Kategori</label>
                <select v-model="form.category_id" :class="input">
                    <option value="">Tanpa kategori</option>
                    <option v-for="c in storeCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <p v-if="form.errors.category_id" class="mt-1 text-xs text-rose-400">{{ form.errors.category_id }}</p>
            </div>
        </div>

        <div>
            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#0d685b]">Nama Produk</label>
            <input v-model="form.name" placeholder="Contoh: Paket Nasi Ayam Bakar" :class="input" />
            <p v-if="form.errors.name" class="mt-1 text-xs text-rose-400">{{ form.errors.name }}</p>
        </div>

        <div>
            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#0d685b]">Deskripsi Lengkap</label>
            <textarea v-model="form.description" rows="3" placeholder="Jelaskan detail spesifikasi produk..." :class="input" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#0d685b]">Harga (Rp)</label>
                <input v-model="form.price" type="number" min="0" step="any" :class="input" />
                <p v-if="form.errors.price" class="mt-1 text-xs text-rose-400">{{ form.errors.price }}</p>
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#0d685b]">Stok Tersedia</label>
                <input v-model="form.stock" type="number" min="0" :class="input" />
                <p v-if="form.errors.stock" class="mt-1 text-xs text-rose-400">{{ form.errors.stock }}</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#0d685b]">Satuan Jual</label>
                <input v-model="form.unit" placeholder="porsi, paket, pcs, box, botol..." :class="input" />
            </div>
            <div>
                <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#0d685b]">
                    Berat Produk (Untuk Ongkir Kurir)
                </label>
                <div class="flex gap-2">
                    <input
                        v-model="form.weight"
                        type="number"
                        min="0"
                        step="any"
                        placeholder="Contoh: 500 atau 1.5"
                        :class="input"
                    />
                    <select
                        v-model="form.weight_unit"
                        class="w-36 rounded-xl border border-[#0d685b]/40 bg-[#131d1a] px-3 py-2.5 text-xs font-bold text-[#f3f2e7] focus:outline-none focus:border-[#0d685b]"
                    >
                        <option value="g">Gram (g)</option>
                        <option value="kg">Kilogram (kg)</option>
                    </select>
                </div>
                <p v-if="form.errors.weight" class="mt-1 text-xs text-rose-400">{{ form.errors.weight }}</p>
                <p class="mt-1 text-[11px] text-[#f3f2e7]/50">
                    Estimasi: {{ form.weight ? (form.weight_unit === 'kg' ? (Number(form.weight) * 1000).toLocaleString('id-ID') + ' g' : (Number(form.weight) / 1000).toLocaleString('id-ID', { maximumFractionDigits: 2 }) + ' kg') : '-' }}
                </p>
            </div>
        </div>

        <div>
            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-[#0d685b]">Foto Produk (maks 5, jpg/png/webp, ≤2MB)</label>
            <input type="file" multiple accept="image/jpeg,image/png,image/webp" class="text-xs text-[#f3f2e7]/80 file:mr-3 file:rounded-xl file:border-0 file:bg-[#0d685b] file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-[#f3f2e7]" @change="onFiles" />
            <p v-if="form.errors.images || form.errors['images.0']" class="mt-1 text-xs text-rose-400">
                {{ form.errors.images || form.errors['images.0'] }}
            </p>
            <div v-if="product?.images?.length" class="mt-3 flex flex-wrap gap-3">
                <button v-for="i in product.images" :key="i.id" type="button" class="relative group rounded-xl overflow-hidden border border-[#0d685b]/30" @click="toggleRemove(i.id)">
                    <img :src="i.url" class="h-20 w-20 object-cover"
                        :class="form.remove_images.includes(i.id) ? 'opacity-30 ring-2 ring-rose-500' : ''" />
                    <span v-if="form.remove_images.includes(i.id)" class="absolute inset-0 flex items-center justify-center text-xs font-bold text-rose-400 bg-black/60">Hapus</span>
                </button>
            </div>
            <p v-if="product?.images?.length" class="mt-1 text-xs text-[#f3f2e7]/50">Klik foto untuk menandai siap dihapus.</p>
        </div>

        <label class="flex items-center gap-2 text-sm text-[#f3f2e7] cursor-pointer">
            <input v-model="form.is_active" type="checkbox" class="rounded accent-[#0d685b]" />
            <span>Aktif (Tampilkan produk di etalase toko)</span>
        </label>

        <div class="pt-2">
            <button
                :disabled="form.processing"
                class="rounded-xl bg-[#0d685b] px-6 py-2.5 text-sm font-bold text-[#f3f2e7] shadow-lg shadow-[#0d685b]/30 hover:bg-[#0d685b]/90 transition disabled:opacity-50"
            >
                {{ form.processing ? 'Menyimpan...' : 'Simpan Produk' }}
            </button>
        </div>
    </form>
</template>

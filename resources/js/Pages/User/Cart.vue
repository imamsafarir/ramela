<script setup>
import { Head, Link, router } from "@inertiajs/vue3";
import UserLayout from "../../Layouts/UserLayout.vue";
import { rupiah } from "../../utils/format";

defineOptions({ layout: UserLayout });

defineProps({ groups: Array });

const setQty = (item, qty) => {
    if (qty < 1) return;
    router.patch(
        `/keranjang/${item.id}`,
        { quantity: qty },
        { preserveScroll: true },
    );
};
const remove = (item) =>
    router.delete(`/keranjang/${item.id}`, { preserveScroll: true });
</script>

<template>
    <Head title="Keranjang" />

    <div class="mb-4">
        <Link href="/dashboard" class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#f3f2e7]/70 hover:text-[#f3f2e7] transition">
            <span>←</span>
            <span>Kembali ke Dashboard</span>
        </Link>
    </div>

    <h1 class="text-2xl font-black text-[#f3f2e7] tracking-tight">Keranjang Belanja</h1>

    <div v-if="!groups.length" class="mt-6 rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] p-10 text-center text-sm text-[#f3f2e7]/60 shadow-xl">
        <p class="text-4xl mb-2">🛒</p>
        <p class="font-bold text-[#f3f2e7] text-base mb-1">Keranjang Anda masih kosong</p>
        <p class="text-xs text-[#f3f2e7]/60 mb-4">Yuk jelajahi toko RAMELA dan pilih kebutuhan Anda.</p>
        <Link href="/dashboard" class="inline-flex items-center gap-2 rounded-xl bg-[#0d685b] hover:bg-[#117c6d] px-5 py-2.5 text-xs font-bold text-[#f3f2e7] transition shadow-md shadow-[#0d685b]/30">
            Pilih Toko Sekarang →
        </Link>
    </div>

    <section
        v-for="g in groups"
        :key="g.store.slug"
        class="mt-6 overflow-hidden rounded-2xl border border-[#0d685b]/30 bg-[#1c2a25] shadow-xl"
    >
        <header class="border-b border-[#0d685b]/20 bg-[#131d1a]/50 px-5 py-3.5 font-black text-sm text-[#f3f2e7] flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span>🏪</span>
                <span>{{ g.store.name }}</span>
            </div>
            <Link :href="`/toko/${g.store.slug}`" class="text-xs font-bold text-emerald-400 hover:text-emerald-300 hover:underline">
                Lihat Toko →
            </Link>
        </header>
        <ul class="divide-y divide-[#0d685b]/20">
            <li
                v-for="i in g.items"
                :key="i.id"
                class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 hover:bg-[#131d1a]/30 transition"
            >
                <div>
                    <p class="font-bold text-sm text-[#f3f2e7]">{{ i.name }}</p>
                    <p class="text-xs text-[#f3f2e7]/70 mt-0.5">
                        {{ rupiah(i.price) }}<span v-if="i.unit"> / {{ i.unit }}</span>
                    </p>
                    <p v-if="!i.available" class="text-xs font-bold text-rose-400 mt-1">
                        ⚠️ Produk tidak tersedia
                    </p>
                    <p
                        v-else-if="i.quantity > i.stock"
                        class="text-xs font-bold text-amber-400 mt-1"
                    >
                        ⚠️ Stok tersisa {{ i.stock }}
                    </p>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <button
                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-[#0d685b]/40 bg-[#131d1a] font-bold text-[#f3f2e7] transition hover:bg-[#0d685b]/20 active:scale-95"
                        @click="setQty(i, i.quantity - 1)"
                    >
                        −
                    </button>
                    <span class="w-8 text-center font-bold text-[#f3f2e7]">{{ i.quantity }}</span>
                    <button
                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-[#0d685b]/40 bg-[#131d1a] font-bold text-[#f3f2e7] transition hover:bg-[#0d685b]/20 active:scale-95"
                        @click="setQty(i, i.quantity + 1)"
                    >
                        +
                    </button>
                    <button
                        class="ml-2 text-xs font-bold text-rose-400 hover:text-rose-300 hover:underline transition"
                        @click="remove(i)"
                    >
                        Hapus
                    </button>
                </div>
            </li>
        </ul>
        <footer
            class="flex items-center justify-between border-t border-[#0d685b]/20 bg-[#131d1a]/40 px-5 py-4"
        >
            <div>
                <span class="block text-[11px] font-semibold text-[#f3f2e7]/60 uppercase tracking-wider">Subtotal Belanja</span>
                <span class="font-black text-lg text-emerald-400">{{ rupiah(g.subtotal) }}</span>
            </div>
            <Link
                :href="`/checkout/${g.store.slug}`"
                class="rounded-xl bg-[#0d685b] hover:bg-[#117c6d] px-6 py-2.5 text-xs font-bold text-[#f3f2e7] shadow-md shadow-[#0d685b]/30 transition"
            >
                Checkout Pesanan →
            </Link>
        </footer>
    </section>
</template>

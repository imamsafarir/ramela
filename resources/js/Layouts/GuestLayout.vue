<script setup>
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-[#17231f] text-[#f3f2e7] px-4 py-8">
        <Link href="/" class="mb-6 text-2xl font-black tracking-tight text-[#f3f2e7] group">
            RAMELA<span class="text-emerald-400 group-hover:animate-ping">.</span>
        </Link>
        <div class="w-full max-w-sm rounded-3xl bg-[#1c2a25] p-6 sm:p-8 shadow-2xl border border-[#0d685b]/30">
            <slot />
        </div>
    </div>
</template>

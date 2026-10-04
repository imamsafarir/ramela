<script setup>
import Blogs from './Blogs.vue';

const props = defineProps({
    blogs: { type: Array, default: () => [] },
    faqs: { type: Array, default: () => [] },
    stores: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});
</script>

<template>
    <Blogs
        :blogs="blogs"
        :faqs="faqs"
        :stores="stores"
        :stats="stats"
        :filters="{ ...filters, tab: filters.tab || 'faqs' }"
    />
</template>

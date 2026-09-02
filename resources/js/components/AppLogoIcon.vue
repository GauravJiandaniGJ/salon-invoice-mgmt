<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({ inheritAttrs: false });

defineProps<{ className?: HTMLAttributes['class'] }>();
</script>

<template>
    <!-- Simple "W" mark used where the raster logo would be too small -->
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" :class="className" v-bind="$attrs" aria-hidden="true">
        <rect width="40" height="40" rx="8" fill="#0a0a0a" />
        <path d="M7 11l5.2 18h3.4L20 16.5 24.4 29h3.4L33 11h-3.6l-3.3 12.6L22 11h-4l-4.1 12.6L10.6 11z" fill="currentColor" />
        <circle cx="20" cy="8.5" r="1.4" fill="currentColor" opacity=".8" />
    </svg>
</template>

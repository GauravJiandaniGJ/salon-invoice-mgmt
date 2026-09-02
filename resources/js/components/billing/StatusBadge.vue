<script setup lang="ts">
import type { InvoiceStatus, PaymentStatus } from '@/types';

defineProps<{
    status: InvoiceStatus;
    paymentStatus?: PaymentStatus;
}>();
</script>

<template>
    <span v-if="status === 'void'" class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-950 dark:text-red-300">VOID</span>
    <span
        v-else-if="paymentStatus === 'unpaid'"
        class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300"
    >
        Unpaid
    </span>
    <span v-else class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Paid</span>
</template>

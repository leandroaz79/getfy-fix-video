<script setup>
import { CreditCard } from 'lucide-vue-next';

defineProps({
    method: { type: Object, required: true },
    selected: { type: Boolean, default: false },
    primaryColor: { type: String, default: '#0ea5e9' },
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <span
        class="flex shrink-0 items-center justify-center overflow-hidden rounded-lg"
        :class="compact ? 'h-8 w-8 sm:h-9 sm:w-9' : 'h-11 w-11'"
        :style="{ backgroundColor: !compact && selected && method.id !== 'pix' && method.id !== 'pix_auto' ? primaryColor + '18' : undefined }"
    >
        <img
            v-if="method.id === 'pix'"
            src="/images/gateways/pix.svg"
            alt="PIX"
            class="shrink-0 object-contain self-center"
            :class="compact ? 'h-5 w-5 sm:h-6 sm:w-6' : 'h-7 w-7'"
        />
        <img
            v-else-if="method.id === 'pix_auto'"
            src="/images/gateways/pix-automatico-checkout.png"
            alt="PIX automático"
            class="shrink-0 object-contain self-center"
            :class="compact ? 'h-5 w-5 sm:h-6 sm:w-6' : 'h-7 w-7'"
        />
        <img
            v-else-if="method.id === 'card'"
            src="/images/gateways/card-method.png"
            alt="Cartão"
            class="shrink-0 object-contain self-center"
            :class="compact ? 'h-5 w-5 sm:h-6 sm:w-6' : 'h-7 w-7'"
        />
        <img
            v-else-if="method.id === 'boleto'"
            src="/images/gateways/boleto.png"
            alt="Boleto"
            class="shrink-0 object-contain self-center"
            :class="compact ? 'h-5 w-5 sm:h-6 sm:w-6' : 'h-7 w-7'"
        />
        <CreditCard v-else class="h-5 w-5" />
    </span>
    <div class="min-w-0 flex-1">
        <span
            class="block font-medium text-zinc-900"
            :class="compact ? 'text-[13px] leading-tight sm:text-sm' : ''"
        >{{ method.label }}</span>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { CircleCheck, CreditCard, FileText, QrCode, Landmark } from 'lucide-vue-next';

const props = defineProps({
    primaryColor: { type: String, default: '#7427F1' },
    t: { type: Function, default: (k) => k },
    uiVariant: { type: String, default: 'default' },
});

const isTicto = computed(() => props.uiVariant === 'ticto');

const steps = [
    {
        icon: FileText,
        titleKey: 'checkout.pix_step1_title',
        descKey: 'checkout.pix_step1_desc',
    },
    {
        icon: CreditCard,
        titleKey: 'checkout.pix_step2_title',
        descKey: 'checkout.pix_step2_desc',
    },
    {
        icon: CircleCheck,
        titleKey: 'checkout.pix_step3_title',
        descKey: 'checkout.pix_step3_desc',
    },
];

const tictoSteps = [
    {
        icon: QrCode,
        title: 'Copie os dados de pagamento',
        desc: 'Copie o código Pix ou use o QR Code gerado após confirmar a compra.',
    },
    {
        icon: Landmark,
        title: 'Faça o Pagamento',
        desc: 'Abra o app do seu banco, escolha Pix e cole o código ou escaneie o QR Code.',
    },
];
</script>

<template>
    <div class="space-y-2.5" data-checkout="pix-info">
        <template v-if="isTicto">
            <div class="overflow-hidden rounded-lg border border-[#d0d5dd] bg-white">
                <div class="divide-y divide-[#e4e7ec]">
                    <div
                        v-for="(step, index) in tictoSteps"
                        :key="index"
                        class="flex items-start gap-3 px-4 py-4"
                    >
                        <span
                            class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-md"
                            :style="{ color: primaryColor, backgroundColor: primaryColor + '14' }"
                            aria-hidden="true"
                        >
                            <component :is="step.icon" class="h-5 w-5" stroke-width="2" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-sm font-bold leading-snug" :style="{ color: primaryColor }">
                                {{ step.title }}
                            </h3>
                            <p class="mt-0.5 text-xs leading-snug text-[#667085]">
                                {{ step.desc }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </template>
        <template v-else>
            <div
                class="flex items-center gap-3 rounded-xl border px-3.5 py-3 shadow-sm"
                :style="{
                    borderColor: primaryColor + '40',
                    backgroundColor: primaryColor + '12',
                }"
            >
                <span
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/80"
                    aria-hidden="true"
                >
                    <img src="/images/gateways/pix.svg" alt="" class="h-5 w-5 object-contain" />
                </span>
                <div class="min-w-0">
                    <p
                        class="text-[15px] font-bold leading-tight balance"
                        :style="{ color: primaryColor }"
                    >
                        {{ t('checkout.pague_com_pix') || 'Pague com Pix' }}
                    </p>
                    <p class="mt-1 flex items-center gap-1.5 text-xs font-medium leading-snug text-emerald-700">
                        <CircleCheck class="h-3.5 w-3.5 shrink-0" stroke-width="2.5" aria-hidden="true" />
                        <span>{{ t('checkout.pix_aprovacao_instantanea') || 'Aprovação instantânea' }}</span>
                    </p>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
                <div class="divide-y divide-dashed divide-zinc-200">
                    <div
                        v-for="(step, index) in steps"
                        :key="index"
                        class="flex items-start gap-3 px-3.5 py-3"
                    >
                        <span
                            class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                            :style="{
                                backgroundColor: primaryColor + '14',
                                color: primaryColor,
                            }"
                            aria-hidden="true"
                        >
                            <component :is="step.icon" class="h-4 w-4" stroke-width="2" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-sm font-bold leading-snug text-zinc-900 balance">
                                {{ t(step.titleKey) }}
                            </h3>
                            <p class="mt-0.5 text-xs leading-snug text-zinc-500">
                                {{ t(step.descKey) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

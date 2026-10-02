<script setup>
import { ref, computed, watch } from 'vue';
import { ShoppingBag, Check } from 'lucide-vue-next';
import { retryImageOnError } from '@/lib/imageLoadRetry';

const props = defineProps({
    orderBumps: { type: Array, default: () => [] },
    selectedIds: { type: Array, default: () => [] },
    primaryColor: { type: String, default: '#7427F1' },
    /** Cor da faixa/header e fundo externo (Ticto) ou borda/tag (layout original). */
    orderBumpColor: { type: String, default: '#F59E0B' },
    /** Cor do fundo interno da oferta no layout Ticto. */
    orderBumpInnerColor: { type: String, default: '#86EFAC' },
    t: { type: Function, default: (k) => k },
    formatPrice: { type: Function, default: (v, c) => `R$ ${Number(v).toFixed(2)}` },
    displayCurrency: { type: String, default: 'BRL' },
    uiVariant: { type: String, default: 'default' },
});

const emit = defineEmits(['update:selectedIds']);

const selectedIds = ref([...props.selectedIds]);
const isTicto = computed(() => props.uiVariant === 'ticto');

const selectedSet = computed(() => new Set(selectedIds.value));

const offersLabel = computed(() => {
    const n = (props.orderBumps || []).length;
    return n === 1 ? 'Você possui 01 oferta!' : `Você possui ${String(n).padStart(2, '0')} ofertas!`;
});

function freeLabel() {
    const label = props.t('checkout.gratis');
    if (!label || label === 'checkout.gratis') {
        return 'Grátis';
    }
    return label;
}

function toggle(bump) {
    const id = bump.id;
    if (selectedSet.value.has(id)) {
        selectedIds.value = selectedIds.value.filter((x) => x !== id);
    } else {
        selectedIds.value = [...selectedIds.value, id];
    }
    emit('update:selectedIds', selectedIds.value);
}

watch(
    () => props.selectedIds,
    (val) => {
        selectedIds.value = Array.isArray(val) ? [...val] : [];
    },
    { immediate: true }
);

watch(
    () => props.orderBumps,
    () => {
        const ids = (props.orderBumps || []).map((b) => b.id);
        selectedIds.value = selectedIds.value.filter((id) => ids.includes(id));
        emit('update:selectedIds', selectedIds.value);
    },
    { immediate: true }
);

const selectedBumps = computed(() =>
    props.orderBumps.filter((b) => selectedSet.value.has(b.id))
);
function parseHexColor(hex, fallback = '#F59E0B') {
    const raw = String(hex || fallback).trim();
    const h = raw.replace('#', '');
    const full = h.length === 3 ? h.split('').map((c) => c + c).join('') : h;
    if (!/^[0-9a-fA-F]{6}$/.test(full)) {
        return { r: 245, g: 158, b: 11 };
    }
    const n = Number.parseInt(full, 16);
    return { r: (n >> 16) & 255, g: (n >> 8) & 255, b: n & 255 };
}

function mixWithWhite(hex, amount) {
    const { r, g, b } = parseHexColor(hex);
    const mix = (c) => Math.round(c + (255 - c) * amount);
    return `rgb(${mix(r)}, ${mix(g)}, ${mix(b)})`;
}

function isBumpFree(bump) {
    return Boolean(bump?.is_free) || Number(bump?.amount_brl) <= 0;
}

const ob = computed(() => props.orderBumpColor || '#F59E0B');
const innerOb = computed(() => props.orderBumpInnerColor || '#86EFAC');
const tictoOuterStyle = computed(() => ({
    borderColor: mixWithWhite(ob.value, 0.35),
    backgroundColor: mixWithWhite(ob.value, 0.82),
}));
const tictoHeaderStyle = computed(() => ({
    backgroundColor: mixWithWhite(ob.value, 0.45),
}));
const tictoInnerStyle = computed(() => ({
    borderColor: mixWithWhite(ob.value, 0.4),
    backgroundColor: mixWithWhite(innerOb.value, 0.55),
}));
const orderBumpCardStyle = computed(() => ({
    borderColor: `${ob.value}cc`,
    background: `linear-gradient(to bottom right, ${ob.value}20, white)`,
}));
const orderBumpTagStyle = computed(() => ({
    backgroundColor: `${ob.value}e6`,
    color: '#1f2937',
}));
const totalBumpsBrl = computed(() =>
    selectedBumps.value.reduce((sum, b) => sum + (Number(b.amount_brl) || 0), 0)
);

defineExpose({
    selectedIds,
    selectedBumps,
    totalBumpsBrl,
});
</script>

<template>
    <section v-if="orderBumps && orderBumps.length" class="mb-8" data-id="order_bumps" data-checkout="order-bumps">
        <template v-if="isTicto">
            <div class="mb-4 text-center" data-checkout="order-bumps-header">
                <h2 class="text-base font-bold text-[#101828]">🎉 {{ offersLabel }}</h2>
                <p class="mt-1 text-sm text-[#667085]">
                    Oportunidade única de adquirir produtos incríveis com um super desconto!
                </p>
            </div>
            <ul class="space-y-4" data-checkout="order-bumps-list">
                <li
                    v-for="bump in orderBumps"
                    :key="bump.id"
                    :data-order-bump-id="bump.id"
                    class="overflow-hidden rounded-lg border"
                    data-checkout="order-bump-card"
                    :style="tictoOuterStyle"
                >
                    <div
                        class="px-3 py-2.5 text-center text-sm font-bold text-[#101828]"
                        :style="tictoHeaderStyle"
                    >
                        🔥 Oferta Exclusiva: {{ bump.title || bump.cta_title || bump.target_name }}
                    </div>
                    <div
                        class="m-2 rounded-md border border-dashed p-3"
                        :style="tictoInnerStyle"
                    >
                        <p v-if="bump.description" class="mb-3 text-sm leading-relaxed text-[#344054]">
                            {{ bump.description }}
                        </p>
                        <label class="flex cursor-pointer items-center gap-3 rounded-md border border-[#d0d5dd] bg-white p-3">
                            <input
                                type="checkbox"
                                :checked="selectedSet.has(bump.id)"
                                class="h-5 w-5 shrink-0 rounded border-gray-300"
                                :style="{ accentColor: primaryColor }"
                                @change="toggle(bump)"
                            />
                            <div class="flex h-14 w-14 shrink-0 overflow-hidden rounded-md bg-gray-100">
                                <img
                                    v-if="bump.image_url"
                                    :src="bump.image_url"
                                    :alt="bump.target_name"
                                    class="h-full w-full object-cover"
                                    @error="retryImageOnError"
                                />
                                <div v-else class="flex h-full w-full items-center justify-center text-gray-400">
                                    <ShoppingBag class="h-6 w-6" />
                                </div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-[#101828]">
                                    {{ bump.cta_title || 'Sim, desejo adicionar' }}
                                </p>
                                <p class="mt-0.5 text-xs text-[#667085]">{{ bump.title }}</p>
                                <p class="mt-1 text-sm">
                                    <template v-if="isBumpFree(bump)">
                                        <span
                                            v-if="bump.original_amount_brl != null && Number(bump.original_amount_brl) > 0"
                                            class="text-red-500 line-through"
                                        >
                                            de {{ formatPrice(bump.original_amount_brl, displayCurrency) }}
                                        </span>
                                        <span class="ml-1 font-bold text-emerald-700">{{ freeLabel() }}</span>
                                    </template>
                                    <template v-else-if="bump.original_amount_brl != null && Number(bump.original_amount_brl) > Number(bump.amount_brl)">
                                        <span class="text-red-500 line-through">
                                            de {{ formatPrice(bump.original_amount_brl, displayCurrency) }}
                                        </span>
                                        <span class="ml-1 font-bold" :style="{ color: primaryColor }">
                                            por apenas {{ formatPrice(bump.amount_brl, displayCurrency) }}
                                        </span>
                                    </template>
                                    <template v-else>
                                        <span class="font-bold" :style="{ color: primaryColor }">
                                            {{ formatPrice(bump.amount_brl, displayCurrency) }}
                                        </span>
                                    </template>
                                </p>
                            </div>
                        </label>
                    </div>
                </li>
            </ul>
        </template>

        <template v-else>
            <div class="mb-4 flex items-center justify-between gap-3" data-checkout="order-bumps-header">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-zinc-100 text-zinc-600" aria-hidden="true">
                        <ShoppingBag class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight text-zinc-900 balance">
                            {{ t('checkout.voce_pode_gostar') || 'Você pode gostar' }}
                        </h2>
                        <p v-if="t('checkout.voce_pode_gostar_subtitle')" class="mt-0.5 text-sm text-zinc-500">
                            {{ t('checkout.voce_pode_gostar_subtitle') }}
                        </p>
                    </div>
                </div>
                <button
                    v-if="selectedIds.length > 0"
                    type="button"
                    class="text-sm font-medium text-zinc-500 transition duration-150 hover:text-zinc-700"
                    @click="selectedIds = []; $emit('update:selectedIds', [])"
                >
                    {{ t('checkout.deselect_all') || 'Desmarcar todos' }}
                </button>
            </div>

            <ul class="space-y-4" data-checkout="order-bumps-list">
                <li
                    v-for="bump in orderBumps"
                    :key="bump.id"
                    :data-order-bump-id="bump.id"
                    class="relative overflow-visible rounded-2xl border-2 border-dashed p-4 shadow-sm transition duration-150 bg-white hover:border-zinc-400 hover:shadow-md active:scale-[0.98]"
                    data-checkout="order-bump-card"
                    :style="orderBumpCardStyle"
                >
                    <label class="block w-full cursor-pointer">
                        <input
                            type="checkbox"
                            :checked="selectedSet.has(bump.id)"
                            class="sr-only"
                            @change="toggle(bump)"
                        />
                        <div class="absolute right-4 top-0 -translate-y-1/2">
                            <span
                                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide"
                                :style="orderBumpTagStyle"
                            >
                                {{ t('checkout.oferta_especial') || 'Oferta especial' }}
                            </span>
                        </div>
                        <!-- Foto + título; descrição em largura total abaixo (evita coluna estreita no mobile) -->
                        <div class="flex flex-row items-start gap-3 sm:gap-4">
                            <div class="flex h-16 w-16 shrink-0 overflow-hidden rounded-xl bg-zinc-100 ring-2 ring-zinc-100">
                                <img
                                    v-if="bump.image_url"
                                    :src="bump.image_url"
                                    :alt="bump.target_name"
                                    class="h-full w-full object-cover"
                                    @error="retryImageOnError"
                                />
                                <div v-else class="flex h-full w-full items-center justify-center text-zinc-400">
                                    <ShoppingBag class="h-8 w-8" />
                                </div>
                            </div>
                            <div class="min-w-0 flex-1 pe-16 sm:pe-20">
                                <h3 class="text-base font-bold leading-snug text-zinc-900 balance sm:text-[1.05rem]">
                                    {{ bump.title }}
                                </h3>
                            </div>
                        </div>
                        <p
                            v-if="bump.description"
                            class="mt-2 w-full text-sm leading-relaxed text-zinc-600"
                        >
                            {{ bump.description }}
                        </p>
                        <!-- Abaixo: CTA à esquerda, valor à direita -->
                        <div class="mt-3 flex w-full items-center justify-between gap-3">
                            <span
                                class="flex min-h-[2.25rem] min-w-0 max-w-[65%] items-center gap-1.5 rounded-lg border-2 px-2.5 py-2 text-xs font-medium leading-tight transition duration-150 active:scale-[0.97] sm:max-w-none sm:px-3"
                                :class="selectedSet.has(bump.id)
                                    ? 'border-[var(--primary)] bg-[var(--primary)]/10 text-zinc-800'
                                    : 'border-zinc-200 bg-white text-zinc-800 hover:border-zinc-300 hover:bg-zinc-50'"
                                :style="{ '--primary': primaryColor }"
                            >
                                <span
                                    class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-md border-2 transition-colors"
                                    :class="selectedSet.has(bump.id)
                                        ? 'border-[var(--primary)] bg-[var(--primary)]'
                                        : 'border-zinc-300 bg-white'"
                                    :style="selectedSet.has(bump.id) ? { backgroundColor: primaryColor, borderColor: primaryColor } : {}"
                                >
                                    <Check
                                        v-if="selectedSet.has(bump.id)"
                                        class="h-3 w-3 text-white"
                                    />
                                </span>
                                <span class="min-w-0 break-words text-xs font-medium sm:whitespace-nowrap">{{ bump.cta_title }}</span>
                            </span>
                            <div class="shrink-0 flex flex-col items-end">
                                <template v-if="bump.is_free || Number(bump.amount_brl) <= 0">
                                    <span
                                        v-if="bump.original_amount_brl != null && Number(bump.original_amount_brl) > 0"
                                        class="text-sm font-normal text-zinc-400 line-through"
                                    >
                                        +{{ formatPrice(bump.original_amount_brl, displayCurrency) }}
                                    </span>
                                    <span
                                        class="mt-0.5 inline-flex items-center rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-xs font-bold uppercase tracking-wide text-emerald-700 ring-1 ring-inset ring-emerald-600/20"
                                    >
                                        {{ freeLabel() }}
                                    </span>
                                </template>
                                <template v-else-if="bump.original_amount_brl != null && bump.original_amount_brl > bump.amount_brl">
                                    <span class="text-sm font-normal text-zinc-400 line-through">
                                        +{{ formatPrice(bump.original_amount_brl, displayCurrency) }}
                                    </span>
                                    <span class="text-base font-bold tabular-nums" :style="{ color: primaryColor }">
                                        +{{ formatPrice(bump.amount_brl, displayCurrency) }}
                                    </span>
                                </template>
                                <template v-else>
                                    <span class="text-base font-bold tabular-nums" :style="{ color: primaryColor }">
                                        +{{ formatPrice(bump.amount_brl, displayCurrency) }}
                                    </span>
                                </template>
                            </div>
                        </div>
                    </label>
                </li>
            </ul>
        </template>
    </section>
</template>

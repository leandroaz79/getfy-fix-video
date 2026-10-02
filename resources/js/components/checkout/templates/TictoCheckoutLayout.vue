<script setup>
import { computed, ref } from 'vue';
import { Check, Globe, Shield } from 'lucide-vue-next';
import CheckoutDropdown from '@/components/checkout/CheckoutDropdown.vue';
import CheckoutForm from '@/components/checkout/CheckoutForm.vue';
import CheckoutReviews from '@/components/checkout/CheckoutReviews.vue';

const props = defineProps({
    product: { type: Object, required: true },
    subscriptionPlan: { type: Object, default: null },
    config: { type: Object, default: () => ({}) },
    appliedCoupon: { type: Object, default: null },
    selectedOrderBumps: { type: Array, default: () => [] },
    orderBumpsTotalBrl: { type: Number, default: 0 },
    t: { type: Function, default: (k) => k },
    displayCurrency: { type: [String, Object], default: 'BRL' },
    priceInCurrency: { type: Function, default: (v) => v },
    formatPrice: { type: Function, default: (v) => String(v) },
    primaryColor: { type: String, default: '#00A868' },
    productId: { type: [Number, String], required: true },
    productOfferId: { type: [Number, String], default: null },
    subscriptionPlanId: { type: [Number, String], default: null },
    checkoutSessionToken: { type: String, default: '' },
    affiliateRef: { type: String, default: '' },
    orderBumps: { type: Array, default: () => [] },
    availablePaymentMethods: { type: Array, default: () => [] },
    prefillCoupon: { type: String, default: '' },
    checkoutLocale: { type: [String, Object], default: 'pt_BR' },
    suggestedCountryCode: { type: String, default: null },
    localeStorageKey: { type: String, default: '' },
    cardPayeeCode: { type: String, default: '' },
    cardEfiSandbox: { type: Boolean, default: false },
    cardStripePublishableKey: { type: String, default: '' },
    cardStripeSandbox: { type: Boolean, default: false },
    cardStripeLinkEnabled: { type: Boolean, default: true },
    cardInstallmentsEnabled: { type: Boolean, default: false },
    cardMaxInstallments: { type: Number, default: 1 },
    cardMercadopagoPublicKey: { type: String, default: '' },
    cardMercadopagoSandbox: { type: Boolean, default: false },
    cardPaypalClientId: { type: String, default: '' },
    cardPaypalSandbox: { type: Boolean, default: false },
    cardPaypalCheckoutMode: { type: String, default: 'auto' },
    cardGatewayKeys: { type: Object, default: () => ({}) },
    checkoutTotalBrl: { type: Number, default: 0 },
    checkoutTotalInCurrency: { type: Number, default: 0 },
    mainLinePriceBrl: { type: Number, default: 0 },
    currencyList: { type: Array, default: () => [] },
    featuredCurrencies: { type: Array, default: () => [] },
    otherCurrencies: { type: Array, default: () => [] },
    pluginCheckoutExtensions: { type: Array, default: () => [] },
    productName: { type: String, default: '' },
    cajupayPayAccountId: { type: String, default: '' },
    parceladoSdkOptions: { type: Object, default: () => ({}) },
    locale: { type: [String, Object], default: 'pt_BR' },
    supportedLocales: { type: Array, default: () => [] },
    localeLabels: { type: Object, default: () => ({}) },
    uiVariant: { type: String, default: 'ticto' },
});

const emit = defineEmits([
    'coupon-applied',
    'coupon-cleared',
    'payment-approved',
    'set-currency',
    'set-locale',
    'update:orderBumpIds',
]);

const orderBumpIds = defineModel('orderBumpIds', { type: Array, default: () => [] });

const accent = computed(() => {
    const fromConfig = props.config?.appearance?.primary_color;
    return fromConfig || props.primaryColor || '#00A868';
});

const reviews = computed(() => {
    const list = props.config?.reviews;
    return Array.isArray(list) ? list : [];
});

const productImage = computed(
    () => props.product?.image_url || props.product?.image || '',
);

const productDescription = computed(() => {
    const raw = String(props.product?.description || '').replace(/<[^>]+>/g, ' ');
    return raw.replace(/\s+/g, ' ').trim();
});

const listPriceBrl = computed(() => Number(props.product?.price_brl ?? props.product?.price ?? 0));

const salePriceBrl = computed(() => {
    if (props.appliedCoupon?.final_price != null) {
        return Number(props.appliedCoupon.final_price);
    }
    return listPriceBrl.value;
});

const previousPriceBrl = computed(() => {
    const summary = props.config?.summary || {};
    const previous = Number(summary.previous_price ?? summary.original_price ?? 0);
    return previous > 0 ? previous : 0;
});

const showStrike = computed(
    () => previousPriceBrl.value > 0 && previousPriceBrl.value > salePriceBrl.value,
);

function checkoutCurrencyCode(value) {
    let c = value;
    if (c && typeof c === 'object' && 'value' in c) {
        c = c.value;
    }
    if (c && typeof c === 'object') {
        return String(c.code || c.currency || 'BRL').toUpperCase();
    }
    return String(c || 'BRL').toUpperCase();
}

const previousPriceDisplay = computed(() =>
    props.formatPrice(props.priceInCurrency(previousPriceBrl.value), checkoutCurrencyCode(props.displayCurrency)),
);

const salePriceDisplay = computed(() =>
    props.formatPrice(props.priceInCurrency(salePriceBrl.value), checkoutCurrencyCode(props.displayCurrency)),
);

const localeOpen = ref(false);
const localeCode = computed(() => {
    let loc = props.locale;
    if (loc && typeof loc === 'object' && 'value' in loc) loc = loc.value;
    return String(loc || props.checkoutLocale || 'pt_BR');
});
const showLocalePicker = computed(
    () => Array.isArray(props.supportedLocales) && props.supportedLocales.length > 1,
);

function selectLocale(loc) {
    emit('set-locale', loc);
    localeOpen.value = false;
}
</script>

<template>
    <div class="ticto-layout" :style="{ '--ticto-accent': accent }" data-checkout="ticto-clone">
        <div class="ticto-layout__grid">
            <div class="ticto-layout__main">
                <div class="ticto-layout__secure">
                    <Shield class="h-4 w-4" stroke-width="2.4" aria-hidden="true" />
                    <span>COMPRA 100% SEGURA</span>
                </div>
                <div class="ticto-layout__card">
                    <CheckoutForm
                        :ui-variant="uiVariant || 'ticto'"
                        :product-id="productId"
                        :product-offer-id="productOfferId"
                        :subscription-plan-id="subscriptionPlanId"
                        :checkout-session-token="checkoutSessionToken"
                        :affiliate-ref="affiliateRef"
                        :order-bumps="orderBumps"
                        v-model:order-bump-ids="orderBumpIds"
                        :primary-color="accent"
                        :config="config"
                        :available-payment-methods="availablePaymentMethods"
                        :prefill-coupon="prefillCoupon"
                        :t="t"
                        :display-currency="displayCurrency"
                        :checkout-locale="checkoutLocale"
                        :format-price="formatPrice"
                        :suggested-country-code="suggestedCountryCode"
                        :locale-storage-key="localeStorageKey"
                        :card-payee-code="cardPayeeCode"
                        :card-efi-sandbox="cardEfiSandbox"
                        :card-stripe-publishable-key="cardStripePublishableKey"
                        :card-stripe-sandbox="cardStripeSandbox"
                        :card-stripe-link-enabled="cardStripeLinkEnabled"
                        :card-installments-enabled="cardInstallmentsEnabled"
                        :card-max-installments="cardMaxInstallments"
                        :card-mercadopago-public-key="cardMercadopagoPublicKey"
                        :card-mercadopago-sandbox="cardMercadopagoSandbox"
                        :card-paypal-client-id="cardPaypalClientId"
                        :card-paypal-sandbox="cardPaypalSandbox"
                        :card-paypal-checkout-mode="cardPaypalCheckoutMode"
                        :card-gateway-keys="cardGatewayKeys"
                        :checkout-total-brl="checkoutTotalBrl"
                        :checkout-total-in-currency="checkoutTotalInCurrency"
                        :main-line-price-brl="mainLinePriceBrl"
                        :currency-list="currencyList"
                        :featured-currencies="featuredCurrencies"
                        :other-currencies="otherCurrencies"
                        :plugin-checkout-extensions="pluginCheckoutExtensions"
                        :product-name="productName"
                        :cajupay-pay-account-id="cajupayPayAccountId"
                        :parcelado-sdk-options="parceladoSdkOptions"
                        :price-in-currency="priceInCurrency"
                        @coupon-applied="emit('coupon-applied', $event)"
                        @coupon-cleared="emit('coupon-cleared', $event)"
                        @payment-approved="emit('payment-approved', $event)"
                        @set-currency="emit('set-currency', $event)"
                    >
                        <template #after-coupon>
                            <div v-if="product" class="ticto-product" data-checkout="ticto-product">
                                <div class="ticto-product__kicker-row">
                                    <p class="ticto-product__kicker">Você está comprando:</p>
                                    <div
                                        v-if="showLocalePicker"
                                        class="ticto-product__locale"
                                        data-checkout="summary-locale"
                                    >
                                        <CheckoutDropdown
                                            v-model:open="localeOpen"
                                            :icon="Globe"
                                            aria-label="Idioma"
                                            align="right"
                                        >
                                            <button
                                                v-for="loc in supportedLocales"
                                                :key="loc"
                                                type="button"
                                                role="option"
                                                class="flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left text-sm transition hover:bg-gray-50"
                                                :class="localeCode === loc ? 'bg-gray-50 font-medium text-gray-900' : 'text-gray-700'"
                                                @click="selectLocale(loc)"
                                            >
                                                <span>{{ localeLabels[loc] || loc }}</span>
                                                <Check v-if="localeCode === loc" class="h-4 w-4 shrink-0 text-gray-500" />
                                            </button>
                                        </CheckoutDropdown>
                                    </div>
                                </div>
                                <div class="ticto-product__row">
                                    <img
                                        v-if="productImage"
                                        class="ticto-product__img"
                                        :src="productImage"
                                        :alt="product.name"
                                    />
                                    <div v-else class="ticto-product__img ticto-product__img--ph" />
                                    <div class="min-w-0 flex-1">
                                        <p class="ticto-product__name">{{ product.name }}</p>
                                        <p v-if="productDescription" class="ticto-product__desc">{{ productDescription }}</p>
                                        <p class="ticto-product__from">
                                            <template v-if="showStrike">
                                                De <span class="line-through">{{ previousPriceDisplay }}</span> por
                                            </template>
                                        </p>
                                        <p class="ticto-product__total">
                                            Total <strong>{{ salePriceDisplay }}</strong>
                                        </p>
                                        <p class="ticto-product__cash">à vista</p>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </CheckoutForm>
                </div>
            </div>

            <aside v-if="reviews.length" class="ticto-layout__aside">
                <CheckoutReviews :reviews="reviews" :primary-color="accent" />
            </aside>
        </div>
    </div>
</template>

<style scoped>
.ticto-layout {
    --ticto-accent: #00a868;
    font-family: Inter, ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
}

.ticto-layout__grid {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

@media (min-width: 1024px) {
    .ticto-layout__grid {
        flex-direction: row;
        align-items: flex-start;
        gap: 1.75rem;
    }
    .ticto-layout__main {
        width: 66.666%;
    }
    .ticto-layout__aside {
        width: 33.333%;
        position: sticky;
        top: 1.5rem;
    }
}

.ticto-layout__secure {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
    background: var(--ticto-accent);
    color: #fff;
    font-size: 0.78rem;
    font-weight: 800;
    letter-spacing: 0.08em;
    border-radius: 10px 10px 0 0;
    padding: 0.72rem 1rem;
}

.ticto-layout__card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-top: 0;
    border-radius: 0 0 10px 10px;
    padding: 1.15rem 1.1rem 1.4rem;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
}

@media (min-width: 640px) {
    .ticto-layout__card {
        padding: 1.35rem 1.45rem 1.6rem;
    }
}

.ticto-product {
    margin: 0.35rem 0 0.25rem;
}

.ticto-product__kicker-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin: 0 0 0.85rem;
}

.ticto-product__kicker {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: #344054;
}

.ticto-product__locale {
    flex-shrink: 0;
}

.ticto-product__row {
    display: flex;
    gap: 0.9rem;
    align-items: flex-start;
}

.ticto-product__img {
    width: 72px;
    height: 72px;
    object-fit: cover;
    border-radius: 8px;
    flex-shrink: 0;
}

.ticto-product__img--ph {
    background: #e5e7eb;
}

.ticto-product__name {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 700;
    color: #101828;
}

.ticto-product__desc {
    margin: 0.2rem 0 0;
    font-size: 0.75rem;
    line-height: 1.4;
    color: #667085;
}

.ticto-product__from {
    margin: 0.45rem 0 0;
    font-size: 0.8rem;
    color: #98a2b3;
}

.ticto-product__total {
    margin: 0.15rem 0 0;
    font-size: 0.9rem;
    color: #344054;
}

.ticto-product__total strong {
    color: var(--ticto-accent);
    font-size: 1.2rem;
    font-weight: 800;
}

.ticto-product__cash {
    margin: 0.1rem 0 0;
    font-size: 0.75rem;
    color: #98a2b3;
}
</style>

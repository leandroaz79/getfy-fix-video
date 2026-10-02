/**
 * Layouts de checkout renderizados pelo core (não pelo bundle do plugin).
 *
 * Plugins "thin" liberam um template via `checkout_builder_templates` com
 * `core_layout` apontando para uma chave deste mapa. Plugins "full" exportam
 * `frontend.exports.checkout_template` e não usam este registro.
 *
 * @see PluginExtensionRegistry::resolveActiveCheckoutTemplate()
 */
import TictoCheckoutLayout from './TictoCheckoutLayout.vue';

/** @type {Record<string, import('vue').Component>} */
export const CHECKOUT_CORE_LAYOUTS = {
    ticto: TictoCheckoutLayout,
};

/**
 * @param {string|null|undefined} coreLayout
 * @returns {import('vue').Component|null}
 */
export function resolveCheckoutCoreLayout(coreLayout) {
    const key = String(coreLayout || '').trim();
    if (!key) {
        return null;
    }
    return CHECKOUT_CORE_LAYOUTS[key] || null;
}

/**
 * @param {Record<string, unknown>|null|undefined} meta
 * @returns {boolean}
 */
export function isCheckoutCoreLayoutMeta(meta) {
    if (!meta || typeof meta !== 'object') {
        return false;
    }
    // Bundle do plugin tem prioridade sobre layout core.
    if (typeof meta.export === 'string' && meta.export.trim() !== '') {
        return false;
    }
    return resolveCheckoutCoreLayout(meta.core_layout) != null;
}

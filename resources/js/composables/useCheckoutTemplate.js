import { ref, shallowRef, watch, onMounted } from 'vue';
import { buildPluginUiIndex, ensurePluginUiLoaded } from '@/plugins/pluginUiLoader';
import { isCheckoutCoreLayoutMeta } from '@/components/checkout/templates/coreLayouts';

/**
 * Resolve e carrega o componente de template de checkout de um plugin.
 *
 * Contratos:
 * - thin: meta.core_layout (sem export) → layout no core; este composable não carrega bundle
 * - full: meta.export + entry → carrega Vue do plugin
 *
 * @param {import('vue').Ref|import('vue').ComputedRef} templateMetaRef
 *   active_checkout_template do servidor, ou derivado de config.template + plugin_ui
 * @param {import('vue').Ref|import('vue').ComputedRef|null} pluginUiRef
 */
export function useCheckoutTemplate(templateMetaRef, pluginUiRef = null) {
    const templateComponent = shallowRef(null);
    const templateLoading = ref(false);
    const templateError = ref(null);
    const resolvedTemplateId = ref('original');

    async function loadTemplate(meta) {
        templateError.value = null;

        if (isCheckoutCoreLayoutMeta(meta)) {
            templateComponent.value = null;
            resolvedTemplateId.value = meta.id || String(meta.core_layout);
            return;
        }

        if (!meta || !meta.plugin_slug || !meta.export) {
            templateComponent.value = null;
            resolvedTemplateId.value = 'original';
            return;
        }

        const pluginUi = typeof pluginUiRef === 'function' ? pluginUiRef() : pluginUiRef?.value ?? pluginUiRef;
        const bySlug = buildPluginUiIndex(pluginUi);
        const fromInertia = bySlug[meta.plugin_slug];
        const pluginMeta = {
            slug: meta.plugin_slug,
            entry: meta.entry || fromInertia?.entry || null,
        };

        if (!pluginMeta.entry) {
            templateComponent.value = null;
            resolvedTemplateId.value = 'original';
            templateError.value = 'Bundle do template de checkout ausente.';
            return;
        }

        templateLoading.value = true;
        try {
            const component = await ensurePluginUiLoaded(pluginMeta, meta.export);
            templateComponent.value = component;
            resolvedTemplateId.value = meta.id || 'plugin';
        } catch (e) {
            templateComponent.value = null;
            resolvedTemplateId.value = 'original';
            templateError.value = e?.message || 'Falha ao carregar template de checkout.';
        } finally {
            templateLoading.value = false;
        }
    }

    function getMeta() {
        return typeof templateMetaRef === 'function' ? templateMetaRef() : templateMetaRef?.value ?? templateMetaRef;
    }

    onMounted(() => {
        loadTemplate(getMeta());
    });

    watch(
        () => getMeta(),
        (meta) => {
            loadTemplate(meta);
        },
        { deep: true },
    );

    return {
        templateComponent,
        templateLoading,
        templateError,
        resolvedTemplateId,
        reloadTemplate: () => loadTemplate(getMeta()),
    };
}

/**
 * Monta meta a partir de config.template + lista do Builder / plugin_ui (preview ao vivo).
 *
 * @param {string} templateId
 * @param {Array<{id: string, plugin_slug?: string, core_layout?: string, ui_variant?: string, features?: string[]}>} builderTemplates
 * @param {Record<string, unknown>|null} pluginUi
 */
export function resolveCheckoutTemplateMetaFromClient(templateId, builderTemplates = [], pluginUi = null) {
    const id = String(templateId || 'original').trim() || 'original';
    if (id === 'original') {
        return null;
    }

    const list = Array.isArray(builderTemplates) ? builderTemplates : [];
    const match = list.find((t) => t && t.id === id);
    const bySlug = buildPluginUiIndex(pluginUi);
    const pluginSlug = match?.plugin_slug || null;

    const fromMatch = match
        ? {
              id,
              plugin_slug: pluginSlug || null,
              core_layout: match.core_layout || null,
              ui_variant: match.ui_variant || null,
              features: Array.isArray(match.features) ? match.features : [],
          }
        : null;

    if (pluginSlug && bySlug[pluginSlug]) {
        const meta = bySlug[pluginSlug];
        const exportName = meta?.frontend_exports_map?.checkout_template;
        if (typeof exportName === 'string' && exportName !== '') {
            return {
                ...(fromMatch || { id, plugin_slug: pluginSlug, features: [] }),
                export: exportName,
                entry: meta.entry || null,
            };
        }
    }

    // Thin: template na lista do builder com core_layout, sem bundle.
    if (fromMatch?.core_layout) {
        return {
            ...fromMatch,
            export: null,
            entry: null,
        };
    }

    // Fallback: qualquer plugin com export checkout_template
    for (const meta of Object.values(bySlug)) {
        const exportName = meta?.frontend_exports_map?.checkout_template;
        if (typeof exportName !== 'string' || exportName === '') {
            continue;
        }
        if (!match && list.length === 0) {
            return {
                id,
                plugin_slug: meta.slug,
                export: exportName,
                entry: meta.entry || null,
                core_layout: null,
                ui_variant: null,
                features: [],
            };
        }
    }

    if (pluginSlug) {
        const meta = bySlug[pluginSlug];
        return {
            ...(fromMatch || { id, plugin_slug: pluginSlug, features: [] }),
            export: meta?.frontend_exports_map?.checkout_template || null,
            entry: meta?.entry || null,
        };
    }

    return fromMatch;
}

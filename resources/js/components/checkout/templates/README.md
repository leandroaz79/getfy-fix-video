# Checkout templates (core)

Registro de layouts renderizados pelo Getfy (plugins **thin**).

- Mapa JS: [`coreLayouts.js`](./coreLayouts.js)
- Layout Ticto: [`TictoCheckoutLayout.vue`](./TictoCheckoutLayout.vue)
- Resolução PHP: `PluginExtensionRegistry::resolveActiveCheckoutTemplate()`
- Carga no checkout: `Show.vue` + `useCheckoutTemplate`

## Adicionar um layout core novo

1. Criar o componente Vue nesta pasta.
2. Registrar em `CHECKOUT_CORE_LAYOUTS` (`coreLayouts.js`).
3. Plugin thin declara `"core_layout": "<chave>"` (+ opcional `ui_variant` / `features`).

## Plugin full (sem core)

Exportar `frontend.exports.checkout_template` no manifesto do plugin. O core não precisa de nova chave.

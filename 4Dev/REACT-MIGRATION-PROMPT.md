# Super Prompt — Recriação do Getfy em React + Vite

> **Objetivo**: Recriar a plataforma Getfy (venda de produtos digitais) usando **React 19 + Vite + TypeScript + Tailwind CSS v4**. A interface será desenvolvida no Localhost (dev) e depois integrada ao projeto via OpenCode.
>
> **Referência**: Este prompt é baseado na análise completa do sistema Getfy original (Laravel + Vue 3 + Inertia.js). O novo sistema deve replicar **todas** as funcionalidades listadas abaixo.

---

## 1. VISÃO GERAL DO PROJETO

### O que é o Getfy?
Uma plataforma SaaS completa para venda de produtos digitais, similar a Hotmart, Monetizze, Eduzz e Kiwify. Permite que criadores de conteúdo (infoprodutores) vendam cursos, e-books, assinaturas e outros produtos digitais com checkout personalizado, sistema de afiliados, área de membros e gestão financeira.

### Stack Tecnológica do Novo Projeto
- **Frontend**: React 19 + TypeScript + Vite 7
- **Estilo**: Tailwind CSS v4 (configuração via CSS, sem tailwind.config.js)
- **Componentes**: shadcn/ui (componentes built-in) + Radix UI (primitivas headless)
- **Estado**: Zustand ou Jotai (leve, sem boilerplate)
- **Roteamento**: React Router v7 (ou TanStack Router)
- **Formulários**: React Hook Form + Zod (validação)
- **Charts**: Recharts ou Nivo (substituir ApexCharts)
- **Ícones**: Lucide React
- **HTTP**: Axios + React Query (TanStack Query) para data fetching
- **Fonte**: Instrument Sans (Google Fonts)
- **Build**: Vite 7 com path aliases (`@/` → `src/`)

### Arquitetura Geral
```
src/
├── app/                    # Configurações globais, providers, theme
├── components/             # Componentes reutilizáveis
│   ├── ui/                 # Componentes base (Button, Input, Card, etc.)
│   ├── layout/             # Layouts (Sidebar, Header, MobileNav)
│   ├── dashboard/          # Componentes do dashboard
│   ├── checkout/           # Componentes do checkout
│   │   └── gateways/       # Componentes por gateway de pagamento
│   └── shared/             # Componentes compartilhados
├── pages/                  # Páginas (rotas)
│   ├── auth/               # Login, Register, ForgotPassword
│   ├── dashboard/          # Dashboard principal
│   ├── products/           # CRUD de produtos
│   ├── checkout/           # Páginas de checkout
│   ├── sales/              # Gestão de vendas
│   ├── customers/          # Gestão de alunos/clientes
│   ├── financial/          # Financeiro
│   ├── settings/           # Configurações
│   ├── integrations/       # Integrações
│   ├── affiliates/         # Afiliados
│   ├── member-area/        # Área de membros (aluno)
│   └── partner/            # Portal de parceiros
├── hooks/                  # Custom hooks
├── lib/                    # Utilitários, helpers, constantes
├── services/               # Serviços de API
├── stores/                 # Stores de estado global
├── types/                  # Types TypeScript
└── styles/                 # Estilos globais, tema
```

---

## 2. SISTEMA DE DESIGN

### Paleta de Cores
```
Primária: #0ea5e9 (sky-500) — sobrescrevível por tenant
Secundária: zinc (zinc-50 a zinc-950)
Acento: #52525b (zinc-600)
Sucesso: emerald-500/600
Aviso: amber-500/600
Erro: red-500/600
```

### Modo Escuro
- Implementação via classe `.dark` no `<html>`
- Persistência em `localStorage`
- Tema padrão: **escuro**
- Todos os componentes devem ter variantes `dark:`

### Tipografia
- Fonte principal: **Instrument Sans** (Google Fonts)
- Tabular figures (`font-variant-numeric: tabular-nums`) em números
- `text-wrap: balance` em títulos

### Sombras
- Sombras tintadas com tom quente: `rgba(88, 80, 100, ...)` em vez de preto puro
- Tokens de sombra: `--shadow-theme-xs`, `--shadow-theme-sm`

### Componentes Base (shadcn/ui)
- Button (variantes: default, primary, destructive, outline, secondary, ghost, link)
- Input, Textarea, Select, Checkbox, Radio
- Card, Dialog, Sheet, Tabs
- Table, Badge, Avatar
- Toast/Sonner
- Dropdown Menu, Popover, Tooltip
- Separator, Skeleton

### Utilitários CSS Customizados
- `checkout-card`, `checkout-input`, `checkout-label`
- `panel-card`, `panel-card-sm/md/lg`
- `menu-item`, `menu-item-active`
- `skeleton`, `skeleton-sm/md/lg`
- `tabular-nums`, `balance`
- `no-scrollbar`
- Grain/noise overlay no body

---

## 3. MODELOS DE DADOS (TypeScript Interfaces)

### Core
```typescript
interface User {
  id: string;
  name: string;
  email: string;
  username?: string;
  role: 'admin' | 'infoprodutor' | 'aluno' | 'team' | 'coprodutor' | 'afiliado';
  tenant_id?: string;
  avatar?: string;
  team_role_id?: string;
}

interface Product {
  id: string; // UUID
  tenant_id: string;
  name: string;
  slug: string;
  checkout_slug: string;
  type: 'area_membros' | 'area_membros_externa' | 'link' | 'link_pagamento';
  billing_type: 'one_time' | 'subscription';
  price: number;
  price_brl: number;
  currency: string;
  is_active: boolean;
  image_url?: string;
  description?: string;
  checkout_config: CheckoutConfig;
  member_area_config?: MemberAreaConfig;
  offers: ProductOffer[];
  subscription_plans: SubscriptionPlan[];
  order_bumps: ProductOrderBump[];
  combo_product_ids: string[];
  conversion_pixels?: ConversionPixels;
}

interface ProductOffer {
  id: string;
  product_id: string;
  public_id: string;
  name: string;
  price: number;
  currency: string;
  checkout_slug: string;
  position: number;
}

interface SubscriptionPlan {
  id: string;
  product_id: string;
  public_id: string;
  name: string;
  price: number;
  currency: string;
  interval: 'weekly' | 'monthly' | 'quarterly' | 'semi_annual' | 'annual' | 'lifetime';
  checkout_slug: string;
  position: number;
}

interface ProductOrderBump {
  id: string;
  product_id: string;
  target_product_id: string;
  target_product_offer_id?: string;
  target_subscription_plan_id?: string;
  title: string;
  description?: string;
  price_override?: number;
  is_free: boolean;
  cta_title?: string;
  position: number;
}
```

### Orders & Payments
```typescript
interface Order {
  id: string;
  tenant_id: string;
  user_id: string;
  product_id: string;
  product_offer_id?: string;
  subscription_plan_id?: string;
  status: 'pending' | 'completed' | 'cancelled' | 'disputed' | 'refunded';
  amount: number;
  currency: string;
  gateway: string;
  gateway_id?: string;
  email: string;
  cpf?: string;
  phone?: string;
  customer_ip?: string;
  country_code?: string;
  coupon_code?: string;
  metadata: Record<string, any>;
  period_start?: string;
  period_end?: string;
  is_renewal: boolean;
  created_at: string;
  // Relations
  user?: User;
  product?: Product;
  order_items: OrderItem[];
}

interface OrderItem {
  id: string;
  order_id: string;
  product_id: string;
  product_offer_id?: string;
  subscription_plan_id?: string;
  product_order_bump_id?: string;
  amount: number;
  position: number;
}

interface CheckoutSession {
  id: string;
  tenant_id: string;
  product_id: string;
  step: string;
  session_token: string;
  email?: string;
  name?: string;
  phone?: string;
  customer_ip?: string;
  order_id?: string;
  utm_source?: string;
  utm_medium?: string;
  utm_campaign?: string;
}
```

### Coupon
```typescript
interface Coupon {
  id: string;
  tenant_id: string;
  product_id: string;
  code: string;
  type: 'percent' | 'fixed';
  value: number;
  min_amount?: number;
  max_uses?: number;
  used_count: number;
  valid_from?: string;
  valid_until?: string;
  is_active: boolean;
  products: Product[];
}
```

### Subscription
```typescript
interface Subscription {
  id: string;
  tenant_id: string;
  user_id: string;
  product_id: string;
  subscription_plan_id: string;
  status: 'active' | 'past_due' | 'cancelled';
  current_period_start: string;
  current_period_end: string;
  gateway_subscription_id?: string;
  renewal_token?: string;
}
```

### Settings
```typescript
interface AppSettings {
  theme_primary: string;
  email_provider: 'smtp' | 'hostinger' | 'sendgrid' | 'brevo';
  storage_provider: 'local' | 's3' | 'wasabi' | 'r2';
  checkout_translations: Record<string, Record<string, string>>;
  currencies: Currency[];
}
```

### Gateway
```typescript
interface GatewayCredential {
  id: string;
  tenant_id: string;
  gateway: string;
  credentials: Record<string, string>; // encrypted
  is_active: boolean;
}

interface GatewayConfig {
  slug: string;
  name: string;
  image: string;
  methods: string[];
  credential_keys: string[];
  signup_url: string;
}
```

### Commission/Affiliate
```typescript
interface CommissionEntry {
  id: string;
  order_id: string;
  tenant_id: string;
  beneficiary_user_id: string;
  role: 'producer' | 'affiliate' | 'coproducer';
  gross_amount: number;
  commission_percent: number;
  commission_amount: number;
  status: 'pending' | 'available' | 'paid' | 'cancelled';
}

interface ProductAffiliate {
  id: string;
  product_id: string;
  user_id: string;
  affiliate_code: string;
  commission_percent: number;
  status: 'pending' | 'approved' | 'rejected';
  affiliate_link: string;
}
```

### Member Area
```typescript
interface MemberSection {
  id: string;
  product_id: string;
  title: string;
  position: number;
  section_type: 'modules' | 'live' | 'community';
}

interface MemberModule {
  id: string;
  section_id: string;
  title: string;
  position: number;
  release_after_days?: number;
  access_duration_days?: number;
}

interface MemberLesson {
  id: string;
  module_id: string;
  title: string;
  type: 'video' | 'link' | 'pdf' | 'pdf_presentation' | 'pdf_reader' | 'text';
  content_files?: string[];
  support_files?: string[];
  position: number;
}

interface MemberLessonProgress {
  id: string;
  user_id: string;
  lesson_id: string;
  completed: boolean;
  completed_at?: string;
}
```

---

## 4. PÁGINAS E ROTAS

### Rotas Públicas
```
/login                          → Página de login
/register                       → Cadastro de infoprodutor
/forgot-password                → Esqueci a senha
/reset-password                 → Redefinir senha
/c/:slug                        → Checkout (público)
/c/:slug/pix                    → Pagamento PIX
/c/:slug/boleto                 → Pagamento Boleto
/c/:slug/pix-parcelado          → PIX Parcelado
/c/:slug/obrigado               → Página de obrigado
/c/:slug/upsell                 → Upsell pós-compra
/c/:slug/downsell               → Downsell pós-compra
/afiliar/:slug                  → Página de afiliado
/afiliar/cadastro               → Cadastro de afiliado
/m/:slug                        → Área de membros (login)
/m/:slug/modulos                → Módulos
/m/:slug/lesson/:id             → Aula
/m/:slug/comunidade             → Comunidade
/m/:slug/certificado            → Certificado
/api-checkout/:token            → Checkout via API
```

### Rotas Autenticadas (Painel)
```
/dashboard                      → Dashboard principal
/produtos                       → Lista de produtos
/produtos/criar                 → Criar produto
/produtos/:id/editar            → Editar produto (12 abas)
/produtos/:id/cupons            → Cupons do produto
/produtos/:id/afiliados         → Afiliados do produto
/produtos/:id/coproducers       → Coprodutores
/vendas                         → Lista de vendas
/alunos                         → Lista de alunos
/financeiro                     → Painel financeiro
/integracoes                    → Integrações e gateways
/configuracoes                  → Configurações (7 abas)
/email-marketing                → Email marketing
/usuarios                       → Gestão de usuários
/usuarios/equipe                → Gestão de equipe
/relatorios                     → Relatórios avançados
/afiliados                      → Gestão de afiliados
/assinaturas                    → Gestão de assinaturas
/reembolsos                     → Gestão de reembolsos
/plugins                        → Gestão de plugins
/conquistas                     → Gamificação
/aplicacoes-api                 → Aplicações API
/perfil                         → Perfil do usuário
```

### Rotas de Parceiro
```
/parceiro/dashboard             → Dashboard do parceiro
/parceiro/produtos              → Produtos do parceiro
/parceiro/vendas                → Vendas do parceiro
/parceiro/financeiro            → Financeiro do parceiro
```

---

## 5. FUNCIONALIDADES POR MÓDULO

### 5.1 Dashboard
- **Métricas**: Vendas totais, vendas pendentes, quantidade de vendas, ticket médio, taxa de conversão, abandono de carrinho, reembolsos
- **Gráfico de vendas**: Área (ApexCharts) — hourly para "hoje"/"ontem", daily para outros períodos
- **Filtros de período**: Hoje, Ontem, 7 dias, Mês, Ano, Total, Personalizado
- **Toggle de visibilidade**: Ocultar valores monetários
- **Painel de tracking**: Gastos com anúncios, ROI
- **Widget de conquistas**: Gamificação (mobile)
- **Widgets de plugins**: Extensões de plugins no dashboard

### 5.2 Produtos
- **Tipos**: Área de membros, Área de membros externa, Link, Link de pagamento
- **Billing**: Pagamento único ou Assinatura
- **CRUD completo** com 12 abas de edição:
  1. Geral (info básica, imagem, ofertas, planos de assinatura, combo)
  2. Configurações (gateways por método, redundância, parcelamento)
  3. E-mail (template, recuperação de carrinho 3 estágios)
  4. SMS (IntegraX)
  5. Order Bump (cross-sell)
  6. Upsell/Downsell (pós-compra)
  7. Checkout (slug, links exclusivos)
  8. Links (todas as URLs de checkout)
  9. Co-produção
  10. Afiliados (comissão, aprovação)
  11. Member Builder (módulos, aulas)
  12. Reembolso (política, janela de dias)
- **Operações**: Duplicar, importar/exportar (ZIP), excluir

### 5.3 Checkout
- **Layout**: Duas colunas (2/3 principal + 1/3 sidebar) no desktop; empilhado no mobile
- **Footer mobile sticky**: Total sempre visível
- **Gateways de pagamento**:
  - **PIX**: QR code + copy-paste (Spacepag, Efí, MercadoPago, CajuPay, PushinPay)
  - **Cartão de crédito**: Tokenização client-side (Stripe, Efí, Pagar.me, MercadoPago, Asaas, CajuPay, PayPal)
  - **Boleto**: (Spacepag, Efí, MercadoPago, Asaas, Pagar.me)
  - **PIX Automático** (assinatura recorrente)
  - **PIX Parcelado** (CajuPay)
  - **Apple Pay** (CajuPay)
  - **Google Pay** (CajuPay)
  - **PayPal** (Card Fields, Buttons, Wallet)
  - **Crypto** (placeholder)
- **Redundância de gateway**: Fallback automático entre gateways dentro de 25s
- **Cupons**: Validação em tempo real, aplicação no resumo
- **Multi-moeda**: 130+ moedas, detecção por GeoIP, taxas de câmbio
- **Order Bumps**: Cross-sell com checkbox
- **Timer**: Contagem regressiva para urgência
- **Notificação de venda**: Popup de prova social
- **Exit Popup**: Oferece cupão quando usuário tenta sair
- **Reviews**: Depoimentos na sidebar
- **Conteúdo**: Blocos de texto/imagens, YouTube embed
- **SEO**: Title, description, OG image, favicon por checkout
- **Idempotência**: UUIDs para prevenir pedidos duplicados
- **Abuse Guard**: Rate limiting por IP/produto/email
- **Honeypot**: Campo oculto para detectar bots

### 5.4 Vendas
- **Lista**: Tabela completa com filtros
- **Filtros**: Status, período, produto, oferta, método de pagamento, status de pagamento, UTM
- **Operações**: Ver detalhes, reenviar email de aprovação, aprovar manualmente, reembolsar, contato WhatsApp
- **Exportação**: CSV, XLS, ZIP de comprovantes
- **Sidebar de detalhes**: Informações completas do pedido

### 5.5 Alunos/Clientes
- **Métricas**: Total de alunos, inscrições, produtos ativos, novos em 30 dias
- **Lista**: Tabela (desktop) + cards (mobile)
- **Busca**: Nome ou email (debounced)
- **Filtros**: Produto, período
- **Operações**: Ver detalhes, editar, adicionar manualmente, importar CSV
- **Sidebar de detalhes**: Histórico de pedidos, acesso a produtos

### 5.6 Financeiro
- **Dashboard**: Saldo, saques, comissões pendentes
- **Wallet**: Ledger de transações
- **Payouts**: Solicitações de saque, alocação de comissões
- **Integração CajuPay**: Split de pagamento

### 5.7 Configurações (7 abas)
1. **E-mail**: Provedor (SMTP, Hostinger, SendGrid, Brevo), teste de conexão
2. **Storage**: Provedor (Local, S3, Wasabi, R2), migração
3. **Traduções**: Editor de traduções (pt_BR, en, es)
4. **Moedas**: Catálogo de moedas, taxas de câmbio
5. **Push**: Chaves VAPID para notificações
6. **Cron**: Configuração de crontab
7. **Update**: Verificação de versão, runner de atualização

### 5.8 Integrações (2 abas)
1. **Apps**: Webhook, Checkout externo, UTMfy, Spedy, Cademí, IntegraX, Pixel X, Pixels de rastreamento
2. **Gateways**: Configuração de credenciais por gateway

### 5.9 Email Marketing
- **Campanhas**: Criar, editar, enviar, pausar, retomar, cancelar
- **Destinatários**: Todos os clientes ou por produto
- **Template**: Editor HTML com template padrão
- **Rate limiting**: 30 emails/minuto
- **Placeholders**: `{nome}`, `{email}`

### 5.10 Afiliados
- **Gestão**: Aprovar/rejeitar afiliados, comissão por produto
- **Portal do parceiro**: Dashboard, produtos, vendas, financeiro
- **Cadastro público**: `/afiliar/{slug}`
- **Código de afiliado**: Tracking via URL

### 5.11 Área de Membros
- **Estrutura**: Seções → Módulos → Aulas
- **Tipos de aula**: Vídeo (Vidstack), Link, PDF, PDF Presentation, PDF Reader, Texto
- **Progresso**: Tracking de conclusão por aula
- **Comunidade**: Posts, comentários, likes
- **Certificados**: Geração automática na conclusão
- **Loja**: Produtos internos (upsell)
- **Gamificação**: Conquistas, compartilhamento social
- **PWA**: Manifest + Service Worker + Push Notifications
- **Login**: Template customizável, logo, fundo, cores

### 5.12 Sistema de Comissões
- **Tipos**: Produtor, Afiliado, Coprodutor
- **Cálculo**: Valor bruto - Taxa gateway = Líquido → % comissão
- **Status**: Pendente → Disponível → Pago / Cancelado
- **Wallet**: Ledger de transações por usuário
- **Payouts**: Solicitação, aprovação, alocação

### 5.13 Webhooks
- **Saída**: 13 eventos (pedido_pago, envio_acesso, pix_gerado, etc.)
- **Configuração**: URL, Bearer token, seleção de eventos, filtragem por produto
- **Teste**: Envio de eventos de teste
- **Logs**: Request/response completos, resend

### 5.14 Plugin System
- **Registro**: Plugin registry centralizado
- **Hooks**: WordPress-style actions + filters
- **Extensões**: Dashboard widgets, componentes de checkout, gateways, integrações
- **Gerenciamento**: Loja de plugins, instalação via ZIP, enable/disable

### 5.15 API pública
- **Autenticação**: API Key (Bearer ou X-API-Key)
- **Endpoints**:
  - `POST /api/v1/checkout/sessions` — Criar sessão de checkout
  - `POST /api/v1/payments/pix` — Criar pagamento PIX
  - `POST /api/v1/payments/card` — Criar pagamento cartão
  - `POST /api/v1/payments/boleto` — Criar pagamento boleto
  - `GET /api/v1/payments/{order}` — Status do pagamento

### 5.16 Segurança
- **Rate limiting**: Múltiplas camadas (checkout, login, API, webhooks)
- **CSRF**: XSRF-TOKEN + X-XSRF-TOKEN
- **Headers de segurança**: CSP, HSTS, X-Frame-Options
- **Senhas**: bcrypt
- **Secrets**: Criptografia em repouso
- **Idempotência**: UUIDs para prevenir duplicatas
- **PIX Lock**: Cache::add para prevenir PIX duplicado

---

## 6. PADRÕES DE CÓDIGO

### Componentes
```tsx
// Componentes funcionais com TypeScript
// Props sempre tipadas
// Componentes pequenos e reutilizáveis
// shadcn/ui como base, customizar com CVA

import { cn } from '@/lib/utils';

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'default' | 'primary' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
  size?: 'default' | 'sm' | 'lg' | 'icon';
  asChild?: boolean;
}

export function Button({ className, variant, size, ...props }: ButtonProps) {
  return (
    <button
      className={cn(buttonVariants({ variant, size }), className)}
      {...props}
    />
  );
}
```

### Hooks Customizados
```typescript
// useSidebar() — Estado da sidebar (expand/collapse, mobile)
// useCheckoutLocale() — Locale/moeda do checkout com localStorage
// useTrackingPanel() — Painel de tracking com polling
// usePluginComponentResolver() — Resolver componentes de plugins
// useConversionPurchase() — Deduplicação de eventos de compra
```

### Serviços de API
```typescript
// services/api.ts — Instância Axios com interceptores
// services/products.ts — CRUD de produtos
// services/orders.ts — Gestão de pedidos
// services/checkout.ts — Processamento de checkout
// services/auth.ts — Autenticação
// services/settings.ts — Configurações
```

### Stores (Zustand)
```typescript
// stores/auth.ts — Estado de autenticação
// stores/ui.ts — Estado da UI (sidebar, theme, modals)
// stores/checkout.ts — Estado do checkout
// stores/products.ts — Cache de produtos
```

---

## 7. RESPONSIVIDADE

### Breakpoints
- **Mobile** (< 1024px): Bottom nav, sidebar overlay, cards em vez de tabelas
- **Desktop** (>= 1024px): Sidebar colapsável, tabelas, layout de duas colunas

### Componentes Mobile-Específicos
- `MobileBottomNav` — Navegação inferior
- `MobileSummary` — Resumo do checkout sempre visível
- Cards em vez de tabelas para listas
- Bottom sheets em vez de sidebars

### PWA
- Manifest.json para instalação
- Service Worker para cache offline
- Push notifications (VAPID)
- iOS: `font-size: 16px` em inputs para prevenir zoom

---

## 8. INTERNACIONALIZAÇÃO

- **Idiomas do checkout**: pt_BR, en, es
- **UI do painel**: Apenas pt_BR (hardcoded)
- **Moedas**: 130+ moedas com taxas de câmbio
- **Formatação**: `Intl.NumberFormat` com locale-aware

---

## 9. PRIORIZAÇÃO DE IMPLEMENTAÇÃO

### Fase 1 — MVP (4 semanas)
1. Setup do projeto (Vite + React + Tailwind + shadcn)
2. Sistema de design (cores, tipografia, componentes base)
3. Layout admin (sidebar, header, mobile nav)
4. Autenticação (login, register, forgot password)
5. Dashboard (métricas, gráfico)
6. Produtos (CRUD básico)

### Fase 2 — Checkout (3 semanas)
7. Página de checkout (layout duas colunas)
8. Formulário de checkout (dados do cliente)
9. Gateway PIX (Spacepag/efí)
10. Gateway Cartão (Stripe)
11. Cupons
12. Página de obrigado

### Fase 3 — Gestão (3 semanas)
13. Vendas (lista, filtros, detalhes)
14. Alunos (lista, busca, importação CSV)
15. Financeiro (dashboard, wallet)
16. Configurações (email, storage, moedas)

### Fase 4 — Avançado (4 semanas)
17. Gateways adicionais (MercadoPago, Asaas, Pagar.me, PayPal, CajuPay)
18. Assinaturas (recorrência)
19. Order Bumps, Upsell, Downsell
20. Afiliados e comissões
21. Email marketing
22. Webhooks
23. Área de membros

### Fase 5 — Polish (2 semanas)
24. Dark mode completo
25. PWA (manifest, service worker, push)
26. Performance optimization
27. Testes E2E
28. Documentação

---

## 10. COMPARAÇÃO COM SISTEMA ATUAL

| Aspecto | Getfy Atual (Laravel + Vue) | Novo Sistema (React + Vite) |
|---------|----------------------------|---------------------------|
| Backend | Laravel 12 (PHP) | **Mantido** (não reescrever) |
| Frontend | Vue 3 + Inertia.js | React 19 + React Router |
| Build | Vite 7 | Vite 7 |
| CSS | Tailwind v4 | Tailwind v4 |
| Componentes | Custom (Radix Vue) | shadcn/ui (Radix UI) |
| State | Composables + Inertia props | Zustand + TanStack Query |
| Forms | Manual + Inertia | React Hook Form + Zod |
| Charts | ApexCharts | Recharts/Nivo |
| HTTP | Inertia router | Axios + TanStack Query |

---

## 11. NOTAS IMPORTANTES

1. **Backend permanece Laravel** — O frontend React se comunicará via API (REST ou Inertia adapter para React se disponível)
2. **Plugin system** — Manter compatibilidade com plugins existentes via bridge
3. **Multi-tenancy** — Lógica de tenant continua no backend; frontend apenas consome dados
4. **Checkout é standalone** — Página sem layout do admin, pode ser iframe/embed
5. **Dark mode é obrigatório** — Todos os componentes devem suportar
6. **Mobile-first** — Design responsivo é prioridade
7. **Acessibilidade** — Focus rings, aria labels, keyboard navigation
8. **Performance** — Code splitting, lazy loading, skeleton loaders
9. **Type Safety** — TypeScript strict mode, sem `any`
10. **Testes** — Unit tests (Vitest) + E2E (Playwright)

---

> **Este prompt deve ser usado como referência completa para recriar o sistema Getfy em React + Vite. Cada seção pode ser usada independentemente para implementação incremental.**

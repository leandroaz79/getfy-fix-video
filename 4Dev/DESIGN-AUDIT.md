# Auditoria de Design - Getfy

**Data:** 08/09/2026
**Stack:** Laravel 12 + Vue 3 (Inertia.js) + Tailwind CSS v4 + Radix Vue + Lucide Icons

---

## Resumo Executivo

O Getfy tem uma base de design **sólida e consistente**. O sistema de tokens CSS, a paleta Zinc, e o uso de utility classes são bem estruturados. No entanto, existem **18 problemas** que podem ser melhorados para elevar o design de "bom" para "premium".

---

## 1. TIPOGRAFIA

### ✅ O que está bom
- Fonte **Instrument Sans** com personalidade (não é Inter/genérica)
- Pesos variados: 400, 500, 600, 700
- Letter-spacing em labels (`tracking-[0.2em]`, `tracking-wide`)
- `tracking-tight` em headings

### ❌ Problemas encontrados

| # | Problema | Onde | Impacto |
|---|----------|------|---------|
| 1 | **Headings sem presença** — tamanhos genéricos (text-lg, text-xl) sem hierarquia forte | Dashboard, Settings, Checkout | Baixo |
| 2 | **Body text pode ficar largo** — sem `max-width` para legibilidade em parágrafos longos | Páginas de conteúdo, docs | Médio |
| 3 | **Números em font proportional** — métricas do dashboard (47.2%, R$ 1.234) sem tabular figures | Dashboard metrics | Baixo |
| 4 | **Orphaned words** — títulos quebrando mal em mobile | Page titles | Baixo |

### 🔧 Recomendações

```css
/* Adicionar tabular figures para números */
@theme {
  --font-mono: 'JetBrains Mono', ui-monospace, monospace;
}

/* Usar em métricas */
.text-2xl.tabular-nums { font-variant-numeric: tabular-nums; }
```

---

## 2. COR E SUPERFÍCIES

### ✅ O que está bom
- Paleta **Zinc** consistente (não mistura warm/cool grays)
- Primary color dinâmico (`--color-primary`) por tenant
- Dark mode bem implementado com `bg-zinc-950` (não `#000`)
- Sombras sutis (`shadow-theme-xs`, `shadow-theme-sm`)
- Scrollbar customizada consistente

### ❌ Problemas encontrados

| # | Problema | Onde | Impacto |
|---|----------|------|---------|
| 5 | **Sombras usam rgba(0,0,0)** — não tintadas com a cor de fundo | `--shadow-theme-xs/sm` | Baixo |
| 6 | **Sem textura/grain** — backgrounds 100% flat, sem profundidade | Todo o layout | Médio |
| 7 | **Gradientes perfeitamente uniformes** — nenhum gradient radial ou mesh | Dashboard charts, empty states | Baixo |
| 8 | **Mixed gray families** — checkout usa `gray-*` enquanto admin usa `zinc-*` | Checkout vs Admin | Médio |

### 🔧 Recomendações

```css
/* Sombras tintadas (substituir rgba(0,0,0)) */
--shadow-theme-xs: 0px 1px 2px 0px rgba(88, 80, 100, 0.06);
--shadow-theme-sm: 0px 1px 3px 0px rgba(88, 80, 100, 0.10), 0px 1px 2px 0px rgba(88, 80, 100, 0.05);

/* Grain overlay no body */
body::before {
  content: '';
  position: fixed;
  inset: 0;
  pointer-events: none;
  opacity: 0.025;
  background-image: url("data:image/svg+xml,...noise...");
  z-index: 999999;
}
```

---

## 3. LAYOUT

### ✅ O que está bom
- Sidebar colapsável com indicador visual de item ativo
- Container `max-w-7xl` com auto margins
- Grid responsivo (`grid gap-4 sm:grid-cols-2 lg:grid-cols-3`)
- Main content com `rounded-2xl` e `shadow-sm`
- Checkout sticky sidebar no desktop

### ❌ Problemas encontrados

| # | Problema | Onde | Impacto |
|---|----------|------|---------|
| 9 | **Dashboard sempre com sidebar esquerda** — padrão genérico | AppLayout | Baixo |
| 10 | **3 colunas iguais** — grids de métricas simétricos | Dashboard grid | Médio |
| 11 | **Cards de altura equalizada por flexbox** — conteúdo variado forçado | Settings cards | Baixo |
| 12 | **Padding vertical simétrico** — topo e base sempre iguais | Page sections | Baixo |
| 13 | **Espaçamento denso** — dashboard com pouco "air" | Dashboard | Médio |

### 🔧 Recomendações

```css
/* Mais whitespace no dashboard */
.md\:pt-6 → md\:pt-8 lg\:pt-10
.gap-4 → gap-5 lg\:gap-6

/* Cards com alturas variadas (masonry-like) */
.card-group { columns: 1; }
@media (min-width: 768px) { .card-group { columns: 2; } }
@media (min-width: 1024px) { .card-group { columns: 3; } }
.card-group > * { break-inside: avoid; margin-bottom: 1rem; }
```

---

## 4. INTERATIVIDADE E ESTADOS

### ✅ O que está bom
- Hover states em botões e nav items
- Focus rings consistentes (`focus-visible:ring-2`)
- Toggle com animação suave
- Disabled states (`disabled:opacity-50`)
- Period filter com indicador animado (spring-like)

### ❌ Problemas encontrados

| # | Problema | Onde | Impacto |
|---|----------|------|---------|
| 14 | **Sem active/pressed feedback** — botões sem `scale(0.98)` no click | Todos os botões | Médio |
| 15 | **Loading states genéricos** — apenas `animate-spin` nos ícones | Data loading, forms | Médio |
| 16 | **Sem empty states** — dashboard vazio mostra nada | Dashboard vazio | Médio |
| 17 | **Sem error states inline** — erros de formulário básicos | Forms | Baixo |

### 🔧 Recomendações

```css
/* Active state para botões */
.btn:active { transform: scale(0.98); }

/* Skeleton loader */
.skeleton {
  background: linear-gradient(90deg, zinc-200 25%, zinc-100 50%, zinc-200 75%);
  background-size: 200% 100%;
  animation: skeleton-pulse 1.5s ease-in-out infinite;
}
@keyframes skeleton-pulse {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
```

---

## 5. COMPONENTES

### ✅ O que está bom
- Button variants bem definidos via CVA
- Utility classes semânticas (`panel-card`, `menu-item`)
- Checkout com padrão consistente de section headers
- Scrollbar customizada e moderna

### ❌ Problemas encontrados

| # | Problema | Onde | Impacto |
|---|----------|------|---------|
| 18 | **Cards genéricos** — border + shadow + white bg | Panel cards | Baixo |
| 19 | **Modais para tudo** — beberapa ações usam modal desnecessário | Settings, Plugins | Baixo |
| 20 | **Badges pill-shaped sempre** — sem variação | Beta, Status badges | Baixo |

---

## 6. ICONOGRAFIA

### ✅ O que está bom
- Lucide Icons consistente (mesma biblioteca)
- Ícones com tamanho padronizado (`h-5 w-5`)
- Section headers com padrão `h-10 w-10 rounded-xl bg-gray-100`

### ❌ Problemas encontrados

| # | Problema | Onde | Impacto |
|---|----------|------|---------|
| 21 | **Lucide exclusivamente** — padrão "AI default" | Todo o projeto | Baixo |
| 22 | **Stroke widths inconsistentes** — alguns ícones `stroke-[1.5]`, outros padrão | Mixed | Baixo |

---

## 7. CÓDIGO

### ✅ O que está bom
- Utility classes bem organizadas
- `cn()` utility (clsx + tailwind-merge)
- Custom Tailwind utilities semânticas
- Dark mode via class (não media query)

### ❌ Problemas encontrados

| # | Problema | Onde | Impacto |
|---|----------|------|---------|
| 23 | **Mixed zinc/gray** — checkout usa `gray-*`, admin usa `zinc-*` | Checkout vs Admin | Médio |
| 24 | **Z-index arbitrários** — `z-[99999]`, `z-[100001]` | Sidebar, Toast | Baixo |

---

## 8. OMISSÕES ESTRATÉGICAS

| # | Omissão | Impacto |
|---|---------|---------|
| 25 | **Sem "skip to content" link** | Acessibilidade |
| 26 | **Sem 404 customizado** | UX |
| 27 | **Sem cookie consent** (se aplicável) | Legal |
| 28 | **Sem "back" navigation em fluxos** | UX |

---

## Prioridade de Correção

### Alta (Maior impacto visual, menor risco)

1. **Grain/noise overlay** — adiciona profundidade instantânea
2. **Sombras tintadas** — mais naturais
3. **Active states nos botões** — interface mais viva
4. **Whitespace no dashboard** — mais "air"

### Média (Melhoria significativa)

5. **Tabular figures nas métricas** — profissionalismo
6. **Skeleton loaders** — perception de performance
7. **Empty states** — dashboard vazio mais útil
8. **Migrar checkout gray → zinc** — consistência

### Baixa (Polimento final)

9. **Skip to content** — acessibilidade
10. **404 customizado** — completude
11. **Z-index scale limpo** — manutenção
12. **Header hierarchy** — mais presença

---

## Conclusão

O Getfy tem uma **base de design sólida** (8/10). Os problemas são de **polimento**, não de estrutura. As melhorias de maior impacto são:

1. **Grain overlay** — 5 minutos de trabalho, transformação visual imediata
2. **Sombras tintadas** — mais naturais e premium
3. **Active states** — interface mais responsiva
4. **Whitespace** — mais respiro no layout

Nenhuma dessas mudanças quebra funcionalidade existente.

# Integração Brevo - Guia de Instalação

## Arquivos incluídos

- `brevo-email.patch` — Patch com todas as mudanças PHP/Vue
- `brevo.svg` — Logo do Brevo para o painel

## Pré-requisitos

- Getfy instalado e funcionando (Docker ou manual)
- Acesso SSH ao servidor OU acesso ao painel do Dokploy
- Conta Brevo com chaves SMTP (app.brevo.com/settings/keys/smtp)

---

## Opção 1: Aplicar patch (recomendado)

### Passo 1 — Copie os arquivos para o servidor

```bash
# Via SCP (se tiver SSH)
scp 4Dev/brevo-email.patch usuario@servidor:/caminho/getfy/
scp 4Dev/brevo.svg usuario@servidor:/caminho/getfy/public/images/integrations/
```

Ou copie manualmente via painel de arquivos do Dokploy.

### Passo 2 — Aplique o patch

```bash
cd /caminho/getfy

# Aplica as mudanças nos 5 arquivos PHP/Vue
git apply brevo-email.patch

# Se der erro de "patch does not apply", force:
git apply --3way brevo-email.patch
```

### Passo 3 — Rebuild

```bash
# Se usar Docker:
docker compose exec app composer install --no-dev
docker compose exec app npm install
docker compose exec app npm run build
docker compose exec app php artisan cache:clear
docker compose exec app php artisan config:clear

# Se for manual:
composer install --no-dev
npm install && npm run build
php artisan cache:clear
php artisan config:clear
```

### Passo 4 — Reinicie

```bash
# Docker:
docker compose down && docker compose up -d

# Manual: reinicie PHP-FPM ou o servidor
```

---

## Opção 2: Copiar arquivos manualmente

Se o patch não funcionar, copie cada arquivo individualmente.

### 1. `app/Services/TenantMailConfigService.php`

Busque por `sendgrid_api_key` e adicione o bloco do Brevo logo abaixo (ver diff).

### 2. `app/Http/Controllers/SettingsController.php`

- Na validação `email_provider`, adicione `,brevo` na lista
- Adicione os 4 campos de validação do Brevo
- Adicione `brevo_smtp_username`, `brevo_mail_from_address`, `brevo_mail_from_name` na lista de chaves
- Adicione o bloco de criptografia `brevo_smtp_key`

### 3. `app/Http/Controllers/EmailTestController.php`

- Adicione `,brevo` nas validações `email_provider`
- Adicione os 4 campos de validação do Brevo
- Adicione o bloco `elseif ($provider === 'brevo')` na função `buildMailOverridesFromRequest`

### 4. `resources/js/Pages/Settings/Index.vue`

- Adicione os 4 campos no `useForm`
- Adicione o bloco `else if (provider === 'brevo')` nas funções `testConnection` e `sendTestEmail`
- Adicione o objeto Brevo no array `providers`
- Adicione o bloco `if (providerId === 'brevo')` na função `isProviderConfigured`

### 5. `resources/js/components/EmailProviderSidebar.vue`

- Adicione `const isBrevo = computed(...)`
- Adicione o bloco `<template v-else-if="isBrevo">` com os campos do Brevo
- Atualize os `v-if` para incluir `!isBrevo`

### 6. `public/images/integrations/brevo.svg`

Copie o arquivo SVG para esta pasta.

---

## Configuração no Dokploy

### Variáveis de ambiente

```
GETFY_APP_URL=https://seu-dominio.com.br
GETFY_DB_HOST=mysql
GETFY_DB_DATABASE=getfy
GETFY_DB_USERNAME=getfy
GETFY_DB_PASSWORD=sua_senha
GETFY_REDIS_HOST=redis
```

### Porta

O Dokploy usa Traefik na porta 80. Configure no compose:

```yaml
ports:
  - "3000:80"
```

Acesse via `http://ip-do-servidor:3000` ou configure proxy reverso.

---

## Verificação

1. Acesse o painel → **Integrações** → **E-mail**
2. Selecione **Brevo**
3. Preencha:
   - **E-mail de login SMTP**: seu@email.com (o mesmo da conta Brevo)
   - **Chave SMTP**: xsmtpsib-... (gerada em Brevo > Configurações > Chaves SMTP)
   - **E-mail do remetente**: noreply@seudominio.com (deve estar verificado no Brevo)
   - **Nome do remetente**: Nome da Loja
4. Clique em **Testar Conexão**
5. Se ok, clique em **Salvar**

---

## Troubleshooting

| Erro | Causa | Solução |
|------|-------|---------|
| "Connection refused" | Host/porta errados | Use `smtp-relay.brevo.com:587` |
| "Authentication failed" | Credenciais inválidas | Regenere a chave SMTP no Brevo |
| "Sender not verified" | E-mail não verificado | Verifique o remetente em Brevo > Transacional > Remetentes |
| Patch não aplica | Versão do Getfy diferente | Aplique manualmente via diff |

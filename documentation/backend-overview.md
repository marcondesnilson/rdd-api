# Visao do backend

## Stack

- Laravel Framework 13.6.0.
- PHP `^8.3`.
- PHPUnit 12 via `composer test`.
- Laravel Pint disponivel via `vendor/bin/pint`.
- Laravel Sanctum para tokens bearer de API.
- Banco inicial configurado como SQLite pelo skeleton oficial.
- A tabela `users` guarda apenas identidade/autenticacao minima. Perfil, preferencias, papel e verificacao ficam em tabelas dedicadas para evitar sobrecarga.

## Estrutura inicial

- `app/`: codigo de aplicacao Laravel.
- `bootstrap/app.php`: configuracao de boot, rotas e middleware.
- `config/`: configuracoes Laravel.
- `database/`: migrations, factories, seeders e arquivo SQLite local.
- `public/index.php`: front controller HTTP.
- `routes/web.php`: rota raiz JSON simples de status.
- `routes/api.php`: endpoints JSON sem prefixo `/api`, usando middleware `api`.
- `tests/`: suite inicial feature/unit do Laravel.

## Rotas atuais

Rotas atuais:

- `GET /`: resposta JSON simples com `status: ok`.
- `POST /auth/login`: autentica por email/senha e retorna `{ user, token }`.
- `POST /auth/register`: cria cadastro publico como `membro` e retorna `{ user, token }`.
- `GET /me`: retorna usuario autenticado via bearer token.
- `PATCH /me`: atualiza dados editaveis de perfil/conta do usuario autenticado.
- `GET /me/sessions`: lista tokens/sessoes Sanctum do usuario autenticado.
- `DELETE /me/sessions`: revoga todas as sessoes exceto a atual.
- `DELETE /me/sessions/{id}`: revoga uma sessao especifica do usuario autenticado.
- `GET /me/activity`: retorna atividade basica derivada do banco, como criacao da conta e tokens criados.
- `POST /auth/logout`: revoga o token atual.
- `GET /admin/users`: lista usuarios para `admin` e `editor`.
- `POST /admin/users`: cria usuario para `admin` e `editor`.
- `GET /admin/users/{id}`: detalha um usuario para `admin` e `editor`.
- `GET /admin/users/{id}/sessions`: lista sessoes Sanctum do usuario com IP, navegador e datas.
- `GET /admin/users/{id}/logs`: lista os ultimos eventos de auditoria do usuario.
- `GET /up`: health check registrado pelo framework.
- Rotas locais de storage registradas pelo framework.

### Login

Requisicao:

```http
POST /auth/login
Content-Type: application/json

{
  "email": "admin@admin.com",
  "password": "admin123"
}
```

Resposta:

```json
{
  "user": {
    "id": "01...",
    "name": "Administrador RDD",
    "email": "admin@admin.com",
    "initials": "AD",
    "role": "admin",
    "verification": {
      "status": "approved"
    },
    "createdAt": "2026-05-01T00:00:00.000000Z"
  },
  "token": "1|..."
}
```

Enviar o token nos endpoints autenticados:

```http
Authorization: Bearer 1|...
```

Como a API sera backend-only, os artefatos frontend do skeleton Laravel foram removidos. Novos contratos HTTP de produto devem ser criados como endpoints JSON e nao devem depender de views ou assets de frontend.

### Minha conta

`PATCH /me` aceita os campos editaveis abaixo e retorna `{ user }` no mesmo formato de sessao:

- `name`, `email`, `headline`, `bio`
- `phone`, `language`
- `publicProfile`, `showEmail`, `searchEngineIndex`
- `allowMessages`, `showActivity`

Os campos em camelCase sao convertidos para colunas snake_case no banco. Alteracao de senha, MFA e exclusao definitiva da conta ainda dependem de contratos especificos.

Persistencia relacionada:

- `users`: `id`, `name`, `email`, senha, verificacao de e-mail e timestamps.
- `user_profiles`: iniciais, manchete, bio, avatar, telefone e idioma.
- `user_preferences`: visibilidade e privacidade.
- `user_roles`: papel atual do usuario.
- `user_verifications`: status e dados da verificacao academica/profissional.
- `personal_access_tokens`: tokens Sanctum com IP de criacao, user-agent e ultimo IP de uso.
- `user_access_logs`: trilha de auditoria com ator, alvo, token, evento, metodo, caminho, status, IP, user-agent, metadata e data do evento.

### Auditoria e sessoes

A API registra rastreabilidade em dois niveis:

- dados de sessao no token Sanctum no login/cadastro: `ip_address`, `user_agent` e `last_used_ip_address`;
- eventos em `user_access_logs` para login, falha de login, cadastro, logout, atualizacao de conta, revogacao de sessoes, criacao administrativa de usuario e acessos autenticados.

Os campos `lastLogin` e `lastIp` foram adicionados ao shape de usuario a partir do ultimo evento `auth.login`. `GET /me/sessions` e `GET /admin/users/{id}/sessions` retornam IP e navegador derivados dos metadados do token.

`GET /admin/users/{id}/logs` retorna os ultimos 50 eventos do usuario:

```json
{
  "logs": [
    {
      "id": "01...",
      "event": "auth.login",
      "actor": {
        "id": "01...",
        "name": "Administrador RDD",
        "email": "admin@admin.com"
      },
      "method": "POST",
      "path": "/auth/login",
      "statusCode": null,
      "ip": "127.0.0.1",
      "userAgent": "Mozilla/5.0 ...",
      "metadata": null,
      "occurredAt": "2026-05-01T00:00:00.000000Z"
    }
  ]
}
```

## Contratos planejados

Os contratos iniciais esperados pelo frontend estao documentados em:

```text
../republica-do-direito/documentation/api-readiness.md
```

Ao implementar endpoints, manter controllers enxutos, validacao em Form Requests quando aplicavel e regra de negocio em actions/services.

## Ambiente local

O scaffold criou `.env` e `database/database.sqlite`. A tentativa automatica de migration do `post-create-project-cmd` nao concluiu porque o PHP CLI local nao encontrou o driver SQLite.

Nesta maquina, `php artisan migrate --seed` tambem foi bloqueado pelo mesmo motivo: o PHP CLI nao possui `pdo_sqlite`/`sqlite3`.

Opcoes:

- habilitar `pdo_sqlite`/`sqlite3` no PHP CLI;
- ou configurar `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD` para o banco escolhido.

## Seeds iniciais

Executar:

```bash
php artisan migrate --seed
```

Usuarios criados/atualizados pelo seeder:

- `admin@admin.com` / `admin123` com role `admin`.
- `editor@admin.com` / `editor123` com role `editor`.

Essas credenciais sao apenas para desenvolvimento/local e devem ser trocadas antes de qualquer ambiente publico.

## Variaveis relevantes

- `FRONTEND_URLS`: lista separada por virgulas com origens permitidas no CORS. Padrao local: `http://localhost:8080,http://localhost:8081,https://republica-do-direito.lovable.app`.
- CORS tambem aceita previews Lovable por padrao: `https://*.lovableproject.com` e `https://*.lovable.app`.

## Validacoes executadas

```bash
php artisan --version
php artisan route:list
php artisan test tests/Unit/SessionUserResourceTest.php
php artisan test tests/Feature/AccountSettingsTest.php tests/Feature/AuditTrailTest.php tests/Unit/SessionUserResourceTest.php
composer validate
vendor/bin/pint --test
```

Resultado: Laravel responde como 13.6.0, `composer validate`, `php artisan route:list`, `vendor/bin/pint --test` e o teste unitario de resource passaram. Os testes feature que usam `RefreshDatabase` continuam bloqueados nesta maquina porque o PHP CLI nao possui driver SQLite (`could not find driver`).

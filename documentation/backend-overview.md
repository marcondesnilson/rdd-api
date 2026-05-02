# Visao do backend

## Stack

- Laravel Framework 13.6.0.
- PHP `^8.3`.
- PHPUnit 12 via `composer test`.
- Laravel Pint disponivel via `vendor/bin/pint`.
- Laravel Sanctum para tokens bearer de API.
- `owen-it/laravel-auditing` para auditoria base de modelos persistentes.
- Integracao com CDN externa via `CdnClient` (consumo generico) e `CdnFileUploadService` (caso de uso de upload + persistencia).
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
- `PATCH /me/security`: atualiza configuracoes de seguranca (troca de senha com validacao da senha atual, status de MFA e notificacoes por e-mail).
- `POST /me/avatar`: recebe upload de imagem de perfil (multipart), publica no CDN e atualiza `avatar_url` do usuario.
- `GET /me/sessions`: lista tokens/sessoes Sanctum do usuario autenticado.
- `DELETE /me/sessions`: revoga todas as sessoes exceto a atual.
- `DELETE /me/sessions/{id}`: revoga uma sessao especifica do usuario autenticado.
- `GET /me/activity`: retorna atividade basica derivada do banco, como criacao da conta e tokens criados.
- `POST /auth/logout`: revoga o token atual.
- `GET /admin/users`: lista usuarios para `admin` e `editor`.
- `POST /admin/users`: cria usuario para `admin` e `editor`.
- `GET /admin/users/{id}`: detalha um usuario para `admin` e `editor`.
- `PATCH /admin/users/{id}`: atualiza nome, email, telefone, papel e status administrativo do usuario.
- `GET /admin/users/{id}/sessions`: lista sessoes Sanctum do usuario com IP, navegador e datas.
- `GET /admin/users/{id}/logs`: lista os ultimos eventos de auditoria do usuario.
- `GET /admin/users/{id}/audits`: lista historico de auditoria de modelo para o usuario, perfil, preferencias, papel e verificacao.
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

Os campos em camelCase sao convertidos para colunas snake_case no banco.

`PATCH /me/security` aceita:

- `currentPassword`
- `newPassword`
- `newPasswordConfirmation`
- `mfaEnabled`
- `mfaMethod` (`totp` ou `certificate`)
- `mfaSecret` (base32, obrigatorio ao ativar `totp`)
- `mfaCode` (6 digitos)
- `credentialId` (obrigatorio ao ativar `certificate`)
- `securityEmailAlerts`

Quando `newPassword` for enviada, `currentPassword` e obrigatoria e precisa conferir com a senha atual do usuario.
Quando `mfaEnabled` for enviada, a API atualiza o metodo informado por `mfaMethod` (padrao `totp` se omitido).
Ao ativar `totp`, o backend valida o `mfaCode` contra `mfaSecret` antes de persistir.
Ao ativar `certificate`, o backend exige `credentialId`.

`POST /auth/mfa/verify` (autenticado) valida o segundo fator no login:

- `method`: `totp` ou `certificate`
- `mfaCode`: obrigatorio para `totp`
- `credentialId`: obrigatorio para `certificate`

Persistencia relacionada:

- `users`: `id`, `name`, `email`, senha, verificacao de e-mail e timestamps.
- `user_profiles`: iniciais, manchete, bio, avatar, telefone e idioma.
- `user_preferences`: visibilidade e privacidade.
- `user_mfa`: metodos de MFA por usuario, incluindo segredo TOTP cifrado, `credential_id` de certificado e ultimo uso.
- `user_roles`: papel atual do usuario.
- `user_verifications`: status e dados da verificacao academica/profissional.
- `personal_access_tokens`: tokens Sanctum com IP de criacao, user-agent e ultimo IP de uso.
- `user_access_logs`: trilha de auditoria com ator, alvo, token, evento, metodo, caminho, status, IP, user-agent, metadata e data do evento.
- `files`: retorno normalizado de uploads no CDN externo (`success`, `file_id`, nome original, URL publica relativa, MIME e tamanho).

### Upload de arquivos (CDN)

O backend possui uma camada generica de cliente CDN e um caso de uso de upload:

- cliente generico: `App\Services\CdnClient`
- metodos iniciais do cliente: `getJson()`, `postJson()` e `upload()`
- caso de uso de upload: `App\Services\CdnFileUploadService`
- metodo principal de persistencia: `uploadAndStore(UploadedFile $file, bool $isPublic = true, bool $convert = true): App\Models\File`
- configuracao: `config/services.php` em `services.cdn_upload`

Fluxo:

1. Faz `POST multipart/form-data` para o endpoint configurado (default `/upload`);
2. envia `X-API-Key` via header usando variavel de ambiente;
3. valida resposta JSON (`success=true` e campos obrigatorios);
4. persiste em `files`.

Upload de avatar no produto:

- endpoint autenticado: `POST /me/avatar`
- validacao: apenas imagem (`jpeg`, `jpg`, `png`, `webp`, `gif`) ate 5MB
- resposta inclui `avatarUrl` (URL absoluta para uso no frontend) e metadados do arquivo salvo
- falhas de integracao/configuracao da CDN sao tratadas como indisponibilidade de servico, retornando JSON com `message` e status HTTP `503` (evita `500` generico no cliente).

Campos persistidos do retorno:

- `success`
- `file_id` (coluna `external_file_id`)
- `original_filename`
- `public_url`
- `mime_type`
- `size`

`public_url_full` nao e salvo em banco por definicao de projeto.

Exemplo de uso no backend:

```php
use App\Services\CdnClient;
use App\Services\CdnFileUploadService;

$storedFile = app(CdnFileUploadService::class)->uploadAndStore(
    file: $request->file('file'),
    isPublic: true,
    convert: true,
);

$status = app(CdnClient::class)->getJson('/health');
```

### Auditoria e sessoes

A API registra rastreabilidade em tres niveis:

- dados de sessao no token Sanctum no login/cadastro: `ip_address`, `user_agent` e `last_used_ip_address`;
- eventos em `user_access_logs` para login, falha de login, cadastro, logout, atualizacao de conta, revogacao de sessoes, criacao administrativa de usuario e acessos autenticados.
- auditoria base de alteracoes Eloquent na tabela `audits` via `owen-it/laravel-auditing`.

A auditoria base e controlada por `AUDITING_ENABLED`, com default `true`, e usa o driver `database`. O resolver de usuario considera guards na ordem `api`, `web`. Os eventos auditados sao `created`, `updated`, `deleted` e `restored`.

Campos sensiveis sao excluidos globalmente em `config/audit.php`, incluindo `password`, `remember_token`, tokens, secrets e credenciais. `UserVerification` tambem exclui `document`, por conter documento de verificacao academica/profissional. `UserAccessLog` nao implementa `Auditable`: ele e a trilha operacional de acesso e login, e auditar o proprio log duplicaria eventos sem valor de dominio.

Modelos auditados:

- `User`
- `UserProfile`
- `UserPreference`
- `UserRole`
- `UserVerification`

A migration `audits` usa `user_id` e `auditable_id` como `char(26)` para manter compatibilidade com entidades ULID do projeto. Modelos auxiliares que ainda usam ID numerico continuam sendo gravados como string no campo polimorfico.

Resolucao de IP real:

- se `REMOTE_ADDR` nao estiver em `AUDIT_TRUSTED_PROXY_IPS`, a API usa apenas `REMOTE_ADDR`;
- se `REMOTE_ADDR` estiver na lista confiavel, a API aceita `CF-Connecting-IP`, `True-Client-IP` e `X-Forwarded-For`;
- em `X-Forwarded-For`, a API percorre a lista e usa o primeiro IP publico valido;
- IPs privados, reservados ou invalidos em headers encaminhados sao ignorados.

Em producao publica com proxy, CDN ou load balancer, definir `AUDIT_TRUSTED_PROXY_IPS` com a lista explicita de IPs/CIDRs reais dos proxies. Nao usar ranges amplos sem controle operacional.

Os campos `lastLogin` e `lastIp` foram adicionados ao shape de usuario a partir do ultimo evento `auth.login`. `GET /me/sessions` e `GET /admin/users/{id}/sessions` retornam dispositivo (plataforma + tipo), IP e navegador derivados do `user_agent` e metadados do token.

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

`GET /admin/users/{id}/audits` retorna os ultimos 50 eventos da auditoria base para a entidade de usuario e seus registros relacionados:

```json
{
  "audits": [
    {
      "id": "1",
      "event": "updated",
      "auditableType": "UserProfile",
      "auditableId": "1",
      "actor": {
        "id": "01...",
        "name": "Administrador RDD",
        "email": "admin@admin.com"
      },
      "oldValues": {
        "headline": "Membro da República"
      },
      "newValues": {
        "headline": "Estudante de Direito"
      },
      "ip": "8.8.8.8",
      "userAgent": "Mozilla/5.0 ...",
      "url": "https://api.example.com/me",
      "createdAt": "2026-05-01T00:00:00.000000Z"
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
- `AUDITING_ENABLED`: habilita/desabilita a auditoria base de modelos. Padrao: `true`.
- `AUDIT_TRUSTED_PROXY_IPS`: lista separada por virgulas com IPs/CIDRs de proxies/CDNs/load balancers confiaveis para leitura de `CF-Connecting-IP`, `True-Client-IP` e `X-Forwarded-For`.
- `CDN_UPLOAD_BASE_URL`: dominio base da API de upload (exemplo: `https://cdn.aptest.link`).
- `CDN_UPLOAD_PATH`: caminho de upload na API CDN. Padrao: `/upload`.
- `CDN_UPLOAD_API_KEY`: credencial para header `X-API-Key`.
- `CDN_UPLOAD_TIMEOUT`: timeout total de requisicao HTTP em segundos.
- `CDN_UPLOAD_CONNECT_TIMEOUT`: timeout de conexao HTTP em segundos.

## Validacoes executadas

```bash
php artisan --version
php artisan route:list
php artisan test tests/Unit/SessionUserResourceTest.php
php artisan test tests/Unit/PublicIpAddressTest.php tests/Unit/SessionUserResourceTest.php
php artisan test tests/Feature/AccountSettingsTest.php tests/Feature/AuditTrailTest.php tests/Unit/SessionUserResourceTest.php
composer validate
vendor/bin/pint --test
```

Resultado: Laravel responde como 13.6.0, `composer validate`, `php artisan route:list`, `vendor/bin/pint --test` e os testes unitarios de IP/resource passaram. Os testes feature que usam `RefreshDatabase` continuam bloqueados nesta maquina porque o PHP CLI nao possui driver SQLite (`could not find driver`).

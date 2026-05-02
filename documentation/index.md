# Documentacao da API

Projeto afetado: `rdd-api`.

Esta documentacao registra a base inicial da API Laravel do Republica do Direito. O projeto foi criado a partir do skeleton oficial `laravel/laravel` 13.x.

## Leituras principais

- [Visao do backend](./backend-overview.md): stack, estrutura inicial, rotas atuais e validacoes.
- [Preparacao para API do frontend](../../republica-do-direito/documentation/api-readiness.md): contratos sugeridos para substituir mocks do frontend.

## Estado atual

- Laravel Framework 13.6.0.
- PHP requerido: `^8.3`.
- Composer ja instalou dependencias em `vendor`.
- `.env` foi gerado pelo scaffold e `APP_KEY` foi configurada.
- Banco padrao do scaffold: SQLite em `database/database.sqlite`.
- Tabela `users` mantida enxuta; dados de perfil, preferencias, roles e verificacao foram separados em migrations proprias.
- Artefatos frontend do skeleton foram removidos para manter o projeto backend-only.
- Autenticacao por bearer token com Laravel Sanctum.
- CORS configurado para origens locais e previews Lovable.
- Endpoints iniciais de login, cadastro, sessao atual, atualizacao de "Minha conta", sessoes da conta, criacao/listagem administrativa de usuarios, detalhe administrativo, atualizacao administrativa de usuario, sessoes administrativas, logs operacionais por usuario e historico de auditoria de modelos.
- Endpoint de seguranca da conta (`PATCH /me/security`) com validacao de senha atual para troca de senha e persistencia de MFA/notificacoes de seguranca.
- Endpoint de sessoes da conta (`GET /me/sessions`) retornando identificacao de dispositivo real baseada em `user_agent` (plataforma + tipo do dispositivo), alem de navegador e IP.
- MFA normalizado em tabela dedicada `user_mfa`, com metodos iniciais `totp` e `certificate`.
- Verificacao MFA no login via endpoint autenticado `POST /auth/mfa/verify`, validando codigo TOTP/credentialId persistidos.
- Auditoria base de modelos com `owen-it/laravel-auditing`, driver `database` e tabela `audits`.
- Auditoria operacional com dados de sessao/acesso em tokens Sanctum e eventos persistidos em `user_access_logs`.
- Infraestrutura de upload de imagem via CDN externa com persistencia de retorno em `files`.
- Upload de avatar com tratamento explicito para falhas de CDN, retornando erro HTTP `503` com mensagem JSON em vez de `500` generico.
- Seeds iniciais para `admin@admin.com` e `editor@admin.com`.

## Validacao recomendada

Executar dentro de `rdd-api`:

```bash
composer validate
composer test
php artisan route:list
vendor/bin/pint --test
```

Para rodar migrations locais com o `.env` atual, habilitar a extensao SQLite do PHP CLI ou trocar a conexao em `.env` para outro banco suportado.

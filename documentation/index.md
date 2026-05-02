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
- Endpoints iniciais de login, cadastro, sessao atual, atualizacao de "Minha conta", sessoes da conta, criacao/listagem administrativa de usuarios, detalhe administrativo, sessoes administrativas e logs de auditoria por usuario.
- Auditoria com dados de sessao/acesso em tokens Sanctum e eventos persistidos em `user_access_logs`.
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

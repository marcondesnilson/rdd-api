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
- Artefatos frontend do skeleton foram removidos para manter o projeto backend-only.
- Nenhum endpoint de dominio foi implementado ainda.

## Validacao recomendada

Executar dentro de `rdd-api`:

```bash
composer validate
composer test
php artisan route:list
vendor/bin/pint --test
```

Para rodar migrations locais com o `.env` atual, habilitar a extensao SQLite do PHP CLI ou trocar a conexao em `.env` para outro banco suportado.

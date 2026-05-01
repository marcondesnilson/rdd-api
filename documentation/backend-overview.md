# Visao do backend

## Stack

- Laravel Framework 13.6.0.
- PHP `^8.3`.
- PHPUnit 12 via `composer test`.
- Laravel Pint disponivel via `vendor/bin/pint`.
- Banco inicial configurado como SQLite pelo skeleton oficial.

## Estrutura inicial

- `app/`: codigo de aplicacao Laravel.
- `bootstrap/app.php`: configuracao de boot, rotas e middleware.
- `config/`: configuracoes Laravel.
- `database/`: migrations, factories, seeders e arquivo SQLite local.
- `public/index.php`: front controller HTTP.
- `routes/web.php`: rota raiz JSON simples de status.
- `tests/`: suite inicial feature/unit do Laravel.

## Rotas atuais

O projeto ainda esta sem endpoints de dominio:

- `GET /`: resposta JSON simples com `status: ok`.
- `GET /up`: health check registrado pelo framework.
- Rotas locais de storage registradas pelo framework.

Como a API sera backend-only, os artefatos frontend do skeleton Laravel foram removidos. Novos contratos HTTP de produto devem ser criados como endpoints JSON e nao devem depender de views ou assets de frontend.

## Contratos planejados

Os contratos iniciais esperados pelo frontend estao documentados em:

```text
../republica-do-direito/documentation/api-readiness.md
```

Ao implementar endpoints, manter controllers enxutos, validacao em Form Requests quando aplicavel e regra de negocio em actions/services.

## Ambiente local

O scaffold criou `.env` e `database/database.sqlite`. A tentativa automatica de migration do `post-create-project-cmd` nao concluiu porque o PHP CLI local nao encontrou o driver SQLite.

Opcoes:

- habilitar `pdo_sqlite`/`sqlite3` no PHP CLI;
- ou configurar `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD` para o banco escolhido.

## Validacoes executadas

```bash
php artisan --version
php artisan route:list
composer test
vendor/bin/pint --test
```

Resultado: Laravel responde como 13.6.0 e a suite inicial passou com 2 testes.

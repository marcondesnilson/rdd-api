# RDD API

API Laravel do Republica do Direito.

## Stack

- Laravel Framework 13.x
- PHP `^8.3`
- Composer
- PHPUnit 12

## Setup local

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

O scaffold inicial usa SQLite em `database/database.sqlite`. Para executar migrations com essa configuracao, o PHP CLI precisa ter `pdo_sqlite`/`sqlite3` habilitado.

## Acesso inicial

O seeder cria duas contas administrativas para desenvolvimento:

- `admin@admin.com` / `admin123`
- `editor@admin.com` / `editor123`

Use essas credenciais apenas em ambiente local.

## Validacao

```bash
composer test
php artisan route:list
vendor/bin/pint --test
```

## Documentacao

A documentacao operacional esta em [`documentation/index.md`](documentation/index.md).

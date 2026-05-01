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
```

O scaffold inicial usa SQLite em `database/database.sqlite`. Para executar migrations com essa configuracao, o PHP CLI precisa ter `pdo_sqlite`/`sqlite3` habilitado.

## Validacao

```bash
composer test
php artisan route:list
```

## Documentacao

A documentacao operacional esta em [`documentation/index.md`](documentation/index.md).

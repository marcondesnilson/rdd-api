<?php

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DevelopmentSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:seed-development {--fresh : Recria o banco com migrate:fresh antes de popular} {--users=10 : Quantidade de usuarios de teste}', function () {
    $users = (int) $this->option('users');

    if ($users < 3) {
        $this->error('Use --users com valor maior ou igual a 3.');
        return self::FAILURE;
    }

    if ((bool) $this->option('fresh')) {
        $this->warn('Executando migrate:fresh...');
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->output->write(Artisan::output());
    }

    DB::transaction(function () use ($users): void {
        app(DatabaseSeeder::class)->run();
        app(DevelopmentSeeder::class)->run($users);
    });

    $this->info("Seed de desenvolvimento concluido com {$users} usuarios de teste.");
    $this->line('Credenciais dev: admin.dev@rdd.local / dev12345');
    $this->line('Credenciais dev: editor.dev@rdd.local / dev12345');

    return self::SUCCESS;
})->purpose('Popula dados de desenvolvimento com fixtures de teste');

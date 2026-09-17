<?php

namespace App\Console\Commands;

use Database\Seeders\CoursJsonSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Throwable;

class ImportCours extends Command
{
    use ConfirmableTrait;

    protected $signature = 'cours:import {--dry-run : Simuler puis annuler les insertions} {--force : Autoriser l’import en production}';

    protected $description = 'Ajouter les cours du catalogue en préservant les données existantes et Digital';

    public function handle(): int
    {
        if (! $this->option('dry-run') && ! $this->confirmToProceed()) {
            return self::FAILURE;
        }
        try {
            $seeder = new CoursJsonSeeder;
            $seeder->offline = true;
            $seeder->dryRun = (bool) $this->option('dry-run');
            $seeder->setContainer($this->laravel)->setCommand($this);
            $seeder->run();

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Import interrompu ; insertions annulées : '.$e->getMessage());

            return self::FAILURE;
        }
    }
}

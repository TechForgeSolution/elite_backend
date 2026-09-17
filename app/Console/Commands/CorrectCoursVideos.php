<?php

namespace App\Console\Commands;

use Database\Seeders\CoursVideoLinksSeeder;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Throwable;

class CorrectCoursVideos extends Command
{
    use ConfirmableTrait;

    protected $signature = 'cours:correct-videos {--dry-run : Afficher les corrections sans modifier les URL} {--force : Autoriser en production}';

    protected $description = 'Remplacer uniquement les liens Drive connus par leurs liens vidéo directs';

    public function handle(): int
    {
        if (! $this->option('dry-run') && ! $this->confirmToProceed()) {
            return self::FAILURE;
        }
        try {
            $seeder = new CoursVideoLinksSeeder;
            $seeder->dryRun = (bool) $this->option('dry-run');
            $seeder->setContainer($this->laravel)->setCommand($this)->run();

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Correction interrompue, modifications annulées : '.$e->getMessage());

            return self::FAILURE;
        }
    }
}

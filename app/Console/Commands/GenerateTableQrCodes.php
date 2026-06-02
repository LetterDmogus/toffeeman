<?php

namespace App\Console\Commands;

use App\Models\Table;
use App\Services\QrCodeService;
use Illuminate\Console\Command;

class GenerateTableQrCodes extends Command
{
    protected $signature = 'tables:generate-qr {--force : Regenerate even if QR already exists}';

    protected $description = 'Generate QR code images for all restaurant tables';

    public function handle(QrCodeService $qrCodeService): int
    {
        $tables = Table::all();

        if ($tables->isEmpty()) {
            $this->warn('No tables found.');

            return self::SUCCESS;
        }

        $this->info("Generating QR codes for {$tables->count()} tables...");

        $bar = $this->output->createProgressBar($tables->count());
        $bar->start();

        $generated = 0;
        $skipped = 0;

        foreach ($tables as $table) {
            if ($table->qr_image_path && ! $this->option('force')) {
                $skipped++;
                $bar->advance();

                continue;
            }

            $qrCodeService->generateForTable($table);
            $generated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Done! Generated: {$generated}, Skipped: {$skipped}");

        return self::SUCCESS;
    }
}

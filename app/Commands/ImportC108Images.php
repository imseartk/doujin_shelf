<?php

namespace App\Commands;

use App\Controllers\Circlems;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ImportC108Images extends BaseCommand
{
    protected $group = 'Doujin';
    protected $name = 'circlems:import-c108-images';
    protected $description = 'Download/export Circle.ms image DB and sync C108 circle cut images.';

    public function run(array $params): void
    {
        $eventId = (int) (CLI::getOption('event') ?: 230);
        $imageNo = (int) (CLI::getOption('image') ?: 1);
        $download = CLI::getOption('no-download') === null;
        $force = CLI::getOption('force') !== null;

        CLI::write('Importing C108 Circle.ms images...');
        CLI::write('Event ID: ' . $eventId);
        CLI::write('Image DB: ' . $imageNo);
        CLI::write('Download image DB: ' . ($download ? 'yes' : 'no'));
        CLI::write('Overwrite existing URLs: ' . ($force ? 'yes' : 'no'));

        try {
            $summary = (new Circlems())->importC108ImageCatalog($eventId, $imageNo, $download, $force);
        } catch (\Throwable $exception) {
            CLI::error($exception->getMessage());
            return;
        }

        CLI::write('C108 image import complete.', 'green');
        CLI::write('Tables scanned: ' . number_format((int) $summary['tables_scanned']));
        CLI::write('Image rows scanned: ' . number_format((int) $summary['image_rows_scanned']));
        CLI::write('Images exported: ' . number_format((int) $summary['images_exported']));
        CLI::write('C108 rows updated: ' . number_format((int) $summary['rows_updated']));
        CLI::write('Skipped without match: ' . number_format((int) $summary['skipped_without_match']));
        CLI::write('Skipped existing URL: ' . number_format((int) $summary['skipped_existing']));
        CLI::write('Export dir: ' . (string) $summary['export_dir']);

        if (! empty($summary['download'])) {
            CLI::write('Image DB MD5 OK: ' . (! empty($summary['download']['md5Ok']) ? 'yes' : 'no'));
            CLI::write('Image DB path: ' . (string) ($summary['download']['dbPath'] ?? ''));
        }
    }
}

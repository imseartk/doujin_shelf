<?php

namespace App\Commands;

use App\Controllers\Circlems;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;

class UpdateC108Catalog extends BaseCommand
{
    protected $group = 'Circle.ms';
    protected $name = 'circlems:update-c108';
    protected $description = 'Download the latest Circle.ms text DB and import C108 offline circle data.';
    protected $usage = 'circlems:update-c108 [--event 230] [--limit 1000] [--reset] [--no-download]';
    protected $options = [
        '--event' => 'Circle.ms event ID. Default: 230.',
        '--limit' => 'Import batch size, max 1000. Default: 1000.',
        '--reset' => 'Delete existing rows for the event before importing.',
        '--no-download' => 'Skip downloading the text DB and import from the existing local SQLite file.',
    ];

    public function run(array $params): void
    {
        $eventId = (int) (CLI::getOption('event') ?: 230);
        $limit = (int) (CLI::getOption('limit') ?: 1000);
        $reset = CLI::getOption('reset') !== null;
        $download = CLI::getOption('no-download') === null;

        CLI::write('Updating C108 offline catalog...');
        CLI::write('Event ID: ' . $eventId);
        CLI::write('Download text DB: ' . ($download ? 'yes' : 'no'));
        CLI::write('Reset existing rows: ' . ($reset ? 'yes' : 'no'));

        try {
            $summary = (new Circlems())->updateC108OfflineCatalog($eventId, $limit, $reset, $download);
        } catch (RuntimeException $exception) {
            CLI::error($exception->getMessage());
            return;
        }

        CLI::write('C108 offline catalog updated.', 'green');
        CLI::write('Total source rows: ' . number_format((int) $summary['total']));
        CLI::write('Imported rows: ' . number_format((int) $summary['imported']));
        CLI::write('Matched local circles: ' . number_format((int) $summary['matched_local_circles']));
        CLI::write('Skipped without WCID: ' . number_format((int) $summary['skipped_without_wcid']));

        if (is_array($summary['download'] ?? null)) {
            CLI::write('Text DB MD5 OK: ' . (! empty($summary['download']['md5Ok']) ? 'yes' : 'no'));
            CLI::write('Text DB path: ' . (string) ($summary['download']['dbPath'] ?? ''));
        }
    }
}

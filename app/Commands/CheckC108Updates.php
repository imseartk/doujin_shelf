<?php

namespace App\Commands;

use App\Controllers\Circlems;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;

class CheckC108Updates extends BaseCommand
{
    protected $group = 'Circle.ms';
    protected $name = 'circlems:check-c108-updates';
    protected $description = 'Check Circle.ms favorite circles and mark C108 tracked circles with unread update notices.';
    protected $usage = 'circlems:check-c108-updates [--event 230]';
    protected $options = [
        '--event' => 'Circle.ms event ID. Default: 230.',
    ];

    public function run(array $params): void
    {
        $eventId = (int) (CLI::getOption('event') ?: 230);

        CLI::write('Checking C108 Circle.ms favorite updates...');
        CLI::write('Event ID: ' . $eventId);

        try {
            $summary = (new Circlems())->checkC108FavoriteUpdates($eventId);
        } catch (RuntimeException $exception) {
            CLI::error($exception->getMessage());
            return;
        }

        CLI::write('C108 update check complete.', 'green');
        CLI::write('Favorite circles checked: ' . number_format((int) $summary['favorite_count']));
        CLI::write('Matched C108 rows: ' . number_format((int) $summary['matched_c108_rows']));
        CLI::write('Skipped untracked rows: ' . number_format((int) $summary['skipped_untracked']));
        CLI::write('Unread notices written: ' . number_format((int) $summary['updated_rows']));
        CLI::write('Checked at: ' . (string) $summary['checked_at']);
    }
}

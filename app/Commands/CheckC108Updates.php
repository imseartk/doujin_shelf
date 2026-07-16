<?php

namespace App\Commands;

use App\Controllers\Circlems;
use App\Libraries\LineBotNotifier;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;

class CheckC108Updates extends BaseCommand
{
    protected $group = 'Circle.ms';
    protected $name = 'circlems:check-c108-updates';
    protected $description = 'Check Circle.ms favorite circles and mark C108 tracked circles with unread update notices.';
    protected $usage = 'circlems:check-c108-updates [--event 230] [--no-line]';
    protected $options = [
        '--event' => 'Circle.ms event ID. Default: 230.',
        '--no-line' => 'Skip LINE push notification even when enabled.',
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

        if ((int) $summary['updated_rows'] <= 0 || CLI::getOption('no-line') !== null) {
            return;
        }

        $notifier = new LineBotNotifier();
        if (! $notifier->canSend()) {
            CLI::write('LINE notification skipped: disabled or incomplete configuration.', 'yellow');
            return;
        }

        try {
            $notifier->pushText($this->buildLineMessage($summary));
            CLI::write('LINE notification sent.', 'green');
        } catch (RuntimeException $exception) {
            CLI::error($exception->getMessage());
        }
    }

    private function buildLineMessage(array $summary): string
    {
        $eventId = (int) ($summary['event_id'] ?? 230);
        $checkedAt = (string) ($summary['checked_at'] ?? date('Y-m-d H:i:s'));
        $rows = db_connect()->table('c108_circles c108')
            ->select('c108.circle_name, c108.day, c108.position_label, c108.update_notice_text')
            ->where('c108.event_id', $eventId)
            ->where('c108.update_read', 0)
            ->where('c108.update_detected_at', $checkedAt)
            ->orderBy('c108.day', 'ASC')
            ->orderBy('c108.position_label', 'ASC')
            ->limit(5)
            ->get()
            ->getResultArray();

        $lines = [
            'C108 追蹤社團更新',
            '新增未讀：' . number_format((int) ($summary['updated_rows'] ?? 0)) . ' 件',
        ];

        foreach ($rows as $row) {
            $position = trim((string) ($row['day'] ?? '') . '日目 ' . (string) ($row['position_label'] ?? ''));
            $notice = trim((string) ($row['update_notice_text'] ?? '社團資訊更新'));
            $lines[] = '- ' . (string) ($row['circle_name'] ?? '') . ' / ' . $position . ' / ' . $notice;
        }

        if ((int) ($summary['updated_rows'] ?? 0) > count($rows)) {
            $lines[] = '...還有 ' . number_format((int) ($summary['updated_rows'] - count($rows))) . ' 件';
        }

        $baseUrl = rtrim((string) config('App')->baseURL, '/');
        if ($baseUrl !== '') {
            $lines[] = $baseUrl . '/c108';
        }

        return implode("\n", $lines);
    }
}

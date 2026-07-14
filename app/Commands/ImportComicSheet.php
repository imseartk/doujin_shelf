<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ImportComicSheet extends BaseCommand
{
    protected $group = 'Books';
    protected $name = 'books:import-comic-sheet';
    protected $description = 'Import tankoubon rows from the comic Google Sheet into books.';

    private const DEFAULT_CSV_URL = 'https://docs.google.com/spreadsheets/d/1XPxgFO4aXOS7vjD5KUpKaM-THfwQu-CxqtNg3WLn90s/export?format=csv&gid=1991331123';

    public function run(array $params): void
    {
        $source = $params[0] ?? self::DEFAULT_CSV_URL;
        $handle = @fopen($source, 'rb');
        if ($handle === false) {
            CLI::error('Unable to open comic sheet CSV: ' . $source);
            return;
        }

        $db = db_connect();
        $now = date('Y-m-d H:i:s');
        $rowNumber = 0;
        $inserted = 0;
        $skipped = [
            'header' => 0,
            'empty_title' => 0,
            'type' => 0,
            'status' => 0,
            'duplicate' => 0,
        ];

        $db->transStart();
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if ($rowNumber === 1) {
                $skipped['header']++;
                continue;
            }

            $titleRaw = $this->cell($row, 1);
            $author = $this->nullIfEmpty($this->cell($row, 3));
            $type = $this->cell($row, 4);
            $purchaseStatus = $this->cell($row, 5);

            if ($type !== '単行本') {
                $skipped['type']++;
                continue;
            }

            if ($purchaseStatus === '2026.11') {
                $skipped['status']++;
                continue;
            }

            [$title, $note] = $this->splitTitleAndNote($titleRaw);
            if ($title === '') {
                $skipped['empty_title']++;
                continue;
            }

            if ($this->comicExists($db, $title, $author)) {
                $skipped['duplicate']++;
                continue;
            }

            $db->table('books')->insert([
                'type' => 'comic',
                'title' => $title,
                'circle_kana' => null,
                'circle' => null,
                'circle_id' => null,
                'author' => $author,
                'event' => null,
                'cover_url' => null,
                'status' => $this->statusFromSheet($purchaseStatus),
                'location_id' => null,
                'note' => $this->nullIfEmpty($note),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $inserted++;
        }
        fclose($handle);
        $db->transComplete();

        if ($db->transStatus() === false) {
            CLI::error('Comic import failed.');
            return;
        }

        CLI::write('Comic import complete.');
        CLI::write('Inserted: ' . number_format($inserted));
        foreach ($skipped as $reason => $count) {
            CLI::write('Skipped ' . $reason . ': ' . number_format($count));
        }
    }

    private function cell(array $row, int $index): string
    {
        return trim((string) ($row[$index] ?? ''));
    }

    private function splitTitleAndNote(string $value): array
    {
        $lines = preg_split('/\R/u', trim($value)) ?: [];
        $lines = array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== ''));
        if ($lines === []) {
            return ['', ''];
        }

        $title = array_shift($lines);
        $notes = array_map([$this, 'stripWrappingParentheses'], $lines);

        return [$title, implode("\n", array_filter($notes, static fn (string $line): bool => $line !== ''))];
    }

    private function stripWrappingParentheses(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\((.*)\)$/u', $value, $matches) === 1) {
            return trim($matches[1]);
        }
        if (preg_match('/^（(.*)）$/u', $value, $matches) === 1) {
            return trim($matches[1]);
        }

        return $value;
    }

    private function statusFromSheet(string $value): string
    {
        return in_array($value, ['〇', '○', 'O', 'o'], true) ? 'owned' : 'wishlist';
    }

    private function comicExists($db, string $title, ?string $author): bool
    {
        $builder = $db->table('books')
            ->select('id')
            ->where('type', 'comic')
            ->where('title', $title);

        if ($author === null) {
            $builder->where('author IS NULL', null, false);
        } else {
            $builder->where('author', $author);
        }

        return $builder->limit(1)->get()->getRowArray() !== null;
    }

    private function nullIfEmpty(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}

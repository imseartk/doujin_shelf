<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

class AppApi extends BaseController
{
    private const DEFAULT_LIMIT = 30;
    private const MAX_LIMIT = 80;

    private const TYPE_OPTIONS = [
        'doujin' => '同人誌',
        'comic' => '単行本',
        'other' => '其他',
    ];

    private const STATUS_OPTIONS = [
        'owned' => '已擁有',
        'blacklisted' => '黑名單',
        'ordered' => '已訂購',
        'wishlist' => '願望清單',
    ];

    public function books(): ResponseInterface
    {
        if ($failure = $this->appApiAuthFailure()) {
            return $failure;
        }

        $db = db_connect();
        $q = trim((string) $this->request->getGet('q'));
        $type = trim((string) $this->request->getGet('type'));
        $status = trim((string) $this->request->getGet('status'));
        $tagId = (int) $this->request->getGet('tag_id');
        $page = max(1, (int) $this->request->getGet('page'));
        $limit = (int) $this->request->getGet('limit');
        if ($limit <= 0) {
            $limit = self::DEFAULT_LIMIT;
        }
        $limit = min($limit, self::MAX_LIMIT);

        if ($type !== '' && ! array_key_exists($type, self::TYPE_OPTIONS)) {
            $type = '';
        }
        if ($status !== '' && ! array_key_exists($status, self::STATUS_OPTIONS)) {
            $status = '';
        }
        if ($tagId > 0 && ! $this->tagExists($tagId)) {
            $tagId = 0;
        }

        $applyFilters = static function ($builder) use ($db, $q, $type, $status, $tagId) {
            if ($type !== '') {
                $builder->where('b.type', $type);
            }

            if ($status !== '') {
                $builder->where('b.status', $status);
            }

            if ($tagId > 0) {
                $builder->where("EXISTS (SELECT 1 FROM book_tags filter_bt WHERE filter_bt.book_id = b.id AND filter_bt.tag_id = {$tagId})", null, false);
            }

            if ($q !== '') {
                $escaped = $db->escapeLikeString($q);
                $builder->groupStart()
                    ->like('b.title', $q)
                    ->orLike('b.circle', $q)
                    ->orLike('b.author', $q)
                    ->orLike('b.circle_kana', $q)
                    ->orWhere("EXISTS (SELECT 1 FROM book_tags bt JOIN tags t ON t.id = bt.tag_id WHERE bt.book_id = b.id AND t.name LIKE '%{$escaped}%' ESCAPE '!')", null, false)
                    ->orWhere("EXISTS (SELECT 1 FROM book_works bw JOIN works w ON w.id = bw.work_id WHERE bw.book_id = b.id AND w.name LIKE '%{$escaped}%' ESCAPE '!')", null, false)
                    ->orWhere("EXISTS (SELECT 1 FROM book_characters bc JOIN characters c ON c.id = bc.character_id WHERE bc.book_id = b.id AND c.name LIKE '%{$escaped}%' ESCAPE '!')", null, false)
                    ->groupEnd();
            }

            return $builder;
        };

        $total = (int) ($applyFilters($db->table('books b'))
            ->select('COUNT(DISTINCT b.id) AS total', false)
            ->get()
            ->getRow('total') ?? 0);
        $totalPages = max(1, (int) ceil($total / $limit));
        $page = min($page, $totalPages);

        $rows = $applyFilters($db->table('books b')
            ->select('b.id, b.type, b.title, b.circle, b.circle_kana, b.author, b.cover_url, b.status, b.note')
            ->select('l.name AS location_name, lp.name AS parent_location_name')
            ->select('(SELECT COUNT(*) FROM book_sources bs WHERE bs.book_id = b.id) AS source_count', false)
            ->select('(SELECT MIN(price) FROM book_sources bs WHERE bs.book_id = b.id AND bs.price IS NOT NULL) AS min_price', false)
            ->select("(SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') FROM book_tags bt JOIN tags t ON t.id = bt.tag_id WHERE bt.book_id = b.id) AS tag_names", false)
            ->select("(SELECT GROUP_CONCAT(w.name ORDER BY w.name SEPARATOR ', ') FROM book_works bw JOIN works w ON w.id = bw.work_id WHERE bw.book_id = b.id) AS work_names", false)
            ->select("(SELECT GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') FROM book_characters bc JOIN characters c ON c.id = bc.character_id WHERE bc.book_id = b.id) AS character_names", false)
            ->join('locations l', 'l.id = b.location_id', 'left')
            ->join('locations lp', 'lp.id = l.parent_id', 'left'))
            ->groupBy('b.id')
            ->orderBy('b.title', 'ASC')
            ->orderBy('b.id', 'DESC')
            ->limit($limit, ($page - 1) * $limit)
            ->get()
            ->getResultArray();

        return $this->response->setJSON([
            'books' => array_map([$this, 'formatBook'], $rows),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
            'filters' => [
                'q' => $q,
                'type' => $type,
                'status' => $status,
                'tag_id' => $tagId,
            ],
            'options' => [
                'types' => $this->formatOptions(self::TYPE_OPTIONS),
                'statuses' => $this->formatOptions(self::STATUS_OPTIONS),
                'tags' => $this->tagOptions(),
            ],
        ]);
    }

    private function formatBook(array $row): array
    {
        $location = trim(($row['parent_location_name'] ? $row['parent_location_name'] . ' / ' : '') . ($row['location_name'] ?? ''));
        $cover = trim((string) ($row['cover_url'] ?? ''));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'type' => (string) ($row['type'] ?? ''),
            'type_label' => self::TYPE_OPTIONS[(string) ($row['type'] ?? '')] ?? (string) ($row['type'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'circle' => (string) ($row['circle'] ?? ''),
            'circle_kana' => (string) ($row['circle_kana'] ?? ''),
            'author' => (string) ($row['author'] ?? ''),
            'cover_url' => $this->absoluteUrl($cover === '' ? cover_placeholder_url() : cover_display_url($cover)),
            'has_cover' => $cover !== '',
            'status' => (string) ($row['status'] ?? ''),
            'status_label' => self::STATUS_OPTIONS[(string) ($row['status'] ?? '')] ?? (string) ($row['status'] ?? ''),
            'location' => $location,
            'source_count' => (int) ($row['source_count'] ?? 0),
            'min_price' => isset($row['min_price']) ? (int) $row['min_price'] : null,
            'tags' => $this->splitNames($row['tag_names'] ?? ''),
            'works' => $this->splitNames($row['work_names'] ?? ''),
            'characters' => $this->splitNames($row['character_names'] ?? ''),
            'note' => (string) ($row['note'] ?? ''),
        ];
    }

    private function splitNames(?string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    }

    private function tagOptions(): array
    {
        return array_map(
            static fn (array $row): array => ['value' => (string) $row['id'], 'label' => (string) $row['name']],
            db_connect()->table('tags')->select('id, name')->orderBy('name', 'ASC')->get()->getResultArray()
        );
    }

    private function formatOptions(array $options): array
    {
        $rows = [];
        foreach ($options as $value => $label) {
            $rows[] = ['value' => $value, 'label' => $label];
        }

        return $rows;
    }

    private function tagExists(int $tagId): bool
    {
        return db_connect()->table('tags')->where('id', $tagId)->countAllResults() > 0;
    }

    private function appApiAuthFailure(): ?ResponseInterface
    {
        $expectedHash = trim((string) env('app.apiKeyHash', ''));
        if ($expectedHash === '') {
            $expectedHash = trim((string) env('admin.passwordHash', ''));
        }

        if ($expectedHash === '') {
            return $this->response->setStatusCode(503)->setJSON(['message' => 'app api is not configured']);
        }

        $passcode = trim($this->request->getHeaderLine('X-App-Passcode'));
        $authorization = trim($this->request->getHeaderLine('Authorization'));
        if ($passcode === '' && str_starts_with($authorization, 'Bearer ')) {
            $passcode = trim(substr($authorization, 7));
        }

        if ($passcode === '' || ! password_verify($passcode, $expectedHash)) {
            return $this->response->setStatusCode(401)->setJSON(['message' => 'unauthorized']);
        }

        return null;
    }

    private function absoluteUrl(string $url): string
    {
        if ($url === '' || str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        $uri = $this->request->getUri();
        $base = $uri->getScheme() . '://' . $uri->getHost();
        if ($uri->getPort() !== null) {
            $base .= ':' . $uri->getPort();
        }

        return rtrim($base, '/') . '/' . ltrim($url, '/');
    }
}

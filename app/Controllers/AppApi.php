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

    private const PRIORITY_OPTIONS = [
        'normal' => '普通',
        'high' => '優先',
        'must' => '必看',
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
                'new_book_url' => $this->absoluteUrl('/books/new'),
            ],
        ]);
    }

    public function toggleCircleTracking(int $id): ResponseInterface
    {
        if ($failure = $this->appApiAuthFailure()) {
            return $failure;
        }

        $db = db_connect();
        $circle = $db->table('circles')
            ->select('id, is_tracked')
            ->where('id', $id)
            ->get()
            ->getRowArray();

        if (! $circle) {
            return $this->response->setStatusCode(404)->setJSON(['message' => 'circle not found']);
        }

        $tracked = (int) ($circle['is_tracked'] ?? 0) === 1 ? 0 : 1;
        $db->table('circles')
            ->where('id', $id)
            ->update(['is_tracked' => $tracked]);

        return $this->response->setJSON([
            'id' => $id,
            'is_tracked' => $tracked === 1,
        ]);
    }

    public function circles(): ResponseInterface
    {
        if ($failure = $this->appApiAuthFailure()) {
            return $failure;
        }

        $db = db_connect();
        $q = trim((string) $this->request->getGet('q'));
        $tracked = trim((string) $this->request->getGet('tracked'));
        $priority = trim((string) $this->request->getGet('priority'));
        $page = max(1, (int) $this->request->getGet('page'));
        $limit = (int) $this->request->getGet('limit');
        if ($limit <= 0) {
            $limit = self::DEFAULT_LIMIT;
        }
        $limit = min($limit, self::MAX_LIMIT);

        if ($tracked !== '1' && $tracked !== '0') {
            $tracked = '';
        }
        if ($priority !== '' && ! array_key_exists($priority, self::PRIORITY_OPTIONS)) {
            $priority = '';
        }

        $applyFilters = static function ($builder) use ($q, $tracked, $priority) {
            if ($q !== '') {
                $builder->groupStart()
                    ->like('c.name', $q)
                    ->orLike('c.name_kana', $q)
                    ->orLike('c.note', $q)
                    ->groupEnd();
            }

            if ($tracked !== '') {
                $builder->where('c.is_tracked', (int) $tracked);
            }

            if ($priority !== '') {
                $builder->where('c.priority', $priority);
            }

            return $builder;
        };

        $total = (int) ($applyFilters($db->table('circles c'))
            ->select('COUNT(*) AS total', false)
            ->get()
            ->getRow('total') ?? 0);
        $totalPages = max(1, (int) ceil($total / $limit));
        $page = min($page, $totalPages);

        $rows = $applyFilters($db->table('circles c')
            ->select('c.id, c.name, c.name_kana, c.is_tracked, c.priority, c.pixiv_url, c.twitter_url, c.website_url, c.booth_url, c.melonbooks_url, c.toranoana_url, c.webcatalog_circle_id, c.webcatalog_cut_url, c.note')
            ->select("COUNT(CASE WHEN b.type <> 'comic' THEN b.id END) AS book_count", false)
            ->select("SUM(CASE WHEN b.type <> 'comic' AND b.status = 'wishlist' THEN 1 ELSE 0 END) AS wishlist_count", false)
            ->join('books b', 'b.circle_id = c.id', 'left'))
            ->groupBy('c.id')
            ->orderBy('c.is_tracked', 'DESC')
            ->orderBy("FIELD(c.priority, 'must', 'high', 'normal')", '', false)
            ->orderBy('c.name', 'ASC')
            ->limit($limit, ($page - 1) * $limit)
            ->get()
            ->getResultArray();

        return $this->response->setJSON([
            'circles' => array_map([$this, 'formatCircle'], $rows),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
            'filters' => [
                'q' => $q,
                'tracked' => $tracked,
                'priority' => $priority,
            ],
            'options' => [
                'priorities' => $this->formatOptions(self::PRIORITY_OPTIONS),
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
            'edit_url' => $this->absoluteUrl('/books/' . (int) ($row['id'] ?? 0) . '/edit'),
        ];
    }

    private function formatCircle(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'name_kana' => (string) ($row['name_kana'] ?? ''),
            'is_tracked' => (int) ($row['is_tracked'] ?? 0) === 1,
            'priority' => (string) ($row['priority'] ?? ''),
            'priority_label' => self::PRIORITY_OPTIONS[(string) ($row['priority'] ?? '')] ?? '未設定',
            'cut_url' => $this->absoluteUrl((string) ($row['webcatalog_cut_url'] ?? '')),
            'circlems_id' => (string) ($row['webcatalog_circle_id'] ?? ''),
            'book_count' => (int) ($row['book_count'] ?? 0),
            'wishlist_count' => (int) ($row['wishlist_count'] ?? 0),
            'note' => (string) ($row['note'] ?? ''),
            'books' => $this->circleBooks((int) ($row['id'] ?? 0)),
            'links' => array_values(array_filter([
                $this->linkRow('Web', $row['website_url'] ?? ''),
                $this->linkRow('Pixiv', $row['pixiv_url'] ?? ''),
                $this->linkRow('X', $row['twitter_url'] ?? ''),
                $this->linkRow('BOOTH', $row['booth_url'] ?? ''),
                $this->linkRow('Melon', $row['melonbooks_url'] ?? ''),
                $this->linkRow('Tora', $row['toranoana_url'] ?? ''),
            ])),
        ];
    }

    private function circleBooks(int $circleId): array
    {
        if ($circleId <= 0) {
            return [];
        }

        $rows = db_connect()->table('books')
            ->select('id, title, cover_url, status, type')
            ->where('circle_id', $circleId)
            ->where('type <>', 'comic')
            ->orderBy("FIELD(status, 'owned', 'wishlist', 'ordered', 'blacklisted')", '', false)
            ->orderBy('title', 'ASC')
            ->limit(6)
            ->get()
            ->getResultArray();

        return array_map(function (array $row): array {
            $cover = trim((string) ($row['cover_url'] ?? ''));

            return [
                'id' => (int) ($row['id'] ?? 0),
                'title' => (string) ($row['title'] ?? ''),
                'cover_url' => $this->absoluteUrl($cover === '' ? '' : cover_display_url($cover)),
                'status' => (string) ($row['status'] ?? ''),
                'status_label' => self::STATUS_OPTIONS[(string) ($row['status'] ?? '')] ?? (string) ($row['status'] ?? ''),
                'edit_url' => $this->absoluteUrl('/books/' . (int) ($row['id'] ?? 0) . '/edit'),
            ];
        }, $rows);
    }

    private function linkRow(string $label, ?string $url): ?array
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        return [
            'label' => $label,
            'url' => $url,
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

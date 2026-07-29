<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;

class BookTagBatch extends BaseController
{
    private const PAGE_SIZE = 60;

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

    public function index(): string
    {
        $db = db_connect();
        $q = trim((string) $this->request->getGet('q'));
        $type = trim((string) $this->request->getGet('type'));
        $status = trim((string) $this->request->getGet('status'));
        $filterTagId = (int) $this->request->getGet('filter_tag_id');
        $page = max(1, (int) $this->request->getGet('page'));

        if ($type !== '' && ! array_key_exists($type, self::TYPE_OPTIONS)) {
            $type = '';
        }
        if ($status !== '' && ! array_key_exists($status, self::STATUS_OPTIONS)) {
            $status = '';
        }
        if ($filterTagId > 0 && ! $this->tagExists($filterTagId)) {
            $filterTagId = 0;
        }

        $applyFilters = static function ($builder) use ($db, $q, $type, $status, $filterTagId) {
            if ($type !== '') {
                $builder->where('b.type', $type);
            }

            if ($status !== '') {
                $builder->where('b.status', $status);
            }

            if ($filterTagId > 0) {
                $builder->where("EXISTS (SELECT 1 FROM book_tags filter_bt WHERE filter_bt.book_id = b.id AND filter_bt.tag_id = {$filterTagId})", null, false);
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

        $totalBooks = (int) ($applyFilters($db->table('books b'))
            ->select('COUNT(DISTINCT b.id) AS total', false)
            ->get()
            ->getRow('total') ?? 0);
        $totalPages = max(1, (int) ceil($totalBooks / self::PAGE_SIZE));
        $page = min($page, $totalPages);

        $books = $applyFilters($db->table('books b')
            ->select('b.id, b.type, b.title, b.circle, b.author, b.cover_url, b.status')
            ->select("(SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') FROM book_tags bt JOIN tags t ON t.id = bt.tag_id WHERE bt.book_id = b.id) AS tag_names", false))
            ->groupBy('b.id')
            ->orderBy('b.title', 'ASC')
            ->orderBy('b.id', 'DESC')
            ->limit(self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE)
            ->get()
            ->getResultArray();

        return view('tools/book_tags', [
            'books' => $books,
            'q' => $q,
            'type' => $type,
            'status' => $status,
            'filterTagId' => $filterTagId,
            'page' => $page,
            'perPage' => self::PAGE_SIZE,
            'totalBooks' => $totalBooks,
            'totalPages' => $totalPages,
            'typeOptions' => self::TYPE_OPTIONS,
            'statusOptions' => self::STATUS_OPTIONS,
            'tagOptions' => $this->tagOptions(),
        ]);
    }

    public function apply(): RedirectResponse
    {
        $bookIds = array_values(array_unique(array_filter(array_map(
            static fn ($id): int => (int) $id,
            $this->request->getPost('book_ids') ?? []
        ))));
        $tagId = (int) $this->request->getPost('tag_id');

        if ($bookIds === [] || $tagId <= 0 || ! $this->tagExists($tagId)) {
            return redirect()->to($this->safeReturnTo())->with('error', '請選擇書本與 tag。');
        }

        $db = db_connect();
        $existingRows = $db->table('book_tags')
            ->select('book_id')
            ->where('tag_id', $tagId)
            ->whereIn('book_id', $bookIds)
            ->get()
            ->getResultArray();
        $existingBookIds = array_flip(array_map(static fn (array $row): int => (int) $row['book_id'], $existingRows));

        $inserted = 0;
        foreach ($bookIds as $bookId) {
            if (isset($existingBookIds[$bookId])) {
                continue;
            }

            $db->table('book_tags')->insert([
                'book_id' => $bookId,
                'tag_id' => $tagId,
            ]);
            $inserted++;
        }

        return redirect()->to($this->safeReturnTo())->with('message', '已新增 tag 到 ' . $inserted . ' 本書。');
    }

    private function tagOptions(): array
    {
        return db_connect()->table('tags')
            ->select('id, name')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function tagExists(int $tagId): bool
    {
        return db_connect()->table('tags')
            ->where('id', $tagId)
            ->countAllResults() > 0;
    }

    private function safeReturnTo(): string
    {
        $returnTo = (string) $this->request->getPost('return_to');
        if ($returnTo === '' || str_starts_with($returnTo, '//') || ! str_starts_with($returnTo, '/tools/book-tags')) {
            return '/tools/book-tags';
        }

        return $returnTo;
    }
}

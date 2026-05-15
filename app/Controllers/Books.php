<?php

namespace App\Controllers;

use App\Models\BookModel;
use App\Models\BookSourceModel;
use App\Models\LocationModel;
use App\Models\ShopModel;
use CodeIgniter\HTTP\RedirectResponse;

class Books extends BaseController
{
    private const STATUS_OPTIONS = [
        'owned' => '已擁有',
        'blacklisted' => '黑名單',
        'ordered' => '已訂購',
        'wishlist' => '願望清單',
    ];

    private const TYPE_OPTIONS = [
        'doujin' => '同人誌',
        'comic' => '漫畫/單行本',
        'other' => '其他',
    ];

    public function index(): string
    {
        $db = db_connect();
        $q = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));
        $shopId = (int) $this->request->getGet('shop_id');

        $builder = $db->table('books b')
            ->select('b.*')
            ->select('l.name AS location_name, lp.name AS parent_location_name')
            ->select('(SELECT COUNT(*) FROM book_sources bs WHERE bs.book_id = b.id) AS source_count', false)
            ->select('(SELECT MIN(price) FROM book_sources bs WHERE bs.book_id = b.id AND bs.price IS NOT NULL) AS min_price', false)
            ->select("(SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') FROM book_tags bt JOIN tags t ON t.id = bt.tag_id WHERE bt.book_id = b.id) AS tag_names", false)
            ->select("(SELECT GROUP_CONCAT(w.name ORDER BY w.name SEPARATOR ', ') FROM book_works bw JOIN works w ON w.id = bw.work_id WHERE bw.book_id = b.id) AS work_names", false)
            ->select("(SELECT GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') FROM book_characters bc JOIN characters c ON c.id = bc.character_id WHERE bc.book_id = b.id) AS character_names", false)
            ->join('locations l', 'l.id = b.location_id', 'left')
            ->join('locations lp', 'lp.id = l.parent_id', 'left');

        if ($status !== '' && array_key_exists($status, self::STATUS_OPTIONS)) {
            $builder->where('b.status', $status);
        }

        if ($shopId > 0) {
            $builder->join('book_sources shop_filter', 'shop_filter.book_id = b.id', 'inner')
                ->where('shop_filter.shop_id', $shopId);
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

        $books = $builder
            ->groupBy('b.id')
            ->orderBy('b.updated_at', 'DESC')
            ->orderBy('b.id', 'DESC')
            ->limit(300)
            ->get()
            ->getResultArray();

        return view('books/index', [
            'books' => $books,
            'q' => $q,
            'status' => $status,
            'shopId' => $shopId,
            'shops' => (new ShopModel())->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->findAll(),
            'statusOptions' => self::STATUS_OPTIONS,
        ]);
    }

    public function new(): string
    {
        return $this->renderForm([
            'type' => 'doujin',
            'status' => 'wishlist',
        ]);
    }

    public function create(): RedirectResponse|string
    {
        return $this->saveBook();
    }

    public function edit(int $id): string|RedirectResponse
    {
        $book = (new BookModel())->find($id);

        if (! $book) {
            return redirect()->to('/books')->with('error', '找不到這本書。');
        }

        return $this->renderForm($book);
    }

    public function update(int $id): RedirectResponse|string
    {
        return $this->saveBook($id);
    }

    public function delete(int $id): RedirectResponse
    {
        $db = db_connect();
        $db->transStart();
        foreach (['book_sources', 'book_tags', 'book_works', 'book_characters'] as $table) {
            $db->table($table)->where('book_id', $id)->delete();
        }
        (new BookModel())->delete($id);
        $db->transComplete();

        return redirect()->to('/books')->with('message', '已刪除書本。');
    }

    private function saveBook(?int $id = null): RedirectResponse|string
    {
        $rules = [
            'title' => 'required|max_length[255]',
            'type' => 'required|max_length[20]',
            'status' => 'required|in_list[owned,blacklisted,ordered,wishlist]',
        ];

        if (! $this->validate($rules)) {
            $book = $this->request->getPost();
            $book['id'] = $id;
            return $this->renderForm($book, $this->validator->getErrors());
        }

        $bookModel = new BookModel();
        $data = [
            'type' => (string) $this->request->getPost('type'),
            'title' => trim((string) $this->request->getPost('title')),
            'circle_kana' => $this->nullablePost('circle_kana'),
            'circle' => $this->nullablePost('circle'),
            'author' => $this->nullablePost('author'),
            'event' => $this->nullablePost('event'),
            'cover_url' => $this->nullablePost('cover_url'),
            'status' => (string) $this->request->getPost('status'),
            'location_id' => $this->nullableIntPost('location_id'),
            'note' => $this->nullablePost('note'),
        ];

        $db = db_connect();
        $db->transStart();

        if ($id === null) {
            $bookModel->insert($data);
            $id = (int) $bookModel->getInsertID();
        } else {
            $bookModel->update($id, $data);
        }

        $this->syncNames($id, 'tags_text', 'tags', 'book_tags', 'tag_id');
        $this->syncNames($id, 'works_text', 'works', 'book_works', 'work_id');
        $this->syncCharacters($id);
        $this->syncSources($id);

        $db->transComplete();

        return redirect()->to('/books/' . $id . '/edit')->with('message', '已儲存。');
    }

    private function renderForm(array $book, array $errors = []): string
    {
        $id = isset($book['id']) ? (int) $book['id'] : 0;

        return view('books/form', [
            'book' => $book,
            'errors' => $errors,
            'statusOptions' => self::STATUS_OPTIONS,
            'typeOptions' => self::TYPE_OPTIONS,
            'shops' => (new ShopModel())->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->findAll(),
            'locations' => $this->locationOptions(),
            'sources' => $id > 0 ? $this->bookSources($id) : [],
            'tagsText' => $id > 0 ? $this->relationText($id, 'book_tags', 'tags', 'tag_id') : (string) ($book['tags_text'] ?? ''),
            'worksText' => $id > 0 ? $this->relationText($id, 'book_works', 'works', 'work_id') : (string) ($book['works_text'] ?? ''),
            'charactersText' => $id > 0 ? $this->relationText($id, 'book_characters', 'characters', 'character_id') : (string) ($book['characters_text'] ?? ''),
        ]);
    }

    private function nullablePost(string $key): ?string
    {
        $value = trim((string) $this->request->getPost($key));
        return $value === '' ? null : $value;
    }

    private function nullableIntPost(string $key): ?int
    {
        $value = (int) $this->request->getPost($key);
        return $value > 0 ? $value : null;
    }

    private function parseNames(string $value): array
    {
        $parts = preg_split('/[,，\r\n]+/u', $value) ?: [];
        $names = [];
        foreach ($parts as $part) {
            $name = trim($part);
            if ($name !== '') {
                $names[$name] = $name;
            }
        }
        return array_values($names);
    }

    private function syncNames(int $bookId, string $postKey, string $nameTable, string $pivotTable, string $targetColumn): void
    {
        $db = db_connect();
        $db->table($pivotTable)->where('book_id', $bookId)->delete();

        foreach ($this->parseNames((string) $this->request->getPost($postKey)) as $name) {
            $row = $db->table($nameTable)->where('name', $name)->get()->getRowArray();
            if (! $row) {
                $db->table($nameTable)->insert([
                    'name' => $name,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $targetId = (int) $db->insertID();
            } else {
                $targetId = (int) $row['id'];
            }

            $db->table($pivotTable)->insert([
                'book_id' => $bookId,
                $targetColumn => $targetId,
            ]);
        }
    }

    private function syncCharacters(int $bookId): void
    {
        $db = db_connect();
        $db->table('book_characters')->where('book_id', $bookId)->delete();

        foreach ($this->parseNames((string) $this->request->getPost('characters_text')) as $name) {
            $row = $db->table('characters')->where('work_id', null)->where('name', $name)->get()->getRowArray();
            if (! $row) {
                $db->table('characters')->insert([
                    'work_id' => null,
                    'name' => $name,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $characterId = (int) $db->insertID();
            } else {
                $characterId = (int) $row['id'];
            }

            $db->table('book_characters')->insert([
                'book_id' => $bookId,
                'character_id' => $characterId,
            ]);
        }
    }

    private function syncSources(int $bookId): void
    {
        $sourceModel = new BookSourceModel();
        $ids = $this->request->getPost('source_id') ?? [];
        $shopIds = $this->request->getPost('source_shop_id') ?? [];
        $prices = $this->request->getPost('source_price') ?? [];
        $urls = $this->request->getPost('source_item_url') ?? [];
        $notes = $this->request->getPost('source_note') ?? [];
        $deleted = array_flip($this->request->getPost('source_delete') ?? []);

        foreach ($shopIds as $index => $shopId) {
            $sourceId = (int) ($ids[$index] ?? 0);
            $shopId = (int) $shopId;

            if ($sourceId > 0 && isset($deleted[(string) $sourceId])) {
                $sourceModel->delete($sourceId);
                continue;
            }

            if ($shopId <= 0) {
                continue;
            }

            $data = [
                'book_id' => $bookId,
                'shop_id' => $shopId,
                'price' => $this->nullableArrayInt($prices[$index] ?? null),
                'item_url' => $this->nullableArrayString($urls[$index] ?? null),
                'note' => $this->nullableArrayString($notes[$index] ?? null),
                'availability_status' => 'available',
                'checked_at' => date('Y-m-d H:i:s'),
            ];

            if ($sourceId > 0) {
                $sourceModel->update($sourceId, $data);
            } else {
                $sourceModel->insert($data);
            }
        }
    }

    private function nullableArrayInt(mixed $value): ?int
    {
        $value = trim((string) $value);
        return $value === '' ? null : (int) $value;
    }

    private function nullableArrayString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function relationText(int $bookId, string $pivotTable, string $nameTable, string $targetColumn): string
    {
        $rows = db_connect()->table($pivotTable . ' p')
            ->select('n.name')
            ->join($nameTable . ' n', 'n.id = p.' . $targetColumn)
            ->where('p.book_id', $bookId)
            ->orderBy('n.name', 'ASC')
            ->get()
            ->getResultArray();

        return implode(', ', array_column($rows, 'name'));
    }

    private function bookSources(int $bookId): array
    {
        return db_connect()->table('book_sources bs')
            ->select('bs.*, s.name AS shop_name')
            ->join('shops s', 's.id = bs.shop_id', 'left')
            ->where('bs.book_id', $bookId)
            ->orderBy('s.sort_order', 'ASC')
            ->orderBy('s.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function locationOptions(): array
    {
        $rows = (new LocationModel())->orderBy('parent_id', 'ASC')->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->findAll();
        $parents = [];
        $children = [];

        foreach ($rows as $row) {
            if (empty($row['parent_id'])) {
                $parents[(int) $row['id']] = $row;
            } else {
                $children[(int) $row['parent_id']][] = $row;
            }
        }

        $options = [];
        foreach ($parents as $id => $parent) {
            if (empty($children[$id])) {
                $options[] = ['id' => $id, 'label' => $parent['name']];
                continue;
            }

            foreach ($children[$id] as $child) {
                $options[] = ['id' => (int) $child['id'], 'label' => $parent['name'] . ' / ' . $child['name']];
            }
        }

        return $options;
    }
}

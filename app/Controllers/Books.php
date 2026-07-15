<?php

namespace App\Controllers;

use App\Models\BookModel;
use App\Models\BookSourceModel;
use App\Models\LocationModel;
use App\Models\ShopModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

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
        'comic' => '単行本',
        'other' => '其他',
    ];

    private const COVER_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    private const TAXONOMY_OPTIONS = [
        'tags' => ['table' => 'tags', 'label' => '一般 tag'],
        'works' => ['table' => 'works', 'label' => '原作'],
        'characters' => ['table' => 'characters', 'label' => '角色', 'where' => ['work_id' => null], 'insert' => ['work_id' => null]],
    ];

    private const QUICK_TAG_IDS = [8, 9, 11, 14];
    private const QUICK_WORK_IDS = [1];

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
            ->get()
            ->getResultArray();

        return view('books/index', [
            'books' => $books,
            'q' => $q,
            'status' => $status,
            'shopId' => $shopId,
            'shops' => (new ShopModel())->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->findAll(),
            'statusOptions' => self::STATUS_OPTIONS,
            'typeOptions' => self::TYPE_OPTIONS,
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

    public function taxonomySearch(string $group): ResponseInterface
    {
        $config = $this->taxonomyConfig($group);
        if ($config === null) {
            return $this->response->setStatusCode(404)->setJSON(['message' => '未知的分類類型。']);
        }

        $q = trim((string) $this->request->getGet('q'));
        $builder = db_connect()->table($config['table'])->select('id, name');
        foreach (($config['where'] ?? []) as $column => $value) {
            $builder->where($column, $value);
        }
        if ($q !== '') {
            $builder->like('name', $q);
        }

        return $this->response->setJSON([
            'items' => $builder->orderBy('name', 'ASC')->limit(12)->get()->getResultArray(),
        ]);
    }

    public function circleSearch(): ResponseInterface
    {
        $q = trim((string) $this->request->getGet('q'));
        $builder = db_connect()->table('circles')
            ->select('id, name, name_kana')
            ->select("(SELECT b.author FROM books b WHERE b.circle_id = circles.id AND b.author IS NOT NULL AND b.author <> '' ORDER BY b.updated_at DESC, b.id DESC LIMIT 1) AS author", false);

        if ($q !== '') {
            $builder->groupStart()
                ->like('name', $q)
                ->orLike('name_kana', $q)
                ->groupEnd();
        }

        return $this->response->setJSON([
            'items' => $builder->orderBy('name', 'ASC')->limit(12)->get()->getResultArray(),
        ]);
    }

    public function taxonomyStore(string $group): ResponseInterface
    {
        $config = $this->taxonomyConfig($group);
        if ($config === null) {
            return $this->response->setStatusCode(404)->setJSON(['message' => '未知的分類類型。']);
        }

        $name = $this->normalizeTaxonomyName((string) $this->request->getPost('name'));
        if ($name === '') {
            return $this->response->setStatusCode(422)->setJSON(['message' => '請輸入分類名稱。']);
        }

        $db = db_connect();
        $builder = $db->table($config['table']);
        foreach (($config['where'] ?? []) as $column => $value) {
            $builder->where($column, $value);
        }
        $row = $builder->where('name', $name)->get()->getRowArray();
        $created = false;

        if (! $row) {
            $data = array_merge($config['insert'] ?? [], [
                'name' => $name,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $db->table($config['table'])->insert($data);
            $row = ['id' => (int) $db->insertID(), 'name' => $name];
            $created = true;
        }

        return $this->response->setJSON([
            'item' => ['id' => (int) $row['id'], 'name' => $row['name']],
            'created' => $created,
            'csrf' => csrf_hash(),
        ]);
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
        $type = (string) $this->request->getPost('type');
        [$circleId, $circleName] = $type === 'comic'
            ? [null, null]
            : $this->resolveCircle($this->nullablePost('circle'), $this->nullablePost('circle_kana'));

        $data = [
            'type' => $type,
            'title' => trim((string) $this->request->getPost('title')),
            'circle_kana' => $type === 'comic' ? null : $this->nullablePost('circle_kana'),
            'circle' => $circleName,
            'circle_id' => $circleId,
            'author' => $this->nullablePost('author'),
            'event' => $this->nullablePost('event'),
            'cover_url' => $this->nullablePost('cover_url'),
            'status' => (string) $this->request->getPost('status'),
            'location_id' => $this->nullableIntPost('location_id'),
            'note' => $this->nullablePost('note'),
        ];

        try {
            $uploadedCoverUrl = $this->storeCoverUpload($this->request->getFile('cover_file'));
            if ($uploadedCoverUrl !== null) {
                $data['cover_url'] = $uploadedCoverUrl;
            }
        } catch (RuntimeException $exception) {
            $book = $this->request->getPost();
            $book['id'] = $id;
            return $this->renderForm($book, ['cover_file' => $exception->getMessage()]);
        }

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

        return redirect()->to($this->safeReturnTo((string) $this->request->getPost('return_to')))->with('message', '已儲存。');
    }

    private function safeReturnTo(string $path): string
    {
        if ($path === '' || str_starts_with($path, '//') || ! str_starts_with($path, '/books')) {
            return '/books';
        }

        if (preg_match('#^/books/\d+/edit#', $path) === 1) {
            return '/books';
        }

        return $path;
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
            'quickTags' => $this->quickTagOptions(),
            'quickWorks' => $this->quickWorkOptions(),
        ]);
    }

    private function storeCoverUpload(?UploadedFile $file): ?string
    {
        if (! $file || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (! $file->isValid()) {
            throw new RuntimeException('封面上傳失敗，請重新選擇檔案。');
        }

        if ($file->getSizeByUnit('mb') > 8) {
            throw new RuntimeException('封面圖片不可超過 8MB。');
        }

        if (! in_array($file->getMimeType(), self::COVER_MIME_TYPES, true)) {
            throw new RuntimeException('封面只支援 JPG、PNG、WEBP 或 GIF。');
        }

        $extension = $file->guessExtension() ?: $file->getExtension() ?: 'jpg';
        $fileName = date('YmdHis') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $uploadPath = FCPATH . 'uploads/covers';

        if (! is_dir($uploadPath) && ! mkdir($uploadPath, 0775, true) && ! is_dir($uploadPath)) {
            throw new RuntimeException('無法建立封面上傳目錄。');
        }

        $file->move($uploadPath, $fileName);

        return '/uploads/covers/' . $fileName;
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

    private function resolveCircle(?string $name, ?string $kana): array
    {
        if ($name === null) {
            return [null, null];
        }

        $db = db_connect();
        $row = $db->table('circles')
            ->select('id, name, name_kana')
            ->where('name', $name)
            ->get(1)
            ->getRowArray();

        if (! $row) {
            $now = date('Y-m-d H:i:s');
            $db->table('circles')->ignore(true)->insert([
                'name' => $name,
                'name_kana' => $kana,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $row = $db->table('circles')
                ->select('id, name, name_kana')
                ->where('name', $name)
                ->get(1)
                ->getRowArray();
        }

        if (! $row) {
            return [null, $name];
        }

        return [(int) $row['id'], (string) $row['name']];
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

    private function quickTagOptions(): array
    {
        return $this->quickTaxonomyOptions('tags', self::QUICK_TAG_IDS);
    }

    private function quickWorkOptions(): array
    {
        return $this->quickTaxonomyOptions('works', self::QUICK_WORK_IDS);
    }

    private function quickTaxonomyOptions(string $table, array $ids): array
    {
        $rows = db_connect()->table($table)
            ->select('id, name')
            ->whereIn('id', $ids)
            ->get()
            ->getResultArray();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['id']] = $row;
        }

        $options = [];
        foreach ($ids as $id) {
            if (isset($indexed[$id])) {
                $options[] = $indexed[$id];
            }
        }

        return $options;
    }

    private function taxonomyConfig(string $group): ?array
    {
        return self::TAXONOMY_OPTIONS[$group] ?? null;
    }

    private function normalizeTaxonomyName(string $value): string
    {
        return preg_replace('/\s+/u', ' ', trim($value)) ?? '';
    }
}

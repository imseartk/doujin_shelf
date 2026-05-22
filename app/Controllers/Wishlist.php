<?php

namespace App\Controllers;

use App\Models\BookSourceModel;
use App\Models\ShopModel;
use CodeIgniter\HTTP\RedirectResponse;

class Wishlist extends BaseController
{
    public function index(): string
    {
        $db = db_connect();
        $q = trim((string) $this->request->getGet('q'));
        $shopId = (int) $this->request->getGet('shop_id');

        $builder = $db->table('books b')
            ->select('b.*')
            ->select('(SELECT COUNT(*) FROM book_sources bs WHERE bs.book_id = b.id) AS source_count', false)
            ->select('(SELECT MIN(price) FROM book_sources bs WHERE bs.book_id = b.id AND bs.price IS NOT NULL) AS min_price', false)
            ->select("(SELECT GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') FROM book_tags bt JOIN tags t ON t.id = bt.tag_id WHERE bt.book_id = b.id) AS tag_names", false)
            ->where('b.status', 'wishlist');

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

        return view('wishlist/index', [
            'books' => $books,
            'q' => $q,
            'shopId' => $shopId,
            'shops' => (new ShopModel())->orderBy('name', 'ASC')->findAll(),
            'sourcesByBook' => $this->sourcesByBook(array_column($books, 'id')),
        ]);
    }

    public function createSource(int $bookId): RedirectResponse
    {
        if (! $this->wishlistBookExists($bookId)) {
            return $this->redirectBack()->with('error', '找不到願望清單書本。');
        }

        $shopId = (int) $this->request->getPost('shop_id');
        if ($shopId <= 0) {
            return $this->redirectBack()->with('error', '請選擇來源店鋪。');
        }

        (new BookSourceModel())->insert($this->sourceData($bookId, $shopId));

        return $this->redirectBack()->with('message', '已新增來源。');
    }

    public function updateSource(int $id): RedirectResponse
    {
        $sourceModel = new BookSourceModel();
        $source = $sourceModel->find($id);
        if (! $source || ! $this->wishlistBookExists((int) $source['book_id'])) {
            return $this->redirectBack()->with('error', '找不到這筆來源。');
        }

        $shopId = (int) $this->request->getPost('shop_id');
        if ($shopId <= 0) {
            return $this->redirectBack()->with('error', '請選擇來源店鋪。');
        }

        $sourceModel->update($id, $this->sourceData((int) $source['book_id'], $shopId));

        return $this->redirectBack()->with('message', '已儲存來源。');
    }

    public function deleteSource(int $id): RedirectResponse
    {
        $sourceModel = new BookSourceModel();
        $source = $sourceModel->find($id);
        if (! $source || ! $this->wishlistBookExists((int) $source['book_id'])) {
            return $this->redirectBack()->with('error', '找不到這筆來源。');
        }

        $sourceModel->delete($id);

        return $this->redirectBack()->with('message', '已刪除來源。');
    }

    private function sourcesByBook(array $bookIds): array
    {
        if ($bookIds === []) {
            return [];
        }

        $rows = db_connect()->table('book_sources bs')
            ->select('bs.*, s.name AS shop_name')
            ->join('shops s', 's.id = bs.shop_id', 'left')
            ->whereIn('bs.book_id', array_map('intval', $bookIds))
            ->orderBy('s.name', 'ASC')
            ->orderBy('bs.price', 'ASC')
            ->get()
            ->getResultArray();

        $sourcesByBook = [];
        foreach ($rows as $row) {
            $sourcesByBook[(int) $row['book_id']][] = $row;
        }

        return $sourcesByBook;
    }

    private function wishlistBookExists(int $bookId): bool
    {
        return db_connect()->table('books')
            ->where('id', $bookId)
            ->where('status', 'wishlist')
            ->countAllResults() > 0;
    }

    private function sourceData(int $bookId, int $shopId): array
    {
        return [
            'book_id' => $bookId,
            'shop_id' => $shopId,
            'price' => $this->nullableIntPost('price'),
            'item_url' => $this->nullablePost('item_url'),
            'note' => $this->nullablePost('note'),
            'availability_status' => 'available',
            'checked_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function nullablePost(string $key): ?string
    {
        $value = trim((string) $this->request->getPost($key));
        return $value === '' ? null : $value;
    }

    private function nullableIntPost(string $key): ?int
    {
        $value = trim((string) $this->request->getPost($key));
        return $value === '' ? null : (int) $value;
    }

    private function redirectBack(): RedirectResponse
    {
        $returnTo = trim((string) $this->request->getPost('return_to'));
        if ($returnTo === '' || str_starts_with($returnTo, '//') || ! str_starts_with($returnTo, '/wishlist')) {
            $returnTo = '/wishlist';
        }

        return redirect()->to($returnTo);
    }
}

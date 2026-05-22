<?php

namespace App\Controllers;

use App\Models\ShopModel;

class Wishlist extends BaseController
{
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
            ->join('locations lp', 'lp.id = l.parent_id', 'left')
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

        return view('books/index', [
            'books' => $builder
                ->groupBy('b.id')
                ->orderBy('b.updated_at', 'DESC')
                ->orderBy('b.id', 'DESC')
                ->get()
                ->getResultArray(),
            'q' => $q,
            'status' => 'wishlist',
            'shopId' => $shopId,
            'shops' => (new ShopModel())->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->findAll(),
            'statusOptions' => self::STATUS_OPTIONS,
            'listMode' => 'wishlist',
        ]);
    }
}

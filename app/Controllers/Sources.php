<?php

namespace App\Controllers;

class Sources extends BaseController
{
    public function index(): string
    {
        $rows = db_connect()->table('shops s')
            ->select('s.id, s.name, s.website_url')
            ->select('COUNT(bs.id) AS source_count')
            ->select('COUNT(DISTINCT bs.book_id) AS book_count')
            ->select('SUM(COALESCE(bs.price, 0)) AS total_price')
            ->join('book_sources bs', 'bs.shop_id = s.id', 'left')
            ->join('books b', "b.id = bs.book_id AND b.status IN ('wishlist', 'ordered')", 'left')
            ->where('s.is_active', 1)
            ->groupBy('s.id')
            ->orderBy('book_count', 'DESC')
            ->orderBy('s.sort_order', 'ASC')
            ->orderBy('s.name', 'ASC')
            ->get()
            ->getResultArray();

        $shopId = (int) $this->request->getGet('shop_id');
        $books = [];
        if ($shopId > 0) {
            $books = db_connect()->table('book_sources bs')
                ->select('bs.*, b.title, b.circle, b.author, b.status, s.name AS shop_name')
                ->join('books b', 'b.id = bs.book_id')
                ->join('shops s', 's.id = bs.shop_id')
                ->where('bs.shop_id', $shopId)
                ->whereIn('b.status', ['wishlist', 'ordered'])
                ->orderBy('bs.price', 'ASC')
                ->orderBy('b.title', 'ASC')
                ->get()
                ->getResultArray();
        }

        return view('sources/index', [
            'rows' => $rows,
            'books' => $books,
            'shopId' => $shopId,
        ]);
    }
}

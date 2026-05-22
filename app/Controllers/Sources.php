<?php

namespace App\Controllers;

class Sources extends BaseController
{
    public function index(): string
    {
        $sources = db_connect()->table('book_sources bs')
            ->select('bs.id AS source_id, bs.price, bs.item_url')
            ->select('b.id AS book_id, b.title, b.cover_url')
            ->select('s.id AS shop_id, s.name AS shop_name, s.website_url')
            ->join('books b', 'b.id = bs.book_id')
            ->join('shops s', 's.id = bs.shop_id')
            ->where('b.status', 'wishlist')
            ->where('s.is_active', 1)
            ->orderBy('s.name', 'ASC')
            ->orderBy('bs.price', 'ASC')
            ->orderBy('b.title', 'ASC')
            ->get()
            ->getResultArray();

        $shops = [];
        foreach ($sources as $source) {
            $shopId = (int) $source['shop_id'];
            if (! isset($shops[$shopId])) {
                $shops[$shopId] = [
                    'id' => $shopId,
                    'name' => $source['shop_name'],
                    'website_url' => $source['website_url'],
                    'total_price' => 0,
                    'items' => [],
                ];
            }

            $shops[$shopId]['items'][] = $source;
            $shops[$shopId]['total_price'] += (int) ($source['price'] ?? 0);
        }

        return view('sources/index', [
            'shops' => array_values($shops),
        ]);
    }
}

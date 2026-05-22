<?php

namespace App\Controllers;

use App\Models\OrderModel;
use CodeIgniter\HTTP\RedirectResponse;

class Orders extends BaseController
{
    private const ACTIVE_STATUS = 'active';

    public function index(): string
    {
        $orders = db_connect()->table('orders o')
            ->select('o.*, s.name AS shop_name')
            ->select('(SELECT COUNT(*) FROM books b WHERE b.order_id = o.id) AS book_count', false)
            ->join('shops s', 's.id = o.shop_id', 'left')
            ->where('o.status', self::ACTIVE_STATUS)
            ->orderBy('o.created_at', 'DESC')
            ->orderBy('o.id', 'DESC')
            ->get()
            ->getResultArray();

        return view('orders/index', [
            'orders' => $orders,
        ]);
    }

    public function create(): RedirectResponse
    {
        $shopId = (int) $this->request->getPost('shop_id');
        $bookIds = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            $this->request->getPost('book_ids') ?? []
        ))));

        if ($shopId <= 0 || $bookIds === []) {
            return redirect()->to('/sources')->with('error', '請先保留這次要下單的書本。');
        }

        $db = db_connect();
        $books = $db->table('books b')
            ->select('b.id')
            ->join('book_sources bs', 'bs.book_id = b.id')
            ->join('shops s', 's.id = bs.shop_id')
            ->where('bs.shop_id', $shopId)
            ->where('s.is_active', 1)
            ->where('b.status', 'wishlist')
            ->whereIn('b.id', $bookIds)
            ->groupBy('b.id')
            ->get()
            ->getResultArray();

        $eligibleBookIds = array_map(static fn (array $book): int => (int) $book['id'], $books);
        if ($eligibleBookIds === []) {
            return redirect()->to('/sources')->with('error', '這批書目前沒有可建立訂單的願望清單品項。');
        }

        $db->transStart();

        $orderModel = new OrderModel();
        $orderModel->insert([
            'shop_id' => $shopId,
            'status' => self::ACTIVE_STATUS,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $orderId = (int) $orderModel->getInsertID();

        $db->table('books')
            ->whereIn('id', $eligibleBookIds)
            ->update([
                'order_id' => $orderId,
                'status' => 'ordered',
            ]);

        $db->transComplete();

        if (! $db->transStatus()) {
            return redirect()->to('/sources')->with('error', '訂單建立失敗，請再試一次。');
        }

        return redirect()->to('/orders/' . $orderId)->with('message', '已建立訂單，這批書已轉為已訂購。');
    }

    public function show(int $id): string|RedirectResponse
    {
        $order = db_connect()->table('orders o')
            ->select('o.*, s.name AS shop_name, s.website_url')
            ->join('shops s', 's.id = o.shop_id', 'left')
            ->where('o.id', $id)
            ->get()
            ->getRowArray();

        if (! $order) {
            return redirect()->to('/orders')->with('error', '找不到這張訂單。');
        }

        $books = db_connect()->table('books')
            ->select('id, title, circle, author, cover_url, status, location_id')
            ->where('order_id', $id)
            ->orderBy('title', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        return view('orders/show', [
            'order' => $order,
            'books' => $books,
        ]);
    }
}

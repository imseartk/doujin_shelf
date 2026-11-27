<?php

namespace App\Controllers;

use App\Libraries\ShopProductReader;
use CodeIgniter\HTTP\ResponseInterface;

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
            ->where('bs.availability_status !=', 'unavailable')
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

    public function refresh(int $id): ResponseInterface
    {
        $reply = fn (array $data) => $this->response->setHeader('Cache-Control', 'no-store')->setJSON($data + ['csrf' => csrf_hash()]);
        $db = db_connect();
        $source = $db->table('book_sources bs')->select('bs.*, s.name AS source_shop_name')
            ->join('books b', 'b.id = bs.book_id')->join('shops s', 's.id = bs.shop_id')
            ->where('bs.id', $id)->where('b.status', 'wishlist')->where('s.is_active', 1)->get()->getRowArray();
        if (! $source) return $reply(['status' => 'missing']);
        $identity = ShopProductReader::identify((string) $source['item_url']);
        if (! $identity) return $reply(['status' => 'unsupported']);
        $reader = new ShopProductReader();
        $upload = $this->request->getFile('product_html');
        if ($upload !== null) {
            if (! $upload->isValid() || $upload->getSize() > 4 * 1024 * 1024) return $reply(['status' => 'invalid_file']);
            $result = $reader->parse((string) file_get_contents($upload->getTempName()), $source['item_url'], $source['source_shop_name']);
        } else {
            // One request per platform at a time across tabs, with a cooldown after every attempt.
            $lock = fopen(WRITEPATH . 'cache/shop-refresh-' . sha1($identity['site']) . '.lock', 'c+');
            if (! $lock) return $reply(['status' => 'network']);
            if (! flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); return $reply(['status' => 'busy']); }
            try {
                $last = (float) stream_get_contents($lock);
                if (microtime(true) - $last < 3) return $reply(['status' => 'busy']);
                $result = $reader->fetch($source['item_url'], $source['source_shop_name']);
            } catch (\Throwable $error) {
                log_message('error', 'Shop refresh failed for source {id}: {message}', ['id' => $id, 'message' => $error->getMessage()]);
                $result = ['status' => 'network'];
            } finally {
                rewind($lock);
                ftruncate($lock, 0);
                fwrite($lock, (string) microtime(true));
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
        if (in_array($result['status'], ['available', 'unavailable'], true)) {
            $data = ['availability_status' => $result['status'], 'checked_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')];
            if ($result['status'] === 'available') $data['price'] = $result['price'];
            // Do not overwrite a source edited or ordered while its product page was loading.
            $db->table('book_sources')->where('id', $id)->where('item_url', $source['item_url'])
                ->where('price', $source['price'])->where('updated_at', $source['updated_at'])
                ->where("book_id IN (SELECT id FROM books WHERE status = 'wishlist')", null, false)->update($data);
            if ($db->affectedRows() === 0) return $reply(['status' => 'conflict']);
            $result['checked_at'] = $data['checked_at'];
        }
        return $reply($result);
    }
}

<?php

namespace App\Controllers;

use App\Libraries\ShopProductReader;
use App\Libraries\SurugaSnapshotReader;
use CodeIgniter\HTTP\ResponseInterface;

final class ShopBrowser extends BaseController
{
    public function index(): string
    {
        $sources = db_connect()->table('book_sources bs')
            ->select('bs.id, bs.item_url, b.title, s.name AS shop_name')
            ->join('books b', 'b.id = bs.book_id')->join('shops s', 's.id = bs.shop_id')
            ->where('b.status', 'wishlist')->where('s.is_active', 1)
            ->orderBy('s.name')->orderBy('b.title')->get()->getResultArray();
        $groups = ['Melonbooks' => [], '駿河屋' => [], 'Mandarake' => []];
        foreach ($sources as $source) {
            $identity = ShopProductReader::identify((string) $source['item_url']);
            if ($identity) $groups[$identity['site']][] = $source;
        }
        $this->response->setHeader('Cache-Control', 'no-store')->setHeader('X-Frame-Options', 'DENY');
        return view('sources/browser', ['groups' => $groups]);
    }

    public function command(): ResponseInterface
    {
        $reply = fn (array $data) => $this->response->setHeader('Cache-Control', 'no-store')->setJSON($data + ['csrf' => csrf_hash()]);
        $action = (string) $this->request->getPost('action');
        if (! in_array($action, ['start', 'input', 'read', 'close'], true)) return $reply(['error' => '不支援的操作']);
        $state = session('shop_browser') ?? [];
        $payload = ['action' => $action, 'token' => $state['token'] ?? '', 'generation' => $state['generation'] ?? ''];
        if ($action === 'start') {
            $source = db_connect()->table('book_sources bs')->select('bs.*, s.name AS shop_name')
                ->join('books b', 'b.id = bs.book_id')->join('shops s', 's.id = bs.shop_id')
                ->where('bs.id', (int) $this->request->getPost('source_id'))
                ->where('b.status', 'wishlist')->where('s.is_active', 1)->get()->getRowArray();
            if (! $source || ! ShopProductReader::identify((string) $source['item_url'])) return $reply(['error' => '找不到支援的商品來源']);
            $payload['url'] = preg_replace('~^http:~i', 'https:', $source['item_url']);
        } elseif (! $state) {
            return $reply(['error' => '請先開啟商品']);
        }
        if ($action === 'input') {
            foreach (['kind', 'key', 'text', 'x', 'y', 'delta'] as $key) $payload[$key] = $this->request->getPost($key);
            if (strlen((string) ($payload['text'] ?? '')) > 8000) return $reply(['error' => '輸入文字過長']);
        }
        try {
            $result = $this->agent($payload);
            if ($action === 'start') {
                session()->set('shop_browser', [
                    'token' => $result['token'], 'generation' => $result['generation'],
                    'url' => $payload['url'], 'shop_name' => $source['shop_name'],
                    'source_id' => $source['id'], 'price' => $source['price'],
                ]);
                unset($result['token']);
            } elseif ($action === 'read') {
                // Never write to book_sources: this endpoint is deliberately a dry-run.
                if (isset($result['snapshot']) || isset($result['dom_pending'])) {
                    $parsed = ['status' => isset($result['snapshot']) ? 'dom_observed' : 'dom_pending'];
                    if (isset($result['snapshot']) && str_starts_with(ShopProductReader::identify($state['url'])['key'] ?? '', 'suruga:')) {
                        $parsed = (new SurugaSnapshotReader())->parse($result['snapshot'], $state['url'], $state['shop_name']);
                    }
                    return $reply([
                        'result' => $parsed, 'old_price' => $state['price'],
                        'snapshot' => $result['snapshot'] ?? null, 'url' => $result['url'], 'dry_run' => true,
                    ]);
                }
                $parsed = (new ShopProductReader())->parse($result['html'], $state['url'], $state['shop_name']);
                $result = ['result' => $parsed, 'url' => $result['url'], 'old_price' => $state['price'], 'dry_run' => true, 'diagnostics' => $result['diagnostics'] ?? null];
            } elseif ($action === 'close') {
                session()->remove('shop_browser');
            }
            return $reply($result);
        } catch (\RuntimeException $error) {
            if (in_array($error->getCode(), [401, 409], true)) session()->remove('shop_browser');
            return $reply(['error' => $error->getMessage()]);
        }
    }

    public function frame(): ResponseInterface
    {
        $state = session('shop_browser') ?? [];
        $this->response->setHeader('Cache-Control', 'no-store')->setHeader('X-Content-Type-Options', 'nosniff');
        if (! $state) return $this->response->setStatusCode(401)->setBody('No browser session');
        try {
            $body = $this->agent(['action' => 'frame', 'token' => $state['token'], 'generation' => $state['generation']], true);
            return $this->response->setContentType('image/jpeg')->setBody($body);
        } catch (\RuntimeException $error) {
            return $this->response->setStatusCode(503)->setBody('Browser unavailable');
        }
    }

    private function agent(array $payload, bool $image = false): array|string
    {
        $curl = curl_init('http://localhost/command');
        curl_setopt_array($curl, [
            CURLOPT_UNIX_SOCKET_PATH => '/run/doujin-shop-browser/agent.sock',
            CURLOPT_PROXY => '', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => $payload['action'] === 'start' ? 45 : 12,
        ]);
        $body = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if ($body === false) throw new \RuntimeException('遠端瀏覽器未啟動或連線逾時');
        if ($code !== 200) {
            $message = match ($code) {
                401 => '操作工作階段已逾時，請重新開啟商品',
                409 => '瀏覽器正由另一個視窗使用，請先在原視窗關閉，或等待閒置逾時',
                default => '瀏覽器操作失敗，請稍後重試',
            };
            throw new \RuntimeException($message, $code);
        }
        return $image ? $body : json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }
}

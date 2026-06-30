<?php

namespace App\Controllers;

use App\Libraries\CirclemsClient;
use App\Models\CirclemsTokenModel;
use CodeIgniter\HTTP\RedirectResponse;
use RuntimeException;

class Circlems extends BaseController
{
    public function index(): string
    {
        $client = new CirclemsClient();
        $token = $this->currentToken();
        $events = [];
        $latestEventId = null;
        $eventError = null;

        if ($token) {
            try {
                $token = $this->refreshIfNeeded($token, $client);
                $eventList = $client->eventList((string) $token['access_token']);
                $events = $this->eventOptions($eventList);
                $latestEventId = $this->latestEventId($eventList);
            } catch (RuntimeException $exception) {
                $eventError = $exception->getMessage();
            }
        }

        return view('circlems/index', [
            'configured' => $client->isConfigured(),
            'missingConfigKeys' => $client->missingConfigKeys(),
            'token' => $token,
            'isExpired' => $this->isTokenExpired($token),
            'circleSearch' => session('circlems_circle_search'),
            'circleProbe' => session('circlems_probe_result'),
            'events' => $events,
            'latestEventId' => $latestEventId,
            'eventError' => $eventError,
        ]);
    }

    public function connect(): RedirectResponse
    {
        $client = new CirclemsClient();
        if (! $client->isConfigured()) {
            return redirect()->to('/circlems')->with('error', 'Circle.ms 設定不完整。');
        }

        $state = bin2hex(random_bytes(24));
        session()->set('circlems_oauth_state', $state);

        return redirect()->to($client->authorizationUrl($state));
    }

    public function callback(): RedirectResponse
    {
        $error = (string) $this->request->getGet('error');
        if ($error !== '') {
            return redirect()->to('/circlems')->with('error', 'Circle.ms 授權失敗：' . $error);
        }

        $state = (string) $this->request->getGet('state');
        $expectedState = (string) session('circlems_oauth_state');
        session()->remove('circlems_oauth_state');

        if ($state === '' || $expectedState === '' || ! hash_equals($expectedState, $state)) {
            return redirect()->to('/circlems')->with('error', 'Circle.ms state 驗證失敗，請重新連線。');
        }

        $code = (string) $this->request->getGet('code');
        if ($code === '') {
            return redirect()->to('/circlems')->with('error', 'Circle.ms callback 沒有收到 code。');
        }

        try {
            $client = new CirclemsClient();
            $tokenResponse = $client->exchangeCode($code);
            $this->storeToken($tokenResponse, $client);
        } catch (RuntimeException $exception) {
            return redirect()->to('/circlems')->with('error', $exception->getMessage());
        }

        return redirect()->to('/circlems')->with('message', 'Circle.ms 已連線。');
    }

    public function refresh(): RedirectResponse
    {
        $token = $this->currentToken();
        if (! $token || empty($token['refresh_token'])) {
            return redirect()->to('/circlems')->with('error', '尚未取得 refresh token。');
        }

        try {
            $client = new CirclemsClient();
            $tokenResponse = $client->refreshToken((string) $token['refresh_token']);
            $this->storeToken($tokenResponse, $client);
        } catch (RuntimeException $exception) {
            return redirect()->to('/circlems')->with('error', $exception->getMessage());
        }

        return redirect()->to('/circlems')->with('message', 'Circle.ms token 已更新。');
    }

    public function test(): RedirectResponse
    {
        $token = $this->currentToken();
        if (! $token) {
            return redirect()->to('/circlems')->with('error', '尚未連線 Circle.ms。');
        }

        try {
            $client = new CirclemsClient();
            $token = $this->refreshIfNeeded($token, $client);
            $client->eventList((string) $token['access_token']);
            (new CirclemsTokenModel())->update((int) $token['id'], [
                'last_tested_at' => date('Y-m-d H:i:s'),
                'last_error' => null,
            ]);
        } catch (RuntimeException $exception) {
            (new CirclemsTokenModel())->update((int) $token['id'], [
                'last_tested_at' => date('Y-m-d H:i:s'),
                'last_error' => $exception->getMessage(),
            ]);

            return redirect()->to('/circlems')->with('error', $exception->getMessage());
        }

        return redirect()->to('/circlems')->with('message', 'Circle.ms API 測試成功。');
    }

    public function searchCircle(): RedirectResponse
    {
        $circleName = trim((string) $this->request->getPost('circle_name'));
        $eventId = (int) $this->request->getPost('event_id');
        $page = max(1, (int) $this->request->getPost('page'));

        $token = $this->currentToken();
        if (! $token) {
            return redirect()->to('/circlems')->with('error', '尚未連線 Circle.ms。');
        }

        try {
            $client = new CirclemsClient();
            $token = $this->refreshIfNeeded($token, $client);
            $eventList = $client->eventList((string) $token['access_token']);
            if ($eventId <= 0) {
                $eventId = $this->latestEventId($eventList) ?? 0;
            }

            if ($eventId <= 0) {
                throw new RuntimeException('Circle.ms event list did not include a latest event id.');
            }

            $result = $client->queryCircle((string) $token['access_token'], $eventId, $circleName, $page);
            (new CirclemsTokenModel())->update((int) $token['id'], [
                'last_tested_at' => date('Y-m-d H:i:s'),
                'last_error' => null,
            ]);
        } catch (RuntimeException $exception) {
            (new CirclemsTokenModel())->update((int) $token['id'], [
                'last_tested_at' => date('Y-m-d H:i:s'),
                'last_error' => $exception->getMessage(),
            ]);

            return redirect()->to('/circlems')->with('error', $exception->getMessage());
        }

        return redirect()->to('/circlems')
            ->with('message', 'Circle.ms 社團搜尋完成。')
            ->with('circlems_circle_search', [
                'circleName' => $circleName !== '' ? $circleName : '全部社團樣本',
                'query' => $circleName,
                'eventId' => $eventId,
                'page' => $page,
                'count' => (int) ($result['response']['count'] ?? 0),
                'maxCount' => (int) ($result['response']['maxcount'] ?? 0),
                'rows' => $this->circleRows($result),
                'result' => $this->summarizeCircleResult($result),
            ]);
    }

    public function sampleCircles(): RedirectResponse
    {
        $token = $this->currentToken();
        if (! $token) {
            return redirect()->to('/circlems')->with('error', '尚未連線 Circle.ms。');
        }

        try {
            $client = new CirclemsClient();
            $token = $this->refreshIfNeeded($token, $client);
            $eventList = $client->eventList((string) $token['access_token']);
            $eventId = $this->latestEventId($eventList);

            if ($eventId === null) {
                throw new RuntimeException('Circle.ms event list did not include a latest event id.');
            }

            $result = $client->queryCircle((string) $token['access_token'], $eventId);
            (new CirclemsTokenModel())->update((int) $token['id'], [
                'last_tested_at' => date('Y-m-d H:i:s'),
                'last_error' => null,
            ]);
        } catch (RuntimeException $exception) {
            (new CirclemsTokenModel())->update((int) $token['id'], [
                'last_tested_at' => date('Y-m-d H:i:s'),
                'last_error' => $exception->getMessage(),
            ]);

            return redirect()->to('/circlems')->with('error', $exception->getMessage());
        }

        return redirect()->to('/circlems')
            ->with('message', 'Circle.ms 樣本社團已取得。')
            ->with('circlems_circle_search', [
                'circleName' => '樣本社團',
                'query' => '',
                'eventId' => $eventId,
                'page' => 1,
                'count' => (int) ($result['response']['count'] ?? 0),
                'maxCount' => (int) ($result['response']['maxcount'] ?? 0),
                'names' => $this->circleNames($result),
                'rows' => $this->circleRows($result),
                'result' => $this->summarizeCircleResult($result, 20),
            ]);
    }

    public function circleDetail(): RedirectResponse
    {
        $wcid = (int) $this->request->getPost('wcid');
        $eventId = (int) $this->request->getPost('event_id');
        if ($wcid <= 0) {
            return redirect()->to('/circlems')->with('error', '缺少 WCID，無法查詢社團詳細。');
        }

        $token = $this->currentToken();
        if (! $token) {
            return redirect()->to('/circlems')->with('error', '尚未連線 Circle.ms。');
        }

        try {
            $client = new CirclemsClient();
            $token = $this->refreshIfNeeded($token, $client);
            $result = $client->circleDetail((string) $token['access_token'], $wcid, $eventId);
            (new CirclemsTokenModel())->update((int) $token['id'], [
                'last_tested_at' => date('Y-m-d H:i:s'),
                'last_error' => null,
            ]);
        } catch (RuntimeException $exception) {
            (new CirclemsTokenModel())->update((int) $token['id'], [
                'last_tested_at' => date('Y-m-d H:i:s'),
                'last_error' => $exception->getMessage(),
            ]);

            return redirect()->to('/circlems')->with('error', $exception->getMessage());
        }

        $circle = $result['response']['circle'] ?? [];

        return redirect()->to('/circlems')
            ->with('message', 'Circle.ms 社團詳細已取得。')
            ->with('circlems_probe_result', [
                'type' => 'detail',
                'title' => '社團詳細',
                'wcid' => $wcid,
                'eventId' => $eventId,
                'circle' => is_array($circle) ? ($this->circleRows(['response' => ['list' => [$circle]]])[0] ?? []) : [],
                'result' => $result,
            ]);
    }

    public function circleBooks(): RedirectResponse
    {
        $wcid = (int) $this->request->getPost('wcid');
        $eventId = (int) $this->request->getPost('event_id');
        $page = max(1, (int) $this->request->getPost('page'));
        if ($wcid <= 0 || $eventId <= 0) {
            return redirect()->to('/circlems')->with('error', '缺少活動 ID 或 WCID，無法查詢頒布物。');
        }

        $token = $this->currentToken();
        if (! $token) {
            return redirect()->to('/circlems')->with('error', '尚未連線 Circle.ms。');
        }

        try {
            $client = new CirclemsClient();
            $token = $this->refreshIfNeeded($token, $client);
            $result = $client->queryBooks((string) $token['access_token'], $eventId, $wcid, $page);
            (new CirclemsTokenModel())->update((int) $token['id'], [
                'last_tested_at' => date('Y-m-d H:i:s'),
                'last_error' => null,
            ]);
        } catch (RuntimeException $exception) {
            (new CirclemsTokenModel())->update((int) $token['id'], [
                'last_tested_at' => date('Y-m-d H:i:s'),
                'last_error' => $exception->getMessage(),
            ]);

            return redirect()->to('/circlems')->with('error', $exception->getMessage());
        }

        return redirect()->to('/circlems')
            ->with('message', 'Circle.ms 頒布物已取得。')
            ->with('circlems_probe_result', [
                'type' => 'books',
                'title' => '頒布物',
                'wcid' => $wcid,
                'eventId' => $eventId,
                'page' => $page,
                'count' => (int) ($result['response']['count'] ?? 0),
                'maxCount' => (int) ($result['response']['maxcount'] ?? 0),
                'books' => $this->bookRows($result),
                'result' => $this->summarizeCircleResult($result, 20),
            ]);
    }

    public function catalogBase(): RedirectResponse
    {
        $eventId = (int) $this->request->getPost('event_id');
        if ($eventId <= 0) {
            return redirect()->to('/circlems')->with('error', '缺少活動 ID，無法取得初期資料庫。');
        }

        $token = $this->currentToken();
        if (! $token) {
            return redirect()->to('/circlems')->with('error', '尚未連線 Circle.ms。');
        }

        try {
            $client = new CirclemsClient();
            $token = $this->refreshIfNeeded($token, $client);
            $result = $client->catalogBase((string) $token['access_token'], $eventId);
            (new CirclemsTokenModel())->update((int) $token['id'], [
                'last_tested_at' => date('Y-m-d H:i:s'),
                'last_error' => null,
            ]);
        } catch (RuntimeException $exception) {
            (new CirclemsTokenModel())->update((int) $token['id'], [
                'last_tested_at' => date('Y-m-d H:i:s'),
                'last_error' => $exception->getMessage(),
            ]);

            return redirect()->to('/circlems')->with('error', $exception->getMessage());
        }

        return redirect()->to('/circlems')
            ->with('message', 'Circle.ms 初期資料庫資訊已取得。')
            ->with('circlems_probe_result', [
                'type' => 'catalog',
                'title' => '初期資料庫',
                'eventId' => $eventId,
                'catalogUrls' => $this->catalogUrls($result),
                'result' => $result,
            ]);
    }

    public function catalogDownloadText(): RedirectResponse
    {
        $eventId = (int) $this->request->getPost('event_id');
        if ($eventId <= 0) {
            return redirect()->to('/circlems')->with('error', '缺少活動 ID，無法下載初期資料庫。');
        }

        $token = $this->currentToken();
        if (! $token) {
            return redirect()->to('/circlems')->with('error', '尚未連線 Circle.ms。');
        }

        try {
            $client = new CirclemsClient();
            $token = $this->refreshIfNeeded($token, $client);
            $catalog = $client->catalogBase((string) $token['access_token'], $eventId);
            $download = $this->downloadTextCatalog($eventId, $catalog);
            (new CirclemsTokenModel())->update((int) $token['id'], [
                'last_tested_at' => date('Y-m-d H:i:s'),
                'last_error' => null,
            ]);
        } catch (RuntimeException $exception) {
            (new CirclemsTokenModel())->update((int) $token['id'], [
                'last_tested_at' => date('Y-m-d H:i:s'),
                'last_error' => $exception->getMessage(),
            ]);

            return redirect()->to('/circlems')->with('error', $exception->getMessage());
        }

        return redirect()->to('/circlems')
            ->with('message', 'Circle.ms text DB 已下載並檢查。')
            ->with('circlems_probe_result', [
                'type' => 'catalog_download',
                'title' => '初期資料庫下載檢查',
                'eventId' => $eventId,
                'catalogDownload' => $download,
                'result' => [
                    'selected_url_key' => $download['urlKey'],
                    'expected_md5' => $download['expectedMd5'],
                    'actual_md5' => $download['actualMd5'],
                    'md5_ok' => $download['md5Ok'],
                    'sqlite' => $download['sqlite'],
                ],
            ]);
    }

    public function catalogLookup(): RedirectResponse
    {
        $eventId = (int) $this->request->getPost('event_id');
        $query = trim((string) $this->request->getPost('q'));

        if ($eventId <= 0) {
            return redirect()->to('/circlems')->with('error', '缺少活動 ID，無法查詢 text DB。');
        }
        if ($query === '') {
            return redirect()->to('/circlems')->with('error', '請輸入社團名稱或 WCID。');
        }

        try {
            $rows = $this->catalogLookupRows($eventId, $query);
        } catch (RuntimeException $exception) {
            return redirect()->to('/circlems')->with('error', $exception->getMessage());
        }

        return redirect()->to('/circlems')
            ->with('message', 'Circle.ms text DB 位置查詢完成。')
            ->with('circlems_probe_result', [
                'type' => 'catalog_lookup',
                'title' => 'text DB 位置查詢',
                'eventId' => $eventId,
                'query' => $query,
                'catalogLookupRows' => $rows,
                'result' => [
                    'query' => $query,
                    'count' => count($rows),
                    'list' => $rows,
                ],
            ]);
    }

    private function currentToken(): ?array
    {
        return (new CirclemsTokenModel())
            ->orderBy('id', 'DESC')
            ->first();
    }

    private function storeToken(array $tokenResponse, CirclemsClient $client): void
    {
        $data = [
            'access_token' => (string) ($tokenResponse['access_token'] ?? ''),
            'refresh_token' => (string) ($tokenResponse['refresh_token'] ?? ''),
            'expires_at' => $client->tokenExpiresAt($tokenResponse),
            'scope' => isset($tokenResponse['scope']) ? (string) $tokenResponse['scope'] : null,
            'last_error' => null,
        ];

        if ($data['access_token'] === '' || $data['refresh_token'] === '') {
            throw new RuntimeException('Circle.ms token response missing token values.');
        }

        $model = new CirclemsTokenModel();
        $current = $model->orderBy('id', 'DESC')->first();

        if ($current) {
            $model->update((int) $current['id'], $data);
            return;
        }

        $model->insert($data);
    }

    private function refreshIfNeeded(array $token, CirclemsClient $client): array
    {
        if (! $this->isTokenExpired($token)) {
            return $token;
        }

        $tokenResponse = $client->refreshToken((string) $token['refresh_token']);
        $this->storeToken($tokenResponse, $client);

        return $this->currentToken() ?? $token;
    }

    private function isTokenExpired(?array $token): bool
    {
        if (! $token || empty($token['expires_at'])) {
            return true;
        }

        return strtotime((string) $token['expires_at']) <= time() + 300;
    }

    private function latestEventId(array $eventList): ?int
    {
        $candidates = [
            $eventList['response']['LatestEventId'] ?? null,
            $eventList['response']['latestEventId'] ?? null,
            $eventList['response']['latest_event_id'] ?? null,
            $eventList['LatestEventId'] ?? null,
            $eventList['latestEventId'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate) && (int) $candidate > 0) {
                return (int) $candidate;
            }
        }

        return null;
    }

    private function eventOptions(array $eventList): array
    {
        $list = $eventList['response']['list'] ?? [];
        if (! is_array($list)) {
            return [];
        }

        $latestEventId = $this->latestEventId($eventList);
        $events = [];

        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }

            $eventId = (int) ($row['EventId'] ?? $row['eventId'] ?? $row['event_id'] ?? 0);
            if ($eventId <= 0 || ($latestEventId !== null && $eventId > $latestEventId)) {
                continue;
            }

            $eventNo = (int) ($row['EventNo'] ?? $row['eventNo'] ?? $row['event_no'] ?? 0);
            $events[] = [
                'eventId' => $eventId,
                'eventNo' => $eventNo,
                'label' => $eventNo > 0 ? 'C' . $eventNo . ' / Event ' . $eventId : 'Event ' . $eventId,
            ];
        }

        usort($events, static fn (array $a, array $b): int => $b['eventId'] <=> $a['eventId']);

        return $events;
    }

    private function circleRows(array $result): array
    {
        $list = $result['response']['list'] ?? [];
        if (! is_array($list)) {
            return [];
        }

        $rows = [];
        foreach (array_slice($list, 0, 20) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rows[] = [
                'wcid' => (string) ($row['wcid'] ?? ''),
                'name' => (string) ($row['name'] ?? $row['circle_name'] ?? ''),
                'nameKana' => (string) ($row['name_kana'] ?? ''),
                'circlemsId' => (string) ($row['circlemsId'] ?? ''),
                'genre' => (string) ($row['genre'] ?? ''),
                'cutUrl' => (string) ($row['cut_url'] ?? $row['cut_web_url'] ?? $row['cut_base_url'] ?? ''),
                'url' => (string) ($row['url'] ?? ''),
                'pixivUrl' => (string) ($row['pixiv_url'] ?? ''),
                'twitterUrl' => (string) ($row['twitter_url'] ?? ''),
                'clipstudioUrl' => (string) ($row['clipstudio_url'] ?? ''),
                'niconicoUrl' => (string) ($row['niconico_url'] ?? ''),
                'tag' => (string) ($row['tag'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'stores' => $this->onlineStores($row['onlinestore'] ?? []),
                'updateDate' => (string) ($row['update_date'] ?? ''),
            ];
        }

        return $rows;
    }

    private function onlineStores(mixed $stores): array
    {
        if (! is_array($stores)) {
            return [];
        }

        $normalized = [];
        foreach ($stores as $store) {
            if (! is_array($store)) {
                continue;
            }

            $name = trim((string) ($store['name'] ?? ''));
            $link = trim((string) ($store['link'] ?? ''));
            if ($name === '' && $link === '') {
                continue;
            }

            $normalized[] = [
                'name' => $name !== '' ? $name : $link,
                'link' => $link,
            ];
        }

        return $normalized;
    }

    private function bookRows(array $result): array
    {
        $list = $result['response']['list'] ?? [];
        if (! is_array($list)) {
            return [];
        }

        $rows = [];
        foreach (array_slice($list, 0, 20) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rows[] = [
                'workId' => (string) ($row['work_id'] ?? ''),
                'wcid' => (string) ($row['wcid'] ?? ''),
                'num' => (string) ($row['num'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'size' => (string) ($row['size'] ?? ''),
                'page' => (string) ($row['page'] ?? ''),
                'genre' => (string) ($row['genre'] ?? ''),
                'distDate' => (string) ($row['dist_date'] ?? ''),
                'newBook' => (int) ($row['new_book'] ?? 0),
                'imageUrl' => (string) ($row['image_url'] ?? ''),
                'introduction' => (string) ($row['introduction'] ?? ''),
                'r18' => (int) ($row['r18'] ?? 0),
                'price' => isset($row['price']) ? (string) $row['price'] : '',
                'updateDate' => (string) ($row['update_date'] ?? ''),
            ];
        }

        return $rows;
    }

    private function catalogUrls(array $result): array
    {
        $urls = $result['response']['url'] ?? $result['response']['urls'] ?? [];
        if (! is_array($urls)) {
            return [];
        }

        $rows = [];
        foreach ($urls as $key => $url) {
            $url = trim((string) $url);
            if ($url === '') {
                continue;
            }

            $rows[] = [
                'key' => (string) $key,
                'url' => $url,
                'kind' => $this->catalogUrlKind((string) $key),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $rank = [
                'text-sqlite3-zip' => 1,
                'text-sqlite3' => 2,
                'image-zip' => 3,
                'image' => 4,
                'other' => 9,
            ];

            return ($rank[$a['kind']] ?? 9) <=> ($rank[$b['kind']] ?? 9)
                ?: strcmp($a['key'], $b['key']);
        });

        return $rows;
    }

    private function downloadTextCatalog(int $eventId, array $catalog): array
    {
        $selected = $this->textCatalogUrl($catalog);
        if ($selected === null) {
            throw new RuntimeException('Circle.ms catalog response did not include a text SQLite URL.');
        }

        $dir = WRITEPATH . 'circlems' . DIRECTORY_SEPARATOR . 'catalogs' . DIRECTORY_SEPARATOR . 'event_' . $eventId;
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('Unable to create catalog directory: ' . $dir);
        }

        $archivePath = $dir . DIRECTORY_SEPARATOR . 'webcatalog_text.db.gz';
        $dbPath = $dir . DIRECTORY_SEPARATOR . 'webcatalog_text.db';

        $this->downloadFile($selected['url'], $archivePath);
        $actualMd5 = strtoupper(md5_file($archivePath) ?: '');
        $expectedMd5 = strtoupper((string) $selected['md5']);
        $md5Ok = $expectedMd5 === '' || hash_equals($expectedMd5, $actualMd5);

        if (! $md5Ok) {
            throw new RuntimeException('Downloaded catalog MD5 mismatch.');
        }

        $this->decompressGzip($archivePath, $dbPath);

        return [
            'urlKey' => $selected['key'],
            'archivePath' => $archivePath,
            'dbPath' => $dbPath,
            'archiveSize' => filesize($archivePath) ?: 0,
            'dbSize' => filesize($dbPath) ?: 0,
            'expectedMd5' => $expectedMd5,
            'actualMd5' => $actualMd5,
            'md5Ok' => $md5Ok,
            'sqlite' => $this->inspectSqlite($dbPath),
        ];
    }

    private function textCatalogUrl(array $catalog): ?array
    {
        $urls = $catalog['response']['url'] ?? [];
        $md5s = $catalog['response']['md5'] ?? [];
        if (! is_array($urls)) {
            return null;
        }

        foreach (['textdb_sqlite3_url_ssl', 'textdb_sqlite3_url'] as $key) {
            $url = trim((string) ($urls[$key] ?? ''));
            if ($url !== '') {
                return [
                    'key' => $key,
                    'url' => $url,
                    'md5' => is_array($md5s) ? (string) ($md5s[$key] ?? '') : '',
                ];
            }
        }

        return null;
    }

    private function downloadFile(string $url, string $path): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Unable to write catalog file: ' . $path);
        }

        $curl = curl_init($url);
        if ($curl === false) {
            fclose($handle);
            throw new RuntimeException('Unable to initialize catalog download.');
        }

        curl_setopt_array($curl, [
            CURLOPT_FILE => $handle,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 240,
        ]);

        $ok = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        fclose($handle);

        if ($ok === false || $statusCode < 200 || $statusCode >= 300) {
            @unlink($path);
            throw new RuntimeException('Catalog download failed with HTTP ' . $statusCode . '. ' . $error);
        }
    }

    private function decompressGzip(string $source, string $target): void
    {
        $input = gzopen($source, 'rb');
        if ($input === false) {
            throw new RuntimeException('Unable to open gzip catalog file.');
        }

        $output = fopen($target, 'wb');
        if ($output === false) {
            gzclose($input);
            throw new RuntimeException('Unable to write SQLite catalog file.');
        }

        while (! gzeof($input)) {
            fwrite($output, gzread($input, 1024 * 1024));
        }

        fclose($output);
        gzclose($input);
    }

    private function catalogLookupRows(int $eventId, string $query): array
    {
        if (! class_exists('SQLite3')) {
            throw new RuntimeException('PHP SQLite3 extension is not installed.');
        }

        $dbPath = $this->catalogDbPath($eventId);
        if (! is_file($dbPath)) {
            throw new RuntimeException('尚未下載這個活動的 text DB，請先執行「下載並檢查 text DB」。');
        }

        $db = new \SQLite3($dbPath, SQLITE3_OPEN_READONLY);
        $statement = $db->prepare(
            <<<'SQL'
SELECT
    c.comiketNo,
    c.id,
    c.day,
    c.blockId,
    c.spaceNo,
    c.spaceNoSub,
    c.genreId,
    c.circleName,
    c.circleKana,
    c.penName,
    c.bookName,
    c.url,
    c.description,
    c.updateId,
    e.WCId AS wcId,
    e.twitterURL,
    e.pixivURL,
    e.CirclemsPortalURL,
    b.name AS blockName,
    a.name AS areaName,
    a.simpleName AS areaSimpleName,
    m.id AS mapId,
    m.name AS mapName,
    m.filename AS mapFilename,
    m.rotate AS mapRotate,
    l.xpos,
    l.ypos,
    l.xpos2,
    l.ypos2,
    l.layout,
    l.hallId
FROM ComiketCircleWC c
LEFT JOIN ComiketCircleExtend e
    ON e.comiketNo = c.comiketNo AND e.id = c.id
LEFT JOIN ComiketBlockWC b
    ON b.comiketNo = c.comiketNo AND b.id = c.blockId
LEFT JOIN ComiketAreaWC a
    ON a.comiketNo = c.comiketNo AND a.id = b.areaId
LEFT JOIN ComiketMapWC m
    ON m.comiketNo = c.comiketNo AND m.id = a.mapId
LEFT JOIN ComiketLayoutWC l
    ON l.comiketNo = c.comiketNo AND l.blockId = c.blockId AND l.spaceNo = c.spaceNo
WHERE
    c.circleName LIKE :like
    OR c.circleKana LIKE :like
    OR c.penName LIKE :like
    OR c.circlems LIKE :like
    OR (:numeric > 0 AND (e.WCId = :numeric OR c.updateId = :numeric OR c.id = :numeric))
ORDER BY c.day, m.id, b.id, c.spaceNo, c.spaceNoSub
LIMIT 30
SQL
        );

        if ($statement === false) {
            $db->close();
            throw new RuntimeException('Unable to prepare catalog lookup query.');
        }

        $numeric = ctype_digit($query) ? (int) $query : 0;
        $statement->bindValue(':like', '%' . $query . '%', SQLITE3_TEXT);
        $statement->bindValue(':numeric', $numeric, SQLITE3_INTEGER);

        $result = $statement->execute();
        if ($result === false) {
            $db->close();
            throw new RuntimeException('Unable to execute catalog lookup query.');
        }

        $rows = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $rows[] = $this->formatCatalogLookupRow($row);
        }

        $result->finalize();
        $statement->close();
        $db->close();

        return $rows;
    }

    private function catalogDbPath(int $eventId): string
    {
        return WRITEPATH . 'circlems' . DIRECTORY_SEPARATOR . 'catalogs' . DIRECTORY_SEPARATOR . 'event_' . $eventId . DIRECTORY_SEPARATOR . 'webcatalog_text.db';
    }

    private function formatCatalogLookupRow(array $row): array
    {
        $spaceSub = $this->catalogSpaceSub((int) ($row['spaceNoSub'] ?? -1));
        $spaceNo = (int) ($row['spaceNo'] ?? 0);
        $spaceLabel = trim((string) ($row['blockName'] ?? '')) . sprintf('%02d', $spaceNo) . $spaceSub;
        $areaLabel = trim((string) ($row['areaSimpleName'] ?? ''));
        if ($areaLabel === '') {
            $areaLabel = trim((string) ($row['areaName'] ?? ''));
        }
        if ($areaLabel === '') {
            $areaLabel = trim((string) ($row['mapName'] ?? ''));
        }

        $positionParts = [];
        if ((int) ($row['day'] ?? 0) > 0) {
            $positionParts[] = (int) $row['day'] . '日目';
        }
        if ($areaLabel !== '') {
            $positionParts[] = $areaLabel;
        }
        if ($spaceLabel !== '') {
            $positionParts[] = $spaceLabel;
        }

        return [
            'positionLabel' => implode(' ', $positionParts),
            'wcid' => (string) ($row['wcId'] ?? ''),
            'circleId' => (string) ($row['id'] ?? ''),
            'updateId' => (string) ($row['updateId'] ?? ''),
            'day' => (int) ($row['day'] ?? 0),
            'circleName' => (string) ($row['circleName'] ?? ''),
            'circleKana' => (string) ($row['circleKana'] ?? ''),
            'penName' => (string) ($row['penName'] ?? ''),
            'bookName' => (string) ($row['bookName'] ?? ''),
            'genreId' => (string) ($row['genreId'] ?? ''),
            'blockName' => (string) ($row['blockName'] ?? ''),
            'areaName' => (string) ($row['areaName'] ?? ''),
            'areaSimpleName' => (string) ($row['areaSimpleName'] ?? ''),
            'mapName' => (string) ($row['mapName'] ?? ''),
            'mapFilename' => (string) ($row['mapFilename'] ?? ''),
            'spaceNo' => $spaceNo,
            'spaceNoSub' => $spaceSub,
            'xpos' => (int) ($row['xpos'] ?? 0),
            'ypos' => (int) ($row['ypos'] ?? 0),
            'xpos2' => (int) ($row['xpos2'] ?? 0),
            'ypos2' => (int) ($row['ypos2'] ?? 0),
            'layout' => (string) ($row['layout'] ?? ''),
            'hallId' => (string) ($row['hallId'] ?? ''),
            'url' => (string) ($row['url'] ?? ''),
            'twitterUrl' => (string) ($row['twitterURL'] ?? ''),
            'pixivUrl' => (string) ($row['pixivURL'] ?? ''),
            'circlemsPortalUrl' => (string) ($row['CirclemsPortalURL'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
        ];
    }

    private function catalogSpaceSub(int $spaceNoSub): string
    {
        if ($spaceNoSub === 0) {
            return 'a';
        }
        if ($spaceNoSub === 1) {
            return 'b';
        }
        if ($spaceNoSub >= 0) {
            return (string) $spaceNoSub;
        }

        return '';
    }

    private function inspectSqlite(string $dbPath): array
    {
        if (! class_exists('SQLite3')) {
            return [
                'available' => false,
                'message' => 'PHP SQLite3 extension is not installed.',
                'tables' => [],
                'counts' => [],
            ];
        }

        $db = new \SQLite3($dbPath, SQLITE3_OPEN_READONLY);
        $tables = [];
        $result = $db->query("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name");
        while ($result !== false && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            $tables[] = (string) $row['name'];
        }

        $counts = [];
        foreach (['ComiketCircleWC', 'ComiketBlockWC', 'ComiketAreaWC', 'ComiketMapWC', 'ComiketLayoutWC', 'ComiketFloorWC', 'ComiketMappingWC'] as $table) {
            if (! in_array($table, $tables, true)) {
                continue;
            }

            $count = $db->querySingle('SELECT COUNT(*) FROM "' . $table . '"');
            $counts[$table] = (int) $count;
        }

        $db->close();

        return [
            'available' => true,
            'message' => 'ok',
            'tables' => $tables,
            'counts' => $counts,
        ];
    }

    private function catalogUrlKind(string $key): string
    {
        $key = strtolower($key);

        if (str_contains($key, 'textdb_sqlite3') && str_contains($key, 'zip')) {
            return 'text-sqlite3-zip';
        }
        if (str_contains($key, 'textdb_sqlite3')) {
            return 'text-sqlite3';
        }
        if (str_contains($key, 'imagedb') && str_contains($key, 'zip')) {
            return 'image-zip';
        }
        if (str_contains($key, 'imagedb')) {
            return 'image';
        }

        return 'other';
    }

    private function summarizeCircleResult(array $result, int $limit = 10): array
    {
        $list = $result['response']['list'] ?? [];
        if (! is_array($list)) {
            $list = [];
        }

        $result['response']['list'] = array_slice($list, 0, $limit);

        return $result;
    }

    private function circleNames(array $result): array
    {
        $list = $result['response']['list'] ?? [];
        if (! is_array($list)) {
            return [];
        }

        $names = [];
        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['circle_name'] ?? $row['circleName'] ?? $row['name'] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }

            if (count($names) >= 20) {
                break;
            }
        }

        return array_values(array_unique($names));
    }
}

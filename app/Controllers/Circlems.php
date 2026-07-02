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

    public function convertBindings(): RedirectResponse
    {
        try {
            $summary = $this->convertCircleBindingsToCirclemsId();
        } catch (RuntimeException $exception) {
            return redirect()->to('/circlems')->with('error', $exception->getMessage());
        }

        return redirect()->to('/circlems')->with(
            'message',
            '已轉換既有社團綁定：更新 ' . number_format($summary['converted']) . ' 個社團，回填 ' . number_format($summary['linked']) . ' 筆 C108 攤位。'
        );
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

    public function catalogDownloadImage(): RedirectResponse
    {
        $eventId = (int) $this->request->getPost('event_id');
        $imageNo = (int) $this->request->getPost('image_no');
        if ($imageNo < 1 || $imageNo > 2) {
            $imageNo = 1;
        }

        if ($eventId <= 0) {
            return redirect()->to('/circlems')->with('error', '缺少活動 ID，無法下載 image DB。');
        }

        $token = $this->currentToken();
        if (! $token) {
            return redirect()->to('/circlems')->with('error', '尚未連線 Circle.ms。');
        }

        try {
            $client = new CirclemsClient();
            $token = $this->refreshIfNeeded($token, $client);
            $catalog = $client->catalogBase((string) $token['access_token'], $eventId);
            $download = $this->downloadImageCatalog($eventId, $catalog, $imageNo);
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
            ->with('message', 'Circle.ms image DB 已下載並檢查。')
            ->with('circlems_probe_result', [
                'type' => 'catalog_image_download',
                'title' => 'Image DB 下載檢查',
                'eventId' => $eventId,
                'catalogImageDownload' => $download,
                'result' => [
                    'selected_url_key' => $download['urlKey'],
                    'expected_md5' => $download['expectedMd5'],
                    'actual_md5' => $download['actualMd5'],
                    'md5_ok' => $download['md5Ok'],
                    'sqlite' => $download['sqlite'],
                ],
            ]);
    }

    public function catalogExportCommonImages(): RedirectResponse
    {
        $eventId = (int) $this->request->getPost('event_id');
        $imageNo = (int) $this->request->getPost('image_no');
        if ($imageNo < 1 || $imageNo > 2) {
            $imageNo = 1;
        }

        if ($eventId <= 0) {
            return redirect()->to('/circlems')->with('error', '缺少活動 ID，無法匯出 common images。');
        }

        try {
            $export = $this->exportCommonImages($eventId, $imageNo);
        } catch (RuntimeException $exception) {
            return redirect()->to('/circlems')->with('error', $exception->getMessage());
        }

        return redirect()->to('/circlems')
            ->with('message', 'Circle.ms common images 已匯出。')
            ->with('circlems_probe_result', [
                'type' => 'catalog_common_export',
                'title' => 'Common Images 匯出',
                'eventId' => $eventId,
                'catalogCommonExport' => $export,
                'result' => $export,
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

    public function importC108(): RedirectResponse
    {
        $eventId = (int) $this->request->getPost('event_id');
        $offset = max(0, (int) $this->request->getPost('offset'));
        $limit = (int) $this->request->getPost('limit');
        if ($limit <= 0 || $limit > 1000) {
            $limit = 1000;
        }

        if ($eventId <= 0) {
            return redirect()->to('/circlems')->with('error', '缺少活動 ID，無法匯入 C108。');
        }

        try {
            $summary = $this->importC108Rows($eventId, $offset, $limit);
        } catch (RuntimeException $exception) {
            return redirect()->to('/circlems')->with('error', $exception->getMessage());
        }

        return redirect()->to('/circlems')
            ->with('message', $summary['done'] ? 'C108 社團攤位資料已匯入完成。' : 'C108 社團攤位資料已分批匯入。')
            ->with('circlems_probe_result', [
                'type' => 'catalog_import',
                'title' => 'C108 匯入結果',
                'eventId' => $eventId,
                'catalogImport' => $summary,
                'result' => $summary,
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

    private function downloadImageCatalog(int $eventId, array $catalog, int $imageNo): array
    {
        $selected = $this->imageCatalogUrl($catalog, $imageNo);
        if ($selected === null) {
            throw new RuntimeException('Circle.ms catalog response did not include an image SQLite URL.');
        }

        $dir = WRITEPATH . 'circlems' . DIRECTORY_SEPARATOR . 'catalogs' . DIRECTORY_SEPARATOR . 'event_' . $eventId;
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('Unable to create catalog directory: ' . $dir);
        }

        $archivePath = $dir . DIRECTORY_SEPARATOR . 'webcatalog_image' . $imageNo . '.db.gz';
        $dbPath = $dir . DIRECTORY_SEPARATOR . 'webcatalog_image' . $imageNo . '.db';

        $this->downloadFile($selected['url'], $archivePath);
        $actualMd5 = strtoupper(md5_file($archivePath) ?: '');
        $expectedMd5 = strtoupper((string) $selected['md5']);
        $md5Ok = $expectedMd5 === '' || hash_equals($expectedMd5, $actualMd5);

        if (! $md5Ok) {
            throw new RuntimeException('Downloaded image catalog MD5 mismatch.');
        }

        $this->decompressGzip($archivePath, $dbPath);

        return [
            'imageNo' => $imageNo,
            'urlKey' => $selected['key'],
            'archivePath' => $archivePath,
            'dbPath' => $dbPath,
            'archiveSize' => filesize($archivePath) ?: 0,
            'dbSize' => filesize($dbPath) ?: 0,
            'expectedMd5' => $expectedMd5,
            'actualMd5' => $actualMd5,
            'md5Ok' => $md5Ok,
            'sqlite' => $this->inspectImageSqlite($dbPath, $eventId),
        ];
    }

    private function exportCommonImages(int $eventId, int $imageNo): array
    {
        if (! class_exists('SQLite3')) {
            throw new RuntimeException('PHP SQLite3 extension is not installed.');
        }

        $dbPath = $this->imageCatalogDbPath($eventId, $imageNo);
        if (! is_file($dbPath)) {
            throw new RuntimeException('尚未下載這個活動的 image DB，請先執行「下載並探測 image DB」。');
        }

        $dir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'circlems' . DIRECTORY_SEPARATOR . 'event_' . $eventId . DIRECTORY_SEPARATOR . 'image' . $imageNo . DIRECTORY_SEPARATOR . 'common';
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('Unable to create common image export directory.');
        }

        $db = new \SQLite3($dbPath, SQLITE3_OPEN_READONLY);
        $result = $db->query('SELECT name, width, height, type, size, image FROM ComiketCommonImage ORDER BY name');
        if ($result === false) {
            $db->close();
            throw new RuntimeException('Unable to read ComiketCommonImage.');
        }

        $files = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $name = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $extension = strtolower((string) ($row['type'] ?? 'png'));
            if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                $extension = 'png';
            }

            $fileName = $name . '.' . ($extension === 'jpeg' ? 'jpg' : $extension);
            $path = $dir . DIRECTORY_SEPARATOR . $fileName;
            $image = $row['image'] ?? null;
            if (! is_string($image) || $image === '') {
                continue;
            }

            file_put_contents($path, $image);
            $files[] = [
                'name' => $name,
                'url' => '/uploads/circlems/event_' . $eventId . '/image' . $imageNo . '/common/' . $fileName,
                'width' => (int) ($row['width'] ?? 0),
                'height' => (int) ($row['height'] ?? 0),
                'size' => (int) ($row['size'] ?? 0),
                'type' => $extension,
            ];
        }

        $result->finalize();
        $db->close();

        return [
            'event_id' => $eventId,
            'image_no' => $imageNo,
            'export_dir' => $dir,
            'count' => count($files),
            'files' => $files,
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

    private function imageCatalogUrl(array $catalog, int $imageNo): ?array
    {
        $urls = $catalog['response']['url'] ?? [];
        $md5s = $catalog['response']['md5'] ?? [];
        if (! is_array($urls)) {
            return null;
        }

        foreach (['imagedb' . $imageNo . '_url_ssl', 'imagedb' . $imageNo . '_url'] as $key) {
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

    private function imageCatalogDbPath(int $eventId, int $imageNo): string
    {
        return WRITEPATH . 'circlems' . DIRECTORY_SEPARATOR . 'catalogs' . DIRECTORY_SEPARATOR . 'event_' . $eventId . DIRECTORY_SEPARATOR . 'webcatalog_image' . $imageNo . '.db';
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
    c.updateData,
    c.circlems AS circlemsId,
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

    private function importC108Rows(int $eventId, int $offset, int $limit): array
    {
        if (! class_exists('SQLite3')) {
            throw new RuntimeException('PHP SQLite3 extension is not installed.');
        }
        $mysql = db_connect();
        if (! $mysql->tableExists('c108_circles')) {
            throw new RuntimeException('找不到 c108_circles，請先執行 migration。');
        }

        $dbPath = $this->catalogDbPath($eventId);
        if (! is_file($dbPath)) {
            throw new RuntimeException('尚未下載這個活動的 text DB，請先執行「下載並檢查 text DB」。');
        }

        $sqlite = new \SQLite3($dbPath, SQLITE3_OPEN_READONLY);
        $total = (int) $sqlite->querySingle('SELECT COUNT(*) FROM ComiketCircleWC');
        $localCircleIds = $this->localCircleIdsForCatalog($mysql);
        $statement = $sqlite->prepare(
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
    c.updateData,
    c.circlems AS circlemsId,
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
ORDER BY c.day, m.id, b.id, c.spaceNo, c.spaceNoSub
LIMIT :limit OFFSET :offset
SQL
        );

        if ($statement === false) {
            $sqlite->close();
            throw new RuntimeException('Unable to prepare C108 catalog import query.');
        }

        $statement->bindValue(':limit', $limit, SQLITE3_INTEGER);
        $statement->bindValue(':offset', $offset, SQLITE3_INTEGER);
        $result = $statement->execute();

        if ($result === false) {
            $statement->close();
            $sqlite->close();
            throw new RuntimeException('Unable to read C108 catalog rows.');
        }

        $table = $mysql->table('c108_circles');
        $now = date('Y-m-d H:i:s');
        $imported = 0;
        $matched = 0;
        $skipped = 0;

        $mysql->transStart();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $formatted = $this->formatCatalogLookupRow($row);
            $wcid = (int) $formatted['wcid'];
            if ($wcid <= 0) {
                $skipped++;
                continue;
            }

            $circleId = $localCircleIds['circlems'][$formatted['circlemsId']]
                ?? $localCircleIds['name'][$this->circleNameKey($formatted['circleName'])]
                ?? null;

            if ($circleId !== null) {
                $matched++;
            }

            $table->replace([
                'circle_id' => $circleId,
                'event_id' => $eventId,
                'comiket_no' => $formatted['comiketNo'] > 0 ? $formatted['comiketNo'] : null,
                'catalog_circle_id' => (int) $formatted['circleId'] > 0 ? (int) $formatted['circleId'] : null,
                'wcid' => $wcid,
                'circlems_id' => $this->nullIfEmpty($formatted['circlemsId']),
                'day' => $formatted['day'] > 0 ? $formatted['day'] : null,
                'genre_id' => $this->nullIfEmpty($formatted['genreId']),
                'circle_name' => $formatted['circleName'],
                'circle_kana' => $this->nullIfEmpty($formatted['circleKana']),
                'pen_name' => $this->nullIfEmpty($formatted['penName']),
                'book_name' => $this->nullIfEmpty($formatted['bookName']),
                'block_id' => (int) ($row['blockId'] ?? 0) > 0 ? (int) $row['blockId'] : null,
                'block_name' => $this->nullIfEmpty($formatted['blockName']),
                'area_name' => $this->nullIfEmpty($formatted['areaName']),
                'area_simple_name' => $this->nullIfEmpty($formatted['areaSimpleName']),
                'map_id' => (int) ($row['mapId'] ?? 0) > 0 ? (int) $row['mapId'] : null,
                'map_name' => $this->nullIfEmpty($formatted['mapName']),
                'map_filename' => $this->nullIfEmpty($formatted['mapFilename']),
                'map_rotate' => (int) ($row['mapRotate'] ?? 0),
                'space_no' => $formatted['spaceNo'] > 0 ? $formatted['spaceNo'] : null,
                'space_no_sub' => $this->nullIfEmpty($formatted['spaceNoSub']),
                'space_label' => $this->nullIfEmpty(trim($formatted['blockName'] . sprintf('%02d', $formatted['spaceNo']) . $formatted['spaceNoSub'])),
                'position_label' => $this->nullIfEmpty($formatted['positionLabel']),
                'xpos' => $formatted['xpos'],
                'ypos' => $formatted['ypos'],
                'xpos2' => $formatted['xpos2'],
                'ypos2' => $formatted['ypos2'],
                'layout' => $this->nullIfEmpty($formatted['layout']),
                'hall_id' => $this->nullIfEmpty($formatted['hallId']),
                'website_url' => $this->nullIfEmpty($formatted['url']),
                'twitter_url' => $this->nullIfEmpty($formatted['twitterUrl']),
                'pixiv_url' => $this->nullIfEmpty($formatted['pixivUrl']),
                'circlems_portal_url' => $this->nullIfEmpty($formatted['circlemsPortalUrl']),
                'description' => $this->nullIfEmpty($formatted['description']),
                'source_update_id' => $this->nullIfEmpty($formatted['updateId']),
                'source_updated_at' => $this->catalogDateOrNull($formatted['updateData']),
                'imported_at' => $now,
            ]);

            $imported++;
        }
        $mysql->transComplete();

        $result->finalize();
        $statement->close();
        $sqlite->close();

        if ($mysql->transStatus() === false) {
            throw new RuntimeException('C108 import transaction failed.');
        }

        $nextOffset = $offset + $imported + $skipped;
        $done = $nextOffset >= $total || ($imported + $skipped) === 0;

        return [
            'table' => 'c108_circles',
            'event_id' => $eventId,
            'offset' => $offset,
            'limit' => $limit,
            'next_offset' => $nextOffset,
            'total' => $total,
            'done' => $done,
            'imported' => $imported,
            'matched_local_circles' => $matched,
            'skipped_without_wcid' => $skipped,
            'imported_at' => $now,
        ];
    }

    private function localCircleIdsForCatalog($db): array
    {
        $rows = $db->table('circles')
            ->select('id, name, webcatalog_circle_id')
            ->get()
            ->getResultArray();

        $byCirclems = [];
        $byName = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $circlemsId = trim((string) ($row['webcatalog_circle_id'] ?? ''));
            if ($circlemsId !== '') {
                $byCirclems[$circlemsId] = $id;
            }

            $nameKey = $this->circleNameKey((string) ($row['name'] ?? ''));
            if ($nameKey !== '' && ! isset($byName[$nameKey])) {
                $byName[$nameKey] = $id;
            }
        }

        return [
            'circlems' => $byCirclems,
            'name' => $byName,
        ];
    }

    private function convertCircleBindingsToCirclemsId(): array
    {
        $db = db_connect();
        if (! $db->tableExists('circles') || ! $db->tableExists('c108_circles')) {
            throw new RuntimeException('缺少 circles 或 c108_circles 資料表，無法轉換。');
        }

        $db->transStart();
        $db->query(
            'UPDATE `circles` c
             JOIN `c108_circles` c108 ON c.`webcatalog_circle_id` = c108.`wcid`
             SET c.`webcatalog_circle_id` = c108.`circlems_id`
             WHERE c108.`circlems_id` IS NOT NULL
               AND c108.`circlems_id` <> \'\''
        );
        $converted = $db->affectedRows();

        $db->query(
            'UPDATE `c108_circles` c108
             JOIN `circles` c ON c.`webcatalog_circle_id` = c108.`circlems_id`
             SET c108.`circle_id` = c.`id`
             WHERE c108.`circlems_id` IS NOT NULL
               AND c108.`circlems_id` <> \'\''
        );
        $linked = $db->affectedRows();
        $db->transComplete();

        if ($db->transStatus() === false) {
            throw new RuntimeException('Circle.ms 綁定轉換失敗。');
        }

        return [
            'converted' => max(0, $converted),
            'linked' => max(0, $linked),
        ];
    }

    private function circleNameKey(string $name): string
    {
        return mb_strtolower(trim($name), 'UTF-8');
    }

    private function nullIfEmpty(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function catalogDateOrNull(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
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
            'comiketNo' => (int) ($row['comiketNo'] ?? 0),
            'circlemsId' => (string) ($row['circlemsId'] ?? ''),
            'updateId' => (string) ($row['updateId'] ?? ''),
            'updateData' => (string) ($row['updateData'] ?? ''),
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

    private function inspectImageSqlite(string $dbPath, int $eventId): array
    {
        if (! class_exists('SQLite3')) {
            return [
                'available' => false,
                'message' => 'PHP SQLite3 extension is not installed.',
                'tables' => [],
                'counts' => [],
                'columns' => [],
                'samples' => [],
                'map_filenames' => [],
                'map_matches' => [],
            ];
        }

        $db = new \SQLite3($dbPath, SQLITE3_OPEN_READONLY);
        $tables = [];
        $result = $db->query("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name");
        while ($result !== false && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            $tables[] = (string) $row['name'];
        }

        $counts = [];
        $columns = [];
        $samples = [];
        foreach ($tables as $table) {
            $counts[$table] = (int) $db->querySingle('SELECT COUNT(*) FROM "' . $table . '"');
            $columns[$table] = $this->sqliteColumns($db, $table);
            $samples[$table] = $this->sqliteSampleRows($db, $table, $columns[$table]);
        }

        $mapFilenames = $this->catalogMapFilenames($eventId);
        $mapMatches = $this->findImageDbMapMatches($db, $tables, $columns, $mapFilenames);

        $db->close();

        return [
            'available' => true,
            'message' => 'ok',
            'tables' => $tables,
            'counts' => $counts,
            'columns' => $columns,
            'samples' => $samples,
            'map_filenames' => $mapFilenames,
            'map_matches' => $mapMatches,
        ];
    }

    private function sqliteColumns(\SQLite3 $db, string $table): array
    {
        $columns = [];
        $result = $db->query('PRAGMA table_info("' . str_replace('"', '""', $table) . '")');
        while ($result !== false && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            $columns[] = [
                'name' => (string) ($row['name'] ?? ''),
                'type' => (string) ($row['type'] ?? ''),
            ];
        }

        return $columns;
    }

    private function sqliteSampleRows(\SQLite3 $db, string $table, array $columns): array
    {
        $rows = [];
        $result = $db->query('SELECT * FROM "' . str_replace('"', '""', $table) . '" LIMIT 3');
        while ($result !== false && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            $safeRow = [];
            foreach ($columns as $column) {
                $name = $column['name'];
                $value = $row[$name] ?? null;
                if ($value === null) {
                    $safeRow[$name] = null;
                    continue;
                }

                $text = (string) $value;
                if (! mb_check_encoding($text, 'UTF-8')) {
                    $safeRow[$name] = '[binary ' . strlen($text) . ' bytes]';
                    continue;
                }

                $safeRow[$name] = mb_strimwidth($text, 0, 160, '...', 'UTF-8');
            }
            $rows[] = $safeRow;
        }

        return $rows;
    }

    private function catalogMapFilenames(int $eventId): array
    {
        $dbPath = $this->catalogDbPath($eventId);
        if (! is_file($dbPath) || ! class_exists('SQLite3')) {
            return [];
        }

        $db = new \SQLite3($dbPath, SQLITE3_OPEN_READONLY);
        $filenames = [];
        $result = $db->query('SELECT filename, allFilename FROM ComiketMapWC ORDER BY id');
        while ($result !== false && ($row = $result->fetchArray(SQLITE3_ASSOC))) {
            foreach (['filename', 'allFilename'] as $key) {
                $filename = trim((string) ($row[$key] ?? ''));
                if ($filename !== '') {
                    $filenames[$filename] = true;
                }
            }
        }
        $db->close();

        return array_keys($filenames);
    }

    private function findImageDbMapMatches(\SQLite3 $db, array $tables, array $columnsByTable, array $mapFilenames): array
    {
        if ($mapFilenames === []) {
            return [];
        }

        $matches = [];
        foreach ($tables as $table) {
            $columns = array_column($columnsByTable[$table] ?? [], 'name');
            $textColumns = array_values(array_filter($columns, static function (string $column): bool {
                $name = strtolower($column);
                return str_contains($name, 'file')
                    || str_contains($name, 'name')
                    || str_contains($name, 'path')
                    || str_contains($name, 'url');
            }));

            foreach ($textColumns as $column) {
                foreach (array_slice($mapFilenames, 0, 20) as $filename) {
                    $statement = $db->prepare(
                        'SELECT COUNT(*) FROM "' . str_replace('"', '""', $table) . '" WHERE "' . str_replace('"', '""', $column) . '" = :filename'
                    );
                    if ($statement === false) {
                        continue;
                    }
                    $statement->bindValue(':filename', $filename, SQLITE3_TEXT);
                    $result = $statement->execute();
                    if ($result === false) {
                        $statement->close();
                        continue;
                    }
                    $countRow = $result->fetchArray(SQLITE3_NUM);
                    $count = (int) ($countRow[0] ?? 0);
                    $result->finalize();
                    $statement->close();
                    if ($count > 0) {
                        $matches[] = [
                            'table' => $table,
                            'column' => $column,
                            'filename' => $filename,
                            'count' => $count,
                        ];
                    }
                }
            }
        }

        return $matches;
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

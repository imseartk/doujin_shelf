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

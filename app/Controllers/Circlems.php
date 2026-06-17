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

        return view('circlems/index', [
            'configured' => $client->isConfigured(),
            'missingConfigKeys' => $client->missingConfigKeys(),
            'token' => $token,
            'isExpired' => $this->isTokenExpired($token),
            'circleSearch' => session('circlems_circle_search'),
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
        if ($circleName === '') {
            return redirect()->to('/circlems')->with('error', '請輸入社團名稱。');
        }

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

            $result = $client->queryCircle((string) $token['access_token'], $eventId, $circleName);
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
                'circleName' => $circleName,
                'eventId' => $eventId,
                'count' => (int) ($result['response']['count'] ?? 0),
                'maxCount' => (int) ($result['response']['maxcount'] ?? 0),
                'result' => $this->summarizeCircleResult($result),
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

    private function summarizeCircleResult(array $result): array
    {
        $list = $result['response']['list'] ?? [];
        if (! is_array($list)) {
            $list = [];
        }

        $result['response']['list'] = array_slice($list, 0, 10);

        return $result;
    }
}

<?php

namespace App\Libraries;

use RuntimeException;

class CirclemsClient
{
    private const DEFAULT_SCOPE = 'user_info circle_read circle_write favorite_read favorite_write';

    public function isConfigured(): bool
    {
        return $this->clientId() !== ''
            && $this->clientSecret() !== ''
            && $this->redirectUri() !== ''
            && $this->authBaseUrl() !== ''
            && $this->apiBaseUrl() !== '';
    }

    public function missingConfigKeys(): array
    {
        $keys = [];
        foreach ([
            'circlems.clientId' => $this->clientId(),
            'circlems.clientSecret' => $this->clientSecret(),
            'circlems.redirectUri' => $this->redirectUri(),
            'circlems.authBaseUrl' => $this->authBaseUrl(),
            'circlems.apiBaseUrl' => $this->apiBaseUrl(),
        ] as $key => $value) {
            if ($value === '') {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public function authorizationUrl(string $state): string
    {
        return $this->authUrl('/OAuth2/') . '?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'scope' => self::DEFAULT_SCOPE,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeCode(string $code): array
    {
        return $this->postToken([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
        ]);
    }

    public function refreshToken(string $refreshToken): array
    {
        return $this->postToken([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
        ]);
    }

    public function userInfo(string $accessToken): array
    {
        return $this->apiPost('/User/Info/', $accessToken);
    }

    public function eventList(string $accessToken): array
    {
        return $this->apiGet('/WebCatalog/GetEventList/', $accessToken);
    }

    public function queryCircle(string $accessToken, int $eventId, string $circleName = '', int $page = 1): array
    {
        $query = [
            'event_id' => $eventId,
            'page' => max(1, $page),
        ];

        if ($circleName !== '') {
            $query['circle_name'] = $circleName;
        }

        return $this->apiGet('/WebCatalog/QueryCircle/', $accessToken, $query);
    }

    public function circleDetail(string $accessToken, int $wcid, int $eventId = 0): array
    {
        $query = ['wcid' => $wcid];

        if ($eventId > 0) {
            $query['event_id'] = $eventId;
        }

        return $this->apiGet('/WebCatalog/GetCircle/', $accessToken, $query);
    }

    public function queryBooks(string $accessToken, int $eventId, int $wcid, int $page = 1): array
    {
        return $this->apiGet('/WebCatalog/QueryBook/', $accessToken, [
            'event_id' => $eventId,
            'wcid' => $wcid,
            'page' => max(1, $page),
        ]);
    }

    public function tokenExpiresAt(array $tokenResponse): ?string
    {
        $expiresIn = (int) ($tokenResponse['expires_in'] ?? 0);
        if ($expiresIn <= 0) {
            return null;
        }

        return date('Y-m-d H:i:s', time() + $expiresIn);
    }

    private function postToken(array $form): array
    {
        [$statusCode, $body] = $this->request('POST', $this->authUrl('/OAuth2/Token'), [
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS => http_build_query($form, '', '&', PHP_QUERY_RFC3986),
        ]);

        return $this->decodeResponse($statusCode, $body, 'token');
    }

    private function apiGet(string $path, string $accessToken, array $query = []): array
    {
        $query = ['access_token' => $accessToken] + $query;
        $url = $this->apiUrl($path) . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        [$statusCode, $body] = $this->request('GET', $url, [
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
        ]);

        return $this->decodeResponse($statusCode, $body, 'api');
    }

    private function apiPost(string $path, string $accessToken): array
    {
        [$statusCode, $body] = $this->request('POST', $this->apiUrl($path), [
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_POSTFIELDS => http_build_query(['access_token' => $accessToken], '', '&', PHP_QUERY_RFC3986),
        ]);

        return $this->decodeResponse($statusCode, $body, 'api');
    }

    private function request(string $method, string $url, array $options = []): array
    {
        if (! function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for Circle.ms requests.');
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Unable to initialize Circle.ms request.');
        }

        $defaultOptions = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CUSTOMREQUEST => $method,
        ];

        foreach ($defaultOptions + $options as $option => $value) {
            curl_setopt($handle, $option, $value);
        }

        $body = curl_exec($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($body === false) {
            throw new RuntimeException('Circle.ms request failed: ' . $error);
        }

        return [$statusCode, (string) $body];
    }

    private function decodeResponse(int $statusCode, string $body, string $context): array
    {
        $data = json_decode($body, true);

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(sprintf(
                'Circle.ms %s request failed with HTTP %d. %s',
                $context,
                $statusCode,
                $this->safeBodySummary($body)
            ));
        }

        if (! is_array($data)) {
            throw new RuntimeException(sprintf('Circle.ms %s response was not JSON.', $context));
        }

        if (isset($data['error'])) {
            throw new RuntimeException('Circle.ms error: ' . (string) $data['error']);
        }

        return $data;
    }

    private function authUrl(string $path): string
    {
        return rtrim($this->authBaseUrl(), '/') . '/' . ltrim($path, '/');
    }

    private function apiUrl(string $path): string
    {
        return rtrim($this->apiBaseUrl(), '/') . '/' . ltrim($path, '/');
    }

    private function safeBodySummary(string $body): string
    {
        $body = preg_replace('/\s+/u', ' ', trim(strip_tags($body))) ?? '';
        if ($body === '') {
            return '';
        }

        return mb_substr($body, 0, 180, 'UTF-8');
    }

    private function clientId(): string
    {
        return $this->envValue('circlems.clientId', 'CIRCLEMS_CLIENT_ID');
    }

    private function clientSecret(): string
    {
        return $this->envValue('circlems.clientSecret', 'CIRCLEMS_CLIENT_SECRET');
    }

    private function redirectUri(): string
    {
        return $this->envValue('circlems.redirectUri', 'CIRCLEMS_REDIRECT_URI');
    }

    private function authBaseUrl(): string
    {
        return $this->envValue('circlems.authBaseUrl', 'CIRCLEMS_AUTH_BASE_URL');
    }

    private function apiBaseUrl(): string
    {
        return $this->envValue('circlems.apiBaseUrl', 'CIRCLEMS_API_BASE_URL');
    }

    private function envValue(string $dotKey, string $upperKey): string
    {
        return trim((string) (env($dotKey, '') ?: env($upperKey, '')));
    }
}

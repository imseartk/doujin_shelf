<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;

final class TicketBrowser extends BaseController
{
    private function headers(): void
    {
        $this->response->setHeader('Cache-Control', 'no-store, private')
            ->setHeader('X-Frame-Options', 'DENY')->setHeader('Referrer-Policy', 'no-referrer')
            ->setHeader('X-Content-Type-Options', 'nosniff');
    }

    public function index(): string
    {
        $this->headers();
        return view('ticket_browser/index');
    }

    public function command(): ResponseInterface
    {
        $this->headers();
        $reply = fn(array $data) => $this->response->setJSON($data + ['csrf' => csrf_hash()]);
        $action = (string) $this->request->getPost('action');
        if (!in_array($action, ['start', 'input', 'close'], true)) {
            return $reply(['error' => 'Unsupported action']);
        }
        $state = session('ticket_browser') ?? [];
        $payload = ['action' => $action, 'token' => $state['token'] ?? '', 'generation' => $state['generation'] ?? ''];
        if ($action === 'start') {
            $payload['url'] = 'https://ticketpay.jp/';
        } elseif (!$state) {
            return $reply(['error' => 'Open the browser first.']);
        }
        if ($action === 'input') {
            foreach (['kind', 'key', 'text', 'x', 'y', 'delta'] as $key) {
                $payload[$key] = $this->request->getPost($key);
            }
            if (strlen((string) ($payload['text'] ?? '')) > 2000) {
                return $reply(['error' => 'Text is too long.']);
            }
        }
        try {
            $result = $this->agent($payload);
            if ($action === 'start') {
                session()->set('ticket_browser', ['token' => $result['token'], 'generation' => $result['generation']]);
                unset($result['token']);
            } elseif ($action === 'close') {
                session()->remove('ticket_browser');
            }
            return $reply($result);
        } catch (\RuntimeException $error) {
            if ($error->getCode() === 401) session()->remove('ticket_browser');
            return $reply(['error' => $error->getMessage()]);
        }
    }

    public function frame(): ResponseInterface
    {
        $this->headers();
        $state = session('ticket_browser') ?? [];
        if (!$state) return $this->response->setStatusCode(401)->setBody('No session');
        try {
            $body = $this->agent(['action' => 'frame'] + $state, true);
            return $this->response->setContentType('image/jpeg')->setBody($body);
        } catch (\RuntimeException $error) {
            return $this->response->setStatusCode(503)->setBody('Browser unavailable');
        }
    }

    private function agent(array $payload, bool $image = false): array|string
    {
        $curl = curl_init('http://localhost/command');
        curl_setopt_array($curl, [CURLOPT_UNIX_SOCKET_PATH => '/run/doujin-ticket-browser/agent.sock',
            CURLOPT_PROXY => '', CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => $payload['action'] === 'start' ? 45 : 12]);
        $body = curl_exec($curl);
        $code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if ($body === false || $code !== 200) {
            throw new \RuntimeException(match ($code) {
                401 => 'Session expired. Reopen the browser.',
                409 => 'Browser is in use by another session.',
                default => 'Remote browser unavailable. Please retry.',
            }, $code);
        }
        return $image ? $body : json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }
}

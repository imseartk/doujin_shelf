<?php

namespace App\Libraries;

use RuntimeException;

class LineBotNotifier
{
    private const PUSH_ENDPOINT = 'https://api.line.me/v2/bot/message/push';

    public function isEnabled(): bool
    {
        return filter_var((string) env('line.bot.enabled', 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    public function canSend(): bool
    {
        return $this->isEnabled()
            && $this->channelAccessToken() !== ''
            && $this->userId() !== '';
    }

    public function pushText(string $message): void
    {
        if (! $this->canSend()) {
            return;
        }

        $payload = [
            'to' => $this->userId(),
            'messages' => [
                [
                    'type' => 'text',
                    'text' => $message,
                ],
            ],
        ];

        $ch = curl_init(self::PUSH_ENDPOINT);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->channelAccessToken(),
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 15,
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status < 200 || $status >= 300) {
            $message = $error !== '' ? $error : (string) $body;
            throw new RuntimeException('LINE push failed with HTTP ' . $status . ': ' . $message);
        }
    }

    private function channelAccessToken(): string
    {
        return trim((string) env('line.bot.channelAccessToken', ''));
    }

    private function userId(): string
    {
        return trim((string) env('line.bot.userId', ''));
    }
}

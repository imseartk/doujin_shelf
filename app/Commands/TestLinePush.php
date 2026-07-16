<?php

namespace App\Commands;

use App\Libraries\LineBotNotifier;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;

class TestLinePush extends BaseCommand
{
    protected $group = 'LINE';
    protected $name = 'line:test-push';
    protected $description = 'Send a test LINE push message using the configured Messaging API credentials.';
    protected $usage = 'line:test-push [message]';
    protected $arguments = [
        'message' => 'Optional test message text.',
    ];

    public function run(array $params): void
    {
        $message = trim(implode(' ', $params));
        if ($message === '') {
            $message = 'Doujin Shelf LINE push test: ' . date('Y-m-d H:i:s');
        }

        $notifier = new LineBotNotifier();
        if (! $notifier->canSend()) {
            CLI::error('LINE push is disabled or incomplete. Check line.bot.enabled, line.bot.channelAccessToken, and line.bot.userId.');
            return;
        }

        try {
            $notifier->pushText($message);
        } catch (RuntimeException $exception) {
            CLI::error($exception->getMessage());
            return;
        }

        CLI::write('LINE test push sent.', 'green');
    }
}

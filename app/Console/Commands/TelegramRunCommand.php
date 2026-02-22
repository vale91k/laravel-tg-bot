<?php

namespace App\Console\Commands;

use App\Bot\Client\AiClientInterface;
use Illuminate\Console\Command;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\Polling;
use SergiX44\Nutgram\Telegram\Properties\MessageType;

class TelegramRunCommand extends Command
{
    protected $signature = 'telegram:run';

    protected $description = 'Запуск Telegram-бота (long polling)';

    public function handle(AiClientInterface $aiClient): int
    {
        $token = config('telegram.token');

        if ($token === null || $token === '') {
            $this->error('TELEGRAM_BOT_TOKEN не задан в .env');

            return self::FAILURE;
        }

        $bot = new Nutgram($token);
        $bot->setRunningMode(Polling::class);

        $bot->onCommand('start', function (Nutgram $bot) {
            $bot->sendMessage(
                'Привет! Напиши что-нибудь — отвечу с помощью ИИ.'
            );
        });

        $bot->onMessageType(MessageType::TEXT, function (Nutgram $bot) use ($aiClient) {
            $text = $bot->message()?->getText();

            if ($text === null || trim($text) === '') {
                $bot->sendMessage('Отправь текстовое сообщение.');

                return;
            }

            try {
                $reply = $aiClient->reply(
                    $text,
                    'Ты дружелюбный помощник в Telegram-боте. Отвечай кратко и по делу.'
                );
                $bot->sendMessage($reply);
            } catch (\Throwable $e) {
                $bot->sendMessage('Ошибка при обращении к ИИ. Попробуй позже.');
                $this->error($e->getMessage());
            }
        });

        $this->info('Бот запущен (long polling). Остановка: Ctrl+C');
        $bot->run();

        return self::SUCCESS;
    }
}

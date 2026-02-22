<?php

namespace App\Console\Commands;

use App\Bot\Client\AiClientInterface;
use App\Bot\Services\ReplyFormatter;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Configuration;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\Polling;
use SergiX44\Nutgram\Telegram\Properties\MessageType;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;

class TelegramRunCommand extends Command
{
    protected $signature = 'telegram:run';

    protected $description = 'Запуск Telegram-бота (long polling)';

    public function handle(AiClientInterface $aiClient, ReplyFormatter $replyFormatter): int
    {
        $token = config('telegram.token');

        if ($token === null || $token === '') {
            $this->error('TELEGRAM_BOT_TOKEN не задан в .env');

            return self::FAILURE;
        }

        $clientOptions = [
            // Принудительно IPv4 — часто PHP/cURL таймаутит по IPv6, тогда как curl из терминала идёт по IPv4.
            'force_ip_resolve' => 'v4',
        ];
        if (config('telegram.proxy')) {
            $clientOptions['proxy'] = config('telegram.proxy');
        }

        $config = new Configuration(
            clientTimeout: config('telegram.client_timeout', 30),
            clientOptions: $clientOptions,
            pollingTimeout: config('telegram.polling_timeout', 30),
            enableHttp2: config('telegram.enable_http2', false),
        );

        $bot = new Nutgram($token, $config);
        $bot->setRunningMode(Polling::class);

        $bot->onCommand('start', function (Nutgram $bot) {
            $bot->sendMessage(
                'Привет! Я Бобик — бот с нейросетью. Напиши что-нибудь или нажми /help.'
            );
        });

        $bot->onCommand('help', function (Nutgram $bot) {
            $bot->sendMessage(
                "Что я умею?\n\n"
                . "/start — запустить бота\n"
                . "/help — эта справка\n"
                . "/ask — подсказка, как спросить нейросеть\n"
                . "/about — о проекте\n\n"
                . "Просто напиши текст — и я передам его нейросети и пришлю ответ."
            );
        });

        $bot->onCommand('ask', function (Nutgram $bot) {
            $bot->sendMessage(
                'Напиши свой вопрос обычным сообщением (без команды) — я передам его нейросети и пришлю ответ.'
            );
        });

        $bot->onCommand('about', function (Nutgram $bot) {
            $bot->sendMessage(
                'Бобик AI — Telegram-бот на Laravel с ответами от нейросети (DeepSeek и др.).'
            );
        });

        $bot->onMessageType(MessageType::TEXT, function (Nutgram $bot) use ($aiClient, $replyFormatter) {
            $text = $bot->message()?->getText();
            $chatId = $bot->chatId();
            $userId = $bot->userId();

            if ($text === null || trim($text) === '') {
                $bot->sendMessage('Отправь текстовое сообщение.');

                return;
            }

            if (str_starts_with(trim($text), '/')) {
                $bot->sendMessage('Используй кнопку меню или введи команду заново. Непонятная команда.');

                return;
            }

            Log::channel('single')->info('Telegram incoming', [
                'chat_id' => $chatId,
                'user_id' => $userId,
                'text' => mb_substr($text, 0, 500),
            ]);

            try {
                $reply = $aiClient->reply(
                    $text,
                    'Ты дружелюбный помощник в Telegram-боте. Отвечай кратко и по делу.'
                );
                $useHtml = config('telegram.reply_format', 'plain') === 'html';
                if ($useHtml) {
                    $formatted = $replyFormatter->formatToTelegramHtml($reply);
                    $bot->sendMessage($formatted, parse_mode: ParseMode::HTML);
                } else {
                    $formatted = $replyFormatter->stripMarkdownToPlain($reply);
                    $bot->sendMessage($formatted);
                }

                Log::channel('single')->info('Telegram outgoing', [
                    'chat_id' => $chatId,
                    'text' => mb_substr($reply, 0, 300),
                ]);
            } catch (\Throwable $e) {
                $this->error((string) $e);

                $userMessage = 'Ошибка при обращении к ИИ. Попробуй позже.';
                if (str_contains($e->getMessage(), 'Insufficient Balance') || str_contains($e->getMessage(), '402')) {
                    $userMessage = 'Сейчас нейросеть недоступна: закончился баланс API. '
                        . 'Владелец бота может пополнить счёт в кабинете DeepSeek.';
                }
                $bot->sendMessage($userMessage);
            }
        });

        $this->info('Бот запущен (long polling). Остановка: Ctrl+C');

        while (true) {
            try {
                $bot->run();
                break;
            } catch (ConnectException $e) {
                $this->warn('Таймаут подключения к Telegram: ' . $e->getMessage());
                Log::channel('single')->warning('Telegram connection timeout', ['message' => $e->getMessage()]);
                $this->info('Перезапуск через 5 сек...');
                sleep(5);
            }
        }

        return self::SUCCESS;
    }
}

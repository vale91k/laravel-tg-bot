<?php

namespace App\Bot\Client\DeepSeek;

use App\Bot\Client\AiClientInterface;
use Illuminate\Support\Facades\Log;
use PvSource\Aivory\Client as AivoryClient;
use PvSource\Aivory\LLM\Turn\Turn;

/**
 * Клиент ИИ через DeepSeek (библиотека pv-source/aivory).
 *
 * Параметры только свои: config/ai.php → ключ "deepseek", в .env — DEEPSEEK_API_KEY, DEEPSEEK_MODEL и т.д.
 */
class DeepSeekClient implements AiClientInterface
{
    private const PROVIDER_NAME = 'deepseek';

    private const DEFAULT_MODEL = 'deepseek-chat';

    private ?AivoryClient $client = null;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly float $temperature,
        private readonly int $maxTokens
    ) {
    }

    public static function fromConfig(): self
    {
        $config = config('ai.deepseek', []);

        return new self(
            apiKey: $config['api_key'] ?? '',
            model: $config['model'] ?? self::DEFAULT_MODEL,
            temperature: (float) ($config['temperature'] ?? 0.7),
            maxTokens: (int) ($config['max_tokens'] ?? 1000),
        );
    }

    private function getClient(): AivoryClient
    {
        if ($this->client === null) {
            $this->client = new AivoryClient(
                providerName: self::PROVIDER_NAME,
                auth: ['apiKey' => $this->apiKey],
            );
        }

        return $this->client;
    }

    public function reply(string $userMessage, ?string $systemPrompt = null): string
    {
        if ($this->apiKey === '') {
            throw (new \RuntimeException('DEEPSEEK_API_KEY не задан в .env'));
        }

        Log::channel('deepseek')->info('request', [
            'user_message' => mb_substr($userMessage, 0, 2000),
            'system_prompt' => $systemPrompt !== null && $systemPrompt !== '' ? mb_substr($systemPrompt, 0, 500) : null,
        ]);

        $provider = $this->getClient()->getProvider();

        $builder = Turn::query()
            ->withProvider($provider)
            ->setUserPrompt($userMessage)
            ->setModel($this->model)
            ->setTemperature($this->temperature)
            ->setMaxTokens($this->maxTokens);

        if ($systemPrompt !== null && $systemPrompt !== '') {
            $builder = $builder->setSystemPrompt($systemPrompt);
        }

        $turn = $builder->send();
        $response = $turn->getResponse();
        $assistant = $response->getAssistant();
        $reply = trim($assistant->content ?? '');

        Log::channel('deepseek')->info('response', [
            'text' => mb_substr($reply, 0, 2000),
        ]);

        return $reply;
    }
}

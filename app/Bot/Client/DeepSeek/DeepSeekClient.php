<?php

namespace App\Bot\Client\DeepSeek;

use App\Bot\Client\AiClientInterface;
use PvSource\Aivory\Client as AivoryClient;
use PvSource\Aivory\LLM\Turn\Turn;

/**
 * Клиент ИИ через DeepSeek (библиотека pv-source/aivory).
 *
 * Конфигурация в config/ai.php, ключи: AI_API_KEY, AI_MODEL, AI_TEMPERATURE, AI_MAX_TOKENS.
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
        return new self(
            apiKey: config('ai.api_key', ''),
            model: config('ai.model', self::DEFAULT_MODEL),
            temperature: (float) config('ai.temperature', 0.7),
            maxTokens: (int) config('ai.max_tokens', 1000),
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
            throw (new \RuntimeException('AI_API_KEY не задан в .env'));
        }

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

        return trim($assistant->content ?? '');
    }
}

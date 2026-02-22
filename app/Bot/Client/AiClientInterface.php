<?php

namespace App\Bot\Client;

interface AiClientInterface
{
    /**
     * Отправить сообщение в ИИ и вернуть текстовый ответ.
     *
     * @param  string  $userMessage  Текст от пользователя
     * @param  string|null  $systemPrompt  Системный промпт (роль/контекст бота), по желанию
     * @return string Ответ ассистента
     */
    public function reply(string $userMessage, ?string $systemPrompt = null): string;
}

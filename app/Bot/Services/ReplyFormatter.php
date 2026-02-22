<?php

namespace App\Bot\Services;

/**
 * Обрабатывает ответ нейросети перед отправкой в Telegram.
 */
class ReplyFormatter
{
    /** Символы «больше»/«меньше» (Unicode), чтобы не слать < > в HTML и не показывать &lt;/&gt;. */
    private const CHAR_GT = '›';  // U+203A
    private const CHAR_LT = '‹';  // U+2039

    /**
     * Форматирует сырой ответ ИИ для Telegram (HTML).
     * **жирный** → <b>жирный</b>, *курсив* → <i>курсив</i>, `код` → <code>код</code>.
     * Сравнения: &gt;/&lt; заменяются на символы ›/‹ — Telegram не декодирует &amp;lt; в <.
     */
    public function formatToTelegramHtml(string $raw): string
    {
        $text = trim($raw);
        $text = $this->convertMarkdownToHtml($text);
        // После e() сущности уже в виде &amp;gt; и &amp;lt;, поэтому заменяем оба варианта.
        $text = str_replace(
            ['&amp;gt;', '&amp;lt;', '&gt;', '&lt;'],
            [self::CHAR_GT, self::CHAR_LT, self::CHAR_GT, self::CHAR_LT],
            $text
        );

        return $text;
    }

    /**
     * Убирает только разметку Markdown, возвращает обычный текст.
     * Отправляй без parse_mode — тогда "D < 0" и "D > 0" отображаются без &lt;/&gt;.
     */
    public function stripMarkdownToPlain(string $raw): string
    {
        $text = trim($raw);
        $text = preg_replace('/```\w*\n?(.*?)```/s', '$1', $text);
        $text = preg_replace('/`([^`]+)`/', '$1', $text);
        $text = preg_replace('/\*\*(.+?)\*\*/s', '$1', $text);
        $text = preg_replace('/__(.+?)__/s', '$1', $text);
        $text = preg_replace('/(?<!\w)\*(.+?)\*(?!\w)/s', '$1', $text);
        $text = preg_replace('/(?<!\w)_(.+?)_(?!\w)/s', '$1', $text);
        $text = preg_replace('/^###?\s+/m', '', $text);

        return $text;
    }

    private function convertMarkdownToHtml(string $text): string
    {
        // Блоки кода ```...``` → <pre>...</pre>
        $text = preg_replace_callback('/```(\w*)\n?(.*?)```/s', function ($m) {
            $code = $this->e($m[2]);

            return '<pre>' . $code . '</pre>';
        }, $text);

        // Инлайн `код`
        $text = preg_replace_callback('/`([^`]+)`/', function ($m) {
            return '<code>' . $this->e($m[1]) . '</code>';
        }, $text);

        // **жирный** и __жирный__
        $text = preg_replace_callback('/\*\*(.+?)\*\*/s', fn ($m) => '<b>' . $this->e($m[1]) . '</b>', $text);
        $text = preg_replace_callback('/__(.+?)__/s', fn ($m) => '<b>' . $this->e($m[1]) . '</b>', $text);

        // *курсив*
        $text = preg_replace_callback('/(?<!\w)\*(.+?)\*(?!\w)/s', fn ($m) => '<i>' . $this->e($m[1]) . '</i>', $text);
        $text = preg_replace_callback('/(?<!\w)_(.+?)_(?!\w)/s', fn ($m) => '<i>' . $this->e($m[1]) . '</i>', $text);

        // ### Заголовок
        $text = preg_replace_callback('/^###?\s+(.+)$/m', fn ($m) => '<b>' . $this->e(trim($m[1])) . '</b>', $text);

        // Экранируем оставшиеся <, >, & (теги уже вставлены, защищаем их плейсхолдерами)
        $placeholders = [];
        $i = 0;
        $text = preg_replace_callback('/<\/?(?:b|i|code|pre)>/', function ($m) use (&$placeholders, &$i) {
            $key = "\x00P" . ($i++) . "\x00";
            $placeholders[$key] = $m[0];

            return $key;
        }, $text);
        $text = $this->e($text);
        $text = str_replace(array_keys($placeholders), array_values($placeholders), $text);

        return $text;
    }

    /**
     * Экранирование для Telegram HTML: & < >.
     * Без этого Telegram воспринимает "D < 0" как тег и возвращает 400.
     */
    private function e(string $s): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
    }

}

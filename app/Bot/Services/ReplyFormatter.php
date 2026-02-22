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
        // 1. Сначала выносим блоки кода в плейсхолдеры, чтобы _ и * внутри не превращались в курсив/жирный
        $codePlaceholders = [];
        $idx = 0;

        $text = preg_replace_callback('/```(\w*)\n?(.*?)```/s', function ($m) use (&$codePlaceholders, &$idx) {
            $key = "\x00CODE" . ($idx++) . "\x00";
            $codePlaceholders[$key] = '<pre>' . $this->e($m[2]) . '</pre>';

            return $key;
        }, $text);

        $text = preg_replace_callback('/`([^`]+)`/', function ($m) use (&$codePlaceholders, &$idx) {
            $key = "\x00CODE" . ($idx++) . "\x00";
            $codePlaceholders[$key] = '<code>' . $this->e($m[1]) . '</code>';

            return $key;
        }, $text);

        // 2. Жирный и курсив (внутри кода уже нет — он в плейсхолдерах)
        $text = preg_replace_callback('/\*\*(.+?)\*\*/s', fn ($m) => '<b>' . $this->e($m[1]) . '</b>', $text);
        $text = preg_replace_callback('/__(.+?)__/s', fn ($m) => '<b>' . $this->e($m[1]) . '</b>', $text);
        $text = preg_replace_callback('/(?<!\w)\*(.+?)\*(?!\w)/s', fn ($m) => '<i>' . $this->e($m[1]) . '</i>', $text);
        $text = preg_replace_callback('/(?<!\w)_(.+?)_(?!\w)/s', fn ($m) => '<i>' . $this->e($m[1]) . '</i>', $text);
        $text = preg_replace_callback('/^###?\s+(.+)$/m', fn ($m) => '<b>' . $this->e(trim($m[1])) . '</b>', $text);

        // 3. Экранируем оставшиеся <, >, &; теги защищаем плейсхолдерами
        $tagPlaceholders = [];
        $i = 0;
        $text = preg_replace_callback('/<\/?(?:b|i|code|pre)>/', function ($m) use (&$tagPlaceholders, &$i) {
            $key = "\x00P" . ($i++) . "\x00";
            $tagPlaceholders[$key] = $m[0];

            return $key;
        }, $text);
        $text = $this->e($text);
        $text = str_replace(array_keys($tagPlaceholders), array_values($tagPlaceholders), $text);

        // 4. Возвращаем блоки кода на место
        $text = str_replace(array_keys($codePlaceholders), array_values($codePlaceholders), $text);

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

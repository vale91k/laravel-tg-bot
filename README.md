# Бобик AI — Telegram-бот на Laravel с нейросетью

Telegram-бот с ответами от ИИ (DeepSeek и др.): long polling, форматирование ответов, логирование, авто-перезапуск при таймаутах.

## Возможности

- Команды: `/start`, `/help`, `/ask`, `/about`
- Ответы на текстовые сообщения через выбранный AI-клиент (DeepSeek по умолчанию)
- Форматирование ответов нейросети в HTML для Telegram (**жирный**, *курсив*, `код`)
- Логирование входящих и исходящих сообщений (Laravel log)
- Автоматический перезапуск при таймауте подключения к Telegram API
- Поддержка прокси и принудительного IPv4 при проблемах с сетью

## Требования

- PHP 8.2+
- Composer
- Токен бота от [@BotFather](https://t.me/BotFather)
- API-ключ провайдера ИИ (например [DeepSeek](https://platform.deepseek.com))

## Установка

```bash
git clone <repo> laravel-tg-bot && cd laravel-tg-bot
composer install
cp .env.example .env
php artisan key:generate
```

## Настройка (.env)

### Telegram

| Переменная | Описание |
|------------|----------|
| `TELEGRAM_BOT_TOKEN` | Токен бота от @BotFather (обязательно) |
| `TELEGRAM_CLIENT_TIMEOUT` | Таймаут HTTP-запросов к Telegram (сек), по умолчанию 30 |
| `TELEGRAM_POLLING_TIMEOUT` | Таймаут long polling (сек), по умолчанию 30 |
| `TELEGRAM_PROXY` | Прокси при необходимости, например `socks5://127.0.0.1:1080` |
| `TELEGRAM_ENABLE_HTTP2` | Включить HTTP/2 (по умолчанию `false` из-за таймаутов на части хостингов) |

### ИИ (DeepSeek)

| Переменная | Описание |
|------------|----------|
| `AI_CLIENT` | Драйвер: `deepseek` (по умолчанию) |
| `DEEPSEEK_API_KEY` | API-ключ DeepSeek (обязательно для ответов) |
| `DEEPSEEK_MODEL` | Модель, по умолчанию `deepseek-chat` |
| `DEEPSEEK_TEMPERATURE` | Температура генерации (0–1) |
| `DEEPSEEK_MAX_TOKENS` | Максимум токенов в ответе |

## Запуск

```bash
php artisan telegram:run
```

Остановка: **Ctrl+C**. При таймауте подключения к Telegram бот сам перезапустится через 5 секунд.

## Структура проекта

```
app/
  Bot/
    Client/              # Клиенты ИИ (интерфейс + реализации)
      AiClientInterface.php
      DeepSeek/
        DeepSeekClient.php
    Services/            # Сервисы бота
      ReplyFormatter.php # Markdown → HTML для Telegram
  Console/Commands/
    TelegramRunCommand.php
config/
  ai.php                 # Драйвер ИИ и параметры по клиентам
  telegram.php           # Токен, таймауты, прокси
```

Подробный план разработки и тестирования — в [SETUP.md](SETUP.md).

## Логирование

- **Входящие и исходящие сообщения** пишутся в лог Laravel (`storage/logs/laravel.log`) с тегами `Telegram incoming` и `Telegram outgoing` (chat_id, user_id, обрезка текста).
- Таймауты подключения к Telegram логируются как `Telegram connection timeout`.

При необходимости можно добавить запись в PostgreSQL (отдельная миграция и модель, подписка на события или вызов из обработчика).

## Лицензия

MIT.

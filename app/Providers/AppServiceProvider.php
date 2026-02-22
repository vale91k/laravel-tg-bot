<?php

namespace App\Providers;

use App\Bot\Client\AiClientInterface;
use App\Bot\Client\DeepSeek\DeepSeekClient;
use App\Bot\Services\ReplyFormatter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AiClientInterface::class, function () {
            return match (config('ai.client', 'deepseek')) {
                'deepseek' => DeepSeekClient::fromConfig(),
                default => throw (new \InvalidArgumentException(
                    'Неизвестный AI-клиент: '.config('ai.client').'. Доступно: deepseek.'
                )),
            };
        });

        $this->app->singleton(ReplyFormatter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (['tg', 'deepseek'] as $dir) {
            $path = storage_path('logs/' . $dir);
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }
}

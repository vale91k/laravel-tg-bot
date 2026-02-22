<?php

namespace App\Providers;

use App\Bot\Client\AiClientInterface;
use App\Bot\Client\DeepSeek\DeepSeekClient;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

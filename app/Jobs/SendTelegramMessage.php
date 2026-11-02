<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendTelegramMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var int[] */
    public array $backoff = [10, 60];

    public function __construct(public string $html) {}

    public function handle(): void
    {
        $token = config('services.telegram.token');
        $chatId = config('services.telegram.chat_id');
        if (blank($token) || blank($chatId)) {
            return;
        }

        try {
            $response = Http::timeout(10)->asForm()->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $this->html,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);
        } catch (ConnectionException) {
            // Không log exception gốc: message của nó chứa URL có token bot.
            Log::warning('Telegram: không kết nối được, sẽ thử lại.');
            $this->release(30);

            return;
        }

        if ($response->failed()) {
            Log::warning('Telegram: sendMessage lỗi', ['status' => $response->status(), 'body' => $response->body()]);
            // 4xx (sai token/chat id) thử lại cũng vô ích.
            if ($response->clientError() && $response->status() !== 429) {
                $this->fail();
            } else {
                $this->release(30);
            }
        }
    }
}

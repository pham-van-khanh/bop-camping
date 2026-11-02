<?php

namespace App\Services;

use App\Jobs\SendTelegramMessage;

/**
 * Báo tin cho chủ shop qua Telegram bot — NGUỒN DUY NHẤT gửi ra Telegram.
 *
 * Thiếu TELEGRAM_BOT_TOKEN hoặc TELEGRAM_CHAT_ID thì im lặng bỏ qua (dev/test không cần
 * cấu hình). Gửi qua queue để Telegram chậm/sập không làm chậm trang khách.
 */
class TelegramNotifier
{
    public function enabled(): bool
    {
        return filled(config('services.telegram.token')) && filled(config('services.telegram.chat_id'));
    }

    /** @param string $html Nội dung HTML (chỉ <b>, <i>, <a>); giá trị khách nhập phải qua e() trước. */
    public function send(string $html): void
    {
        if (! $this->enabled()) {
            return;
        }

        SendTelegramMessage::dispatch($html);
    }
}

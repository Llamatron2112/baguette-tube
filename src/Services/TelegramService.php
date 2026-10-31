<?php

namespace App\Services;

use App\Entity\Video;
use Luzrain\TelegramBotApi\BotApi;
use Luzrain\TelegramBotApi\Exception\TelegramApiServerException;
use Luzrain\TelegramBotApi\Method;
use Psr\Log\LoggerInterface;

class TelegramService
{
    public function __construct(
        private readonly BotApi $botApi,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function publishToTelegram(Video $video, string $username): void
    {
        if (!isset($_ENV['TELEGRAM_API_TOKEN'], $_ENV['TELEGRAM_CHAT_ID'])) {
            return;
        }

        $identifier = $video->getIdentifier();
        $channel = $video->getChannel()->getName();
        $title = $video->getTitle();

        try {
            $this->botApi->call(new Method\SendMessage(
                chatId: $_ENV['TELEGRAM_CHAT_ID'],
                text: "@$username vous recommande $title sur $channel https://www.youtube.com/watch?v=$identifier",
            ));
        } catch (TelegramApiServerException $e) {
            if ($e->getPrevious() === null) {
                // A response was received (e.g. "200 Unexpected server response") but the body
                // could not be parsed. The message was most likely delivered already, so do not
                // let Messenger retry and post it again.
                $this->logger->warning('Telegram response could not be parsed; assuming delivery', [
                    'exception' => $e,
                ]);

                return;
            }

            // Transport failure (connection refused, timeout, ...): not delivered.
            // Rethrow so only this Telegram message is retried.
            throw $e;
        }
    }
}

<?php

namespace App\MessageHandler;

use App\Message\TelegramPost;
use App\Repository\UserRepository;
use App\Repository\VideoRepository;
use App\Services\TelegramService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class TelegramPostHandler
{
    public function __construct(
        private readonly VideoRepository $videos,
        private readonly UserRepository $users,
        private readonly TelegramService $telegram,
    ) {
    }

    public function __invoke(TelegramPost $message): void
    {
        $video = $this->videos->find($message->getVideoId());
        $user = $this->users->findOneBy(['email' => $message->getUserId()]);

        $this->telegram->publishToTelegram($video, $user->getUsername());
    }
}

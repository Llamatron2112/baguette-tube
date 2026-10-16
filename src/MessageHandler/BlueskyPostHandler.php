<?php

namespace App\MessageHandler;

use App\Message\BlueskyPost;
use App\Repository\UserRepository;
use App\Repository\VideoRepository;
use App\Services\BlueskyService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class BlueskyPostHandler
{
    public function __construct(
        private VideoRepository $videos,
        private UserRepository $users,
    ) {}

    public function __invoke(BlueskyPost $message): void
    {
        $video = $this->videos->find($message->getVideoId());
        $user = $this->users->findOneBy(["email" => $message->getUserId()]);

        new BlueskyService()->publish($video, $user->getUsername());
    }
}

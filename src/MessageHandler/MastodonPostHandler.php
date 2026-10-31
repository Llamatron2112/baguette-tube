<?php

namespace App\MessageHandler;

use App\Message\MastodonPost;
use App\Repository\UserRepository;
use App\Repository\VideoRepository;
use App\Services\MastodonService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class MastodonPostHandler
{
    public function __construct(
        private readonly VideoRepository $videos,
        private readonly UserRepository $users,
        private readonly MastodonService $mastodon,
    ) {
    }

    public function __invoke(MastodonPost $message): void
    {
        $video = $this->videos->find($message->getVideoId());
        $user = $this->users->findOneBy(['email' => $message->getUserId()]);

        $this->mastodon->publishToMastodon($video, $user->getUsername());
    }
}

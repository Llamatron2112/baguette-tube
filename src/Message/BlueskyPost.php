<?php

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;
use Symfony\Component\Uid\UuidV7;

#[AsMessage('async')]
class BlueskyPost
{
    public function __construct(private UuidV7 $videoId, private string $userId) {}

    public function getVideoId(): UuidV7
    {
        return $this->videoId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }
}

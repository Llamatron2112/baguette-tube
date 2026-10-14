<?php

namespace App\Scheduler;

use App\Repository\ChannelRepository;
use App\Services\ChannelService;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsPeriodicTask(frequency: 5)]
class UpdateChannel
{
    public function __construct(readonly private ChannelRepository $repository, readonly private ChannelService $service)
    {
    }

    public function __invoke(): void
    {
        $channel = $this->repository->findOneBy(['isActive' => true], ['updatedAt' => 'ASC']);

    if (null === $channel) {
        return;
    }

        $this->service->refreshFeed($channel);
    }
}

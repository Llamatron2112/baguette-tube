<?php

namespace App\Scheduler;

use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsPeriodicTask(frequency: '1 day', from: '03:00', transports: 'async')]
class DeleteUnverifiedUsers
{
    public function __construct(
        private UserRepository $users,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(): void
    {
        $deleted = $this->users->deleteUnverifiedBefore(
            new \DateTimeImmutable('-2 days')
        );

        $this->logger->info('Deleted {count} unverified user account(s).', [
            'count' => $deleted,
        ]);
    }
}

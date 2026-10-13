<?php

namespace App\Twig\Components;

use App\Repository\ChannelRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class ChannelSearch
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    public function __construct(private ChannelRepository $repository) {}

    public function getChannels(): array
    {
        return $this->repository->search($this->query);
    }
}

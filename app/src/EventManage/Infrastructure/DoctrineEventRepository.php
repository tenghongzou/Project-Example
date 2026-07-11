<?php

declare(strict_types=1);

namespace App\EventManage\Infrastructure;

use App\EventManage\Domain\Event;
use App\EventManage\Domain\EventNotFound;
use App\EventManage\Domain\EventRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineEventRepository implements EventRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Event $event): void
    {
        $this->entityManager->persist($event);
        $this->entityManager->flush();
    }

    public function get(string $id): Event
    {
        $event = $this->entityManager->find(Event::class, $id);

        if (null === $event) {
            throw EventNotFound::withId($id);
        }

        return $event;
    }

    public function list(): array
    {
        /** @var list<Event> $events */
        $events = $this->entityManager
            ->getRepository(Event::class)
            ->createQueryBuilder('e')
            ->orderBy('e.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $events;
    }
}

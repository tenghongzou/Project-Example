<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure;

use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationNotFound;
use App\Notification\Domain\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineNotificationRepository implements NotificationRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function save(Notification $notification): void
    {
        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    public function get(string $id): Notification
    {
        $notification = $this->entityManager->find(Notification::class, $id);

        if (null === $notification) {
            throw NotificationNotFound::withId($id);
        }

        return $notification;
    }

    public function list(): array
    {
        /** @var list<Notification> $notifications */
        $notifications = $this->entityManager
            ->getRepository(Notification::class)
            ->createQueryBuilder('n')
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $notifications;
    }
}

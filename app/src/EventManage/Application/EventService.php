<?php

declare(strict_types=1);

namespace App\EventManage\Application;

use App\EventManage\Application\Message\EventCancelled;
use App\EventManage\Application\Message\EventCreated;
use App\EventManage\Application\Message\EventPublished;
use App\EventManage\Domain\Event;
use App\EventManage\Domain\EventRepository;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class EventService
{
    public function __construct(
        private EventRepository $eventRepository,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function create(string $name, \DateTimeImmutable $scheduledAt, ?string $description): Event
    {
        $event = new Event($name, $scheduledAt, $description);
        $this->eventRepository->save($event);

        $this->messageBus->dispatch(new EventCreated(
            eventId: $event->getId(),
            name: $event->getName(),
            scheduledAt: $event->getScheduledAt()->format(\DateTimeInterface::ATOM),
        ));

        return $event;
    }

    public function publish(string $id): Event
    {
        $event = $this->eventRepository->get($id);
        $event->publish();
        $this->eventRepository->save($event);

        $this->messageBus->dispatch(new EventPublished(
            eventId: $event->getId(),
            name: $event->getName(),
        ));

        return $event;
    }

    public function cancel(string $id): Event
    {
        $event = $this->eventRepository->get($id);
        $event->cancel();
        $this->eventRepository->save($event);

        $this->messageBus->dispatch(new EventCancelled(
            eventId: $event->getId(),
            name: $event->getName(),
        ));

        return $event;
    }
}

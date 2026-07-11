<?php

declare(strict_types=1);

namespace App\Tests\EventManage\Application;

use App\EventManage\Application\EventService;
use App\EventManage\Application\Message\EventCancelled;
use App\EventManage\Application\Message\EventCreated;
use App\EventManage\Application\Message\EventPublished;
use App\EventManage\Domain\Event;
use App\EventManage\Domain\EventRepository;
use App\EventManage\Domain\EventStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class EventServiceTest extends TestCase
{
    public function testCreateSavesTheEventAndDispatchesEventCreated(): void
    {
        $eventRepository = $this->createMock(EventRepository::class);
        $eventRepository->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(Event::class));

        $dispatchedMessage = null;
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$dispatchedMessage): Envelope {
                $dispatchedMessage = $message;

                return new Envelope($message);
            });

        $scheduledAt = new \DateTimeImmutable('2026-08-01 10:00:00', new \DateTimeZone('+08:00'));
        $event = (new EventService($eventRepository, $messageBus))
            ->create('Team Meetup', $scheduledAt, 'A casual meetup');

        self::assertInstanceOf(EventCreated::class, $dispatchedMessage);
        self::assertSame($event->getId(), $dispatchedMessage->eventId);
        self::assertSame('Team Meetup', $dispatchedMessage->name);
        self::assertSame('2026-08-01T02:00:00+00:00', $dispatchedMessage->scheduledAt);
    }

    public function testPublishPublishesADraftEventAndDispatchesEventPublished(): void
    {
        $event = new Event('Team Meetup', new \DateTimeImmutable('2026-08-01 10:00:00', new \DateTimeZone('UTC')));

        $eventRepository = $this->createMock(EventRepository::class);
        $eventRepository->expects(self::once())
            ->method('get')
            ->with($event->getId())
            ->willReturn($event);
        $eventRepository->expects(self::once())
            ->method('save')
            ->with($event);

        $dispatchedMessage = null;
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$dispatchedMessage): Envelope {
                $dispatchedMessage = $message;

                return new Envelope($message);
            });

        $result = (new EventService($eventRepository, $messageBus))->publish($event->getId());

        self::assertSame($event, $result);
        self::assertInstanceOf(EventPublished::class, $dispatchedMessage);
        self::assertSame($event->getId(), $dispatchedMessage->eventId);
        self::assertSame('Team Meetup', $dispatchedMessage->name);
        self::assertSame('2026-08-01T10:00:00+00:00', $dispatchedMessage->scheduledAt);
    }

    public function testCancelCancelsTheEventAndDispatchesEventCancelled(): void
    {
        $event = new Event('Team Meetup', new \DateTimeImmutable('2026-08-01 10:00:00', new \DateTimeZone('UTC')));

        $eventRepository = $this->createMock(EventRepository::class);
        $eventRepository->expects(self::once())
            ->method('get')
            ->with($event->getId())
            ->willReturn($event);
        $eventRepository->expects(self::once())
            ->method('save')
            ->with($event);

        $dispatchedMessage = null;
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects(self::once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$dispatchedMessage): Envelope {
                $dispatchedMessage = $message;

                return new Envelope($message);
            });

        $result = (new EventService($eventRepository, $messageBus))->cancel($event->getId());

        self::assertSame(EventStatus::Cancelled, $result->getStatus());
        self::assertInstanceOf(EventCancelled::class, $dispatchedMessage);
        self::assertSame($event->getId(), $dispatchedMessage->eventId);
    }
}

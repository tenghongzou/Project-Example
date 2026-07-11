<?php

declare(strict_types=1);

namespace App\Tests\EventManage\Domain;

use App\EventManage\Domain\Event;
use App\EventManage\Domain\EventStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class EventTest extends TestCase
{
    public function testNewEventIsDraftWithValidUuid(): void
    {
        $event = new Event('Team Meetup', new \DateTimeImmutable('2026-08-01 10:00:00', new \DateTimeZone('UTC')));

        self::assertTrue(Uuid::isValid($event->getId()));
        self::assertSame(EventStatus::Draft, $event->getStatus());
    }

    public function testScheduledAtIsNormalizedToUtcWithoutChangingTheInstant(): void
    {
        $scheduledAt = new \DateTimeImmutable('2026-08-01 10:00:00', new \DateTimeZone('+08:00'));

        $event = new Event('Team Meetup', $scheduledAt);

        self::assertSame('UTC', $event->getScheduledAt()->getTimezone()->getName());
        self::assertSame($scheduledAt->getTimestamp(), $event->getScheduledAt()->getTimestamp());
        self::assertSame('2026-08-01T02:00:00+00:00', $event->getScheduledAt()->format(\DateTimeInterface::ATOM));
    }

    public function testDraftEventCanBePublished(): void
    {
        $event = $this->createEvent();

        $event->publish();

        self::assertSame(EventStatus::Published, $event->getStatus());
    }

    public function testPublishedEventCannotBePublishedAgain(): void
    {
        $event = $this->createEvent();
        $event->publish();

        $this->expectException(\DomainException::class);

        $event->publish();
    }

    public function testCancelledEventCannotBePublished(): void
    {
        $event = $this->createEvent();
        $event->cancel();

        $this->expectException(\DomainException::class);

        $event->publish();
    }

    public function testDraftEventCanBeCancelled(): void
    {
        $event = $this->createEvent();

        $event->cancel();

        self::assertSame(EventStatus::Cancelled, $event->getStatus());
    }

    public function testPublishedEventCanBeCancelled(): void
    {
        $event = $this->createEvent();
        $event->publish();

        $event->cancel();

        self::assertSame(EventStatus::Cancelled, $event->getStatus());
    }

    public function testCancelledEventCannotBeCancelledAgain(): void
    {
        $event = $this->createEvent();
        $event->cancel();

        $this->expectException(\DomainException::class);

        $event->cancel();
    }

    private function createEvent(): Event
    {
        return new Event('Team Meetup', new \DateTimeImmutable('2026-08-01 10:00:00', new \DateTimeZone('UTC')));
    }
}

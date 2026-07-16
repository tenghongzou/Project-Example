<?php

declare(strict_types=1);

namespace App\Tests\EventManage\Infrastructure;

use App\EventManage\Domain\Event;
use App\EventManage\Domain\EventNotFound;
use App\EventManage\Domain\EventStatus;
use App\EventManage\Infrastructure\DoctrineEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * 走真實 PostgreSQL 的整合測試；DAMA extension 讓每個測試包在 transaction 內回滾。
 */
final class DoctrineEventRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineEventRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;
        $this->repository = new DoctrineEventRepository($entityManager);
    }

    public function testSaveAndGetRoundTripPreservesFields(): void
    {
        $scheduledAt = new \DateTimeImmutable('2026-08-01 10:00:00', new \DateTimeZone('+08:00'));
        $event = new Event('Team Meetup', $scheduledAt, 'A casual meetup');

        $this->repository->save($event);
        // 清掉 identity map，強迫 get() 真的從 DB 重新載入而不是回傳同一個物件
        $this->entityManager->clear();

        $reloaded = $this->repository->get($event->getId());

        self::assertNotSame($event, $reloaded);
        self::assertSame($event->getId(), $reloaded->getId());
        self::assertSame('Team Meetup', $reloaded->getName());
        self::assertSame('A casual meetup', $reloaded->getDescription());
        self::assertSame(EventStatus::Draft, $reloaded->getStatus());
        // 建構子已把 +08:00 正規化成 UTC；DB 欄位無時區，比對字面值即可
        self::assertSame('2026-08-01 02:00:00', $reloaded->getScheduledAt()->format('Y-m-d H:i:s'));
        self::assertSame(
            $event->getCreatedAt()->format('Y-m-d H:i:s'),
            $reloaded->getCreatedAt()->format('Y-m-d H:i:s'),
        );
        self::assertSame(1, $reloaded->getVersion());
    }

    public function testGetThrowsEventNotFoundForUnknownId(): void
    {
        $this->expectException(EventNotFound::class);

        $this->repository->get('a2752c9e-8e6a-4d3b-9c7d-2f1e5b6a7c8d');
    }

    public function testListReturnsEventsOrderedByCreatedAtDesc(): void
    {
        $older = new Event('Older Event', new \DateTimeImmutable('2026-08-01 10:00:00', new \DateTimeZone('UTC')));
        $this->backdateCreatedAt($older, new \DateTimeImmutable('2026-01-01 00:00:00', new \DateTimeZone('UTC')));

        $newer = new Event('Newer Event', new \DateTimeImmutable('2026-08-02 10:00:00', new \DateTimeZone('UTC')));
        $this->backdateCreatedAt($newer, new \DateTimeImmutable('2026-06-01 00:00:00', new \DateTimeZone('UTC')));

        // 先存 older 再存 newer：若排序只是剛好照寫入順序，這裡會抓出假陽性
        $this->repository->save($older);
        $this->repository->save($newer);
        $this->entityManager->clear();

        $ids = array_map(
            static fn (Event $event): string => $event->getId(),
            $this->repository->list(),
        );

        // DB 內可能有其他測試前就存在的資料，只驗證這兩筆的相對順序
        $newerPosition = array_search($newer->getId(), $ids, true);
        $olderPosition = array_search($older->getId(), $ids, true);
        self::assertNotFalse($newerPosition);
        self::assertNotFalse($olderPosition);
        self::assertLessThan($olderPosition, $newerPosition);
    }

    /**
     * Event 沒有注入 clock，createdAt 固定為建構當下；
     * 用 reflection 覆寫才能做出可預期的排序資料（欄位是 TIMESTAMP(0)，同秒建立無法比序）。
     */
    private function backdateCreatedAt(Event $event, \DateTimeImmutable $createdAt): void
    {
        $property = new \ReflectionProperty(Event::class, 'createdAt');
        $property->setValue($event, $createdAt);
    }
}

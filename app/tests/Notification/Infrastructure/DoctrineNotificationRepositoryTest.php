<?php

declare(strict_types=1);

namespace App\Tests\Notification\Infrastructure;

use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationNotFound;
use App\Notification\Domain\NotificationStatus;
use App\Notification\Infrastructure\DoctrineNotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * 走真實 PostgreSQL 的整合測試；DAMA extension 讓每個測試包在 transaction 內回滾。
 */
final class DoctrineNotificationRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineNotificationRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;
        $this->repository = new DoctrineNotificationRepository($entityManager);
    }

    public function testSaveAndGetRoundTripPreservesFields(): void
    {
        $context = ['event_id' => 'e-1', 'nested' => ['count' => 3, 'flag' => true]];
        $notification = new Notification('log', 'Subject', 'Body text', $context);

        $this->repository->save($notification);
        // 清掉 identity map，強迫 get() 真的從 DB 重新載入而不是回傳同一個物件
        $this->entityManager->clear();

        $reloaded = $this->repository->get($notification->getId());

        self::assertNotSame($notification, $reloaded);
        self::assertSame($notification->getId(), $reloaded->getId());
        self::assertSame('log', $reloaded->getChannel());
        self::assertSame('Subject', $reloaded->getSubject());
        self::assertSame('Body text', $reloaded->getBody());
        self::assertSame($context, $reloaded->getContext());
        self::assertSame(NotificationStatus::Pending, $reloaded->getStatus());
        self::assertNull($reloaded->getError());
        self::assertNull($reloaded->getSentAt());
        self::assertSame(1, $reloaded->getVersion());
    }

    public function testGetThrowsNotificationNotFoundForUnknownId(): void
    {
        $this->expectException(NotificationNotFound::class);

        $this->repository->get('a2752c9e-8e6a-4d3b-9c7d-2f1e5b6a7c8d');
    }

    public function testStatusTransitionPersistsAndBumpsTheVersion(): void
    {
        $notification = new Notification('log', 'Subject', 'Body');
        $this->repository->save($notification);

        $notification->markSent();
        $this->repository->save($notification);
        $this->entityManager->clear();

        $reloaded = $this->repository->get($notification->getId());

        self::assertSame(NotificationStatus::Sent, $reloaded->getStatus());
        self::assertNotNull($reloaded->getSentAt());
        self::assertSame(2, $reloaded->getVersion());
    }

    public function testListReturnsNotificationsOrderedByCreatedAtDesc(): void
    {
        $older = new Notification('log', 'Older', 'Body');
        $this->backdateCreatedAt($older, new \DateTimeImmutable('2026-01-01 00:00:00', new \DateTimeZone('UTC')));

        $newer = new Notification('log', 'Newer', 'Body');
        $this->backdateCreatedAt($newer, new \DateTimeImmutable('2026-06-01 00:00:00', new \DateTimeZone('UTC')));

        // 先存 older 再存 newer：若排序只是剛好照寫入順序，這裡會抓出假陽性
        $this->repository->save($older);
        $this->repository->save($newer);
        $this->entityManager->clear();

        $ids = array_map(
            static fn (Notification $notification): string => $notification->getId(),
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
     * Notification 沒有注入 clock，createdAt 固定為建構當下；
     * 用 reflection 覆寫才能做出可預期的排序資料（欄位是 TIMESTAMP(0)，同秒建立無法比序）。
     */
    private function backdateCreatedAt(Notification $notification, \DateTimeImmutable $createdAt): void
    {
        $property = new \ReflectionProperty(Notification::class, 'createdAt');
        $property->setValue($notification, $createdAt);
    }
}

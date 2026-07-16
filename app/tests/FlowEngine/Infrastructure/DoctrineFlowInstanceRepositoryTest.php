<?php

declare(strict_types=1);

namespace App\Tests\FlowEngine\Infrastructure;

use App\FlowEngine\Domain\FlowInstance;
use App\FlowEngine\Domain\FlowInstanceNotFound;
use App\FlowEngine\Domain\FlowInstanceStatus;
use App\FlowEngine\Infrastructure\DoctrineFlowInstanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * 走真實 PostgreSQL 的整合測試；DAMA extension 讓每個測試包在 transaction 內回滾。
 * definition_id 是跨 aggregate 的 id 引用（無 FK），測試可直接用任意 UUID。
 */
final class DoctrineFlowInstanceRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineFlowInstanceRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;
        $this->repository = new DoctrineFlowInstanceRepository($entityManager);
    }

    public function testSaveAndGetRoundTripPreservesFields(): void
    {
        $definitionId = Uuid::v7()->toRfc4122();
        $instance = new FlowInstance($definitionId, ['order_id' => 'A-001', 'qty' => 3]);

        $this->repository->save($instance);
        // 清掉 identity map，強迫 get() 真的從 DB 重新載入而不是回傳同一個物件
        $this->entityManager->clear();

        $reloaded = $this->repository->get($instance->getId());

        self::assertNotSame($instance, $reloaded);
        self::assertSame($instance->getId(), $reloaded->getId());
        self::assertSame($definitionId, $reloaded->getDefinitionId());
        self::assertSame(FlowInstanceStatus::Running, $reloaded->getStatus());
        self::assertSame(0, $reloaded->getCurrentStepIndex());
        self::assertSame(['order_id' => 'A-001', 'qty' => 3], $reloaded->getContext());
        self::assertNull($reloaded->getError());
        self::assertSame(
            $instance->getCreatedAt()->format('Y-m-d H:i:s'),
            $reloaded->getCreatedAt()->format('Y-m-d H:i:s'),
        );
        self::assertSame(1, $reloaded->getVersion());
    }

    public function testGetThrowsFlowInstanceNotFoundForUnknownId(): void
    {
        $this->expectException(FlowInstanceNotFound::class);

        $this->repository->get('a2752c9e-8e6a-4d3b-9c7d-2f1e5b6a7c8d');
    }

    public function testAdvancePersistsTheStateUpdate(): void
    {
        $instance = new FlowInstance(Uuid::v7()->toRfc4122(), []);
        $this->repository->save($instance);
        $this->entityManager->clear();

        // 模擬 worker 的實際流程：重新載入、推進、再存回
        $loaded = $this->repository->get($instance->getId());
        $loaded->advance(['foo' => 'bar']);
        $this->repository->save($loaded);
        $this->entityManager->clear();

        $reloaded = $this->repository->get($instance->getId());

        self::assertSame(FlowInstanceStatus::Running, $reloaded->getStatus());
        self::assertSame(1, $reloaded->getCurrentStepIndex());
        self::assertSame(['foo' => 'bar'], $reloaded->getContext());
        self::assertNull($reloaded->getError());
    }

    public function testCompletePersistsTheTerminalState(): void
    {
        $instance = new FlowInstance(Uuid::v7()->toRfc4122(), ['order_id' => 'A-001']);
        $this->repository->save($instance);
        $this->entityManager->clear();

        $loaded = $this->repository->get($instance->getId());
        $loaded->complete(['order_id' => 'A-001', 'result' => 42]);
        $this->repository->save($loaded);
        $this->entityManager->clear();

        $reloaded = $this->repository->get($instance->getId());

        self::assertSame(FlowInstanceStatus::Completed, $reloaded->getStatus());
        self::assertFalse($reloaded->isRunning());
        self::assertSame(['order_id' => 'A-001', 'result' => 42], $reloaded->getContext());
        self::assertNull($reloaded->getError());
    }

    public function testVersionIncrementsOnEachStateChangingFlush(): void
    {
        $instance = new FlowInstance(Uuid::v7()->toRfc4122(), []);

        $this->repository->save($instance);
        self::assertSame(1, $instance->getVersion());

        // 樂觀鎖欄位：每次帶有變更的 flush 都要遞增，並發 lost update 才擋得住
        $instance->advance(['a' => 1]);
        $this->repository->save($instance);
        self::assertSame(2, $instance->getVersion());

        $instance->complete(['a' => 1, 'b' => 2]);
        $this->repository->save($instance);
        self::assertSame(3, $instance->getVersion());

        // 重新載入確認遞增後的版本確實落到 DB
        $this->entityManager->clear();
        $reloaded = $this->repository->get($instance->getId());
        self::assertSame(3, $reloaded->getVersion());
    }
}

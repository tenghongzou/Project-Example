<?php

declare(strict_types=1);

namespace App\Tests\FlowEngine\Infrastructure;

use App\FlowEngine\Domain\FlowDefinition;
use App\FlowEngine\Domain\FlowDefinitionNotFound;
use App\FlowEngine\Infrastructure\DoctrineFlowDefinitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * 走真實 PostgreSQL 的整合測試；DAMA extension 讓每個測試包在 transaction 內回滾。
 */
final class DoctrineFlowDefinitionRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DoctrineFlowDefinitionRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $this->entityManager = $entityManager;
        $this->repository = new DoctrineFlowDefinitionRepository($entityManager);
    }

    public function testSaveAndGetRoundTripPreservesStepsJson(): void
    {
        // 混合型別的 params：確認 JSON 欄位往返後 int/bool/巢狀陣列都不失真
        $steps = [
            ['type' => 'set', 'params' => ['key' => 'answer', 'value' => 42]],
            ['type' => 'set', 'params' => ['key' => 'flag', 'value' => true]],
            ['type' => 'log', 'params' => ['message' => 'done', 'tags' => ['a', 'b']]],
        ];
        $definition = new FlowDefinition('Order Flow', $steps);

        $this->repository->save($definition);
        // 清掉 identity map，強迫 get() 真的從 DB 重新載入而不是回傳同一個物件
        $this->entityManager->clear();

        $reloaded = $this->repository->get($definition->getId());

        self::assertNotSame($definition, $reloaded);
        self::assertSame($definition->getId(), $reloaded->getId());
        self::assertSame('Order Flow', $reloaded->getName());
        self::assertSame($steps, $reloaded->getSteps());
        self::assertSame(3, $reloaded->getStepCount());
        self::assertSame(
            $definition->getCreatedAt()->format('Y-m-d H:i:s'),
            $reloaded->getCreatedAt()->format('Y-m-d H:i:s'),
        );
    }

    public function testGetThrowsFlowDefinitionNotFoundForUnknownId(): void
    {
        $this->expectException(FlowDefinitionNotFound::class);

        $this->repository->get('a2752c9e-8e6a-4d3b-9c7d-2f1e5b6a7c8d');
    }

    public function testListReturnsDefinitionsOrderedByCreatedAtDesc(): void
    {
        $older = new FlowDefinition('Older Flow', [['type' => 'log', 'params' => []]]);
        $this->backdateCreatedAt($older, new \DateTimeImmutable('2026-01-01 00:00:00', new \DateTimeZone('UTC')));

        $newer = new FlowDefinition('Newer Flow', [['type' => 'log', 'params' => []]]);
        $this->backdateCreatedAt($newer, new \DateTimeImmutable('2026-06-01 00:00:00', new \DateTimeZone('UTC')));

        // 先存 older 再存 newer：若排序只是剛好照寫入順序，這裡會抓出假陽性
        $this->repository->save($older);
        $this->repository->save($newer);
        $this->entityManager->clear();

        $ids = array_map(
            static fn (FlowDefinition $definition): string => $definition->getId(),
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
     * FlowDefinition 沒有注入 clock，createdAt 固定為建構當下；
     * 用 reflection 覆寫才能做出可預期的排序資料（欄位是 TIMESTAMP(0)，同秒建立無法比序）。
     */
    private function backdateCreatedAt(FlowDefinition $definition, \DateTimeImmutable $createdAt): void
    {
        $property = new \ReflectionProperty(FlowDefinition::class, 'createdAt');
        $property->setValue($definition, $createdAt);
    }
}

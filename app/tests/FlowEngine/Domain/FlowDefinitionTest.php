<?php

declare(strict_types=1);

namespace App\Tests\FlowEngine\Domain;

use App\FlowEngine\Domain\FlowDefinition;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class FlowDefinitionTest extends TestCase
{
    public function testConstructorRejectsEmptySteps(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new FlowDefinition('Empty Flow', []);
    }

    public function testNewDefinitionHasValidUuidAndKeepsNameAndSteps(): void
    {
        $definition = $this->createDefinition();

        self::assertTrue(Uuid::isValid($definition->getId()));
        self::assertSame('Sample Flow', $definition->getName());
        self::assertSame([
            ['type' => 'set', 'params' => ['key' => 'foo', 'value' => 'bar']],
            ['type' => 'log', 'params' => ['message' => 'hello']],
        ], $definition->getSteps());
    }

    public function testGetStepReturnsTheStepAtTheGivenIndex(): void
    {
        $definition = $this->createDefinition();

        self::assertSame(['type' => 'set', 'params' => ['key' => 'foo', 'value' => 'bar']], $definition->getStep(0));
        self::assertSame(['type' => 'log', 'params' => ['message' => 'hello']], $definition->getStep(1));
    }

    public function testGetStepReturnsNullWhenIndexIsOutOfRange(): void
    {
        $definition = $this->createDefinition();

        self::assertNull($definition->getStep(2));
        self::assertNull($definition->getStep(-1));
    }

    public function testGetStepCountReturnsTheNumberOfSteps(): void
    {
        self::assertSame(2, $this->createDefinition()->getStepCount());
        self::assertSame(1, (new FlowDefinition('One Step', [['type' => 'log', 'params' => []]]))->getStepCount());
    }

    private function createDefinition(): FlowDefinition
    {
        return new FlowDefinition('Sample Flow', [
            ['type' => 'set', 'params' => ['key' => 'foo', 'value' => 'bar']],
            ['type' => 'log', 'params' => ['message' => 'hello']],
        ]);
    }
}

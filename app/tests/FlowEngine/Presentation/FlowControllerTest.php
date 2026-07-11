<?php

declare(strict_types=1);

namespace App\Tests\FlowEngine\Presentation;

use App\FlowEngine\Application\Message\ExecuteNextStep;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class FlowControllerTest extends WebTestCase
{
    public function testCreateReturnsCreatedDefinitionWithLocationHeader(): void
    {
        $client = self::createClient();

        $payload = $this->createFlow($client);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertResponseHeaderSame('Location', '/api/flows/'.$payload['id']);
        self::assertNotSame('', $payload['id']);
        self::assertSame('Order Flow', $payload['name']);
        self::assertSame([
            ['type' => 'set', 'params' => ['key' => 'foo', 'value' => 'bar']],
            ['type' => 'log', 'params' => ['message' => 'done']],
        ], $payload['steps']);
    }

    public function testCreateWithEmptyStepsIsRejected(): void
    {
        $client = self::createClient();

        $client->request(
            'POST',
            '/api/flows',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Order Flow',
                'steps' => [],
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateWithBlankNameIsRejected(): void
    {
        $client = self::createClient();

        $client->request(
            'POST',
            '/api/flows',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => '',
                'steps' => [
                    ['type' => 'log', 'params' => ['message' => 'done']],
                ],
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateWithUnknownStepTypeIsRejected(): void
    {
        $client = self::createClient();

        $client->request(
            'POST',
            '/api/flows',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Order Flow',
                'steps' => [
                    ['type' => 'teleport', 'params' => []],
                ],
            ], JSON_THROW_ON_ERROR),
        );

        // 建立時 fail-fast：不該等到非同步執行才發現 type 跑不起來
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        /** @var array{type: string, title: string, status: int, detail: string} $problem */
        $problem = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('teleport', $problem['detail']);
    }

    public function testStartInstanceReturnsNotFoundForUnknownDefinition(): void
    {
        $client = self::createClient();

        $client->request(
            'POST',
            '/api/flows/a2752c9e-8e6a-4d3b-9c7d-2f1e5b6a7c8d/instances',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['context' => []], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testShowReturnsNotFoundProblemForUnknownId(): void
    {
        $client = self::createClient();

        // 合法格式但不存在的 v4 UUID（nil UUID 會被路由的 Requirement::UUID 擋掉，進不到 controller）
        $client->request('GET', '/api/flows/a2752c9e-8e6a-4d3b-9c7d-2f1e5b6a7c8d');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        // 業務錯誤統一 RFC 7807 problem+json 形狀
        /** @var array{type: string, title: string, status: int, detail: string} $problem */
        $problem = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(Response::HTTP_NOT_FOUND, $problem['status']);
        self::assertArrayHasKey('detail', $problem);
    }

    public function testStartInstanceReturnsAcceptedAndQueuesExecuteNextStep(): void
    {
        $client = self::createClient();
        $definition = $this->createFlow($client);

        $client->request(
            'POST',
            '/api/flows/'.$definition['id'].'/instances',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'context' => ['order_id' => 'A-001'],
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        /** @var array{id: string, definition_id: string, status: string, current_step_index: int, context: array<string, mixed>, error: ?string} $instance */
        $instance = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($definition['id'], $instance['definition_id']);
        self::assertSame('running', $instance['status']);
        self::assertSame(0, $instance['current_step_index']);
        self::assertSame(['order_id' => 'A-001'], $instance['context']);

        // 測試環境的 async transport 是 in-memory（見 messenger.yaml 的 when@test）
        $transport = self::getContainer()->get('messenger.transport.async');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $sent = $transport->getSent();
        self::assertCount(1, $sent);

        $message = $sent[0]->getMessage();
        self::assertInstanceOf(ExecuteNextStep::class, $message);
        self::assertSame($instance['id'], $message->instanceId);
        self::assertSame(0, $message->stepIndex);
    }

    public function testShowInstanceReturnsTheInstance(): void
    {
        $client = self::createClient();
        $definition = $this->createFlow($client);
        $instance = $this->startInstance($client, $definition['id']);

        $client->request('GET', '/api/flow-instances/'.$instance['id']);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        /** @var array{id: string, definition_id: string, status: string, current_step_index: int, context: array<string, mixed>, error: ?string, created_at: string, updated_at: string} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($instance['id'], $payload['id']);
        self::assertSame($definition['id'], $payload['definition_id']);
        self::assertSame('running', $payload['status']);
        self::assertSame(0, $payload['current_step_index']);
        self::assertSame(['order_id' => 'A-001'], $payload['context']);
        self::assertNull($payload['error']);
    }

    public function testShowInstanceReturnsNotFoundProblemForUnknownId(): void
    {
        $client = self::createClient();

        $client->request('GET', '/api/flow-instances/a2752c9e-8e6a-4d3b-9c7d-2f1e5b6a7c8d');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        /** @var array{type: string, title: string, status: int, detail: string} $problem */
        $problem = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(Response::HTTP_NOT_FOUND, $problem['status']);
        self::assertArrayHasKey('detail', $problem);
    }

    /**
     * @return array{id: string, name: string, steps: list<array{type: string, params: array<string, mixed>}>, created_at: string}
     */
    private function createFlow(KernelBrowser $client): array
    {
        $client->request(
            'POST',
            '/api/flows',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'name' => 'Order Flow',
                'steps' => [
                    ['type' => 'set', 'params' => ['key' => 'foo', 'value' => 'bar']],
                    ['type' => 'log', 'params' => ['message' => 'done']],
                ],
            ], JSON_THROW_ON_ERROR),
        );

        /** @var array{id: string, name: string, steps: list<array{type: string, params: array<string, mixed>}>, created_at: string} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $payload;
    }

    /**
     * @return array{id: string, definition_id: string, status: string, current_step_index: int, context: array<string, mixed>, error: ?string}
     */
    private function startInstance(KernelBrowser $client, string $definitionId): array
    {
        $client->request(
            'POST',
            '/api/flows/'.$definitionId.'/instances',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'context' => ['order_id' => 'A-001'],
            ], JSON_THROW_ON_ERROR),
        );

        /** @var array{id: string, definition_id: string, status: string, current_step_index: int, context: array<string, mixed>, error: ?string} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $payload;
    }
}

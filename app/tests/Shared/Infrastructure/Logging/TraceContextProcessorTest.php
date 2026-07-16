<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Logging;

use App\Shared\Infrastructure\Logging\TraceContextProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanContext;
use OpenTelemetry\API\Trace\TraceFlags;
use OpenTelemetry\Context\Context;
use PHPUnit\Framework\TestCase;

final class TraceContextProcessorTest extends TestCase
{
    private const string TRACE_ID = '0af7651916cd43dd8448eb211c80319c';
    private const string SPAN_ID = 'b7ad6b7169203331';

    public function testRecordIsUnchangedWhenThereIsNoActiveSpan(): void
    {
        $record = $this->createRecord();

        $processed = (new TraceContextProcessor())($record);

        self::assertSame($record, $processed);
        self::assertArrayNotHasKey('trace_id', $processed->extra);
        self::assertArrayNotHasKey('span_id', $processed->extra);
        self::assertSame(['existing' => 'value'], $processed->extra);
    }

    public function testTraceAndSpanIdsAreAddedWhenASpanContextIsActive(): void
    {
        // OTel API 是純 PHP：把包了有效 SpanContext 的 NonRecordingSpan 掛進 context storage
        $span = Span::wrap(SpanContext::create(self::TRACE_ID, self::SPAN_ID, TraceFlags::SAMPLED));
        $scope = $span->storeInContext(Context::getCurrent())->activate();

        try {
            $processed = (new TraceContextProcessor())($this->createRecord());
        } finally {
            $scope->detach();
        }

        self::assertSame(self::TRACE_ID, $processed->extra['trace_id']);
        self::assertSame(self::SPAN_ID, $processed->extra['span_id']);
        // 原有的 extra 欄位不可被覆蓋掉
        self::assertSame('value', $processed->extra['existing']);
    }

    public function testInvalidSpanContextDoesNotAddTraceKeys(): void
    {
        // 全零的 trace/span id 不合法，wrap 會退回 invalid span
        $span = Span::wrap(SpanContext::getInvalid());
        $scope = $span->storeInContext(Context::getCurrent())->activate();

        try {
            $processed = (new TraceContextProcessor())($this->createRecord());
        } finally {
            $scope->detach();
        }

        self::assertArrayNotHasKey('trace_id', $processed->extra);
        self::assertArrayNotHasKey('span_id', $processed->extra);
    }

    private function createRecord(): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable('2026-07-16T00:00:00+00:00'),
            channel: 'app',
            level: Level::Info,
            message: 'something happened',
            context: ['key' => 'value'],
            extra: ['existing' => 'value'],
        );
    }
}

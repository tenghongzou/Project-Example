<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging;

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;
use OpenTelemetry\API\Trace\Span;

/**
 * 在每筆 log 附上目前 OTel span 的 trace_id / span_id，
 * 讓 log 可以和 Jaeger 上的 trace 互相對照。
 */
#[AsMonologProcessor]
final class TraceContextProcessor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $spanContext = Span::getCurrent()->getContext();

        if ($spanContext->isValid()) {
            $record->extra['trace_id'] = $spanContext->getTraceId();
            $record->extra['span_id'] = $spanContext->getSpanId();
        }

        return $record;
    }
}

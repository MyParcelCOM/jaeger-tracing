<?php

declare(strict_types=1);

namespace MyParcelCom\JaegerTracing\Subscribers;

use Carbon\Carbon;
use Illuminate\Events\Dispatcher;
use MyParcelCom\JaegerTracing\Events\SpanEnd;
use MyParcelCom\JaegerTracing\Events\SpanStart;
use OpenTracing\Reference;
use OpenTracing\Span;
use OpenTracing\Tracer;

class SpanSubscriber
{
    private Tracer $tracer;

    /** @var Span[] */
    private static array $spans = [];

    public function __construct(Tracer $tracer)
    {
        $this->tracer = $tracer;
    }

    public function start(SpanStart $event): void
    {
        if (!config('jaeger.enabled')) {
            return;
        }

        if (!$this->tracer->getActiveSpan()) {
            return;
        }

        $span = $this->tracer->startSpan($event->getName(), $this->getSpanOptions($event->getStartTime()));

        $event->registerTags($span);
        $event->insertLogs($span);

        self::$spans[$event->getId()] = $span;
    }

    public function end(SpanEnd $event): void
    {
        if (array_key_exists($event->getId(), self::$spans)) {
            $span = self::$spans[$event->getId()];
            $event->registerTags($span);
            $event->insertLogs($span);
            $span->finish($event->getEndTime() ? $event->getEndTime()->getPreciseTimestamp() / 1000000 : null);
        }
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            SpanStart::class,
            [self::class, 'start'],
        );

        $events->listen(
            SpanEnd::class,
            [self::class, 'end'],
        );
    }

    private function getSpanOptions(?Carbon $startTime): array
    {
        if (!$this->tracer->getActiveSpan()) {
            return [];
        }

        $spanContext = $this->tracer->getActiveSpan()->getContext();

        if (null === $startTime) {
            return [Reference::CHILD_OF => $spanContext];
        }

        return [
            Reference::CHILD_OF => $spanContext,
            'start_time'        => $startTime->getPreciseTimestamp() / 1000000,
        ];
    }
}

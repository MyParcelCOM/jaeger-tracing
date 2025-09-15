<?php

declare(strict_types=1);

namespace MyParcelCom\JaegerTracing\Events;

use OpenTracing\Span;

abstract class SpanEvent
{
    public function __construct(
        private readonly string $id,
        private readonly array $tags = [],
        private readonly ?array $log = null,
    ) {
    }

    public function registerTags(Span $span): void
    {
        foreach ($this->tags as $key => $value) {
            if (is_scalar($value)) {
                $span->setTag($key, $value);
            }
        }
    }

    public function insertLogs(Span $span): void
    {
        if (!$this->log) {
            return;
        }

        $span->log($this->log);
    }

    public function getId(): string
    {
        return $this->id;
    }
}

<?php

declare(strict_types=1);

namespace MyParcelCom\JaegerTracing\Events;

use Carbon\Carbon;

class SpanStart extends SpanEvent
{
    public function __construct(
        string $id,
        private readonly string $name,
        array $tags = [],
        array $log = [],
        private readonly ?Carbon $startTime = null,
    ) {
        parent::__construct($id, $tags, $log);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStartTime(): ?Carbon
    {
        return $this->startTime;
    }
}

<?php

declare(strict_types=1);

namespace MyParcelCom\JaegerTracing\Events;

use Carbon\Carbon;

class SpanEnd extends SpanEvent
{
    public function __construct(
        string $id,
        array $tags = [],
        array $log = [],
        private readonly ?Carbon $endTime = null,
    ) {
        parent::__construct($id, $tags, $log);
    }

    public function getEndTime(): ?Carbon
    {
        return $this->endTime;
    }
}

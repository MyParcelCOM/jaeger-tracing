<?php

declare(strict_types=1);

namespace Tests\Events;

use Carbon\Carbon;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use MyParcelCom\JaegerTracing\Events\SpanEnd;
use OpenTracing\Span;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;

class SpanEndTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_it_has_id(): void
    {
        assertSame('my-span-1', (new SpanEnd('my-span-1'))->getId());
        assertSame('my-span-2', (new SpanEnd('my-span-2'))->getId());
    }

    public function test_it_registers_tags_on_a_span(): void
    {
        // no tags
        $spanEnd = new SpanEnd('my-span');
        $span = Mockery::mock(Span::class);
        // no expectations
        $span->expects('setTag')->never();

        $spanEnd->registerTags($span);

        $spanEnd = new SpanEnd('my-span', ['my-tag-1' => 'my-tag-value-1', 'my-tag-2' => 'my-tag-value-2']);
        $span->expects('setTag')->with('my-tag-1', 'my-tag-value-1');
        $span->expects('setTag')->with('my-tag-2', 'my-tag-value-2');
        $spanEnd->registerTags($span);
    }

    public function test_it_inserts_logs_on_a_span(): void
    {
        // no tags
        $spanEnd = new SpanEnd('my-span');
        $span = Mockery::mock(Span::class);
        // no expectations
        $span->expects('log')->never();

        $spanEnd->insertLogs($span);

        $spanEnd = new SpanEnd('my-span', log: ['something']);
        $span->expects('log')->with(['something']);
        $spanEnd->insertLogs($span);
    }

    public function test_it_can_have_end_time(): void
    {
        $spanEnd = new SpanEnd('my-span');

        assertNull($spanEnd->getEndTime());

        $endTime = new Carbon();
        $spanEnd = new SpanEnd('my-span', endTime: $endTime);

        assertSame($endTime, $spanEnd->getEndTime());
    }
}

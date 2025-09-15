<?php

declare(strict_types=1);

namespace Tests\Events;

use Carbon\Carbon;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use MyParcelCom\JaegerTracing\Events\SpanStart;
use OpenTracing\Span;
use PHPUnit\Framework\TestCase;

use function PHPUnit\Framework\assertNull;
use function PHPUnit\Framework\assertSame;

class SpanStartTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_it_has_id(): void
    {
        assertSame('my-span-1', (new SpanStart('my-span-1', 'My Span 1'))->getId());
        assertSame('my-span-2', (new SpanStart('my-span-2', 'My Span 2'))->getId());
    }

    public function test_it_has_name(): void
    {
        assertSame('My Span 1', (new SpanStart('my-span-1', 'My Span 1'))->getName());
        assertSame('My Span 2', (new SpanStart('my-span-2', 'My Span 2'))->getName());
    }

    public function test_it_registers_tags_on_a_span(): void
    {
        // no tags
        $spanStart = new SpanStart('my-span', 'My Span');
        $span = Mockery::mock(Span::class);
        // no expectations
        $span->expects('setTag')->never();

        $spanStart->registerTags($span);

        $spanStart = new SpanStart(
            'my-span',
            'My Span',
            ['my-tag-1' => 'my-tag-value-1', 'my-tag-2' => 'my-tag-value-2'],
        );
        $span->expects('setTag')->with('my-tag-1', 'my-tag-value-1');
        $span->expects('setTag')->with('my-tag-2', 'my-tag-value-2');
        $spanStart->registerTags($span);
    }

    public function test_it_inserts_logs_on_a_span(): void
    {
        // no tags
        $spanStart = new SpanStart('my-span', 'My Span');
        $span = Mockery::mock(Span::class);
        // no expectations
        $span->expects('log')->never();

        $spanStart->insertLogs($span);

        $spanStart = new SpanStart('my-span', 'My Span', log: ['something']);
        $span->expects('log')->with(['something']);
        $spanStart->insertLogs($span);
    }

    public function test_it_can_have_start_time(): void
    {
        $spanStart = new SpanStart('my-span', 'My Span');

        assertNull($spanStart->getStartTime());

        $endTime = new Carbon();
        $spanStart = new SpanStart('my-span', 'My Span', startTime: $endTime);

        assertSame($endTime, $spanStart->getStartTime());
    }
}

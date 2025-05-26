<?php

declare(strict_types=1);

namespace MyParcelCom\JaegerTracing\Subscribers;

use Illuminate\Events\Dispatcher;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use OpenTracing\Scope;
use OpenTracing\Tracer;
use Throwable;

class JobSpanSubscriber
{
    private static ?Scope $scope = null;

    public function __construct(
        private readonly Tracer $tracer,
    ) {
    }

    public function start(JobProcessing $event): void
    {
        if (!config('jaeger.enabled')) {
            return;
        }

        $jobName = $event->job->resolveName();

        if (!$this->isJobTraceable($jobName)) {
            return;
        }

        // make sure no previous active job spans are left hanging
        $this->finish();

        $scope = $this->tracer->startActiveSpan($jobName);

        $scope->getSpan()->setTag('type', 'console');

        self::$scope = $scope;
    }

    public function finish(JobProcessed|JobFailed|JobExceptionOccurred $event = null): void
    {
        if (!config('jaeger.enabled')) {
            return;
        }

        try {
            if (self::$scope) {
                if ($event) {
                    $this->injectSpanTags($event);
                }
                self::$scope->close();
            }

            $this->tracer->flush();
            self::$scope = null;
        } catch (Throwable $e) {
            Log::critical($e->getMessage());
        }
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            JobProcessing::class,
            [self::class, 'start'],
        );

        $events->listen(
            JobProcessed::class,
            [self::class, 'finish'],
        );

        $events->listen(
            JobFailed::class,
            [self::class, 'finish'],
        );

        $events->listen(
            JobExceptionOccurred::class,
            [self::class, 'finish'],
        );
    }

    private function getHorizonTags(JobProcessed|JobFailed|JobExceptionOccurred $event): array
    {
        /** @noinspection JsonEncodingApiUsageInspection */
        $horizonTags = Arr::get(json_decode($event->job->getRawBody(), true), 'tags', []);

        $tags = [];
        foreach ($horizonTags as $horizonTag) {
            [$key, $value] = explode(':', $horizonTag);
            $tags[$key] = $value;
        }

        return $tags;
    }

    private function injectSpanTags(JobProcessed|JobFailed|JobExceptionOccurred $event): void
    {
        $tags = $this->getHorizonTags($event);

        foreach ($tags as $key => $value) {
            self::$scope->getSpan()->setTag($key, $value);
        }
    }

    private function isJobTraceable(string $jobName): bool
    {
        return in_array($jobName, config('jaeger.trace_jobs', []), true);
    }
}

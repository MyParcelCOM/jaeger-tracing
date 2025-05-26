<?php

declare(strict_types=1);

namespace Tests\Subscribers;

use Exception;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use MyParcelCom\JaegerTracing\Subscribers\HttpRequestSpanSubscriber;
use OpenTracing\Scope;
use OpenTracing\Span;
use OpenTracing\SpanContext;
use OpenTracing\Tracer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Component\HttpFoundation\HeaderBag;

class HttpRequestSpanSubscriberTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_it_does_not_subscribe_to_route_events_because_jaeger_is_disabled(): void
    {
        $tracer = Mockery::mock(Tracer::class);
        $request = Mockery::mock(Request::class);
        $router = Mockery::mock(Router::class);
        $config = Mockery::mock(Repository::class);
        $logger = Mockery::mock(LoggerInterface::class);

        $subscriber = new HttpRequestSpanSubscriber(
            tracer: $tracer,
            request: $request,
            router: $router,
            config: $config,
            logger: $logger,
        );

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher
            ->expects('listen')
            ->never();

        $config
            ->expects('get')
            ->with('jaeger.enabled', false)
            ->andReturnFalse();

        $subscriber->subscribe($dispatcher);
    }

    public function test_it_subscribes_to_route_events(): void
    {
        $tracer = Mockery::mock(Tracer::class);
        $request = Mockery::mock(Request::class);
        $router = Mockery::mock(Router::class);
        $config = Mockery::mock(Repository::class);
        $logger = Mockery::mock(LoggerInterface::class);

        $subscriber = new HttpRequestSpanSubscriber(
            tracer: $tracer,
            request: $request,
            router: $router,
            config: $config,
            logger: $logger,
        );

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher
            ->expects('listen')
            ->withSomeOfArgs(RouteMatched::class);
        $dispatcher
            ->expects('listen')
            ->withSomeOfArgs(RequestHandled::class);

        $config
            ->expects('get')
            ->with('jaeger.enabled', false)
            ->andReturnTrue();

        $subscriber->subscribe($dispatcher);
    }

    public function test_it_cannot_start_because_route_not_whitelisted(): void
    {
        $tracer = Mockery::mock(Tracer::class);
        $request = Mockery::mock(Request::class);
        $router = Mockery::mock(Router::class);
        $config = Mockery::mock(Repository::class);
        $logger = Mockery::mock(LoggerInterface::class);

        $subscriber = new HttpRequestSpanSubscriber(
            tracer: $tracer,
            request: $request,
            router: $router,
            config: $config,
            logger: $logger,
        );

        $route = Mockery::mock(Route::class);
        $event = new RouteMatched($route, $request);

        $route
            ->expects('getName')
            ->andReturn('a-route');

        $config
            ->expects('get')
            ->with('jaeger.trace_routes', [])
            ->andReturn(['another-route']);

        $tracer->expects('startActiveSpan')->never();

        $subscriber->start($event);
    }

    public function test_it_starts_active_span_no_parent(): void
    {
        $tracer = Mockery::mock(Tracer::class);
        $request = Mockery::mock(Request::class);
        $router = Mockery::mock(Router::class);
        $config = Mockery::mock(Repository::class);
        $scope = Mockery::mock(Scope::class);
        $span = Mockery::mock(Span::class);
        $logger = Mockery::mock(LoggerInterface::class);

        $subscriber = new HttpRequestSpanSubscriber(
            tracer: $tracer,
            request: $request,
            router: $router,
            config: $config,
            logger: $logger,
        );

        $route = Mockery::mock(Route::class);
        $event = new RouteMatched($route, $request);

        $route
            ->expects('getName')
            ->andReturn('the-route');

        $request
            ->expects('getMethod')
            ->andReturn('GET');

        $router
            ->expects('currentRouteAction')
            ->andReturn('App\\Http\\Controllers\\IndexController@index');

        $config
            ->expects('get')
            ->with('jaeger.trace_routes', [])
            ->andReturn(['the-route']);

        $tracer
            ->expects('startActiveSpan')
            ->with('GET App\\Http\\Controllers\\IndexController@index', [])
            ->andReturn($scope);

        $request->headers = new HeaderBag([]);

        // no context, basically no parent span
        $tracer
            ->expects('extract')
            ->andReturnNull();

        $scope
            ->expects('getSpan')
            ->andReturn($span);

        $span
            ->expects('setTag')
            ->with('type', 'http');

        $subscriber->start($event);
    }

    public function test_it_starts_active_span_with_parent(): void
    {
        $tracer = Mockery::mock(Tracer::class);
        $request = Mockery::mock(Request::class);
        $router = Mockery::mock(Router::class);
        $config = Mockery::mock(Repository::class);
        $scope = Mockery::mock(Scope::class);
        $span = Mockery::mock(Span::class);
        $parentContext = Mockery::mock(SpanContext::class);
        $logger = Mockery::mock(LoggerInterface::class);

        $subscriber = new HttpRequestSpanSubscriber(
            tracer: $tracer,
            request: $request,
            router: $router,
            config: $config,
            logger: $logger,
        );

        $route = Mockery::mock(Route::class);
        $event = new RouteMatched($route, $request);

        $route
            ->expects('getName')
            ->andReturn('the-route');

        $request
            ->expects('getMethod')
            ->andReturn('GET');

        $router
            ->expects('currentRouteAction')
            ->andReturn('App\\Http\\Controllers\\IndexController@index');

        $config
            ->expects('get')
            ->with('jaeger.trace_routes', [])
            ->andReturn(['the-route']);

        $tracer
            ->expects('startActiveSpan')
            ->with('GET App\\Http\\Controllers\\IndexController@index', [
                'child_of' => $parentContext,
            ])
            ->andReturn($scope);

        $request->headers = new HeaderBag(['these-are-jaeger' => 'specific-headers']);

        // Tracer finds context, subscriber considers it parent
        $tracer
            ->expects('extract')
            ->andReturn($parentContext);

        $scope
            ->expects('getSpan')
            ->andReturn($span);

        $span
            ->expects('setTag')
            ->with('type', 'http');

        $subscriber->start($event);
    }

    public function test_it_closes_span_and_flushes_tracer(): void
    {
        $tracer = Mockery::mock(Tracer::class);
        $request = Mockery::mock(Request::class);
        $router = Mockery::mock(Router::class);
        $config = Mockery::mock(Repository::class);
        $logger = Mockery::mock(LoggerInterface::class);
        $scope = Mockery::mock(Scope::class);
        $span = Mockery::mock(Span::class);
        $response = Mockery::mock(Response::class);
        $route = Mockery::mock(Route::class);

        $subscriber = new HttpRequestSpanSubscriber(
            tracer: $tracer,
            request: $request,
            router: $router,
            config: $config,
            logger: $logger,
        );

        // use reflection to set a value of a private static variable HttpRequestSpanSubscriber::$scope
        $reflection = new ReflectionClass(HttpRequestSpanSubscriber::class);
        $reflectionProperty = $reflection->getProperty('scope');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($subscriber, $scope);

        $scope
            ->expects('getSpan')
            ->andReturn($span);

        $span
            ->expects('log')
            ->with([
                'successful'      => true, // $response->isSuccessful(),
                'request.host'    => 'localhost', // $request->getHost(),
                'request.method'  => 'GET', // $request->method(),
                'request.path'    => '/', // $request->path(),
                'input'           => [], // $request->input(),
                'response.status' => 200, // $response->getStatusCode(),
            ]);

        $response
            ->expects('isSuccessful')
            ->andReturnTrue();

        $request
            ->expects('getHost')
            ->andReturn('localhost');

        $request
            ->expects('method')
            ->andReturn('GET');

        $request
            ->expects('path')
            ->andReturn('/');

        $request
            ->expects('input')
            ->andReturn([]);

        $response
            ->expects('getStatusCode')
            ->andReturn(200);

        $request
            ->expects('route')
            ->twice()
            ->andReturn($route);

        $route
            ->expects('parameters')
            ->andReturn([
                'param1' => 'value1',
                'param2' => 'value2',
            ]);

        $span
            ->expects('setTag')
            ->with('param1', 'value1');
        $span
            ->expects('setTag')
            ->with('param2', 'value2');

        $scope
            ->expects('close');

        $tracer
            ->expects('flush');

        $event = new RequestHandled($request, $response);

        $subscriber->end($event);
    }

    /** 💩 */
    public function test_it_logs_critical_error_when_tracer_cannot_flush(): void
    {
        $tracer = Mockery::mock(Tracer::class);
        $request = Mockery::mock(Request::class);
        $router = Mockery::mock(Router::class);
        $config = Mockery::mock(Repository::class);
        $logger = Mockery::mock(LoggerInterface::class);
        $scope = Mockery::mock(Scope::class);
        $span = Mockery::mock(Span::class);
        $response = Mockery::mock(Response::class);
        $route = Mockery::mock(Route::class);

        $subscriber = new HttpRequestSpanSubscriber(
            tracer: $tracer,
            request: $request,
            router: $router,
            config: $config,
            logger: $logger,
        );

        // use reflection to set a value of a private static variable HttpRequestSpanSubscriber::$scope
        $reflection = new ReflectionClass(HttpRequestSpanSubscriber::class);
        $reflectionProperty = $reflection->getProperty('scope');
        /** @noinspection PhpExpressionResultUnusedInspection */
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($subscriber, $scope);

        $scope
            ->expects('getSpan')
            ->andReturn($span);

        $span
            ->expects('log')
            ->with([
                'successful'      => true, // $response->isSuccessful(),
                'request.host'    => 'localhost', // $request->getHost(),
                'request.method'  => 'GET', // $request->method(),
                'request.path'    => '/', // $request->path(),
                'input'           => [], // $request->input(),
                'response.status' => 200, // $response->getStatusCode(),
            ]);

        $response
            ->expects('isSuccessful')
            ->andReturnTrue();

        $request
            ->expects('getHost')
            ->andReturn('localhost');

        $request
            ->expects('method')
            ->andReturn('GET');

        $request
            ->expects('path')
            ->andReturn('/');

        $request
            ->expects('input')
            ->andReturn([]);

        $response
            ->expects('getStatusCode')
            ->andReturn(200);

        $request
            ->expects('route')
            ->twice()
            ->andReturn($route);

        $route
            ->expects('parameters')
            ->andReturn([
                'param1' => 'value1',
                'param2' => 'value2',
            ]);

        $span
            ->expects('setTag')
            ->with('param1', 'value1');
        $span
            ->expects('setTag')
            ->with('param2', 'value2');

        $scope
            ->expects('close');

        $exception = new Exception('poop');


        $tracer
            ->expects('flush')
            ->andThrow($exception);

        $event = new RequestHandled($request, $response);

        $logger
            ->expects('critical')
            ->with('poop');

        $subscriber->end($event);
    }
}

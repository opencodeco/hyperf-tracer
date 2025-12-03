<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf + OpenCodeCo
 *
 * @link     https://opencodeco.dev
 * @document https://hyperf.wiki
 * @contact  leo@opencodeco.dev
 * @license  https://github.com/opencodeco/hyperf-metric/blob/main/LICENSE
 */

namespace HyperfTest;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Uri;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Tracer\Aspect\HttpClientAspect;
use Hyperf\Tracer\SpanTagManager;
use Hyperf\Tracer\SwitchManager;
use OpenTracing\Span;
use OpenTracing\Tracer;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 * @coversNothing
 */
class HttpClientAspectTest extends TestCase
{
    private Tracer $tracer;

    private SwitchManager $switchManager;

    private SpanTagManager $spanTagManager;

    private HttpClientAspect $aspect;

    private Span $mockSpan;

    protected function setUp(): void
    {
        $this->tracer = $this->createMock(Tracer::class);
        $this->switchManager = $this->createMock(SwitchManager::class);
        $this->spanTagManager = $this->createMock(SpanTagManager::class);
        $this->mockSpan = $this->createMock(Span::class);

        $this->tracer
            ->expects($this->any())
            ->method('startSpan')
            ->willReturn($this->mockSpan);

        $this->aspect = new HttpClientAspect($this->tracer, $this->switchManager, $this->spanTagManager);
    }

    public function testProcessWithGuzzleSwitchDisabled(): void
    {
        $this->switchManager
            ->expects($this->once())
            ->method('isEnabled')
            ->with('guzzle')
            ->willReturn(false);

        $proceedingJoinPoint = $this->createMock(ProceedingJoinPoint::class);

        $proceedingJoinPoint
            ->expects($this->once())
            ->method('process')
            ->willReturn('result');

        $result = $this->aspect->process($proceedingJoinPoint);

        $this->assertEquals('result', $result);
    }

    public function testProcessWithNoAspectOption(): void
    {
        $this->switchManager
            ->expects($this->once())
            ->method('isEnabled')
            ->with('guzzle')
            ->willReturn(true);

        $proceedingJoinPoint = $this->createMock(ProceedingJoinPoint::class);
        $proceedingJoinPoint->arguments = [
            'keys' => [
                'options' => ['no_aspect' => true],
            ],
        ];
        $proceedingJoinPoint
            ->expects($this->once())
            ->method('process')
            ->willReturn('result');

        $result = $this->aspect->process($proceedingJoinPoint);

        $this->assertEquals('result', $result);
    }

    public function testProcessSuccessfully(): void
    {
        $this->setupSuccessfulRequest();

        $client = $this->createMock(Client::class);
        $client
            ->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturnCallback(function ($key) {
                if ($key === 'base_uri') {
                    return new Uri('https://api.example.com');
                }
                if ($key === 'ignore_uri') {
                    return false;
                }
                if ($key === 'uri_mask') {
                    return [];
                }
                return null;
            });

        $response = $this->createMock(ResponseInterface::class);
        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        $this->mockSpan
            ->expects($this->atLeastOnce())
            ->method('setTag');

        $this->mockSpan
            ->expects($this->once())
            ->method('finish');

        $promise = $this->createMock(PromiseInterface::class);
        $promise
            ->expects($this->once())
            ->method('then')
            ->willReturnCallback(function ($onFulfilled) use ($response, $promise) {
                $onFulfilled($response);
                return $promise;
            });

        $proceedingJoinPoint = $this->createMock(ProceedingJoinPoint::class);
        $proceedingJoinPoint->className = 'TestClass';
        $proceedingJoinPoint->methodName = 'testMethod';
        $proceedingJoinPoint->arguments = [
            'keys' => [
                'method' => 'GET',
                'uri' => 'https://api.example.com/users',
                'options' => ['headers' => []],
            ],
        ];
        $proceedingJoinPoint
            ->expects($this->once())
            ->method('getInstance')
            ->willReturn($client);
        $proceedingJoinPoint
            ->expects($this->once())
            ->method('process')
            ->willReturn($promise);

        $this->tracer
            ->expects($this->once())
            ->method('inject')
            ->willReturnCallback(function ($context, $format, &$headers) {
                $headers['X-Trace-ID'] = 'test-trace-id';
            });

        $result = $this->aspect->process($proceedingJoinPoint);

        $this->assertInstanceOf(PromiseInterface::class, $result);
        $this->assertArrayHasKey('X-Trace-ID', $proceedingJoinPoint->arguments['keys']['options']['headers']);
    }

    public function testProcessSuccessfullyWithoutBaseUri(): void
    {
        $this->setupSuccessfulRequest();

        $client = $this->createMock(Client::class);
        $client
            ->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturnCallback(function ($key) {
                if ($key === 'base_uri') {
                    return null;
                }
                if ($key === 'ignore_uri') {
                    return false;
                }
                if ($key === 'uri_mask') {
                    return [];
                }
                return null;
            });

        $response = $this->createMock(ResponseInterface::class);
        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        $this->mockSpan
            ->expects($this->atLeastOnce())
            ->method('setTag');

        $this->mockSpan
            ->expects($this->once())
            ->method('finish');

        $promise = $this->createMock(PromiseInterface::class);
        $promise
            ->expects($this->once())
            ->method('then')
            ->willReturnCallback(function ($onFulfilled) use ($response, $promise) {
                $onFulfilled($response);
                return $promise;
            });

        $proceedingJoinPoint = $this->createMock(ProceedingJoinPoint::class);
        $proceedingJoinPoint->className = 'TestClass';
        $proceedingJoinPoint->methodName = 'testMethod';
        $proceedingJoinPoint->arguments = [
            'keys' => [
                'method' => 'POST',
                'uri' => 'https://api.example.com/users',
                'options' => ['headers' => []],
            ],
        ];
        $proceedingJoinPoint
            ->expects($this->once())
            ->method('getInstance')
            ->willReturn($client);
        $proceedingJoinPoint
            ->expects($this->once())
            ->method('process')
            ->willReturn($promise);

        $result = $this->aspect->process($proceedingJoinPoint);

        $this->assertInstanceOf(PromiseInterface::class, $result);
    }

    public function testProcessWithIgnoredUri(): void
    {
        $this->setupSuccessfulRequest();

        $client = $this->createMock(Client::class);
        $client
            ->expects($this->atLeastOnce())
            ->method('getConfig')
            ->willReturnCallback(function ($key) {
                if ($key === 'base_uri') {
                    return new Uri('https://api.example.com');
                }
                if ($key === 'ignore_uri') {
                    return true;
                }
                if ($key === 'uri_mask') {
                    return [];
                }
                return null;
            });

        $response = $this->createMock(ResponseInterface::class);
        $response
            ->expects($this->once())
            ->method('getStatusCode')
            ->willReturn(200);

        $this->mockSpan
            ->expects($this->atLeastOnce())
            ->method('setTag');

        $this->mockSpan
            ->expects($this->once())
            ->method('finish');

        $promise = $this->createMock(PromiseInterface::class);
        $promise
            ->expects($this->once())
            ->method('then')
            ->willReturnCallback(function ($onFulfilled) use ($response, $promise) {
                $onFulfilled($response);
                return $promise;
            });

        $proceedingJoinPoint = $this->createMock(ProceedingJoinPoint::class);
        $proceedingJoinPoint->className = 'TestClass';
        $proceedingJoinPoint->methodName = 'testMethod';
        $proceedingJoinPoint->arguments = [
            'keys' => [
                'method' => 'GET',
                'uri' => 'https://api.example.com/users/sensitive',
                'options' => ['headers' => []],
            ],
        ];
        $proceedingJoinPoint
            ->expects($this->once())
            ->method('getInstance')
            ->willReturn($client);
        $proceedingJoinPoint
            ->expects($this->once())
            ->method('process')
            ->willReturn($promise);

        $result = $this->aspect->process($proceedingJoinPoint);

        $this->assertInstanceOf(PromiseInterface::class, $result);
    }

    private function setupSuccessfulRequest(): void
    {
        $this->switchManager
            ->expects($this->once())
            ->method('isEnabled')
            ->with('guzzle')
            ->willReturn(true);

        $this->spanTagManager
            ->expects($this->atLeastOnce())
            ->method('has')
            ->willReturn(true);

        $this->spanTagManager
            ->expects($this->atLeastOnce())
            ->method('get')
            ->willReturnCallback(function ($category, $key) {
                return "{$category}.{$key}";
            });

        $this->tracer
            ->expects($this->once())
            ->method('inject');
    }
}

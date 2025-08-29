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
namespace Hyperf\Tracer\Adapter;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Tracer\Contract\NamedFactoryInterface;
use Zipkin\Endpoint;
use Zipkin\Reporters\Http;
use Zipkin\Samplers\BinarySampler;
use Zipkin\TracingBuilder;
use ZipkinOpenTracing\Tracer;

class ZipkinTracerFactory implements NamedFactoryInterface
{
    private string $prefix = 'opentracing.tracer.';

    private string $name = '';

    public function __construct(private ConfigInterface $config, private HttpClientFactory $clientFactory)
    {
    }

    public function make(string $name): \OpenTracing\Tracer
    {
        $this->name = $name;
        [$app, $options, $sampler] = $this->parseConfig();
        $endpoint = Endpoint::create($app['name'], $app['ipv4'], $app['ipv6'], $app['port']);
        $reporter = new Http($options, $this->clientFactory);
        $tracing = TracingBuilder::create()
            ->havingLocalEndpoint($endpoint)
            ->havingSampler($sampler)
            ->havingReporter($reporter)
            ->build();
        return new Tracer($tracing);
    }

    private function parseConfig(): array
    {
        // @TODO Detect the ipv4, ipv6, port from server object or system info automatically.
        return [
            $this->getConfig('app', [
                'name' => 'skeleton',
                'ipv4' => '127.0.0.1',
                'ipv6' => null,
                'port' => 9501,
            ]),
            $this->getConfig('options', [
                'timeout' => 1,
            ]),
            $this->getConfig('sampler', BinarySampler::createAsAlwaysSample()),
        ];
    }

    private function getConfig(string $key, $default)
    {
        return $this->config->get($this->getPrefix() . $key, $default);
    }

    private function getPrefix(): string
    {
        return rtrim($this->prefix . $this->name, '.') . '.';
    }
}

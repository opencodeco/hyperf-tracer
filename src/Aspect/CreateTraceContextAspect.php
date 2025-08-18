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

namespace Hyperf\Tracer\Aspect;

use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Tracer\TracerContext;
use Zipkin\Propagation\TraceContext;

class CreateTraceContextAspect extends AbstractAspect
{
    public array $classes = [
        TraceContext::class . '::create',
        TraceContext::class . '::create*',
    ];

    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        $traceContext = $proceedingJoinPoint->process();
        if ($traceContext instanceof TraceContext) {
            TracerContext::setTraceId($traceContext->getTraceId());
        }
        return $traceContext;
    }
}

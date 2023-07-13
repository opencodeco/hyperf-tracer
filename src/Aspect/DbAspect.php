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

use Hyperf\DB\DB;
use Hyperf\Di\Aop\AbstractAspect;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\Tracer\SpanStarter;
use Hyperf\Tracer\SpanTagManager;
use Hyperf\Tracer\SwitchManager;
use OpenTracing\Tracer;
use Throwable;

class DbAspect extends AbstractAspect
{
    use SpanStarter;

    public array $classes = [
        DB::class . '::__call',
    ];

    public function __construct(private Tracer $tracer, private SwitchManager $switchManager, private SpanTagManager $spanTagManager)
    {
    }

    /**
     * @return mixed return the value from process method of ProceedingJoinPoint, or the value that you handled
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        if ($this->switchManager->isEnable('db') === false) {
            return $proceedingJoinPoint->process();
        }

        $arguments = $proceedingJoinPoint->arguments['keys'];
        $span = $this->startSpan('Db::' . $arguments['name']);
        $span->setTag($this->spanTagManager->get('db', 'db.query'), json_encode($arguments['arguments'], JSON_THROW_ON_ERROR));
        try {
            $result = $proceedingJoinPoint->process();
        } catch (Throwable $e) {
            $span->setTag('error', true);
            $span->log(['message', $e->getMessage(), 'code' => $e->getCode(), 'stacktrace' => $e->getTraceAsString()]);
            throw $e;
        } finally {
            $span->finish();
        }
        return $result;
    }
}

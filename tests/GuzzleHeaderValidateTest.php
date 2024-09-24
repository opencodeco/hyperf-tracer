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

namespace HyperfTest\Tracer\Support;

use Hyperf\Tracer\Support\GuzzleHeaderValidate;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
final class GuzzleHeaderValidateTest extends TestCase
{
    public function testTraceIdDecoder(): void
    {
        self::assertFalse(GuzzleHeaderValidate::isValidHeader('uberctx-40247e74-4e8ccd0c@dt', '1234'));
        self::assertTrue(GuzzleHeaderValidate::isValidHeader('uberctx-40247e74-4e8ccd0c', '1234'));
    }
}

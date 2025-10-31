<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\SRT\Exception\InvalidTimerException;

/**
 * @internal
 */
#[CoversClass(InvalidTimerException::class)]
final class InvalidTimerExceptionTest extends AbstractExceptionTestCase
{
    public function testNoCallbackSet(): void
    {
        $exception = InvalidTimerException::noCallbackSet('keepalive');
        $this->assertInstanceOf(InvalidTimerException::class, $exception);
        $this->assertSame('No callback set for timer type: keepalive', $exception->getMessage());
    }

    public function testInvalidTimeout(): void
    {
        $exception = InvalidTimerException::invalidTimeout(-100);
        $this->assertSame('Invalid timeout value: -100', $exception->getMessage());
    }

    public function testTimerNotFound(): void
    {
        $exception = InvalidTimerException::timerNotFound('timer_123');
        $this->assertSame('Timer not found: timer_123', $exception->getMessage());
    }

    public function testInvalidTimerType(): void
    {
        $exception = InvalidTimerException::invalidTimerType('unknown');
        $this->assertSame('Invalid timer type: unknown', $exception->getMessage());
    }
}

<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\SRT\Exception\SendException;

/**
 * @internal
 */
#[CoversClass(SendException::class)]
final class SendExceptionTest extends AbstractExceptionTestCase
{
    public function testCanBeCreated(): void
    {
        $exception = new SendException('Send failed');
        $this->assertInstanceOf(SendException::class, $exception);
        $this->assertSame('Send failed', $exception->getMessage());
    }

    public function testCanBeCreatedWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Network error');
        $exception = new SendException('Failed to send data', 300, $previous);

        $this->assertSame('Failed to send data', $exception->getMessage());
        $this->assertSame(300, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}

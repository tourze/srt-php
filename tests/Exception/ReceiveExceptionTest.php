<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\SRT\Exception\ReceiveException;

/**
 * @internal
 */
#[CoversClass(ReceiveException::class)]
final class ReceiveExceptionTest extends AbstractExceptionTestCase
{
    public function testCanBeCreated(): void
    {
        $exception = new ReceiveException('Receive failed');
        $this->assertInstanceOf(ReceiveException::class, $exception);
        $this->assertSame('Receive failed', $exception->getMessage());
    }

    public function testCanBeCreatedWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Socket error');
        $exception = new ReceiveException('Failed to receive data', 200, $previous);

        $this->assertSame('Failed to receive data', $exception->getMessage());
        $this->assertSame(200, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}

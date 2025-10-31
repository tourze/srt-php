<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\SRT\Exception\HandshakeException;

/**
 * @internal
 */
#[CoversClass(HandshakeException::class)]
final class HandshakeExceptionTest extends AbstractExceptionTestCase
{
    public function testCanBeCreated(): void
    {
        $exception = new HandshakeException('Handshake failed');
        $this->assertInstanceOf(HandshakeException::class, $exception);
        $this->assertSame('Handshake failed', $exception->getMessage());
    }

    public function testCanBeCreatedWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new HandshakeException('Handshake error', 100, $previous);

        $this->assertSame('Handshake error', $exception->getMessage());
        $this->assertSame(100, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}

<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\SRT\Exception\HandshakeException;

class HandshakeExceptionTest extends TestCase
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
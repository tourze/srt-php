<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\SRT\Exception\InvalidPacketException;

class InvalidPacketExceptionTest extends TestCase
{
    public function testInvalidHeaderLength(): void
    {
        $exception = InvalidPacketException::invalidHeaderLength(10);
        $this->assertInstanceOf(InvalidPacketException::class, $exception);
        $this->assertSame('SRT header must be exactly 16 bytes, got 10 bytes', $exception->getMessage());
    }

    public function testInvalidSequenceNumber(): void
    {
        $exception = InvalidPacketException::invalidSequenceNumber(2147483648);
        $this->assertSame('Sequence number must be in range 0-2147483647, got 2147483648', $exception->getMessage());
    }

    public function testInvalidMessageNumber(): void
    {
        $exception = InvalidPacketException::invalidMessageNumber(67108864);
        $this->assertSame('Message number must be in range 0-67108863, got 67108864', $exception->getMessage());
    }

    public function testInvalidControlType(): void
    {
        $exception = InvalidPacketException::invalidControlType(999);
        $this->assertSame('Invalid control type: 999', $exception->getMessage());
    }

    public function testInvalidPacketPosition(): void
    {
        $exception = InvalidPacketException::invalidPacketPosition(5);
        $this->assertSame('Packet position must be 0-3, got 5', $exception->getMessage());
    }

    public function testInvalidEncryptionFlags(): void
    {
        $exception = InvalidPacketException::invalidEncryptionFlags(4);
        $this->assertSame('Encryption flags must be 0-3, got 4', $exception->getMessage());
    }
}
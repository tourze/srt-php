<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\SRT\Exception\TransportException;

class TransportExceptionTest extends TestCase
{
    public function testBindFailed(): void
    {
        $exception = TransportException::bindFailed('127.0.0.1', 8080, 'Address already in use');
        $this->assertInstanceOf(TransportException::class, $exception);
        $this->assertSame('Failed to bind to 127.0.0.1:8080: Address already in use', $exception->getMessage());
    }

    public function testConnectFailed(): void
    {
        $exception = TransportException::connectFailed('192.168.1.1', 9999, 'Connection refused');
        $this->assertSame('Failed to connect to 192.168.1.1:9999: Connection refused', $exception->getMessage());
    }

    public function testSendFailed(): void
    {
        $exception = TransportException::sendFailed('Broken pipe');
        $this->assertSame('Failed to send data: Broken pipe', $exception->getMessage());
    }

    public function testReceiveFailed(): void
    {
        $exception = TransportException::receiveFailed('Connection reset by peer');
        $this->assertSame('Failed to receive data: Connection reset by peer', $exception->getMessage());
    }

    public function testSocketCreationFailed(): void
    {
        $exception = TransportException::socketCreationFailed('No buffer space available');
        $this->assertSame('Failed to create socket: No buffer space available', $exception->getMessage());
    }

    public function testSocketOptionFailed(): void
    {
        $exception = TransportException::socketOptionFailed('SO_REUSEADDR', 'Invalid argument');
        $this->assertSame('Failed to set socket option SO_REUSEADDR: Invalid argument', $exception->getMessage());
    }

    public function testInvalidAddress(): void
    {
        $exception = TransportException::invalidAddress('not.a.valid.address:port');
        $this->assertSame('Invalid address: not.a.valid.address:port', $exception->getMessage());
    }
}
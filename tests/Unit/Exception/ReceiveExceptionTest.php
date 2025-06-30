<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\SRT\Exception\ReceiveException;

class ReceiveExceptionTest extends TestCase
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
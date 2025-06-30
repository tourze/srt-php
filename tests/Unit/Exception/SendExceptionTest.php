<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\SRT\Exception\SendException;

class SendExceptionTest extends TestCase
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
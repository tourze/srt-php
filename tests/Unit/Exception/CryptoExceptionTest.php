<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use Tourze\SRT\Exception\CryptoException;

class CryptoExceptionTest extends TestCase
{
    public function testCanBeCreated(): void
    {
        $exception = new CryptoException('Test message');
        $this->assertInstanceOf(CryptoException::class, $exception);
    }

    public function testCanBeCreatedWithCode(): void
    {
        $exception = new CryptoException('Encryption failed', CryptoException::ENCRYPTION_FAILED);
        $this->assertSame('Encryption failed', $exception->getMessage());
        $this->assertSame(CryptoException::ENCRYPTION_FAILED, $exception->getCode());
    }

    public function testConstants(): void
    {
        $this->assertSame(1001, CryptoException::ENCRYPTION_FAILED);
        $this->assertSame(1002, CryptoException::DECRYPTION_FAILED);
        $this->assertSame(1003, CryptoException::KEY_GENERATION_FAILED);
        $this->assertSame(1004, CryptoException::UNSUPPORTED_ALGORITHM);
        $this->assertSame(1005, CryptoException::KEY_EXCHANGE_FAILED);
    }
}
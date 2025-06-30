<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Crypto;

use PHPUnit\Framework\TestCase;
use Tourze\SRT\Crypto\EncryptionManager;

class EncryptionManagerTest extends TestCase
{
    public function testPlaceholder(): void
    {
        $this->assertTrue(class_exists(EncryptionManager::class));
    }
} 
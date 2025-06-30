<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Protocol;

use PHPUnit\Framework\TestCase;
use Tourze\SRT\Protocol\PacketHeader;

class PacketHeaderTest extends TestCase
{
    public function testPlaceholder(): void
    {
        // 基本测试确保类可以实例化
        $this->assertTrue(class_exists(PacketHeader::class));
    }
} 
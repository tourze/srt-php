<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Tourze\SRT\Transport\UdpTransport;

class UdpTransportTest extends TestCase
{
    public function testPlaceholder(): void
    {
        $this->assertTrue(class_exists(UdpTransport::class));
    }
} 
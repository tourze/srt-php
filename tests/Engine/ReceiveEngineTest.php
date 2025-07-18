<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Engine;

use PHPUnit\Framework\TestCase;
use Tourze\SRT\Engine\ReceiveEngine;

class ReceiveEngineTest extends TestCase
{
    public function testPlaceholder(): void
    {
        $this->assertTrue(class_exists(ReceiveEngine::class));
    }
} 
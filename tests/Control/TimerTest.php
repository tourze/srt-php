<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Control;

use PHPUnit\Framework\TestCase;
use Tourze\SRT\Control\Timer;

/**
 * 定时器测试
 */
class TimerTest extends TestCase
{
    public function testTimerConstruction(): void
    {
        $callback = fn() => 'test';
        $timer = new Timer(
            'test-id',
            'test-type',
            1000000, // 1秒后过期
            $callback,
            ['key' => 'value']
        );

        $this->assertEquals('test-id', $timer->id);
        $this->assertEquals('test-type', $timer->type);
        $this->assertEquals(1000000, $timer->expireTime);
        $this->assertEquals(['key' => 'value'], $timer->data);
        $this->assertEquals($callback, $timer->getCallback());
    }

    public function testTimerCallback(): void
    {
        $executed = false;
        $callback = function() use (&$executed) {
            $executed = true;
            return 'executed';
        };

        $timer = new Timer('test', 'test', 1000000, $callback);
        
        $result = call_user_func($timer->getCallback());
        
        $this->assertTrue($executed);
        $this->assertEquals('executed', $result);
    }

    public function testTimerWithEmptyData(): void
    {
        $timer = new Timer('test', 'test', 1000000, fn() => null);
        
        $this->assertEmpty($timer->data);
    }

    public function testTimerWithCallbackParameters(): void
    {
        $receivedParams = [];
        $callback = function($param1, $param2) use (&$receivedParams) {
            $receivedParams = [$param1, $param2];
        };

        $timer = new Timer('test', 'test', 1000000, $callback);
        
        call_user_func($timer->getCallback(), 'param1', 'param2');
        
        $this->assertEquals(['param1', 'param2'], $receivedParams);
    }
} 
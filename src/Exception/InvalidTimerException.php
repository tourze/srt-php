<?php

declare(strict_types=1);

namespace Tourze\SRT\Exception;

use InvalidArgumentException;

/**
 * 定时器无效异常
 *
 * 当定时器操作遇到无效参数或状态时抛出此异常
 */
class InvalidTimerException extends InvalidArgumentException
{
    public static function noCallbackSet(string $timerType): self
    {
        return new self("No callback set for timer type: {$timerType}");
    }

    public static function invalidTimeout(int $timeout): self
    {
        return new self("Invalid timeout value: {$timeout}");
    }

    public static function timerNotFound(string $timerId): self
    {
        return new self("Timer not found: {$timerId}");
    }

    public static function invalidTimerType(string $timerType): self
    {
        return new self("Invalid timer type: {$timerType}");
    }
}

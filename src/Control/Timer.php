<?php

declare(strict_types=1);

namespace Tourze\SRT\Control;

/**
 * 定时器结构
 */
class Timer
{
    private $callback;

    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly int $expireTime,
        callable $callback,
        public readonly array $data = []
    ) {
        $this->callback = $callback;
    }

    public function getCallback(): callable
    {
        return $this->callback;
    }
}
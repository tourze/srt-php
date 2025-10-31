<?php

declare(strict_types=1);

namespace Tourze\SRT\Control;

/**
 * 定时器结构
 */
class Timer
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly int $expireTime,
        private readonly \Closure $callback,
        public readonly array $data = [],
    ) {
    }

    public function getCallback(): callable
    {
        return $this->callback;
    }
}

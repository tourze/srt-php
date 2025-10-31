<?php

declare(strict_types=1);

namespace Tourze\SRT\Transport;

/**
 * 传输层接口
 *
 * 定义SRT协议传输层的标准接口
 */
interface TransportInterface
{
    /**
     * 发送数据
     */
    public function send(string $data): int;

    /**
     * 发送数据到指定地址
     */
    public function sendTo(string $data, string $host, int $port): int;

    /**
     * 接收数据
     *
     * @return array{data: string, from: array{host: string, port: int}}|null
     */
    public function receive(int $maxLength): ?array;

    /**
     * 连接到远程地址
     */
    public function connect(string $host, int $port): bool;

    /**
     * 绑定本地地址
     */
    public function bind(string $host, int $port): bool;

    /**
     * 设置非阻塞模式
     */
    public function setNonBlocking(bool $nonBlocking): void;

    /**
     * 设置Socket选项
     *
     * @param array<mixed>|int|string $value
     */
    public function setSocketOption(int $option, array|int|string $value): void;

    /**
     * 检查是否已连接
     */
    public function isConnected(): bool;

    /**
     * 获取本地地址
     *
     * @return array{host: string, port: int}|null
     */
    public function getLocalAddress(): ?array;

    /**
     * 获取远程地址
     *
     * @return array{host: string, port: int}|null
     */
    public function getRemoteAddress(): ?array;

    /**
     * 获取统计信息
     *
     * @return array<string, int>
     */
    public function getStatistics(): array;

    /**
     * 重置统计信息
     */
    public function resetStatistics(): void;

    /**
     * 关闭连接
     */
    public function close(): void;
}

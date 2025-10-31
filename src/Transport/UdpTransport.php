<?php

declare(strict_types=1);

namespace Tourze\SRT\Transport;

use Tourze\SRT\Exception\TransportException;

/**
 * UDP 传输层实现
 *
 * 提供基于 UDP 的网络传输功能，作为 SRT 协议的底层传输
 */
class UdpTransport implements TransportInterface
{
    /** @var \Socket|null UDP Socket 资源 */
    private ?\Socket $socket = null;

    /** @var array{host: string, port: int}|null 本地绑定地址 */
    private ?array $localAddress = null;

    /** @var array{host: string, port: int}|null 远程连接地址 */
    private ?array $remoteAddress = null;

    /** @var array<string, int> 统计信息 */
    private array $statistics = [
        'bytes_sent' => 0,
        'bytes_received' => 0,
        'packets_sent' => 0,
        'packets_received' => 0,
        'errors' => 0,
    ];

    /**
     * 构造函数
     */
    public function __construct()
    {
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (false === $socket) {
            throw TransportException::socketCreationFailed(socket_strerror(socket_last_error()));
        }
        $this->socket = $socket;
    }

    /**
     * 析构函数
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * 绑定到本地地址
     */
    public function bind(string $host, int $port): bool
    {
        if (null === $this->socket) {
            return false;
        }

        $result = socket_bind($this->socket, $host, $port);
        if (!$result) {
            $error = socket_strerror(socket_last_error($this->socket));
            throw TransportException::bindFailed($host, $port, $error);
        }

        // 获取实际绑定的地址（当端口为0时系统会自动分配）
        $actualHost = '';
        $actualPort = 0;
        if (!socket_getsockname($this->socket, $actualHost, $actualPort)) {
            $error = socket_strerror(socket_last_error($this->socket));
            throw TransportException::bindFailed($host, $port, "Cannot get socket name: {$error}");
        }

        assert(is_string($actualHost));
        assert(is_int($actualPort));

        $this->localAddress = [
            'host' => $actualHost,
            'port' => $actualPort,
        ];

        return true;
    }

    /**
     * 连接到远程地址
     */
    public function connect(string $host, int $port): bool
    {
        if (null === $this->socket) {
            return false;
        }

        // 验证地址有效性
        if (false === filter_var($host, FILTER_VALIDATE_IP) && gethostbyname($host) === $host) {
            throw TransportException::invalidAddress("{$host}:{$port}");
        }

        $result = socket_connect($this->socket, $host, $port);
        if (!$result) {
            $error = socket_strerror(socket_last_error($this->socket));
            throw TransportException::connectFailed($host, $port, $error);
        }

        $this->remoteAddress = [
            'host' => $host,
            'port' => $port,
        ];

        return true;
    }

    /**
     * 发送数据到已连接的地址
     */
    public function send(string $data): int
    {
        if (null === $this->socket) {
            throw TransportException::sendFailed('Socket not initialized');
        }

        $bytesSent = socket_send($this->socket, $data, strlen($data), 0);
        if (false === $bytesSent) {
            $error = socket_strerror(socket_last_error($this->socket));
            ++$this->statistics['errors'];
            throw TransportException::sendFailed($error);
        }

        $this->statistics['bytes_sent'] += $bytesSent;
        ++$this->statistics['packets_sent'];

        return $bytesSent;
    }

    /**
     * 发送数据到指定地址
     */
    public function sendTo(string $data, string $host, int $port): int
    {
        if (null === $this->socket) {
            throw TransportException::sendFailed('Socket not initialized');
        }

        $bytesSent = socket_sendto($this->socket, $data, strlen($data), 0, $host, $port);
        if (false === $bytesSent) {
            $error = socket_strerror(socket_last_error($this->socket));
            ++$this->statistics['errors'];
            throw TransportException::sendFailed($error);
        }

        $this->statistics['bytes_sent'] += $bytesSent;
        ++$this->statistics['packets_sent'];

        return $bytesSent;
    }

    /**
     * 接收数据
     *
     * @return array{data: string, from: array{host: string, port: int}}|null
     */
    public function receive(int $maxLength): ?array
    {
        if (null === $this->socket) {
            return null;
        }

        $data = '';
        $from = '';
        $port = 0;

        $bytesReceived = socket_recvfrom($this->socket, $data, $maxLength, 0, $from, $port);

        if (false === $bytesReceived) {
            $error = socket_last_error($this->socket);

            // 对于非阻塞模式，EAGAIN/EWOULDBLOCK 不是错误
            if (in_array($error, [SOCKET_EAGAIN, SOCKET_EWOULDBLOCK], true)) {
                return null;
            }

            $errorMsg = socket_strerror($error);
            ++$this->statistics['errors'];
            throw TransportException::receiveFailed($errorMsg);
        }

        if (0 === $bytesReceived) {
            return null;
        }

        $this->statistics['bytes_received'] += $bytesReceived;
        ++$this->statistics['packets_received'];

        assert(is_string($data));
        assert(is_string($from));
        assert(is_int($port));

        return [
            'data' => $data,
            'from' => [
                'host' => $from,
                'port' => $port,
            ],
        ];
    }

    /**
     * 设置非阻塞模式
     */
    public function setNonBlocking(bool $nonBlocking): void
    {
        if (null === $this->socket) {
            throw new TransportException('无法设置非阻塞模式：Socket 未初始化');
        }

        $result = $nonBlocking ? socket_set_nonblock($this->socket) : socket_set_block($this->socket);
        if (!$result) {
            $error = socket_strerror(socket_last_error($this->socket));
            throw new TransportException("设置非阻塞模式失败: {$error}");
        }
    }

    /**
     * 设置 Socket 选项
     * @param array<mixed>|int|string $value
     */
    public function setSocketOption(int $option, array|int|string $value): void
    {
        if (null === $this->socket) {
            throw new TransportException('无法设置 Socket 选项：Socket 未初始化');
        }

        $result = socket_set_option($this->socket, SOL_SOCKET, $option, $value);
        if (!$result) {
            $error = socket_strerror(socket_last_error($this->socket));
            throw TransportException::socketOptionFailed("option_{$option}", $error);
        }
    }

    /**
     * 检查是否已连接
     */
    public function isConnected(): bool
    {
        return null !== $this->socket && (null !== $this->localAddress || null !== $this->remoteAddress);
    }

    /**
     * 获取本地地址
     *
     * @return array{host: string, port: int}|null
     */
    public function getLocalAddress(): ?array
    {
        return $this->localAddress;
    }

    /**
     * 获取远程地址
     *
     * @return array{host: string, port: int}|null
     */
    public function getRemoteAddress(): ?array
    {
        return $this->remoteAddress;
    }

    /**
     * 获取统计信息
     *
     * @return array<string, int>
     */
    public function getStatistics(): array
    {
        return $this->statistics;
    }

    /**
     * 重置统计信息
     */
    public function resetStatistics(): void
    {
        $this->statistics = [
            'bytes_sent' => 0,
            'bytes_received' => 0,
            'packets_sent' => 0,
            'packets_received' => 0,
            'errors' => 0,
        ];
    }

    /**
     * 关闭连接
     */
    public function close(): void
    {
        if (null !== $this->socket) {
            socket_close($this->socket);
            $this->socket = null;
            $this->localAddress = null;
            $this->remoteAddress = null;
        }
    }
}

<?php

declare(strict_types=1);

namespace Tourze\SRT\Protocol;

use Tourze\SRT\Exception\HandshakeException;

/**
 * SRT 握手管理器
 *
 * 负责处理 SRT 连接的握手流程，包括：
 * - Caller-Listener 握手
 * - Rendezvous 握手  
 * - 加密协商
 * - 能力交换
 *
 * @see https://datatracker.ietf.org/doc/html/draft-sharabayko-srt-01#section-3
 */
class HandshakeManager
{
    // 握手类型
    public const TYPE_CALLER_LISTENER = 1;
    public const TYPE_RENDEZVOUS = 2;

    // 握手状态
    public const STATE_INIT = 0;
    public const STATE_INDUCTION = 1;
    public const STATE_CONCLUSION = 2;
    public const STATE_DONE = 3;
    public const STATE_ERROR = -1;

    // SRT 版本信息
    public const SRT_VERSION = 0x010405; // v1.4.5
    public const MIN_VERSION = 0x010300; // v1.3.0

    // 握手扩展字段
    public const HS_EXT_SRT_VERSION = 1;
    public const HS_EXT_SRT_FLAGS = 2;
    public const HS_EXT_SRT_TSBPD_DELAY = 3;
    public const HS_EXT_SRT_PEER_LATENCY = 4;
    public const HS_EXT_SRT_ENCRYPTION = 5;

    private int $state = self::STATE_INIT;
    private int $type;
    private string $streamId = '';
    private array $extensions = [];
    private ?string $passphrase = null;
    private bool $encryptionEnabled = false;
    private int $latencyMs = 120; // 默认延迟 120ms

    public function __construct(int $type = self::TYPE_CALLER_LISTENER)
    {
        $this->type = $type;
    }

    /**
     * 创建 Caller 握手包（Induction 阶段）
     */
    public function createCallerInduction(int $socketId): HandshakePacket
    {
        $packet = new HandshakePacket();
        $packet->setVersion(self::SRT_VERSION);
        $packet->setEncryptionField(0); // Induction 阶段不使用加密
        $packet->setExtensionField(0);
        $packet->setInitialSequenceNumber(random_int(1, 0x7FFFFFFF));
        $packet->setMaxTransmissionUnitSize(1500);
        $packet->setMaxFlowWinSize(8192);
        $packet->setHandshakeType(1); // Induction
        $packet->setSrtSocketId($socketId);
        $packet->setPeerIpAddress('0.0.0.0'); // 由对端填充

        $this->state = self::STATE_INDUCTION;
        return $packet;
    }

    /**
     * 创建 Caller 握手包（Conclusion 阶段）
     */
    public function createCallerConclusion(int $socketId, HandshakePacket $inductionResponse): HandshakePacket
    {
        if ($this->state !== self::STATE_INDUCTION) {
            throw new HandshakeException('Invalid state for Conclusion phase');
        }

        $packet = new HandshakePacket();
        $packet->setVersion(self::SRT_VERSION);
        $packet->setEncryptionField($this->encryptionEnabled ? 2 : 0);
        $packet->setExtensionField(0x4A17); // SRT magic number
        $packet->setInitialSequenceNumber(random_int(1, 0x7FFFFFFF));
        $packet->setMaxTransmissionUnitSize(1500);
        $packet->setMaxFlowWinSize(8192);
        $packet->setHandshakeType(-1); // Conclusion
        $packet->setSrtSocketId($socketId);
        $packet->setPeerIpAddress($inductionResponse->getPeerIpAddress());

        // 添加 SRT 扩展
        $this->addSrtExtensions($packet);

        $this->state = self::STATE_CONCLUSION;
        return $packet;
    }

    /**
     * 处理 Listener 收到的握手包
     */
    public function processListenerHandshake(HandshakePacket $packet): HandshakePacket
    {
        $version = $packet->getVersion();
        if ($version < self::MIN_VERSION) {
            throw new HandshakeException("Unsupported SRT version: " . dechex($version));
        }

        $hsType = $packet->getHandshakeType();

        if ($hsType === 1) { // Induction
            return $this->createListenerInductionResponse($packet);
        } elseif ($hsType === -1) { // Conclusion
            return $this->createListenerConclusionResponse($packet);
        }

        throw new HandshakeException("Invalid handshake type: $hsType");
    }

    /**
     * 创建 Listener Induction 响应
     */
    private function createListenerInductionResponse(HandshakePacket $request): HandshakePacket
    {
        $response = new HandshakePacket();
        $response->setVersion(self::SRT_VERSION);
        $response->setEncryptionField(0);
        $response->setExtensionField(0);
        $response->setInitialSequenceNumber(random_int(1, 0x7FFFFFFF));
        $response->setMaxTransmissionUnitSize(1500);
        $response->setMaxFlowWinSize(8192);
        $response->setHandshakeType(0); // Response
        $response->setSrtSocketId(random_int(1, 0x7FFFFFFF));
        $response->setPeerIpAddress((string)$request->getSrtSocketId());

        $this->state = self::STATE_INDUCTION;
        return $response;
    }

    /**
     * 创建 Listener Conclusion 响应
     */
    private function createListenerConclusionResponse(HandshakePacket $request): HandshakePacket
    {
        // 验证 SRT 扩展
        $this->validateSrtExtensions($request);

        $response = new HandshakePacket();
        $response->setVersion(self::SRT_VERSION);
        $response->setEncryptionField($this->encryptionEnabled ? 2 : 0);
        $response->setExtensionField(0x4A17); // SRT magic number
        $response->setInitialSequenceNumber(random_int(1, 0x7FFFFFFF));
        $response->setMaxTransmissionUnitSize(1500);
        $response->setMaxFlowWinSize(8192);
        $response->setHandshakeType(0); // Response
        $response->setSrtSocketId((int)$request->getPeerIpAddress());
        $response->setPeerIpAddress((string)$request->getSrtSocketId());

        // 添加 SRT 扩展
        $this->addSrtExtensions($response);

        $this->state = self::STATE_DONE;
        return $response;
    }

    /**
     * 添加 SRT 扩展字段
     */
    private function addSrtExtensions(HandshakePacket $packet): void
    {
        $extensions = [];

        // SRT 版本
        $extensions[self::HS_EXT_SRT_VERSION] = self::SRT_VERSION;

        // SRT 标志
        $flags = 0;
        if ($this->encryptionEnabled) {
            $flags |= 0x01; // 启用加密
        }
        $extensions[self::HS_EXT_SRT_FLAGS] = $flags;

        // TSBPD 延迟
        $extensions[self::HS_EXT_SRT_TSBPD_DELAY] = $this->latencyMs * 1000; // 转换为微秒

        // 对端延迟
        $extensions[self::HS_EXT_SRT_PEER_LATENCY] = $this->latencyMs * 1000;

        // 加密配置
        if ($this->encryptionEnabled && $this->passphrase !== null) {
            $extensions[self::HS_EXT_SRT_ENCRYPTION] = $this->generateEncryptionData();
        }

        // 合并用户自定义扩展
        $extensions = array_merge($extensions, $this->extensions);

        $packet->setSrtExtensions($extensions);
    }

    /**
     * 验证 SRT 扩展字段
     */
    private function validateSrtExtensions(HandshakePacket $packet): void
    {
        $extensions = $packet->getSrtExtensions();

        // 验证版本兼容性
        if (isset($extensions[self::HS_EXT_SRT_VERSION])) {
            $peerVersion = $extensions[self::HS_EXT_SRT_VERSION];
            if ($peerVersion < self::MIN_VERSION) {
                throw new HandshakeException("Peer SRT version too old: " . dechex($peerVersion));
            }
        }

        // 验证加密要求
        if (isset($extensions[self::HS_EXT_SRT_FLAGS])) {
            $peerFlags = $extensions[self::HS_EXT_SRT_FLAGS];
            $peerEncryption = ($peerFlags & 0x01) !== 0;

            if ($this->encryptionEnabled !== $peerEncryption) {
                throw new HandshakeException("Encryption requirement mismatch");
            }
        }

        // 协商延迟参数
        if (isset($extensions[self::HS_EXT_SRT_PEER_LATENCY])) {
            $peerLatency = $extensions[self::HS_EXT_SRT_PEER_LATENCY] / 1000; // 转换为毫秒
            $this->latencyMs = max($this->latencyMs, $peerLatency);
        }
    }

    /**
     * 生成加密数据
     */
    private function generateEncryptionData(): string
    {
        if ($this->passphrase === null) {
            throw new HandshakeException("Passphrase required for encryption");
        }

        // 生成密钥盐值
        $salt = random_bytes(16);

        // 使用 PBKDF2 派生密钥
        $key = hash_pbkdf2('sha256', $this->passphrase, $salt, 2048, 32, true);

        return $salt . $key;
    }

    /**
     * 设置流ID
     */
    public function setStreamId(string $streamId): void
    {
        if (strlen($streamId) > 512) {
            throw new HandshakeException("Stream ID too long (max 512 characters)");
        }
        $this->streamId = $streamId;
    }

    /**
     * 获取流ID
     */
    public function getStreamId(): string
    {
        return $this->streamId;
    }

    /**
     * 获取握手类型
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * 获取扩展字段
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * 设置扩展字段
     */
    public function setExtensions(array $extensions): void
    {
        $this->extensions = $extensions;
    }

    /**
     * 启用加密
     */
    public function enableEncryption(string $passphrase): void
    {
        if (strlen($passphrase) < 10 || strlen($passphrase) > 79) {
            throw new HandshakeException("Passphrase length must be 10-79 characters");
        }
        $this->passphrase = $passphrase;
        $this->encryptionEnabled = true;
    }

    /**
     * 设置延迟
     */
    public function setLatency(int $latencyMs): void
    {
        if ($latencyMs < 20 || $latencyMs > 8000) {
            throw new HandshakeException("Latency must be between 20-8000ms");
        }
        $this->latencyMs = $latencyMs;
    }

    /**
     * 获取当前状态
     */
    public function getState(): int
    {
        return $this->state;
    }

    /**
     * 是否握手完成
     */
    public function isCompleted(): bool
    {
        return $this->state === self::STATE_DONE;
    }

    /**
     * 是否握手失败
     */
    public function isFailed(): bool
    {
        return $this->state === self::STATE_ERROR;
    }
}

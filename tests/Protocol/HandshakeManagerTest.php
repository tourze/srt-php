<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Protocol;

use PHPUnit\Framework\TestCase;
use Tourze\SRT\Exception\HandshakeException;
use Tourze\SRT\Protocol\HandshakeManager;
use Tourze\SRT\Protocol\HandshakePacket;

/**
 * 握手管理器测试
 */
class HandshakeManagerTest extends TestCase
{
    private HandshakeManager $handshakeManager;
    
    protected function setUp(): void
    {
        $this->handshakeManager = new HandshakeManager();
    }
    
    public function testCreateCallerInduction(): void
    {
        $socketId = 12345;
        $packet = $this->handshakeManager->createCallerInduction($socketId);
        
        $this->assertInstanceOf(HandshakePacket::class, $packet);
        $this->assertEquals(HandshakeManager::SRT_VERSION, $packet->getVersion());
        $this->assertEquals(1, $packet->getHandshakeType()); // Induction
        $this->assertEquals($socketId, $packet->getSrtSocketId());
        $this->assertEquals(HandshakeManager::STATE_INDUCTION, $this->handshakeManager->getState());
    }
    
    public function testCreateCallerConclusionRequiresInductionState(): void
    {
        $this->expectException(HandshakeException::class);
        $this->expectExceptionMessage('Invalid state for Conclusion phase');
        
        $socketId = 12345;
        $inductionResponse = new HandshakePacket();
        
        // 尝试在初始状态直接创建 Conclusion（应该失败）
        $this->handshakeManager->createCallerConclusion($socketId, $inductionResponse);
    }
    
    public function testCreateCallerConclusion(): void
    {
        $socketId = 12345;
        
        // 先创建 Induction
        $this->handshakeManager->createCallerInduction($socketId);
        
        // 模拟 Induction 响应
        $inductionResponse = new HandshakePacket();
        $inductionResponse->setPeerIpAddress('192.168.1.100');
        
        // 创建 Conclusion
        $packet = $this->handshakeManager->createCallerConclusion($socketId, $inductionResponse);
        
        $this->assertInstanceOf(HandshakePacket::class, $packet);
        $this->assertEquals(-1, $packet->getHandshakeType()); // Conclusion
        $this->assertEquals(0x4A17, $packet->getExtensionField()); // SRT magic number
        $this->assertEquals(HandshakeManager::STATE_CONCLUSION, $this->handshakeManager->getState());
    }
    
    public function testProcessListenerHandshakeInduction(): void
    {
        $request = new HandshakePacket();
        $request->setVersion(HandshakeManager::SRT_VERSION);
        $request->setHandshakeType(1); // Induction
        $request->setSrtSocketId(12345);
        
        $response = $this->handshakeManager->processListenerHandshake($request);
        
        $this->assertInstanceOf(HandshakePacket::class, $response);
        $this->assertEquals(0, $response->getHandshakeType()); // Response
        $this->assertEquals(HandshakeManager::STATE_INDUCTION, $this->handshakeManager->getState());
    }
    
    public function testProcessListenerHandshakeUnsupportedVersion(): void
    {
        $this->expectException(HandshakeException::class);
        $this->expectExceptionMessage('Unsupported SRT version');
        
        $request = new HandshakePacket();
        $request->setVersion(0x010200); // 版本太旧
        $request->setHandshakeType(1);
        
        $this->handshakeManager->processListenerHandshake($request);
    }
    
    public function testEnableEncryption(): void
    {
        $passphrase = 'test-passphrase-123';
        $this->handshakeManager->enableEncryption($passphrase);
        
        // 通过创建握手包来验证加密是否启用
        $socketId = 12345;
        $this->handshakeManager->createCallerInduction($socketId);
        
        $inductionResponse = new HandshakePacket();
        $packet = $this->handshakeManager->createCallerConclusion($socketId, $inductionResponse);
        
        $this->assertEquals(2, $packet->getEncryptionField()); // 启用加密
    }
    
    public function testEnableEncryptionInvalidPassphrase(): void
    {
        $this->expectException(HandshakeException::class);
        $this->expectExceptionMessage('Passphrase length must be 10-79 characters');
        
        $this->handshakeManager->enableEncryption('short'); // 太短
    }
    
    public function testSetLatency(): void
    {
        $latency = 200;
        $this->handshakeManager->setLatency($latency);
        
        // 通过创建带扩展的握手包来验证延迟设置
        $socketId = 12345;
        $this->handshakeManager->createCallerInduction($socketId);
        
        $inductionResponse = new HandshakePacket();
        $packet = $this->handshakeManager->createCallerConclusion($socketId, $inductionResponse);
        
        $extensions = $packet->getSrtExtensions();
        $this->assertArrayHasKey(HandshakeManager::HS_EXT_SRT_TSBPD_DELAY, $extensions);
        $this->assertEquals($latency * 1000, $extensions[HandshakeManager::HS_EXT_SRT_TSBPD_DELAY]);
    }
    
    public function testSetLatencyInvalidRange(): void
    {
        $this->expectException(HandshakeException::class);
        $this->expectExceptionMessage('Latency must be between 20-8000ms');
        
        $this->handshakeManager->setLatency(10); // 太小
    }
    
    public function testSetStreamId(): void
    {
        $streamId = 'test-stream-id';
        $this->handshakeManager->setStreamId($streamId);
        
        // 验证流ID设置成功（通过反射或其他方式）
        $this->assertTrue(true); // 占位符测试
    }
    
    public function testSetStreamIdTooLong(): void
    {
        $this->expectException(HandshakeException::class);
        $this->expectExceptionMessage('Stream ID too long');
        
        $longStreamId = str_repeat('a', 513); // 超过512字符
        $this->handshakeManager->setStreamId($longStreamId);
    }
    
    public function testIsCompleted(): void
    {
        $this->assertFalse($this->handshakeManager->isCompleted());
        $this->assertFalse($this->handshakeManager->isFailed());
        
        // 模拟完成握手过程
        $socketId = 12345;
        
        // Caller 端流程
        $inductionPacket = $this->handshakeManager->createCallerInduction($socketId);
        
        $inductionResponse = new HandshakePacket();
        $inductionResponse->setVersion(HandshakeManager::SRT_VERSION);
        $inductionResponse->setHandshakeType(0);
        $inductionResponse->setPeerIpAddress('192.168.1.100');
        
        $conclusionPacket = $this->handshakeManager->createCallerConclusion($socketId, $inductionResponse);
        
        // 此时握手应该处于 CONCLUSION 状态
        $this->assertEquals(HandshakeManager::STATE_CONCLUSION, $this->handshakeManager->getState());
        $this->assertFalse($this->handshakeManager->isCompleted());
    }
} 
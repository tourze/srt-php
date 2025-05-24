<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Unit\Transport;

use PHPUnit\Framework\TestCase;
use Tourze\SRT\Exception\TransportException;
use Tourze\SRT\Transport\UdpTransport;

/**
 * UDP 传输层测试
 * 
 * 测试 UDP Socket 的基本操作
 */
final class UdpTransportTest extends TestCase
{
    private UdpTransport $transport;

    protected function setUp(): void
    {
        $this->transport = new UdpTransport();
    }

    protected function tearDown(): void
    {
        $this->transport->close();
    }

    /**
     * 测试 UDP Socket 创建和绑定
     */
    public function testCanCreateAndBindSocket(): void
    {
        $result = $this->transport->bind('127.0.0.1', 0); // 端口0自动分配
        $this->assertTrue($result);
        $this->assertTrue($this->transport->isConnected());
        
        $localAddress = $this->transport->getLocalAddress();
        $this->assertEquals('127.0.0.1', $localAddress['host']);
        $this->assertGreaterThan(0, $localAddress['port']);
    }

    /**
     * 测试发送和接收数据
     */
    public function testCanSendAndReceiveData(): void
    {
        // 创建两个 transport 实例进行通信测试
        $sender = new UdpTransport();
        $receiver = new UdpTransport();

        try {
            // 接收端绑定到随机端口
            $receiver->bind('127.0.0.1', 0);
            $receiverAddr = $receiver->getLocalAddress();

            // 发送端连接到接收端
            $sender->connect($receiverAddr['host'], $receiverAddr['port']);

            // 发送数据
            $testData = 'Hello SRT!';
            $bytesSent = $sender->send($testData);
            $this->assertEquals(strlen($testData), $bytesSent);

            // 接收数据
            $receivedData = $receiver->receive(1024);
            $this->assertEquals($testData, $receivedData['data']);
            $this->assertArrayHasKey('from', $receivedData);
            $this->assertArrayHasKey('host', $receivedData['from']);
            $this->assertArrayHasKey('port', $receivedData['from']);

        } finally {
            $sender->close();
            $receiver->close();
        }
    }

    /**
     * 测试发送到指定地址
     */
    public function testCanSendToSpecificAddress(): void
    {
        $receiver = new UdpTransport();
        $receiver->bind('127.0.0.1', 0);
        $receiverAddr = $receiver->getLocalAddress();

        try {
            $testData = 'Direct send test';
            $bytesSent = $this->transport->sendTo(
                $testData, 
                $receiverAddr['host'], 
                $receiverAddr['port']
            );
            $this->assertEquals(strlen($testData), $bytesSent);

            $receivedData = $receiver->receive(1024);
            $this->assertEquals($testData, $receivedData['data']);

        } finally {
            $receiver->close();
        }
    }

    /**
     * 测试非阻塞模式
     */
    public function testNonBlockingMode(): void
    {
        $this->transport->bind('127.0.0.1', 0);
        $this->transport->setNonBlocking(true);

        // 非阻塞模式下，没有数据时应该返回 null
        $result = $this->transport->receive(1024);
        $this->assertNull($result);
    }

    /**
     * 测试 Socket 选项设置
     */
    public function testCanSetSocketOptions(): void
    {
        $this->transport->bind('127.0.0.1', 0);

        // 设置接收缓冲区大小
        $result = $this->transport->setSocketOption(SO_RCVBUF, 65536);
        $this->assertTrue($result);

        // 设置发送缓冲区大小
        $result = $this->transport->setSocketOption(SO_SNDBUF, 65536);
        $this->assertTrue($result);
    }

    /**
     * 测试获取统计信息
     */
    public function testCanGetStatistics(): void
    {
        $this->transport->bind('127.0.0.1', 0);
        
        $stats = $this->transport->getStatistics();
        
        $this->assertArrayHasKey('bytes_sent', $stats);
        $this->assertArrayHasKey('bytes_received', $stats);
        $this->assertArrayHasKey('packets_sent', $stats);
        $this->assertArrayHasKey('packets_received', $stats);
        $this->assertArrayHasKey('errors', $stats);
        
        $this->assertEquals(0, $stats['bytes_sent']);
        $this->assertEquals(0, $stats['bytes_received']);
    }

    /**
     * 测试错误处理
     */
    public function testErrorHandling(): void
    {
        // 测试绑定到已占用端口
        $this->transport->bind('127.0.0.1', 0);
        $addr = $this->transport->getLocalAddress();
        
        $transport2 = new UdpTransport();
        try {
            $this->expectException(TransportException::class);
            $transport2->bind('127.0.0.1', $addr['port']);
        } finally {
            $transport2->close();
        }
    }

    /**
     * 测试连接到无效地址
     */
    public function testConnectToInvalidAddress(): void
    {
        $this->expectException(TransportException::class);
        $this->transport->connect('invalid.host', 12345);
    }
} 
<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Transport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\SRT\Exception\TransportException;
use Tourze\SRT\Transport\UdpTransport;

/**
 * UDP 传输层测试
 *
 * 测试 UDP Socket 的基本操作
 *
 * @internal
 */
#[CoversClass(UdpTransport::class)]
final class UdpTransportTest extends TestCase
{
    private UdpTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

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
        $this->assertNotNull($localAddress);
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
            $this->assertNotNull($receiverAddr);

            // 发送端连接到接收端
            $sender->connect($receiverAddr['host'], $receiverAddr['port']);

            // 发送数据
            $testData = 'Hello SRT!';
            $bytesSent = $sender->send($testData);
            $this->assertEquals(strlen($testData), $bytesSent);

            // 接收数据
            $receivedData = $receiver->receive(1024);
            $this->assertNotNull($receivedData);
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
        $this->assertNotNull($receiverAddr);

        try {
            $testData = 'Direct send test';
            $bytesSent = $this->transport->sendTo(
                $testData,
                $receiverAddr['host'],
                $receiverAddr['port']
            );
            $this->assertEquals(strlen($testData), $bytesSent);

            $receivedData = $receiver->receive(1024);
            $this->assertNotNull($receivedData);
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
        $this->transport->setSocketOption(SO_RCVBUF, 65536);
        $this->assertTrue(true); // 方法没有抛出异常就是成功

        // 设置发送缓冲区大小
        $this->transport->setSocketOption(SO_SNDBUF, 65536);
        $this->assertTrue(true); // 方法没有抛出异常就是成功
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
     * 测试连接到无效地址
     */
    public function testConnectToInvalidAddress(): void
    {
        $this->expectException(TransportException::class);
        $this->transport->connect('invalid.host', 12345);
    }

    /**
     * 测试绑定操作
     */
    public function testBind(): void
    {
        // 测试成功绑定
        $result = $this->transport->bind('127.0.0.1', 0);
        $this->assertTrue($result);
        $this->assertTrue($this->transport->isConnected());

        // 验证本地地址已设置
        $localAddress = $this->transport->getLocalAddress();
        $this->assertNotNull($localAddress);
        $this->assertEquals('127.0.0.1', $localAddress['host']);
        $this->assertGreaterThan(0, $localAddress['port']);

        // 测试绑定失败（端口已占用）
        $transport2 = new UdpTransport();
        try {
            $this->expectException(TransportException::class);
            // 抑制警告以避免测试中的 PHP Warning
            @$transport2->bind($localAddress['host'], $localAddress['port']);
        } finally {
            $transport2->close();
        }
    }

    /**
     * 测试关闭连接
     */
    public function testClose(): void
    {
        // 绑定一个地址
        $this->transport->bind('127.0.0.1', 0);
        $this->assertTrue($this->transport->isConnected());
        $this->assertNotNull($this->transport->getLocalAddress());

        // 关闭连接
        $this->transport->close();

        // 验证连接状态和地址信息被清空
        $this->assertFalse($this->transport->isConnected());
        $this->assertNull($this->transport->getLocalAddress());
        $this->assertNull($this->transport->getRemoteAddress());

        // 验证关闭后的操作会失败
        $this->expectException(TransportException::class);
        $this->transport->setNonBlocking(true);
    }

    /**
     * 测试接收数据
     */
    public function testReceive(): void
    {
        $sender = new UdpTransport();
        $receiver = new UdpTransport();

        try {
            // 设置接收端
            $receiver->bind('127.0.0.1', 0);
            $receiverAddr = $receiver->getLocalAddress();
            $this->assertNotNull($receiverAddr);

            // 发送数据
            $testData = 'Test message for receive';
            $sender->sendTo($testData, $receiverAddr['host'], $receiverAddr['port']);

            // 接收数据
            $result = $receiver->receive(1024);

            $this->assertNotNull($result);
            $this->assertArrayHasKey('data', $result);
            $this->assertArrayHasKey('from', $result);
            $this->assertEquals($testData, $result['data']);
            $this->assertArrayHasKey('host', $result['from']);
            $this->assertArrayHasKey('port', $result['from']);

            // 测试接收长度限制
            $sender->sendTo('Long message', $receiverAddr['host'], $receiverAddr['port']);
            $shortResult = $receiver->receive(4); // 只接收4字节

            $this->assertNotNull($shortResult);
            $this->assertEquals('Long', $shortResult['data']);

            // 测试没有数据时的情况（非阻塞模式）
            $receiver->setNonBlocking(true);
            $noDataResult = $receiver->receive(1024);
            $this->assertNull($noDataResult);
        } finally {
            $sender->close();
            $receiver->close();
        }
    }

    /**
     * 测试重置统计信息
     */
    public function testResetStatistics(): void
    {
        $receiver = new UdpTransport();
        $receiver->bind('127.0.0.1', 0);
        $receiverAddr = $receiver->getLocalAddress();
        $this->assertNotNull($receiverAddr);

        try {
            // 发送一些数据以产生统计
            $testData = 'Statistics test';
            $this->transport->sendTo($testData, $receiverAddr['host'], $receiverAddr['port']);

            // 验证统计信息已更新
            $stats = $this->transport->getStatistics();
            $this->assertGreaterThan(0, $stats['bytes_sent']);
            $this->assertGreaterThan(0, $stats['packets_sent']);

            // 重置统计信息
            $this->transport->resetStatistics();

            // 验证统计信息被重置
            $resetStats = $this->transport->getStatistics();
            $this->assertEquals(0, $resetStats['bytes_sent']);
            $this->assertEquals(0, $resetStats['bytes_received']);
            $this->assertEquals(0, $resetStats['packets_sent']);
            $this->assertEquals(0, $resetStats['packets_received']);
            $this->assertEquals(0, $resetStats['errors']);
        } finally {
            $receiver->close();
        }
    }

    /**
     * 测试发送方法
     */
    public function testSend(): void
    {
        $receiver = new UdpTransport();
        $receiver->bind('127.0.0.1', 0);
        $receiverAddr = $receiver->getLocalAddress();
        $this->assertNotNull($receiverAddr);

        try {
            // 连接到接收端
            $this->transport->connect($receiverAddr['host'], $receiverAddr['port']);

            // 测试发送数据
            $testData = 'Direct send test data';
            $bytesSent = $this->transport->send($testData);

            $this->assertEquals(strlen($testData), $bytesSent);

            // 验证统计信息更新
            $stats = $this->transport->getStatistics();
            $this->assertEquals(strlen($testData), $stats['bytes_sent']);
            $this->assertEquals(1, $stats['packets_sent']);

            // 验证数据被接收
            $received = $receiver->receive(1024);
            $this->assertNotNull($received);
            $this->assertEquals($testData, $received['data']);
        } finally {
            $receiver->close();
        }
    }

    /**
     * 测试发送到指定地址
     */
    public function testSendTo(): void
    {
        $receiver = new UdpTransport();
        $receiver->bind('127.0.0.1', 0);
        $receiverAddr = $receiver->getLocalAddress();
        $this->assertNotNull($receiverAddr);

        try {
            // 测试发送到指定地址
            $testData = 'SendTo test message';
            $bytesSent = $this->transport->sendTo(
                $testData,
                $receiverAddr['host'],
                $receiverAddr['port']
            );

            $this->assertEquals(strlen($testData), $bytesSent);

            // 验证统计信息
            $stats = $this->transport->getStatistics();
            $this->assertEquals(strlen($testData), $stats['bytes_sent']);
            $this->assertEquals(1, $stats['packets_sent']);

            // 验证接收到的数据
            $received = $receiver->receive(1024);
            $this->assertNotNull($received);
            $this->assertEquals($testData, $received['data']);
            $this->assertEquals('127.0.0.1', $received['from']['host']);

            // 测试发送空数据
            $emptyBytesSent = $this->transport->sendTo(
                '',
                $receiverAddr['host'],
                $receiverAddr['port']
            );
            $this->assertEquals(0, $emptyBytesSent);
        } finally {
            $receiver->close();
        }
    }
}

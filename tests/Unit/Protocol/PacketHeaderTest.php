<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Unit\Protocol;

use PHPUnit\Framework\TestCase;
use Tourze\SRT\Exception\InvalidPacketException;
use Tourze\SRT\Protocol\PacketHeader;

/**
 * SRT 包头处理测试
 *
 * 测试 SRT 数据包头部的解析和构建功能
 */
final class PacketHeaderTest extends TestCase
{
    /**
     * 测试解析数据包头部 - TDD 第一个测试
     */
    public function testCanParseDataPacketHeader(): void
    {
        // 构造一个标准的 SRT 数据包头部 (16 bytes)
        // F=0, Seq=1234, PP=11, O=1, KK=01, R=0, Msg=5678, Timestamp=1000, DestID=999
        $headerData = pack('N4', 
            1234,           // 包序列号 (F=0 + 31位序列号)
            // PP=11 (位30-31), O=1 (位29), KK=01 (位27-28), R=0 (位26), Msg=5678 (位0-25)
            (0b11 << 30) | (1 << 29) | (0b01 << 27) | (0 << 26) | 5678,
            1000,           // 时间戳
            999             // 目标Socket ID
        );

        $header = PacketHeader::fromBinary($headerData);

        $this->assertFalse($header->isControlPacket());
        $this->assertEquals(1234, $header->getSequenceNumber());
        $this->assertEquals(0b11, $header->getPacketPosition()); // 单包消息
        $this->assertTrue($header->isOrdered());
        $this->assertEquals(0b01, $header->getEncryptionFlags()); // 奇数密钥
        $this->assertFalse($header->isRetransmitted());
        $this->assertEquals(5678, $header->getMessageNumber());
        $this->assertEquals(1000, $header->getTimestamp());
        $this->assertEquals(999, $header->getDestinationSocketId());
    }

    /**
     * 测试解析控制包头部
     */
    public function testCanParseControlPacketHeader(): void
    {
        // 构造一个 SRT 控制包头部
        // F=1, Type=0x0002 (ACK), Subtype=0, Info=12345, Timestamp=2000, DestID=888
        $headerData = pack('N4',
            0x80000002,     // F=1 + 控制类型 ACK (0x0002)
            12345,          // 类型特定信息
            2000,           // 时间戳
            888             // 目标Socket ID
        );

        $header = PacketHeader::fromBinary($headerData);

        $this->assertTrue($header->isControlPacket());
        $this->assertEquals(0x0002, $header->getControlType());
        $this->assertEquals(0, $header->getSubtype());
        $this->assertEquals(12345, $header->getTypeSpecificInfo());
        $this->assertEquals(2000, $header->getTimestamp());
        $this->assertEquals(888, $header->getDestinationSocketId());
    }

    /**
     * 测试构建数据包头部
     */
    public function testCanBuildDataPacketHeader(): void
    {
        $header = PacketHeader::createDataPacket(
            sequenceNumber: 1234,
            packetPosition: 0b11,    // 单包消息
            isOrdered: true,
            encryptionFlags: 0b01,   // 奇数密钥
            isRetransmitted: false,
            messageNumber: 5678,
            timestamp: 1000,
            destinationSocketId: 999
        );

        $binary = $header->toBinary();
        $this->assertEquals(16, strlen($binary)); // SRT头部固定16字节

        // 重新解析验证
        $parsed = PacketHeader::fromBinary($binary);
        $this->assertEquals($header->getSequenceNumber(), $parsed->getSequenceNumber());
        $this->assertEquals($header->getMessageNumber(), $parsed->getMessageNumber());
        $this->assertEquals($header->getTimestamp(), $parsed->getTimestamp());
        $this->assertEquals($header->getDestinationSocketId(), $parsed->getDestinationSocketId());
    }

    /**
     * 测试构建控制包头部
     */
    public function testCanBuildControlPacketHeader(): void
    {
        $header = PacketHeader::createControlPacket(
            controlType: 0x0002,     // ACK
            subtype: 0,
            typeSpecificInfo: 12345,
            timestamp: 2000,
            destinationSocketId: 888
        );

        $binary = $header->toBinary();
        $this->assertEquals(16, strlen($binary));

        $parsed = PacketHeader::fromBinary($binary);
        $this->assertTrue($parsed->isControlPacket());
        $this->assertEquals(0x0002, $parsed->getControlType());
        $this->assertEquals(12345, $parsed->getTypeSpecificInfo());
    }

    /**
     * 测试包头验证 - 无效长度
     */
    public function testThrowsExceptionForInvalidHeaderLength(): void
    {
        $this->expectException(InvalidPacketException::class);
        $this->expectExceptionMessage('SRT header must be exactly 16 bytes');

        PacketHeader::fromBinary('short');
    }

    /**
     * 测试序列号范围
     */
    public function testSequenceNumberRange(): void
    {
        // 测试最大序列号 (31位)
        $maxSeq = 0x7FFFFFFF;
        $header = PacketHeader::createDataPacket(
            sequenceNumber: $maxSeq,
            packetPosition: 0,
            isOrdered: false,
            encryptionFlags: 0,
            isRetransmitted: false,
            messageNumber: 0,
            timestamp: 0,
            destinationSocketId: 0
        );

        $parsed = PacketHeader::fromBinary($header->toBinary());
        $this->assertEquals($maxSeq, $parsed->getSequenceNumber());
    }

    /**
     * 测试控制包类型常量
     */
    public function testControlPacketTypeConstants(): void
    {
        $this->assertEquals(0x0000, PacketHeader::CONTROL_HANDSHAKE);
        $this->assertEquals(0x0001, PacketHeader::CONTROL_KEEPALIVE);
        $this->assertEquals(0x0002, PacketHeader::CONTROL_ACK);
        $this->assertEquals(0x0003, PacketHeader::CONTROL_NAK);
        $this->assertEquals(0x0005, PacketHeader::CONTROL_SHUTDOWN);
        $this->assertEquals(0x0006, PacketHeader::CONTROL_ACKACK);
    }
} 
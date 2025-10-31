<?php

declare(strict_types=1);

namespace Tourze\SRT\Tests\Protocol;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\SRT\Exception\InvalidPacketException;
use Tourze\SRT\Protocol\ControlPacket;

/**
 * 控制包测试
 *
 * @internal
 */
#[CoversClass(ControlPacket::class)]
final class ControlPacketTest extends TestCase
{
    private ControlPacket $packet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->packet = new ControlPacket();
    }

    public function testInitialState(): void
    {
        $this->assertEquals(0, $this->packet->getControlType());
        $this->assertEquals(0, $this->packet->getSubType());
        $this->assertEquals(0, $this->packet->getAdditionalInfo());
        $this->assertEquals(0, $this->packet->getDestinationSocketId());
        $this->assertEquals('', $this->packet->getControlInformation());
    }

    public function testSetAndGetControlType(): void
    {
        // 测试通过构造函数设置控制类型
        $packet = new ControlPacket(ControlPacket::TYPE_ACK);
        $this->assertEquals(ControlPacket::TYPE_ACK, $packet->getControlType());

        // setControlType 方法现在不起作用，因为 controlType 是 readonly 的
        $this->packet->setControlType(ControlPacket::TYPE_ACK);
        $this->assertEquals(0, $this->packet->getControlType()); // 应该保持原值
    }

    public function testSetAndGetSubType(): void
    {
        $this->packet->setSubType(123);
        $this->assertEquals(123, $this->packet->getSubType());
    }

    public function testSetAndGetAdditionalInfo(): void
    {
        $this->packet->setAdditionalInfo(456);
        $this->assertEquals(456, $this->packet->getAdditionalInfo());
    }

    public function testSetAndGetDestinationSocketId(): void
    {
        $this->packet->setDestinationSocketId(789);
        $this->assertEquals(789, $this->packet->getDestinationSocketId());
    }

    public function testGetControlInformation(): void
    {
        // controlInformation 现在是 readonly 属性，只能在构造函数中设置
        $info = 'test control information';
        $packet = new ControlPacket(ControlPacket::TYPE_ACK, $info);
        $this->assertEquals($info, $packet->getControlInformation());
    }

    public function testCreateAck(): void
    {
        $packet = ControlPacket::createAck(12345, 67890);

        $this->assertEquals(ControlPacket::TYPE_ACK, $packet->getControlType());
        $this->assertEquals(12345, $packet->getAdditionalInfo());
        $this->assertEquals(67890, $packet->getDestinationSocketId());
    }

    public function testCreateNak(): void
    {
        $lostSequences = [100, 101, 102];
        $packet = ControlPacket::createNak($lostSequences, 12345);

        $this->assertEquals(ControlPacket::TYPE_NAK, $packet->getControlType());
        $this->assertEquals(12345, $packet->getDestinationSocketId());

        $retrievedSequences = $packet->getNakLostSequences();
        $this->assertEquals($lostSequences, $retrievedSequences);
    }

    public function testCreateKeepAlive(): void
    {
        $packet = ControlPacket::createKeepAlive(12345);

        $this->assertEquals(ControlPacket::TYPE_KEEPALIVE, $packet->getControlType());
        $this->assertEquals(12345, $packet->getDestinationSocketId());
    }

    public function testCreateCongestionWarning(): void
    {
        $packet = ControlPacket::createCongestionWarning(12345);

        $this->assertEquals(ControlPacket::TYPE_CONGESTION_WARNING, $packet->getControlType());
        $this->assertEquals(12345, $packet->getDestinationSocketId());
    }

    public function testCreateShutdown(): void
    {
        $packet = ControlPacket::createShutdown(12345);

        $this->assertEquals(ControlPacket::TYPE_SHUTDOWN, $packet->getControlType());
        $this->assertEquals(12345, $packet->getDestinationSocketId());
    }

    public function testCreateAckAck(): void
    {
        $packet = ControlPacket::createAckAck(54321, 12345);

        $this->assertEquals(ControlPacket::TYPE_ACKACK, $packet->getControlType());
        $this->assertEquals(54321, $packet->getAdditionalInfo());
        $this->assertEquals(12345, $packet->getDestinationSocketId());
    }

    public function testGetAckSequenceNumber(): void
    {
        $packet = ControlPacket::createAck(12345, 67890);
        $this->assertEquals(12345, $packet->getAckSequenceNumber());

        // 非ACK包应该返回0
        $keepalive = ControlPacket::createKeepAlive(123);
        $this->assertEquals(0, $keepalive->getAckSequenceNumber());
    }

    public function testSerializeAndDeserialize(): void
    {
        $originalPacket = ControlPacket::createAck(12345, 67890);
        $originalPacket->setTimestamp(1234567890);

        $serialized = $originalPacket->serialize();
        $deserializedPacket = ControlPacket::deserialize($serialized);

        $this->assertEquals($originalPacket->getControlType(), $deserializedPacket->getControlType());
        $this->assertEquals($originalPacket->getAdditionalInfo(), $deserializedPacket->getAdditionalInfo());
        $this->assertEquals($originalPacket->getTimestamp(), $deserializedPacket->getTimestamp());
        $this->assertEquals($originalPacket->getDestinationSocketId(), $deserializedPacket->getDestinationSocketId());
    }

    public function testDeserializeTooShort(): void
    {
        $this->expectException(InvalidPacketException::class);

        ControlPacket::deserialize('short');
    }

    public function testDeserializeNotControlPacket(): void
    {
        $this->expectException(InvalidPacketException::class);

        // 创建一个数据包的二进制数据（F=0）
        $dataPacketData = pack('NNNN', 0x12345678, 0x87654321, 0x11111111, 0x22222222);
        ControlPacket::deserialize($dataPacketData);
    }

    public function testGetTypeName(): void
    {
        $testCases = [
            ControlPacket::TYPE_HANDSHAKE => 'HANDSHAKE',
            ControlPacket::TYPE_KEEPALIVE => 'KEEPALIVE',
            ControlPacket::TYPE_ACK => 'ACK',
            ControlPacket::TYPE_NAK => 'NAK',
            ControlPacket::TYPE_CONGESTION_WARNING => 'CONGESTION_WARNING',
            ControlPacket::TYPE_SHUTDOWN => 'SHUTDOWN',
            ControlPacket::TYPE_ACKACK => 'ACKACK',
            ControlPacket::TYPE_DROP_REQUEST => 'DROP_REQUEST',
            ControlPacket::TYPE_PEER_ERROR => 'PEER_ERROR',
            9999 => 'UNKNOWN',
        ];

        foreach ($testCases as $type => $expectedName) {
            $packet = new ControlPacket($type);
            $this->assertEquals($expectedName, $packet->getTypeName());
        }
    }

    public function testGetTotalSize(): void
    {
        $packet = new ControlPacket(ControlPacket::TYPE_ACK, 'test data');

        // 16字节头部 + 9字节数据
        $this->assertEquals(25, $packet->getTotalSize());
    }
}

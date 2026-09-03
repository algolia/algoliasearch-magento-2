<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Service\Serializer;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\TestCase;

class SerializerTest extends TestCase
{
    protected function createObjectToTest(?SerializerInterface $serializerMock = null): Serializer
    {
        return new Serializer($serializerMock ?? $this->createStub(SerializerInterface::class));
    }

    public function testSerializeReturnsString(): void
    {
        $unserialized = ['foo' => 'bar'];

        $serializerMock = $this->createMock(SerializerInterface::class);
        $serializerMock->expects($this->once())
            ->method('serialize')
            ->with($unserialized)
            ->willReturn('{"foo":"bar"}');

        $serializer = $this->createObjectToTest($serializerMock);

        $result = $serializer->serialize($unserialized);
        $this->assertEquals('{"foo":"bar"}', $result);
    }

    public function testSerializeFailure(): void
    {
        $unserialized = [];

        $serializerMock = $this->createMock(SerializerInterface::class);
        $serializerMock->expects($this->once())
            ->method('serialize')
            ->with($unserialized)
            ->willReturn(false);

        $serializer = $this->createObjectToTest($serializerMock);

        $result = $serializer->serialize($unserialized);
        $this->assertEquals('', $result);
    }

    public function testUnserializeReturnsFalseOnEmptyValues(): void
    {
        $serializer = $this->createObjectToTest();

        $this->assertFalse($serializer->unserialize(null));
        $this->assertFalse($serializer->unserialize(false));
        $this->assertFalse($serializer->unserialize(''));
    }

    public function testUnserializeHandlesValidJson(): void
    {
        $json = '{"key":"value"}';

        $serializer = $this->createObjectToTest();

        $this->assertEquals(['key' => 'value'], $serializer->unserialize($json));
    }

    public function testUnserializeFallsBackToSerializer(): void
    {
        $serialized = 'a:1:{s:3:"foo";s:3:"bar";}'; // PHP serialized data

        $serializerMock = $this->createMock(SerializerInterface::class);
        $serializerMock->expects($this->once())
            ->method('unserialize')
            ->with($serialized)
            ->willReturn(['foo' => 'bar']);

        $serializer = $this->createObjectToTest($serializerMock);

        $this->assertEquals(['foo' => 'bar'], $serializer->unserialize($serialized));
    }

    public function testUnserializeFailsBothJsonAndSerializer(): void
    {
        $badData = 'not_serialized_at_all';

        $serializerMock = $this->createMock(SerializerInterface::class);
        $serializerMock->expects($this->once())
            ->method('unserialize')
            ->with($badData)
            ->willThrowException(new \InvalidArgumentException());

        $serializer = $this->createObjectToTest($serializerMock);

        $this->expectException(\InvalidArgumentException::class);

        $serializer->unserialize($badData);
    }
}

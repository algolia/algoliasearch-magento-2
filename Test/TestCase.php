<?php

namespace Algolia\AlgoliaSearch\Test;

/**
 * Base test case for all tests in the project
 * Includes helper methods for invoking protected/private methods and properties
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * Call protected/private method of a class.
     *
     * @param object $object instantiated object that we will run method on
     * @param string $methodName Method name to call
     * @param array $parameters array of parameters to pass into method
     *
     * @throws \ReflectionException
     *
     * @return mixed method return
     */
    protected function invokeMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new \ReflectionClass($object::class);

        return $reflection->getMethod($methodName)->invokeArgs($object, $parameters);
    }

    /**
     * Set a private property of a class
     *
     * @param object $obj The object to set the property for
     * @param string $prop The name of the property to set
     * @param mixed $value The value to set the property to
     *
     * @throws \ReflectionException
     */
    protected function setPrivateProperty(object $obj, string $prop, mixed $value): void
    {
        $ref = new \ReflectionClass($obj);
        while ($ref !== false) {
            try {
                $ref->getProperty($prop)->setValue($obj, $value);
                return;
            } catch (\ReflectionException) {
                $ref = $ref->getParentClass();
            }
        }
        throw new \ReflectionException("Property {$prop} does not exist in class hierarchy of " . get_class($obj));
    }

    /**
     * Get a private property of a class
     *
     * @param object $obj The object to get the property from
     * @param string $prop The name of the property to get the value for
     * @return mixed The value of the property
     *
     * @throws \ReflectionException
     */
    protected function getPrivateProperty(object $obj, string $prop): mixed
    {
        $ref = new \ReflectionClass($obj);
        while ($ref !== false) {
            try {
                return $ref->getProperty($prop)->getValue($obj);
            } catch (\ReflectionException) {
                $ref = $ref->getParentClass();
            }
        }
        throw new \ReflectionException("Property {$prop} does not exist in class hierarchy of " . get_class($obj));
    }

    /**
     * Mock a private property of a class
     *
     * @param object $object The object to mock the property for
     * @param string $propertyName The name of the property to mock
     * @param string $propertyClass The class of the property to mock
     *
     * @throws \ReflectionException
     */
    protected function mockProperty(object $object, string $propertyName, string $propertyClass): void
    {
        $mock = $this->createMock($propertyClass);
        $this->setPrivateProperty($object, $propertyName, $mock);
    }
}

<?php

namespace Doctum\Tests;

use Doctum\Project;
use Doctum\Store\ArrayStore;
use PHPUnit\Framework\TestCase;

/**
 * @author William Desportes <williamdes@wdes.fr>
 */
abstract class AbstractTestCase extends TestCase
{
    protected function getProject(): Project
    {
        $store = new ArrayStore();
        return new Project($store);
    }

    protected function getTestConfigFilePath(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'doctum.php';
    }

    /**
     * Call a non accessible method
     *
     * @param object $obj
     * @param string $name
     * @param mixed[] $args
     * @return mixed
     */
    public static function callMethod($obj, string $name, array $args)
    {
        $class = new \ReflectionClass($obj);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method->invokeArgs($obj, $args);
    }
}
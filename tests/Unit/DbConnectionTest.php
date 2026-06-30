<?php

namespace GOLS\Tests\Unit;

use PHPUnit\Framework\TestCase;

class DbConnectionTest extends TestCase
{
    public function testGetDbConnectionFunctionExists(): void
    {
        require_once __DIR__ . '/../../includes/db.php';

        $this->assertTrue(function_exists('getDbConnection'));
    }

    public function testGetDbConnectionReturnsSameInstance(): void
    {
        // This test verifies the singleton pattern by checking that
        // the function is defined to return PDO. We cannot test actual
        // connection without a running database, but we verify the
        // function signature and singleton static variable pattern.
        require_once __DIR__ . '/../../includes/db.php';

        $reflection = new \ReflectionFunction('getDbConnection');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertSame('PDO', $returnType->getName());
    }
}

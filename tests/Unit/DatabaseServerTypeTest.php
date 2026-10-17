<?php

namespace Tests\Unit;

use App\Support\Maintenance\DatabaseProbe;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DatabaseServerTypeTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string}>
     */
    public static function versions(): array
    {
        return [
            'MariaDB on cPanel' => ['mysql', '10.6.18-MariaDB-cll-lve', 'MariaDB'],
            'MariaDB via mariadb driver' => ['mariadb', '11.4.2-MariaDB', 'MariaDB'],
            'MySQL 8' => ['mysql', '8.0.39', 'MySQL'],
            'MySQL 5.7 on CloudLinux' => ['mysql', '5.7.44-cll-lve', 'MySQL'],
            'SQLite' => ['sqlite', '3.45.1', 'SQLite'],
        ];
    }

    #[DataProvider('versions')]
    public function test_server_type_is_detected_from_the_version_string(string $driver, string $version, string $expected): void
    {
        $this->assertSame($expected, DatabaseProbe::serverType($driver, $version));
    }
}

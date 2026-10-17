<?php

namespace Tests\Unit;

use App\Support\SharedPath;
use PHPUnit\Framework\TestCase;

class SharedPathTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/sdm-shared-'.bin2hex(random_bytes(4));
        mkdir($this->root.'/app', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->root));
        parent::tearDown();
    }

    public function test_no_sibling_shared_folder_keeps_laravel_defaults(): void
    {
        $this->assertNull(SharedPath::resolve($this->root.'/app'));
    }

    public function test_empty_shared_folder_is_ignored(): void
    {
        mkdir($this->root.'/shared');

        $this->assertNull(SharedPath::resolve($this->root.'/app'));
    }

    public function test_shared_folder_with_env_file_is_used(): void
    {
        mkdir($this->root.'/shared');
        touch($this->root.'/shared/.env');

        $this->assertSame($this->root.'/shared', SharedPath::resolve($this->root.'/app'));
    }

    public function test_shared_folder_with_storage_is_used(): void
    {
        mkdir($this->root.'/shared/storage', 0777, true);

        $this->assertSame($this->root.'/shared', SharedPath::resolve($this->root.'/app'));
    }

    public function test_override_wins_when_it_exists(): void
    {
        mkdir($this->root.'/elsewhere');

        $this->assertSame($this->root.'/elsewhere', SharedPath::resolve($this->root.'/app', $this->root.'/elsewhere/'));
        $this->assertNull(SharedPath::resolve($this->root.'/app', $this->root.'/missing'));
    }
}

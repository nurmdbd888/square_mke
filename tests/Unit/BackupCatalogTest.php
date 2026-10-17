<?php

namespace Tests\Unit;

use App\Domain\Backup\BackupCatalog;
use Tests\TestCase;

class BackupCatalogTest extends TestCase
{
    public function test_catalog_lists_and_validates_names(): void
    {
        $catalog = new BackupCatalog;
        $dir = $catalog->directory();
        $this->assertDirectoryExists($dir);

        $name = BackupCatalog::PREFIX.'test.zip';
        file_put_contents($dir.'/'.$name, 'test');
        $this->assertContains($name, $catalog->listLocal());
        $this->assertSame($dir.'/'.$name, $catalog->pathFor($name));
        unlink($dir.'/'.$name);
    }
}

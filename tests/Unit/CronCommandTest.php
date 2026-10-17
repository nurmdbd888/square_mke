<?php

namespace Tests\Unit;

use App\Support\Maintenance\CronCommand;
use PHPUnit\Framework\TestCase;

class CronCommandTest extends TestCase
{
    public function test_litespeed_binary_maps_to_the_cli_in_the_same_folder(): void
    {
        $cron = new CronCommand('/opt/alt/php84/usr/bin/lsphp', '8.4.12');

        $this->assertSame('/opt/alt/php84/usr/bin/php', $cron->candidates()[0]);
    }

    public function test_php_fpm_binary_maps_to_the_bin_folder(): void
    {
        $cron = new CronCommand('/opt/cpanel/ea-php84/root/usr/sbin/php-fpm', '8.4.12');

        $this->assertSame('/opt/cpanel/ea-php84/root/usr/bin/php', $cron->candidates()[0]);
    }

    public function test_candidates_include_cpanel_and_cloudlinux_paths_for_the_running_version(): void
    {
        $candidates = (new CronCommand('', '8.4.12'))->candidates();

        $this->assertContains('/opt/cpanel/ea-php84/root/usr/bin/php', $candidates);
        $this->assertContains('/opt/alt/php84/usr/bin/php', $candidates);
    }

    public function test_first_existing_executable_candidate_is_used(): void
    {
        $dir = sys_get_temp_dir().'/sdm-cron-'.bin2hex(random_bytes(4));
        mkdir($dir);
        touch($dir.'/php');
        chmod($dir.'/php', 0755);

        $cron = new CronCommand(candidates: [$dir.'/missing', $dir.'/php']);

        $this->assertSame($dir.'/php', $cron->phpPath());
        $this->assertSame($dir.'/php /home/u/mke/app/artisan schedule:run >> /dev/null 2>&1', $cron->command('/home/u/mke/app/artisan'));
        $this->assertSame('* * * * * '.$dir.'/php /home/u/mke/app/artisan schedule:run >> /dev/null 2>&1', $cron->cronLine('/home/u/mke/app/artisan'));

        unlink($dir.'/php');
        rmdir($dir);
    }

    public function test_falls_back_to_usr_local_bin_php(): void
    {
        $this->assertSame('/usr/local/bin/php', (new CronCommand(candidates: ['/nope/php']))->phpPath());
    }
}

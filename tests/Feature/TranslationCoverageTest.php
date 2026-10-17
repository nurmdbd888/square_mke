<?php

namespace Tests\Feature;

use App\Settings\SettingsRegistry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * "English/Bangla throughout": every UI string has a Bangla translation.
 */
class TranslationCoverageTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function stringsUsedInCode(): array
    {
        $keys = [];

        foreach (['app', 'resources/views'] as $dir) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir), RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if (! str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }

                preg_match_all("/__\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", (string) file_get_contents($file->getPathname()), $matches);

                foreach ($matches[1] as $key) {
                    $keys[] = str_replace("\\'", "'", $key);
                }
            }
        }

        foreach (app(SettingsRegistry::class)->all() as $definition) {
            $keys[] = $definition->label;

            if ($definition->help !== null) {
                $keys[] = $definition->help;
            }
        }

        // Keys such as "roles.owner" or "audit.events.*" live in lang/{en,bn}/*.php.
        return array_values(array_unique(array_filter($keys, fn ($k) => ! preg_match('/^[a-z_]+\.[a-z0-9_.*]*$/', $k))));
    }

    public function test_every_ui_string_has_a_bangla_translation(): void
    {
        $bn = json_decode((string) file_get_contents(lang_path('bn.json')), true, flags: JSON_THROW_ON_ERROR);
        $missing = array_values(array_filter($this->stringsUsedInCode(), fn ($k) => ! array_key_exists($k, $bn)));

        $this->assertSame([], $missing, 'Add these to lang/bn.json');
    }

    public function test_bangla_translations_keep_their_placeholders(): void
    {
        $bn = json_decode((string) file_get_contents(lang_path('bn.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ($bn as $en => $translation) {
            preg_match_all('/:[a-z_]+/', $en, $expected);
            preg_match_all('/:[a-z_]+/', $translation, $actual);
            sort($expected[0]);
            sort($actual[0]);
            $this->assertSame($expected[0], $actual[0], "Placeholders differ in: {$en}");
        }
    }

    public function test_the_product_name_is_never_translated(): void
    {
        $bn = json_decode((string) file_get_contents(lang_path('bn.json')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('Square Distributor Manager', $bn['Square Distributor Manager']);

        foreach ($bn as $en => $translation) {
            if (str_contains($en, 'Square Distributor Manager')) {
                $this->assertStringContainsString('Square Distributor Manager', $translation, $en);
            }
        }
    }

    public function test_bangla_lang_files_have_the_same_keys_as_english(): void
    {
        foreach (glob(lang_path('en/*.php')) as $file) {
            $name = basename($file);
            $this->assertFileExists(lang_path("bn/{$name}"));

            $en = $this->flatten(require $file);
            $bnKeys = $this->flatten(require lang_path("bn/{$name}"));

            if ($name === 'validation.php') {
                $this->assertSame([], array_values(array_diff($en, $bnKeys)), "lang/bn/{$name} is missing keys");

                continue;
            }

            sort($en);
            sort($bnKeys);
            $this->assertSame($en, $bnKeys, "lang/bn/{$name} keys differ from English");
        }
    }

    /**
     * @param  array<string, mixed>  $array
     * @return list<string>
     */
    private function flatten(array $array, string $prefix = ''): array
    {
        $keys = [];

        foreach ($array as $key => $value) {
            $keys = is_array($value) && $value !== [] && ! array_is_list($value)
                ? [...$keys, ...$this->flatten($value, $prefix.$key.'.')]
                : [...$keys, $prefix.$key];
        }

        return $keys;
    }
}

<?php

namespace Tests\Feature\Content;

use HTMLPurifier_Config;
use HTMLPurifier_ConfigSchema;
use Tests\TestCase;

class PurifierConfigTest extends TestCase
{
    public function test_global_settings_follow_the_blueprint(): void
    {
        $this->assertSame('UTF-8', config('purifier.encoding'));
        $this->assertTrue(config('purifier.finalize'));
        $this->assertFalse(config('purifier.ignoreNonStrings'));
        $this->assertSame(storage_path('app/purifier'), config('purifier.cachePath'));
        $this->assertSame(0755, config('purifier.cacheFileMode'));
    }

    public function test_only_the_default_and_rich_text_profiles_exist(): void
    {
        $this->assertSame(['default', 'rich_text'], array_keys(config('purifier.settings')));
    }

    public function test_default_profile_allows_only_basic_inline_markup(): void
    {
        $this->assertSame([
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'p,br,strong,em',
            'AutoFormat.RemoveEmpty' => true,
        ], config('purifier.settings.default'));
    }

    public function test_rich_text_profile_matches_blueprint_section_7_2(): void
    {
        $profile = config('purifier.settings.rich_text');
        $host = $profile['URI.Host'];
        unset($profile['URI.Host']);

        $this->assertSame([
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'p,br,h2,h3,h4,strong,em,u,ul,ol,li,a[href|title|target|rel],img[src|alt|width|height],blockquote,table,thead,tbody,tr,th,td',
            'Attr.AllowedFrameTargets' => ['_blank'],
            'Attr.AllowedRel' => ['noopener', 'noreferrer'],
            'HTML.TargetNoopener' => true,
            'HTML.TargetNoreferrer' => true,
            'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true],
            'URI.DisableExternalResources' => true,
            'CSS.AllowedProperties' => [],
            'Attr.EnableID' => false,
            'AutoFormat.RemoveEmpty' => true,
            'AutoFormat.AutoParagraph' => false,
            'Core.RemoveInvalidImg' => true,
            'HTML.MaxImgLength' => 1600,
        ], $profile);

        $this->assertTrue($host === null || (is_string($host) && $host !== ''));
    }

    public function test_every_rich_text_key_is_a_valid_htmlpurifier_directive(): void
    {
        $directives = HTMLPurifier_ConfigSchema::instance()->info;

        foreach (array_keys(config('purifier.settings.rich_text')) as $key) {
            $this->assertArrayHasKey($key, $directives, "Kunci HTMLPurifier tidak valid: {$key}");
        }
    }

    public function test_rich_text_profile_loads_into_htmlpurifier_without_warnings(): void
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->loadArray(config('purifier.settings.rich_text'));

        $this->assertSame('HTML 4.01 Transitional', $config->get('HTML.Doctype'));
        $this->assertSame(1600, $config->get('HTML.MaxImgLength'));
        $this->assertTrue($config->get('URI.DisableExternalResources'));
    }

    public function test_cache_directory_keeps_only_a_gitignore(): void
    {
        $path = base_path('storage/app/purifier/.gitignore');

        $this->assertFileExists($path);
        $this->assertSame("*\n!.gitignore\n", str_replace("\r\n", "\n", (string) file_get_contents($path)));
    }

    public function test_storage_app_gitignore_tracks_the_purifier_folder(): void
    {
        $lines = array_map('trim', file(base_path('storage/app/.gitignore')));

        $this->assertContains('!purifier/', $lines);
    }
}

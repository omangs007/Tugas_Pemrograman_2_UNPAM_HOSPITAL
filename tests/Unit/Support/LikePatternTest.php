<?php

namespace Tests\Unit\Support;

use App\Support\LikePattern;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * F4B-R26: satu pembantu untuk pola LIKE "mengandung". Karakter `\`, `%`, dan `_` diperlakukan sebagai teks biasa.
 */
class LikePatternTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string}> */
    public static function terms(): array
    {
        return [
            'kata biasa' => ['anak', '%anak%'],
            'persen di-escape' => ['50%', '%50\\%%'],
            'garis bawah di-escape' => ['a_b', '%a\\_b%'],
            'garis miring terbalik digandakan' => ['a\\b', '%a\\\\b%'],
            'gabungan ketiganya' => ['100%_\\', '%100\\%\\_\\\\%'],
            'hanya persen' => ['%', '%\\%%'],
            'hanya garis bawah' => ['_', '%\\_%'],
            'kosong' => ['', '%%'],
            'spasi tepi dipertahankan' => [' anak ', '% anak %'],
            'multibyte' => ['Jantung Çinta 心', '%Jantung Çinta 心%'],
            'tanda kutip tidak diubah' => ["O'Brien \"x\"", "%O'Brien \"x\"%"],
        ];
    }

    #[DataProvider('terms')]
    public function test_contains_wraps_and_escapes_the_term(string $term, string $expected): void
    {
        $this->assertSame($expected, LikePattern::contains($term));
    }

    public function test_the_class_is_final_and_has_a_single_public_static_method(): void
    {
        $class = new \ReflectionClass(LikePattern::class);

        $this->assertTrue($class->isFinal());
        $this->assertSame(['contains'], array_map(fn (\ReflectionMethod $m): string => $m->getName(), $class->getMethods(\ReflectionMethod::IS_PUBLIC)));
        $this->assertTrue($class->getMethod('contains')->isStatic());
    }

    public function test_no_other_copy_of_the_like_escape_remains_in_the_application_code(): void
    {
        $offenders = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('app'), FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || $file->getFilename() === 'LikePattern.php') {
                continue;
            }

            if (preg_match('/\'%\'\s*\.\s*(?:str_replace|addcslashes)\s*\(/', (string) file_get_contents($file->getPathname())) === 1) {
                $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders, 'Pakai App\\Support\\LikePattern::contains(), jangan menyalin escape LIKE.');
    }
}

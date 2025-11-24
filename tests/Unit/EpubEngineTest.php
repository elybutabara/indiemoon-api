<?php

namespace Tests\Unit;

use App\Domain\Export\EpubEngine;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class EpubEngineTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = storage_path('framework/testing/epub_tmp');
        File::deleteDirectory($this->tmpDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tmpDir);

        parent::tearDown();
    }

    public function test_it_generates_epub_and_stores_on_disk(): void
    {
        config([
            'export.epub.disk' => 's3',
            'export.epub.path_prefix' => 'exports/epub',
            'export.epub.tmp_dir' => $this->tmpDir,
            'export.epub.epubcheck_binary' => 'echo',
            'export.epub.epubcheck_args' => ['epubcheck'],
        ]);

        Storage::fake('s3');

        $engine = new class extends EpubEngine {
            public bool $epubcheckCalled = false;

            protected function runEpubcheck(string $epubPath): void
            {
                $this->epubcheckCalled = true;
            }
        };

        $storagePath = $engine->generateFromHtml('<h1>Hello</h1>', 'My Book.epub', [
            'title' => 'My Book',
            'language' => 'en',
            'creator' => 'Test Author',
        ]);

        Storage::disk('s3')->assertExists($storagePath);
        $this->assertStringStartsWith('exports/epub/', $storagePath);
        $this->assertSame('epub', pathinfo($storagePath, PATHINFO_EXTENSION));

        $zipPath = Storage::disk('s3')->path($storagePath);
        $zip = new ZipArchive();

        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertNotFalse($zip->locateName('mimetype'));
        $this->assertSame('application/epub+zip', trim($zip->getFromName('mimetype')));

        foreach ([
            'META-INF/container.xml',
            'OEBPS/content.opf',
            'OEBPS/nav.xhtml',
            'OEBPS/index.xhtml',
            'OEBPS/styles.css',
        ] as $expectedEntry) {
            $this->assertNotFalse($zip->locateName($expectedEntry), sprintf('Missing %s in archive', $expectedEntry));
        }

        $zip->close();

        $this->assertTrue($engine->epubcheckCalled);
        $this->assertTrue(File::exists($this->tmpDir));
        $this->assertCount(0, File::allFiles($this->tmpDir));
        $this->assertCount(0, File::directories($this->tmpDir));
    }
}

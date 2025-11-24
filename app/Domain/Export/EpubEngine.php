<?php

namespace App\Domain\Export;

use App\Domain\Export\Exceptions\EpubGenerationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use ZipArchive;

class EpubEngine
{
    private string $disk;
    private string $pathPrefix;
    private string $tmpDir;
    private string $epubcheckBinary;
    private array $epubcheckArgs;

    public function __construct()
    {
        $config = config('export.epub');

        $this->disk            = $config['disk'];
        $this->pathPrefix      = trim($config['path_prefix'], '/');
        $this->tmpDir          = $config['tmp_dir'];
        $this->epubcheckBinary = $config['epubcheck_binary'];
        $this->epubcheckArgs   = $config['epubcheck_args'] ?? ['epubcheck'];

        if (! is_dir($this->tmpDir)) {
            mkdir($this->tmpDir, 0775, true);
        }
    }

    /**
     * Build a minimal valid EPUB3 file from HTML and run epubcheck validation.
     *
     * @param  string  $html     Body HTML content.
     * @param  string  $filename Desired output filename, e.g. "manuscript.epub".
     * @param  array   $metadata Optional metadata: title, language, creator, identifier.
     * @return string            Path on configured disk, e.g. "exports/epub/20250101_uuid.epub".
     */
    public function generateFromHtml(string $html, string $filename, array $metadata = []): string
    {
        $uuid       = (string) Str::uuid();
        $workDir    = $this->tmpDir . DIRECTORY_SEPARATOR . $uuid;
        $epubPath   = $this->tmpDir . DIRECTORY_SEPARATOR . $uuid . '.epub';

        try {
            $this->buildEpubScaffold($workDir, $html, $metadata);
            $this->archiveEpub($workDir, $epubPath);
            $this->runEpubcheck($epubPath);

            return $this->storeFinalEpub($epubPath, $filename);
        } finally {
            $this->cleanup($workDir);
            @unlink($epubPath);
        }
    }

    protected function buildEpubScaffold(string $workDir, string $html, array $metadata): void
    {
        $metaDefaults = [
            'title'      => 'Untitled Manuscript',
            'language'   => 'en',
            'creator'    => 'Unknown Author',
            'identifier' => 'urn:uuid:' . (string) Str::uuid(),
        ];

        $meta = array_merge($metaDefaults, $metadata);

        if (! is_dir($workDir)) {
            mkdir($workDir, 0775, true);
        }

        $metaInf = $workDir . DIRECTORY_SEPARATOR . 'META-INF';
        $oebps   = $workDir . DIRECTORY_SEPARATOR . 'OEBPS';

        if (! is_dir($metaInf)) {
            mkdir($metaInf, 0775, true);
        }

        if (! is_dir($oebps)) {
            mkdir($oebps, 0775, true);
        }

        file_put_contents($workDir . DIRECTORY_SEPARATOR . 'mimetype', 'application/epub+zip');
        file_put_contents($metaInf . DIRECTORY_SEPARATOR . 'container.xml', $this->containerXml());
        file_put_contents($oebps . DIRECTORY_SEPARATOR . 'content.opf', $this->contentOpf($meta));
        file_put_contents($oebps . DIRECTORY_SEPARATOR . 'nav.xhtml', $this->navDocument($meta['title'], $meta['language']));
        file_put_contents(
            $oebps . DIRECTORY_SEPARATOR . 'index.xhtml',
            $this->contentDocument($meta['title'], $meta['language'], $html)
        );
        file_put_contents($oebps . DIRECTORY_SEPARATOR . 'styles.css', $this->baseStyles());
    }

    protected function archiveEpub(string $workDir, string $epubPath): void
    {
        $zip = new ZipArchive();

        $epubDir = dirname($epubPath);

        if (! is_dir($epubDir)) {
            mkdir($epubDir, 0775, true);
        }

        if ($zip->open($epubPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new EpubGenerationException('Unable to create EPUB archive.');
        }

        $mimetypePath = $workDir . DIRECTORY_SEPARATOR . 'mimetype';
        $zip->addFile($mimetypePath, 'mimetype');
        $zip->setCompressionName('mimetype', ZipArchive::CM_STORE);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($workDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $filePath = $file->getRealPath();
            $localPath = ltrim(str_replace($workDir, '', $filePath), DIRECTORY_SEPARATOR);

            if ($localPath === 'mimetype') {
                continue;
            }

            if ($file->isDir()) {
                $zip->addEmptyDir($localPath);

                continue;
            }

            if ($zip->addFile($filePath, $localPath) !== true) {
                throw new EpubGenerationException(sprintf('Unable to add %s to EPUB archive.', $localPath));
            }
        }

        if ($zip->close() !== true) {
            throw new EpubGenerationException('Unable to finalize EPUB archive.');
        }
    }

    protected function runEpubcheck(string $epubPath): void
    {
        $args = array_merge([$this->epubcheckBinary], $this->epubcheckArgs, [$epubPath]);

        $process = new Process($args);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new EpubGenerationException('epubcheck failed: ' . trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }

    protected function storeFinalEpub(string $epubPath, string $filename): string
    {
        $safeFilename = Str::slug(pathinfo($filename, PATHINFO_FILENAME)) ?: 'epub';
        $ext          = pathinfo($filename, PATHINFO_EXTENSION) ?: 'epub';
        $finalName    = now()->format('Ymd_His') . '_' . $safeFilename . '.' . $ext;

        $storagePath  = $this->pathPrefix . '/' . $finalName;

        $stream = fopen($epubPath, 'r');

        Storage::disk($this->disk)->put($storagePath, $stream, [
            'visibility' => 'private',
            'ContentType' => 'application/epub+zip',
        ]);

        if (is_resource($stream)) {
            fclose($stream);
        }

        return $storagePath;
    }

    protected function cleanup(string $workDir): void
    {
        if (! is_dir($workDir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($workDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getRealPath());
            } else {
                @unlink($item->getRealPath());
            }
        }

        @rmdir($workDir);
    }

    protected function containerXml(): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
    <rootfiles>
        <rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/>
    </rootfiles>
</container>
XML;
    }

    protected function contentOpf(array $meta): string
    {
        $date = now()->format('Y-m-d\TH:i:s\Z');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf" unique-identifier="pub-id" version="3.0" xml:lang="{$meta['language']}">
  <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:identifier id="pub-id">{$meta['identifier']}</dc:identifier>
    <dc:title>{$meta['title']}</dc:title>
    <dc:language>{$meta['language']}</dc:language>
    <dc:creator>{$meta['creator']}</dc:creator>
    <meta property="dcterms:modified">{$date}</meta>
  </metadata>
  <manifest>
    <item id="nav" href="nav.xhtml" media-type="application/xhtml+xml" properties="nav" />
    <item id="content" href="index.xhtml" media-type="application/xhtml+xml" />
    <item id="css" href="styles.css" media-type="text/css" />
  </manifest>
  <spine>
    <itemref idref="content" />
  </spine>
</package>
XML;
    }

    protected function navDocument(string $title, string $language): string
    {
        return <<<HTML
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops" lang="{$language}">
<head>
  <meta charset="utf-8" />
  <title>Table of contents</title>
</head>
<body>
  <nav epub:type="toc" aria-label="Table of contents">
    <h1>Contents</h1>
    <ol>
      <li><a href="index.xhtml">{$title}</a></li>
    </ol>
  </nav>
</body>
</html>
HTML;
    }

    protected function contentDocument(string $title, string $language, string $bodyHtml): string
    {
        return <<<HTML
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="{$language}">
<head>
  <meta charset="utf-8" />
  <title>{$title}</title>
  <link rel="stylesheet" type="text/css" href="styles.css" />
</head>
<body>
{$bodyHtml}
</body>
</html>
HTML;
    }

    protected function baseStyles(): string
    {
        return <<<CSS
body {
    font-family: serif;
    line-height: 1.5;
    margin: 1.5rem;
}

h1, h2, h3, h4, h5, h6 {
    font-family: sans-serif;
    margin: 1.2rem 0 0.6rem;
}
CSS;
    }
}

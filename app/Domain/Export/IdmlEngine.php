<?php

namespace App\Domain\Export;

use App\Domain\Export\Exceptions\IdmlGenerationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class IdmlEngine
{
    private string $disk;
    private string $pathPrefix;
    private string $tmpDir;

    public function __construct()
    {
        $config = config('export.idml');

        $this->disk       = $config['disk'];
        $this->pathPrefix = trim($config['path_prefix'], '/');
        $this->tmpDir     = $config['tmp_dir'];

        if (! is_dir($this->tmpDir)) {
            mkdir($this->tmpDir, 0775, true);
        }
    }

    /**
     * Build a lightweight Adobe IDML package and upload it to the configured disk.
     *
     * @param  string  $content   Plain text or HTML content that should appear in the document.
     * @param  string  $filename  Desired name for the exported IDML file.
     * @param  array   $metadata  Optional metadata such as title, creator, and page sizing.
     * @return string             Storage path for the generated IDML archive.
     */
    public function generateFromText(string $content, string $filename, array $metadata = []): string
    {
        $uuid      = (string) Str::uuid();
        $workDir   = $this->tmpDir . DIRECTORY_SEPARATOR . $uuid;
        $idmlPath  = $this->tmpDir . DIRECTORY_SEPARATOR . $uuid . '.idml';

        try {
            $this->buildIdmlPackage($workDir, $content, $metadata);
            $this->archiveIdml($workDir, $idmlPath);

            return $this->storeFinalIdml($idmlPath, $filename);
        } finally {
            $this->cleanup($workDir);
            @unlink($idmlPath);
        }
    }

    protected function buildIdmlPackage(string $workDir, string $body, array $metadata): void
    {
        $metaDefaults = [
            'title'       => 'Untitled Layout',
            'creator'     => 'Indiemoon',
            'language'    => 'en',
            'page_width'  => '210mm',
            'page_height' => '297mm',
        ];

        $meta = array_merge($metaDefaults, $metadata);

        $storyId  = 'u1';
        $spreadId = 'u2';
        $frameId  = 'u3';
        $masterId = 'u4';

        $directories = [
            $workDir,
            $workDir . DIRECTORY_SEPARATOR . 'Stories',
            $workDir . DIRECTORY_SEPARATOR . 'Spreads',
            $workDir . DIRECTORY_SEPARATOR . 'MasterSpreads',
            $workDir . DIRECTORY_SEPARATOR . 'Resources',
            $workDir . DIRECTORY_SEPARATOR . 'XML',
        ];

        foreach ($directories as $dir) {
            if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
                throw new IdmlGenerationException(sprintf('Unable to create directory: %s', $dir));
            }
        }

        file_put_contents(
            $workDir . DIRECTORY_SEPARATOR . 'designmap.xml',
            $this->designMapXml($storyId, $spreadId, $masterId)
        );

        file_put_contents(
            $workDir . DIRECTORY_SEPARATOR . 'Stories' . DIRECTORY_SEPARATOR . 'Story_' . $storyId . '.xml',
            $this->storyXml($storyId, $body, $meta)
        );

        file_put_contents(
            $workDir . DIRECTORY_SEPARATOR . 'Spreads' . DIRECTORY_SEPARATOR . 'Spread_' . $spreadId . '.xml',
            $this->spreadXml($spreadId, $storyId, $frameId, $meta)
        );

        file_put_contents(
            $workDir . DIRECTORY_SEPARATOR . 'MasterSpreads' . DIRECTORY_SEPARATOR . 'MasterSpread_' . $masterId . '.xml',
            $this->masterSpreadXml($masterId, $meta)
        );

        file_put_contents(
            $workDir . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'Resources.xml',
            $this->resourcesXml($meta)
        );

        file_put_contents(
            $workDir . DIRECTORY_SEPARATOR . 'XML' . DIRECTORY_SEPARATOR . 'Preferences.xml',
            $this->preferencesXml($meta)
        );
    }

    protected function archiveIdml(string $workDir, string $idmlPath): void
    {
        $zip = new ZipArchive();

        if ($zip->open($idmlPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new IdmlGenerationException('Unable to create IDML archive.');
        }

        $basePath = rtrim(realpath($workDir) ?: $workDir, DIRECTORY_SEPARATOR);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($workDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $filePath = $file->getRealPath();

            if ($filePath === false) {
                throw new IdmlGenerationException('Unable to resolve file path while creating IDML archive.');
            }

            $relativePath = ltrim(str_replace($basePath, '', $filePath), DIRECTORY_SEPARATOR);
            $relativePath = str_replace('\\', '/', $relativePath);

            if ($file->isDir()) {
                if ($zip->addEmptyDir($relativePath) !== true) {
                    throw new IdmlGenerationException(sprintf('Unable to add directory %s to IDML archive.', $relativePath));
                }

                continue;
            }

            if ($zip->addFile($filePath, $relativePath) !== true) {
                throw new IdmlGenerationException(sprintf('Unable to add %s to IDML archive.', $relativePath));
            }
        }

        if ($zip->close() !== true) {
            throw new IdmlGenerationException('Unable to finalize IDML archive.');
        }
    }

    protected function storeFinalIdml(string $idmlPath, string $filename): string
    {
        $safeFilename = Str::slug(pathinfo($filename, PATHINFO_FILENAME)) ?: 'layout';
        $ext          = pathinfo($filename, PATHINFO_EXTENSION) ?: 'idml';
        $finalName    = now()->format('Ymd_His') . '_' . $safeFilename . '.' . $ext;

        $storagePath  = $this->pathPrefix . '/' . $finalName;

        $stream = fopen($idmlPath, 'r');

        Storage::disk($this->disk)->put($storagePath, $stream, [
            'visibility' => 'private',
            'ContentType' => 'application/vnd.adobe.indesign-idml-package',
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

    protected function designMapXml(string $storyId, string $spreadId, string $masterId): string
    {
        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<idPkg:Package xmlns:idPkg="http://ns.adobe.com/AdobeInDesign/idml/1.0/packaging">
  <idPkg:Preferences src="XML/Preferences.xml" />
  <idPkg:MasterSpread src="MasterSpreads/MasterSpread_{$masterId}.xml" />
  <idPkg:Spread src="Spreads/Spread_{$spreadId}.xml" />
  <idPkg:Story src="Stories/Story_{$storyId}.xml" />
  <idPkg:Resource src="Resources/Resources.xml" />
</idPkg:Package>
XML;
    }

    protected function storyXml(string $storyId, string $body, array $meta): string
    {
        $content = htmlspecialchars($body, ENT_XML1 | ENT_COMPAT, 'UTF-8');

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Story xmlns="http://ns.adobe.com/AdobeInDesign/4.0/" Self="{$storyId}">
  <StoryPreferences OpticalMarginAlignment="false" OpticalMarginSize="0" />
  <ParagraphStyleRange AppliedParagraphStyle="ParagraphStyle/$ID/NormalParagraphStyle">
    <CharacterStyleRange AppliedCharacterStyle="CharacterStyle/$ID/[No character style]">
      <Content>{$content}</Content>
    </CharacterStyleRange>
  </ParagraphStyleRange>
</Story>
XML;
    }

    protected function spreadXml(string $spreadId, string $storyId, string $frameId, array $meta): string
    {
        $width  = $this->dimensionInPoints($meta['page_width']);
        $height = $this->dimensionInPoints($meta['page_height']);

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Spread xmlns="http://ns.adobe.com/AdobeInDesign/4.0/" Self="{$spreadId}" PageCount="1">
  <Properties>
    <ItemTransform>1 0 0 1 0 0</ItemTransform>
  </Properties>
  <TextFrame Self="{$frameId}" ParentStory="{$storyId}" ContentType="TextType">
    <Properties>
      <GeometricBounds>0 0 {$height} {$width}</GeometricBounds>
      <TextFramePreferences ColumnCount="1" TextColumnGutter="12" VerticalJustification="TopAlign" />
    </Properties>
  </TextFrame>
</Spread>
XML;
    }

    protected function masterSpreadXml(string $masterId, array $meta): string
    {
        $width  = $this->dimensionInPoints($meta['page_width']);
        $height = $this->dimensionInPoints($meta['page_height']);

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<MasterSpread xmlns="http://ns.adobe.com/AdobeInDesign/4.0/" Self="{$masterId}" Name="A-Master">
  <Properties>
    <MasterSpreadTransform>1 0 0 1 0 0</MasterSpreadTransform>
  </Properties>
  <Page Self="{$masterId}P" Name="1" AppliedMaster="{$masterId}" ItemTransform="1 0 0 1 0 0">
    <Properties>
      <MarginPreference ColumnCount="1" ColumnGutter="12" Top="36" Bottom="36" Left="36" Right="36" />
      <PagePreference PageColor="Paper" PageWidth="{$width}" PageHeight="{$height}" />
    </Properties>
  </Page>
</MasterSpread>
XML;
    }

    protected function resourcesXml(array $meta): string
    {
        $title   = htmlspecialchars($meta['title'], ENT_XML1 | ENT_COMPAT, 'UTF-8');
        $creator = htmlspecialchars($meta['creator'], ENT_XML1 | ENT_COMPAT, 'UTF-8');

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Resource xmlns="http://ns.adobe.com/AdobeInDesign/4.0/">
  <Document>
    <Properties>
      <DocumentPreferences PageBinding="LeftToRight" PreserveLayoutWhenShuffling="true" />
      <XMPPacket>
        <![CDATA[
<x:xmpmeta xmlns:x="adobe:ns:meta/">
  <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
    <rdf:Description xmlns:dc="http://purl.org/dc/elements/1.1/">
      <dc:title>{$title}</dc:title>
      <dc:creator>{$creator}</dc:creator>
    </rdf:Description>
  </rdf:RDF>
</x:xmpmeta>
        ]]>
      </XMPPacket>
    </Properties>
  </Document>
</Resource>
XML;
    }

    protected function preferencesXml(array $meta): string
    {
        $language = htmlspecialchars($meta['language'], ENT_XML1 | ENT_COMPAT, 'UTF-8');

        return <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Preferences xmlns="http://ns.adobe.com/AdobeInDesign/4.0/">
  <StringList key="DocumentLanguage">
    <StringItem>{$language}</StringItem>
  </StringList>
</Preferences>
XML;
    }

    protected function dimensionInPoints(string $value): string
    {
        $numeric = (float) $value;

        if (str_contains($value, 'mm')) {
            return number_format($numeric * 2.83465, 2, '.', '');
        }

        if (str_contains($value, 'cm')) {
            return number_format($numeric * 28.3465, 2, '.', '');
        }

        return number_format($numeric, 2, '.', '');
    }
}

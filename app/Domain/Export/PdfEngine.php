<?php

namespace App\Domain\Export;

use App\Domain\Export\Exceptions\PdfGenerationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class PdfEngine
{
    private string $nodeBinary;
    private string $pagedScript;
    private string $ghostscriptBinary;
    private string $disk;
    private string $pathPrefix;
    private string $tmpDir;

    public function __construct()
    {
        $config = config('export.pdf');

        $this->nodeBinary       = $config['node_binary'];
        $this->pagedScript      = $config['paged_script'];
        $this->ghostscriptBinary= $config['ghostscript_binary'];
        $this->disk             = $config['disk'];
        $this->pathPrefix       = trim($config['path_prefix'], '/');
        $this->tmpDir           = $config['tmp_dir'];

        if (! is_dir($this->tmpDir)) {
            mkdir($this->tmpDir, 0775, true);
        }
    }

    /**
     * Main entry: take HTML string and produce a PDF/X file on the configured disk.
     *
     * @param  string  $html     Full HTML (with Paged.js-ready markup)
     * @param  string  $filename Desired filename (e.g. "Hundedagene_innmat.pdf")
     * @param  array   $options  Extra options (trim size, etc) if needed
     * @return string            Storage path on the disk, e.g. "exports/pdf/uuid_Hundedagene_innmat.pdf"
     *
     * @throws PdfGenerationException
     */
    public function generateFromHtml(string $html, string $filename, array $options = []): string
    {
        // 1) Write HTML to temporary file
        $uuid           = (string) Str::uuid();
        $tmpHtmlPath    = $this->tmpDir . DIRECTORY_SEPARATOR . $uuid . '.html';
        $tmpPdfPath     = $this->tmpDir . DIRECTORY_SEPARATOR . $uuid . '.pdf';
        $tmpPdfxPath    = $this->tmpDir . DIRECTORY_SEPARATOR . $uuid . '_pdfx.pdf';

        file_put_contents($tmpHtmlPath, $html);

        // 2) Run Node + Paged.js to render "screen" PDF
        $this->runPagedJs($tmpHtmlPath, $tmpPdfPath, $options);

        // 3) Convert to PDF/X using Ghostscript
        $this->runGhostscriptPdfx($tmpPdfPath, $tmpPdfxPath);

        if (! file_exists($tmpPdfxPath)) {
            throw new PdfGenerationException('PDF/X file was not created.');
        }

        // 4) Store final PDF/X on disk (S3/Supabase/etc)
        $storagePath = $this->storeFinalPdf($tmpPdfxPath, $filename);

        // 5) Cleanup temp files
        @unlink($tmpHtmlPath);
        @unlink($tmpPdfPath);
        @unlink($tmpPdfxPath);

        return $storagePath;
    }

    /**
     * Call Node script that uses Paged.js to generate base PDF.
     */
    protected function runPagedJs(string $htmlPath, string $outputPdfPath, array $options = []): void
    {
        $args = [
            $this->nodeBinary,
            $this->pagedScript,
            $htmlPath,
            $outputPdfPath,
        ];

        // Optionally pass JSON options to the script
        if (! empty($options)) {
            $args[] = json_encode($options);
        }

        $process = new Process($args);
        $process->setTimeout(300); // 5 minutes for big books

        $process->run();

        if (! $process->isSuccessful()) {
            throw new PdfGenerationException(
                'Paged.js render failed: ' . $process->getErrorOutput()
            );
        }

        if (! file_exists($outputPdfPath)) {
            throw new PdfGenerationException('Paged.js did not produce a PDF.');
        }
    }

    /**
     * Convert PDF to PDF/X via Ghostscript.
     * Adjust switches here for PDF/X-1a or PDF/X-4 as you finalize your profile.
     */
    protected function runGhostscriptPdfx(string $inputPdf, string $outputPdfx): void
    {
        // Basic example; you will tune this for your exact PDF/X settings
        $args = [
            $this->ghostscriptBinary,
            '-dNOPAUSE',
            '-dBATCH',
            '-dSAFER',
            '-sDEVICE=pdfwrite',
            // Example for PDF/X-3 or PDF/X-4 — adjust profile and psd file
            '-dPDFX',
            '-sOutputFile=' . $outputPdfx,
            $inputPdf,
        ];

        $process = new Process($args);
        $process->setTimeout(300);

        $process->run();

        if (! $process->isSuccessful()) {
            throw new PdfGenerationException(
                'Ghostscript PDF/X conversion failed: ' . $process->getErrorOutput()
            );
        }
    }

    /**
     * Store final file on configured disk with a unique path.
     */
    protected function storeFinalPdf(string $localPath, string $filename): string
    {
        $safeFilename = Str::slug(pathinfo($filename, PATHINFO_FILENAME));
        $ext          = pathinfo($filename, PATHINFO_EXTENSION) ?: 'pdf';
        $finalName    = now()->format('Ymd_His') . '_' . $safeFilename . '.' . $ext;

        $storagePath  = $this->pathPrefix . '/' . $finalName;

        $stream = fopen($localPath, 'r');

        Storage::disk($this->disk)->put($storagePath, $stream, [
            'visibility' => 'private',
            'ContentType' => 'application/pdf',
        ]);

        if (is_resource($stream)) {
            fclose($stream);
        }

        return $storagePath; // You can also return Storage::disk(...)->url($storagePath) if you want public URL
    }
}

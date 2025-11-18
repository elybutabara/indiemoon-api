<?php

namespace App\Domain\Barcode;

use InvalidArgumentException;

class BarcodeService
{
    private const LEFT_PARITY = [
        'A' => [
            '0' => '0001101',
            '1' => '0011001',
            '2' => '0010011',
            '3' => '0111101',
            '4' => '0100011',
            '5' => '0110001',
            '6' => '0101111',
            '7' => '0111011',
            '8' => '0110111',
            '9' => '0001011',
        ],
        'B' => [
            '0' => '0100111',
            '1' => '0110011',
            '2' => '0011011',
            '3' => '0100001',
            '4' => '0011101',
            '5' => '0111001',
            '6' => '0000101',
            '7' => '0010001',
            '8' => '0001001',
            '9' => '0010111',
        ],
    ];

    private const RIGHT_PARITY = [
        '0' => '1110010',
        '1' => '1100110',
        '2' => '1101100',
        '3' => '1000010',
        '4' => '1011100',
        '5' => '1001110',
        '6' => '1010000',
        '7' => '1000100',
        '8' => '1001000',
        '9' => '1110100',
    ];

    private const PARITY_PATTERN = [
        '0' => 'AAAAAA',
        '1' => 'AABABB',
        '2' => 'AABBAB',
        '3' => 'AABBBA',
        '4' => 'ABAABB',
        '5' => 'ABBAAB',
        '6' => 'ABBBAA',
        '7' => 'ABABAB',
        '8' => 'ABABBA',
        '9' => 'ABBABA',
    ];

    private const ADDON_PARITY_2 = ['AA', 'AB', 'BA', 'BB'];

    private const ADDON_PARITY_5 = [
        'BBAAA', 'BABAA', 'BAABA', 'BAAAB', 'ABBBA',
        'AABBA', 'AAABB', 'ABABA', 'ABAAB', 'AABAB',
    ];

    /**
     * Generate an EAN-13 barcode as a vector PDF.
     */
    public function generate(string $code, ?string $priceAddon = null): string
    {
        $normalized = $this->normalizeDigits($code);
        $normalized = $this->ensureChecksum($normalized);

        if (strlen($normalized) !== 13) {
            throw new InvalidArgumentException('EAN-13 code must contain 13 digits.');
        }

        if ($priceAddon !== null) {
            $priceAddon = $this->normalizeDigits($priceAddon);
            if (! in_array(strlen($priceAddon), [2, 5], true)) {
                throw new InvalidArgumentException('Add-on must be 2 or 5 digits to encode a price.');
            }
        }

        [$mainPattern, $mainLength] = $this->buildEan13Pattern($normalized);
        [$addonPattern, $addonLength] = $priceAddon ? $this->buildAddonPattern($priceAddon) : [[], 0];

        $barWidth = 1.0;
        $margin = 10.0;
        $gap = $priceAddon ? 6.0 * $barWidth : 0.0;

        $pageWidth = $margin * 2 + ($mainLength * $barWidth) + ($priceAddon ? $gap + ($addonLength * $barWidth) : 0.0);
        $pageHeight = 140.0;

        $barBottom = 40.0;
        $barHeight = 70.0;
        $guardExtra = 10.0;

        $content = [];
        $content[] = '0 0 0 rg';
        $x = $margin;

        foreach ($mainPattern as [$bits, $isGuard]) {
            foreach (str_split($bits) as $bit) {
                if ($bit === '1') {
                    $height = $barHeight + ($isGuard ? $guardExtra : 0.0);
                    $content[] = sprintf('%.2f %.2f %.2f %.2f re f', $x, $barBottom, $barWidth, $height);
                }
                $x += $barWidth;
            }
        }

        $addonStartX = $x + $gap;
        if ($addonPattern !== []) {
            $x = $addonStartX;
            foreach ($addonPattern as [$bits, $isGuard]) {
                foreach (str_split($bits) as $bit) {
                    if ($bit === '1') {
                        $height = ($isGuard ? $barHeight + $guardExtra : $barHeight - 8.0);
                        $content[] = sprintf('%.2f %.2f %.2f %.2f re f', $x, $barBottom, $barWidth, $height);
                    }
                    $x += $barWidth;
                }
            }
        }

        $content[] = 'BT /F1 12 Tf';
        $content[] = sprintf('%.2f %.2f Td (%s) Tj', $margin, $barBottom - 18.0, $normalized);
        if ($priceAddon) {
            $textX = $addonStartX;
            $content[] = sprintf('%.2f 0 Td (Price %s) Tj', $textX - $margin, $priceAddon);
        }
        $content[] = 'ET';

        $contentStream = implode("\n", $content);

        $objects = [
            1 => "<< /Type /Catalog /Pages 2 0 R >>",
            2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
            3 => sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2f %.2f] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>', $pageWidth, $pageHeight),
            4 => sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($contentStream), $contentStream),
            5 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
        ];

        return $this->assemblePdf($objects);
    }

    private function normalizeDigits(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value);
        if ($digits === null || $digits === '') {
            throw new InvalidArgumentException('Barcode values must be numeric.');
        }

        return $digits;
    }

    private function ensureChecksum(string $digits): string
    {
        if (strlen($digits) === 12) {
            return $digits.$this->calculateChecksum($digits);
        }

        if (strlen($digits) !== 13) {
            throw new InvalidArgumentException('EAN-13 requires 12 or 13 digits.');
        }

        $expected = $this->calculateChecksum(substr($digits, 0, 12));
        if ((int) substr($digits, -1) !== $expected) {
            throw new InvalidArgumentException('Invalid EAN-13 checksum.');
        }

        return $digits;
    }

    private function calculateChecksum(string $digits): int
    {
        $sum = 0;
        foreach (str_split($digits) as $index => $digit) {
            $value = (int) $digit;
            $sum += ($index % 2 === 0) ? $value : $value * 3;
        }

        return (10 - ($sum % 10)) % 10;
    }

    private function buildEan13Pattern(string $digits): array
    {
        $firstDigit = $digits[0];
        $leftDigits = substr($digits, 1, 6);
        $rightDigits = substr($digits, 7, 6);

        $parityPattern = self::PARITY_PATTERN[$firstDigit];

        $segments = [
            ['101', true],
        ];

        $leftBits = '';
        foreach (str_split($leftDigits) as $index => $digit) {
            $parity = $parityPattern[$index];
            $leftBits .= self::LEFT_PARITY[$parity][$digit];
        }
        $segments[] = [$leftBits, false];
        $segments[] = ['01010', true];

        $rightBits = '';
        foreach (str_split($rightDigits) as $digit) {
            $rightBits .= self::RIGHT_PARITY[$digit];
        }
        $segments[] = [$rightBits, false];
        $segments[] = ['101', true];

        $length = array_sum(array_map(static fn ($segment) => strlen($segment[0]), $segments));

        return [$segments, $length];
    }

    private function buildAddonPattern(string $addon): array
    {
        $digits = str_split($addon);
        $segments = [
            ['1011', true],
        ];

        if (count($digits) === 2) {
            $checksum = ((int) $digits[0] * 3 + (int) $digits[1] * 9) % 4;
            $pattern = self::ADDON_PARITY_2[$checksum];
        } else {
            $checksum = ((int) $digits[0] * 3 + (int) $digits[1] * 9 + (int) $digits[2] * 3 + (int) $digits[3] * 9 + (int) $digits[4] * 3) % 10;
            $pattern = self::ADDON_PARITY_5[$checksum];
        }

        foreach ($digits as $index => $digit) {
            $parity = $pattern[$index];
            $segments[] = [self::LEFT_PARITY[$parity][$digit], false];
            if ($index !== count($digits) - 1) {
                $segments[] = ['01', true];
            }
        }

        $length = array_sum(array_map(static fn ($segment) => strlen($segment[0]), $segments));

        return [$segments, $length];
    }

    private function assemblePdf(array $objects): string
    {
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= sprintf("%d 0 obj\n%s\nendobj\n", $number, $body);
        }

        $xrefPosition = strlen($pdf);
        $pdf .= sprintf("xref\n0 %d\n", count($objects) + 1);
        $pdf .= "0000000000 65535 f \n";

        foreach ($objects as $number => $_) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number]);
        }

        $pdf .= sprintf("trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF", count($objects) + 1, $xrefPosition);

        return $pdf;
    }
}

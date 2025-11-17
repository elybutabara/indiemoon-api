<?php

namespace App\Domain\Spine;

final class SpineCalculator
{
    public function widthMm(int $pageCount, float $paperCaliperMm): float
    {
        $spineWidth = ($pageCount * $paperCaliperMm) / 2;

        return round($spineWidth, 2);
    }
}

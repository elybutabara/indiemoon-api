<?php
namespace App\Domain\Spine;

final class SpineCalculator {

    public function widthMm(int $pageCount, float $paperCaliperMm): float
    {
        return round(($pageCount * $paperCaliperMm) / 2, 2);
    }
    
}
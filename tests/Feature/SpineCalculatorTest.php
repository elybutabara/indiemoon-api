<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpineCalculatorTest extends TestCase
{
    public function test_it_calculates_spine_width()
    {
        $response = $this->postJson('/spine/calculate', [
            'page_count' => 300,
            'paper_caliper_mm' => 0.05,
        ]);

        $response->assertOk()->assertJson([
            'width_mm' => 7.5,
        ]);
    }

    public function test_it_validates_input()
    {
        $response = $this->postJson('/spine/calculate', []);

        $response->assertUnprocessable();
    }
}

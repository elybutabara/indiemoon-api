<?php

namespace Tests\Feature;

use Tests\TestCase;

class CoverTemplateControllerTest extends TestCase
{
    public function test_it_generates_cover_template(): void
    {
        $payload = [
            'height_mm' => 220,
            'back_cover_mm' => 140,
            'spine_mm' => 18,
            'front_cover_mm' => 140,
            'back_flap_mm' => 40,
            'front_flap_mm' => 30,
            'bleed_mm' => 3,
        ];

        $response = $this->postJson('/cover/template', $payload);

        $response->assertOk()->assertJsonStructure([
            'layout' => [
                'width_mm',
                'height_mm',
                'bleed_mm',
                'panels',
            ],
            'svg',
            'pdf_base64',
        ]);

        $data = $response->json();

        $this->assertStringStartsWith('<svg', $data['svg']);
        $this->assertStringStartsWith('%PDF-1.4', base64_decode($data['pdf_base64']));
    }

    public function test_it_validates_input(): void
    {
        $response = $this->postJson('/cover/template', []);

        $response->assertUnprocessable();
    }
}

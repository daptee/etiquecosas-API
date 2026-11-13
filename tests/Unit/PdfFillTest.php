<?php

namespace Tests\Unit;

use App\Services\PdfDesignSanitizer;
use Tests\TestCase;

class PdfFillTest extends TestCase
{
    private const COLOR = ['mode' => 'hex', 'value' => '#EBA4AB', 'dynamic_attribute_id' => null, 'editable_by_customer' => false];

    public function test_fill_valido_pasa_limpio(): void
    {
        $this->assertSame(['type' => 'none'], PdfDesignSanitizer::sanitizeFill(['type' => 'none']));
        $this->assertSame(['type' => 'color', 'color' => self::COLOR], PdfDesignSanitizer::sanitizeFill(['type' => 'color', 'color' => self::COLOR, 'image' => 'x']));
        $this->assertSame(
            ['type' => 'image', 'image' => 'pdf-element-images/99/el_abc.png'],
            PdfDesignSanitizer::sanitizeFill(['type' => 'image', 'image' => 'pdf-element-images/99/el_abc.png', 'color' => self::COLOR])
        );
    }

    public function test_fill_que_no_se_puede_dibujar_queda_transparente(): void
    {
        foreach ([
            ['type' => 'gradient'],
            ['type' => 'color'],
            ['type' => 'image'],
            ['type' => 'image', 'image' => '../../.env'],
            ['type' => 'image', 'image' => 'pdf-element-images/99/../../.env'],
            ['type' => 'image', 'image' => 'pdf-backgrounds/99/bg.png'],
            'color',
        ] as $fill) {
            $this->assertSame(['type' => 'none'], PdfDesignSanitizer::sanitizeFill($fill), json_encode($fill));
        }
    }

    public function test_fill_manda_sobre_el_formato_viejo_de_la_etiqueta(): void
    {
        $el = [
            'type' => 'background',
            'fill' => ['type' => 'none'],
            'color' => self::COLOR,
            'background_image' => 'pdf-element-images/99/el_abc.png',
        ];

        $this->assertSame(['type' => 'none'], PdfDesignSanitizer::fillDelElemento($el));
    }

    public function test_etiqueta_sin_fill_usa_el_formato_viejo(): void
    {
        $this->assertSame(
            ['type' => 'image', 'image' => 'pdf-element-images/99/el_abc.png'],
            PdfDesignSanitizer::fillDelElemento(['type' => 'background', 'color' => self::COLOR, 'background_image' => 'pdf-element-images/99/el_abc.png'])
        );
        $this->assertSame(['type' => 'color', 'color' => self::COLOR], PdfDesignSanitizer::fillDelElemento(['type' => 'background', 'color' => self::COLOR]));
        $this->assertSame(['type' => 'none'], PdfDesignSanitizer::fillDelElemento(['type' => 'background']));
    }

    public function test_texto_sin_fill_no_tiene_fondo_aunque_tenga_color(): void
    {
        $this->assertSame(['type' => 'none'], PdfDesignSanitizer::fillDelElemento(['type' => 'text', 'color' => self::COLOR]));
    }

    public function test_sanitize_elements_limpia_fill_y_saca_null(): void
    {
        [$conFill, $sinFill] = PdfDesignSanitizer::sanitizeElements([
            ['type' => 'icon', 'fill' => ['type' => 'image', 'image' => '<b>x</b>']],
            ['type' => 'icon', 'fill' => null],
        ]);

        $this->assertSame(['type' => 'none'], $conFill['fill']);
        $this->assertArrayNotHasKey('fill', $sinFill);
    }
}

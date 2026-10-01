<?php

namespace Tests\Unit;

use App\Models\ProductPdfDesign;
use App\Services\EtiquetaService;
use App\Services\PdfDesignSanitizer;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Corte de renglones y layout_groups del editor de PDF
 * (ver PDF_LAYOUT_GROUPS.md). No toca la base de datos: los diseños se arman
 * en memoria y los íconos se resuelven con el ícono "libre" del cliente.
 */
class PdfLayoutGroupsTest extends TestCase
{
    private const ICON = 'icons/icons/icon_684c2d93f12b1.svg';

    private function dividir(string $texto, int $maxLines, int $maxChars, ?string $firstName = null): array
    {
        $method = new ReflectionMethod(EtiquetaService::class, 'dividirEnRenglones');
        $method->setAccessible(true);

        return $method->invoke(null, $texto, $maxLines, $maxChars, $firstName);
    }

    public static function casosDeCorte(): array
    {
        return [
            'corto' => ['Ana', 3, 10, null, ['Ana']],
            'dos palabras' => ['María Fernanda', 3, 10, null, ['María', 'Fernanda']],
            'partículas' => ['María de los Ángeles González', 3, 10, null, ['María', 'de los Ángeles', 'González']],
            'primera palabra larga' => ['GUILLERMINA', 3, 10, null, ['GUILLERMINA']],
            'primera palabra justa' => ['ABCDEFGHIJ', 3, 10, null, ['ABCDEFGHIJ']],
            'nombre completo' => ['María Fernanda', 2, 8, 'María', ['María', 'Fernanda']],
            'sobra de max_lines' => ['uno dos tres cuatro cinco seis siete', 3, 8, null, ['uno dos', 'tres', 'cuatro cinco seis siete']],
            'espacios dobles' => ['Ana  Paz', 3, 10, null, ['Ana Paz']],
            'salto de línea' => ["Ana\nPaz", 3, 10, null, ['Ana Paz']],
            'solo espacios' => ['   ', 3, 10, null, ['']],
            'nombre completo con max_lines 1' => ['Ana Paz', 1, 5, 'Ana', ['Ana…']],
        ];
    }

    /** @dataProvider casosDeCorte */
    public function test_corte_de_renglones(string $texto, int $maxLines, int $maxChars, ?string $firstName, array $esperado): void
    {
        $this->assertSame($esperado, $this->dividir($texto, $maxLines, $maxChars, $firstName));
    }

    private function elementos(): array
    {
        return [
            ['id' => 'bg', 'type' => 'background'],
            ['id' => 'bg2', 'type' => 'background'],
            ['id' => 't1', 'type' => 'text'],
            ['id' => 'i1', 'type' => 'icon'],
            ['id' => 's1', 'type' => 'shape'],
        ];
    }

    private function grupo(array $extra = []): array
    {
        return array_merge([
            'id' => 'g1',
            'container_element_id' => 'bg',
            'direction' => 'vertical',
            'gap_cm' => 0.3,
            'align' => 'center',
            'members' => ['i1', 't1'],
        ], $extra);
    }

    public function test_grupo_valido_no_tiene_errores(): void
    {
        $this->assertSame([], PdfDesignSanitizer::validateLayoutGroups([$this->grupo()], $this->elementos()));
    }

    public static function gruposInvalidos(): array
    {
        return [
            'sin id' => [['id' => null]],
            'contenedor inexistente' => [['container_element_id' => 'nope']],
            'contenedor que no es background' => [['container_element_id' => 't1']],
            'direction desconocida' => [['direction' => 'diagonal']],
            'align desconocido' => [['align' => 'middle']],
            'gap negativo' => [['gap_cm' => -1]],
            'gap mayor a 50' => [['gap_cm' => 51]],
            'gap no numérico' => [['gap_cm' => 'mucho']],
            'sin miembros' => [['members' => []]],
            'miembro inexistente' => [['members' => ['i1', 'nope']]],
            'miembro de tipo shape' => [['members' => ['s1']]],
            'miembro repetido en el mismo grupo' => [['members' => ['i1', 'i1']]],
        ];
    }

    /** @dataProvider gruposInvalidos */
    public function test_grupo_invalido(array $extra): void
    {
        $errores = PdfDesignSanitizer::validateLayoutGroups([$this->grupo($extra)], $this->elementos());

        $this->assertArrayHasKey(0, $errores);
    }

    public function test_id_de_grupo_repetido_y_miembro_en_dos_grupos(): void
    {
        $errores = PdfDesignSanitizer::validateLayoutGroups([
            $this->grupo(),
            $this->grupo(['members' => ['t1']]),
            $this->grupo(['id' => 'g3', 'container_element_id' => 'bg2', 'members' => ['t1']]),
        ], $this->elementos());

        $this->assertSame([1, 2], array_keys($errores));
        $this->assertStringContainsString('repetido', $errores[1]);
        $this->assertStringContainsString('ya está en el grupo "g1"', $errores[2]);
    }

    private function calcular(array $elements, array $groups, string $lastName = 'Paz'): array
    {
        $design = new ProductPdfDesign(['name' => 'TEST', 'data' => ['pages' => [[
            'id' => 'p1',
            'sheet' => ['width_cm' => 7, 'height_cm' => 6],
            'elements' => $elements,
            'layout_groups' => $groups,
        ]]]]);
        $design->id = 1;
        $productOrder = (object)['id' => 'test', 'product_id' => 222222, 'product' => (object)['name' => 'TEST'], 'variant' => null];

        $layout = EtiquetaService::calcularLayoutDesdeDesign(111111, $design, $productOrder, trim("Ana {$lastName}"), null, self::ICON, now(), 'Ana', $lastName);

        return $layout['pages'][0]['groups'];
    }

    private function background(array $extra = []): array
    {
        return array_merge(['id' => 'bg', 'type' => 'background', 'x_cm' => 1, 'y_cm' => 1, 'width_cm' => 5, 'height_cm' => 4], $extra);
    }

    private function texto(string $content, array $extra = []): array
    {
        return array_merge([
            'id' => 't1', 'type' => 'text', 'content' => $content,
            'x_cm' => 0, 'y_cm' => 0, 'width_cm' => 5, 'height_cm' => 1,
            'font_size_px' => 32, 'line_height' => 1.15, 'max_lines' => 3, 'max_chars_per_line' => 10, 'min_lines' => 3,
        ], $extra);
    }

    private function icono(string $id = 'i1', bool $conImagen = true): array
    {
        return ['id' => $id, 'type' => 'icon', 'x_cm' => 0, 'y_cm' => 0, 'width_cm' => 1.2, 'height_cm' => 1.2, 'editable_by_customer' => $conImagen, 'editable_field' => 'icon'];
    }

    public function test_icono_y_texto_quedan_centrados_con_gap_fijo(): void
    {
        // Texto de 1 renglón: 32px × 1.15 × 2.54/96 = 0.9737cm (min_lines no reserva).
        // Total: 1.2 + 0.3 + 0.9737 = 2.4737 → arranca en 1 + (4 - 2.4737)/2 = 1.7632.
        $grupo = $this->calcular(
            [$this->background(), $this->icono(), $this->texto('Ana')],
            [$this->grupo()]
        )[0];

        [$icono, $texto] = $grupo['members'];
        $this->assertSame(['x_cm' => 2.9, 'y_cm' => 1.76], ['x_cm' => $icono['x_cm'], 'y_cm' => $icono['y_cm']]);
        $this->assertSame(['x_cm' => 1.0, 'y_cm' => 3.26, 'height_cm' => 0.97], ['x_cm' => $texto['x_cm'], 'y_cm' => $texto['y_cm'], 'height_cm' => $texto['height_cm']]);
        $this->assertSame(['Ana'], $texto['lines']);
        $this->assertEquals(0, $grupo['overflow_cm']);
    }

    public function test_la_distancia_entre_miembros_no_cambia_con_el_largo_del_texto(): void
    {
        foreach (['Ana', 'María Fernanda', 'María de los Ángeles González'] as $content) {
            [$icono, $texto] = $this->calcular(
                [$this->background(), $this->icono(), $this->texto($content)],
                [$this->grupo()]
            )[0]['members'];

            $this->assertEqualsWithDelta(0.3, $texto['y_cm'] - ($icono['y_cm'] + $icono['height_cm']), 0.011, $content);
        }
    }

    public function test_miembros_ausentes_no_suman_lugar_ni_gap(): void
    {
        // Texto vacío (apellido sin dato): el ícono queda solo y centrado.
        $grupo = $this->calcular(
            [$this->background(), $this->icono(), $this->texto('{{customer_last_name}}')],
            [$this->grupo()],
            ''
        )[0];
        $this->assertFalse($grupo['members'][1]['present']);
        $this->assertSame(2.4, $grupo['members'][0]['y_cm']);

        // Ícono sin imagen resuelta: el texto queda solo y centrado.
        $grupo = $this->calcular(
            [$this->background(), $this->icono('i1', false), $this->texto('Ana')],
            [$this->grupo()]
        )[0];
        $this->assertFalse($grupo['members'][0]['present']);
        $this->assertSame(2.51, $grupo['members'][1]['y_cm']);
    }

    public function test_align_start_y_end_en_el_eje_transversal(): void
    {
        $elements = [$this->background(), $this->icono(), $this->texto('Ana', ['width_cm' => 3])];

        [$icono] = $this->calcular($elements, [$this->grupo(['align' => 'start'])])[0]['members'];
        $this->assertSame(2.0, $icono['x_cm']); // bloque de 3cm centrado en 5cm → empieza en 2

        [$icono] = $this->calcular($elements, [$this->grupo(['align' => 'end'])])[0]['members'];
        $this->assertSame(3.8, $icono['x_cm']); // 2 + 3 - 1.2
    }

    public function test_el_padding_del_contenedor_no_cambia_el_centrado(): void
    {
        $sinPadding = $this->calcular([$this->background(), $this->icono(), $this->texto('Ana')], [$this->grupo()]);
        $conPadding = $this->calcular([$this->background(['padding_cm' => 0.8]), $this->icono(), $this->texto('Ana')], [$this->grupo()]);

        $this->assertSame($sinPadding, $conPadding);
    }

    public function test_desborde_no_escala(): void
    {
        $grupo = $this->calcular(
            [$this->background(['height_cm' => 1]), $this->icono(), $this->texto('Ana')],
            [$this->grupo()]
        )[0];

        $this->assertSame(1.47, $grupo['overflow_cm']);
        $this->assertSame(1.2, $grupo['members'][0]['height_cm']);
    }

    public function test_tokens_de_la_venta_en_texto_fijo(): void
    {
        $grupo = $this->calcular(
            [$this->background(), $this->texto('{{numero_pedido}} {{id_producto}}', ['max_chars_per_line' => 20])],
            [$this->grupo(['members' => ['t1']])]
        )[0];

        $this->assertSame(['111111 222222'], $grupo['members'][0]['lines']);
    }

    public function test_grupo_invalido_se_descarta_al_generar(): void
    {
        $grupos = $this->calcular(
            [$this->background(), $this->icono(), $this->texto('Ana')],
            [$this->grupo(), $this->grupo(['id' => 'g2', 'members' => ['t1']])]
        );

        $this->assertSame(['g1'], array_column($grupos, 'id'));
    }
}

<?php

namespace App\Services;

use App\Models\Typography;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Regenera desde cero el caché de fuentes de dompdf
 * (`public/fonts/installed-fonts.json` + los .ttf/.ufm con hash que dompdf
 * copia al registrar cada fuente + las métricas en `storage/fonts`).
 *
 * Con el tiempo ese caché acumula registros viejos/duplicados (misma familia
 * registrada desde `file://` y sin `file://`, versiones remotas de Google, etc.)
 * y alguno de ellos puede quedar corrupto — reconstruirlo deja una sola entrada
 * por familia/estilo apuntando a los archivos locales.
 */
class FontCacheService
{
    /**
     * Fuentes fijas que usan las vistas Blade (@font-face). La clave es la
     * family tal como la declaran las vistas.
     */
    private const STATIC_FONTS = [
        'Oswald' => [
            ['weight' => 'normal', 'style' => 'normal', 'file' => 'Oswald-Regular.ttf'],
            ['weight' => 'bold',   'style' => 'normal', 'file' => 'Oswald-Bold.ttf'],
        ],
        'Lora' => [
            ['weight' => 'normal', 'style' => 'normal', 'file' => 'Lora-Regular.ttf'],
            ['weight' => 'bold',   'style' => 'normal', 'file' => 'Lora-Bold.ttf'],
            ['weight' => 'normal', 'style' => 'italic', 'file' => 'Lora-Italic.ttf'],
            ['weight' => 'bold',   'style' => 'italic', 'file' => 'Lora-BoldItalic.ttf'],
        ],
        'TitanOne' => [
            ['weight' => 'normal', 'style' => 'normal', 'file' => 'TitanOne-Regular.ttf'],
        ],
        'QuicksandBook' => [
            ['weight' => 'normal', 'style' => 'normal', 'file' => 'QuicksandBook-Regular.ttf'],
        ],
        'Antonio' => [
            ['weight' => 'normal', 'style' => 'normal', 'file' => 'Antonio.ttf'],
        ],
        'Liberation Sans' => [
            ['weight' => 'normal', 'style' => 'normal', 'file' => 'LiberationSans-Regular.ttf'],
        ],
    ];

    /** Archivos generados por dompdf: `<familia>_<estilo>_<md5>.<ext>` */
    private const GENERATED_FILE_REGEX = '/_[0-9a-f]{32}\.(ttf|otf|ufm|afm)(\.json)?$/i';

    /**
     * @return array{registered: array<int, string>, failed: array<int, string>}
     */
    public function rebuild(): array
    {
        $fontDir = config('dompdf.options.font_dir', public_path('fonts'));
        $fontCache = config('dompdf.options.font_cache', storage_path('fonts'));

        $this->clearGeneratedFiles($fontDir, $fontCache);

        $options = new Options();
        $options->setFontDir($fontDir);
        $options->setFontCache($fontCache);
        $options->setChroot([base_path()]);
        $options->setIsRemoteEnabled(false);

        $fontMetrics = (new Dompdf($options))->getFontMetrics();

        $registered = [];
        $failed = [];

        foreach ($this->fontsToRegister($fontDir) as $font) {
            $label = "{$font['family']} ({$font['weight']} {$font['style']})";

            if (!is_file($font['path'])) {
                $failed[] = "{$label}: archivo inexistente {$font['path']}";
                continue;
            }

            try {
                $ok = $fontMetrics->registerFont(
                    ['family' => $font['family'], 'weight' => $font['weight'], 'style' => $font['style']],
                    'file://' . str_replace('\\', '/', $font['path'])
                );
                $ok ? $registered[] = $label : $failed[] = $label;
            } catch (\Throwable $e) {
                $failed[] = "{$label}: {$e->getMessage()}";
            }
        }

        // Garantiza que el JSON exista aunque no se haya registrado nada.
        $fontMetrics->saveFontFamilies();

        if ($failed) {
            Log::warning('[fonts] Fuentes que no se pudieron registrar al regenerar el caché', $failed);
        }

        return compact('registered', 'failed');
    }

    private function clearGeneratedFiles(string $fontDir, string $fontCache): void
    {
        File::delete($fontDir . DIRECTORY_SEPARATOR . 'installed-fonts.json');

        foreach (array_unique([$fontDir, $fontCache]) as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            foreach (File::files($dir) as $file) {
                if (preg_match(self::GENERATED_FILE_REGEX, $file->getFilename())) {
                    File::delete($file->getPathname());
                }
            }
        }
    }

    /**
     * @return array<int, array{family: string, weight: string, style: string, path: string}>
     */
    private function fontsToRegister(string $fontDir): array
    {
        $fonts = [];

        // Tipografías del catálogo (subidas desde el admin). Van primero: si
        // alguna se llama igual que una fuente fija (ej. "oswald"), la fija
        // —la que usan las vistas Blade— se registra después y prevalece.
        $typographies = Typography::with('files')->get();
        foreach ($typographies as $typography) {
            foreach ($typography->files as $file) {
                $name = mb_strtolower($file->file_name ?? '');
                $fonts[] = [
                    'family' => $typography->name,
                    'weight' => str_contains($name, 'bold') ? 'bold' : 'normal',
                    'style'  => str_contains($name, 'italic') ? 'italic' : 'normal',
                    'path'   => public_path($file->file_path),
                ];
            }
        }

        foreach (self::STATIC_FONTS as $family => $variants) {
            foreach ($variants as $variant) {
                $fonts[] = [
                    'family' => $family,
                    'weight' => $variant['weight'],
                    'style'  => $variant['style'],
                    'path'   => $fontDir . DIRECTORY_SEPARATOR . $variant['file'],
                ];
            }
        }

        return $fonts;
    }
}

<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\LabelShape;
use App\Models\PersonalizationIcon;
use App\Models\ProductPdfDesign;
use App\Models\Typography;
use App\Models\TypographyFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Pasa un diseño del editor de PDFs de un entorno a otro (ej. dev → prod),
 * ver PDF_EXPORTAR_IMPORTAR_DISENO.md.
 *
 * export() arma un JSON autocontenido: el diseño, los datos de catálogo a los
 * que apunta por id (tipografías, íconos, formas de etiqueta, atributos) y los
 * archivos que usa (imágenes subidas, archivos de fuente, SVG de íconos) en
 * base64. Los ids de un entorno no sirven en el otro, así que import() los
 * busca por NOMBRE en el entorno destino; si no existen, crea la tipografía /
 * ícono / forma con sus archivos (los atributos no se crean: quedan en null y
 * se avisa). Los vínculos con productos no viajan.
 */
class PdfDesignTransferService
{
    public const FORMAT = 'etiquecosas.pdf-design';
    public const VERSION = 1;

    // Claves dentro de `data` que guardan un id de catálogo.
    private const ID_KEYS = [
        'font_id' => 'typographies',
        'icon_id' => 'icons',
        'label_shape_id' => 'label_shapes',
        'dynamic_attribute_id' => 'attributes',
    ];

    // Claves dentro de `data` que guardan la ruta (relativa a public/) de un
    // archivo subido: sheet.background_image, elements[].background_image,
    // elements[].custom_icon_path y elements[].fill.image.
    private const FILE_KEYS = ['background_image', 'custom_icon_path', 'image'];

    // Imágenes propias del diseño: la carpeta lleva el id del diseño, que
    // cambia al importar (y PdfDesignSanitizer exige pdf-element-images/{id}/).
    private const DESIGN_FILE_PATTERN = '#^(pdf-element-images|pdf-backgrounds)/\d+/([A-Za-z0-9_][A-Za-z0-9._-]*)$#';

    // Lo único que se escribe en public/ al importar: imágenes y fuentes.
    private const ALLOWED_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'ttf', 'otf', 'woff', 'woff2', 'eot'];

    /** Rutas escritas por import(), para borrarlas si después algo falla. */
    private array $writtenFiles = [];

    // ---------------------------------------------------------------------
    // Exportar
    // ---------------------------------------------------------------------

    public function export(ProductPdfDesign $design): array
    {
        $warnings = [];
        $data = $this->inlineBorrowedPages($design->data ?? [], $warnings);

        $refs = ['typographies' => [], 'icons' => [], 'label_shapes' => [], 'attributes' => []];
        $paths = [];
        $this->collect($data, $refs, $paths);
        if ($design->label_shape_id) {
            $refs['label_shapes'][$design->label_shape_id] = true;
        }

        $files = [];
        $addFile = function (?string $path) use (&$files, &$warnings) {
            if (!$path || isset($files[$path])) {
                return;
            }
            $absolute = public_path($path);
            if (!is_file($absolute)) {
                $warnings[] = "Archivo inexistente, no se exporta: {$path}";
                return;
            }
            $files[$path] = base64_encode(file_get_contents($absolute));
        };
        foreach (array_keys($paths) as $path) {
            $addFile($path);
        }

        $typographies = [];
        foreach (Typography::with('files')->whereIn('id', array_keys($refs['typographies']))->get() as $typography) {
            $typographies[$typography->id] = [
                'name' => $typography->name,
                'files' => $typography->files->map(function ($file) use ($addFile) {
                    $addFile($file->file_path);
                    return ['path' => $file->file_path, 'file_name' => $file->file_name, 'file_type' => $file->file_type];
                })->values()->all(),
            ];
        }

        $icons = [];
        foreach (PersonalizationIcon::whereIn('id', array_keys($refs['icons']))->get() as $icon) {
            $addFile($icon->icon);
            $icons[$icon->id] = ['name' => $icon->name, 'path' => $icon->icon];
        }

        $labelShapes = [];
        foreach (LabelShape::whereIn('id', array_keys($refs['label_shapes']))->get() as $shape) {
            $labelShapes[$shape->id] = $shape->only(['name', 'shape_type', 'width_cm', 'height_cm', 'is_system', 'data']);
        }

        $attributes = [];
        foreach (Attribute::whereIn('id', array_keys($refs['attributes']))->get() as $attribute) {
            $attributes[$attribute->id] = ['name' => $attribute->name, 'type' => $attribute->type];
        }

        foreach (self::ID_KEYS as $group) {
            $found = ['typographies' => $typographies, 'icons' => $icons, 'label_shapes' => $labelShapes, 'attributes' => $attributes][$group];
            foreach (array_keys($refs[$group]) as $id) {
                if (!isset($found[$id])) {
                    $warnings[] = "{$group} #{$id} no existe en este entorno: al importar quedará vacío";
                }
            }
        }

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'source' => ['app_url' => config('app.url'), 'design_id' => $design->id],
            'design' => [
                'name' => $design->name,
                'label_shape_id' => $design->label_shape_id,
                'fill_mode' => $design->fill_mode,
                'accumulation_group_attribute' => $design->accumulation_group_attribute,
                'accumulation_split_attributes' => $design->accumulation_split_attributes,
                'accumulation_cols' => $design->accumulation_cols,
                'accumulation_rows' => $design->accumulation_rows,
                'accumulation_gap_cm' => $design->accumulation_gap_cm,
                'accumulation_quantity_mode' => $design->accumulation_quantity_mode,
                'pages_output_mode' => $design->pages_output_mode,
                'data' => $data,
            ],
            // Claves = id en el entorno de origen (objetos JSON, no listas).
            'refs' => [
                'typographies' => (object) $typographies,
                'icons' => (object) $icons,
                'label_shapes' => (object) $labelShapes,
                'attributes' => (object) $attributes,
            ],
            'files' => (object) $files,
            'warnings' => $warnings,
        ];
    }

    /**
     * Páginas prestadas (source = {designId, pageId}) apuntan a OTRO diseño
     * por id, que en el destino no existe o es otro: se exportan ya con el
     * contenido de la página origen, como páginas normales.
     */
    private function inlineBorrowedPages(array $data, array &$warnings): array
    {
        foreach ($data['pages'] ?? [] as $idx => $page) {
            $sourceId = $page['source']['designId'] ?? null;
            if (!$sourceId) {
                continue;
            }

            $sourceData = ProductPdfDesign::find((int) $sourceId)?->data ?? [];
            $origen = collect($sourceData['pages'] ?? [])->firstWhere('id', $page['source']['pageId'] ?? '');
            unset($page['source'], $page['cut_lines'], $page['layout_groups']);
            $page['elements'] = [];
            if ($origen) {
                foreach (['sheet', 'elements', 'layout_groups', 'cut_lines'] as $key) {
                    if (array_key_exists($key, $origen)) {
                        $page[$key] = $origen[$key];
                    }
                }
                $warnings[] = "La página " . ($idx + 1) . " tomaba el contenido del diseño #{$sourceId}: se exporta como copia, ya no queda vinculada";
            } else {
                $warnings[] = "La página " . ($idx + 1) . " apunta al diseño #{$sourceId}, que ya no tiene esa página: se exporta vacía";
            }
            $data['pages'][$idx] = $page;
        }

        return $data;
    }

    private function collect(array $node, array &$refs, array &$paths): void
    {
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $this->collect($value, $refs, $paths);
            } elseif (isset(self::ID_KEYS[$key]) && is_numeric($value)) {
                $refs[self::ID_KEYS[$key]][(int) $value] = true;
            } elseif (in_array($key, self::FILE_KEYS, true) && is_string($value) && $value !== '') {
                $paths[$value] = true;
            }
        }
    }

    // ---------------------------------------------------------------------
    // Importar
    // ---------------------------------------------------------------------

    /** Error de formato del archivo, o null si se puede importar. */
    public static function bundleError($bundle): ?string
    {
        if (!is_array($bundle) || ($bundle['format'] ?? null) !== self::FORMAT) {
            return 'El archivo no es una exportación de diseño de PDF.';
        }
        if ((int) ($bundle['version'] ?? 0) !== self::VERSION) {
            return 'Versión de exportación no soportada: ' . ($bundle['version'] ?? '?');
        }
        if (!is_array($bundle['design']['data'] ?? null) || !is_array($bundle['design']['data']['pages'] ?? null)) {
            return 'La exportación no trae data.pages.';
        }

        return null;
    }

    /**
     * Resuelve los ids de catálogo contra ESTE entorno (crea lo que falte),
     * escribe los archivos y devuelve el `data` ya remapeado (sin sanear) +
     * qué se hizo con cada referencia. $designId es el diseño destino (ya
     * creado), para la carpeta de sus imágenes propias.
     */
    public function import(array $bundle, int $designId, bool $createMissingIcons = true, array $iconActions = []): array
    {
        $report = ['matched' => [], 'created' => [], 'missing' => [], 'warnings' => array_values($bundle['warnings'] ?? [])];
        $files = (array) ($bundle['files'] ?? []);
        $refs = $bundle['refs'] ?? [];

        $maps = [
            'label_shapes' => $this->importLabelShapes((array) ($refs['label_shapes'] ?? []), $report),
            'typographies' => $this->importTypographies((array) ($refs['typographies'] ?? []), $files, $report),
            'icons' => $this->importIcons((array) ($refs['icons'] ?? []), $files, $report, $createMissingIcons, $iconActions),
            'attributes' => $this->importAttributes((array) ($refs['attributes'] ?? []), $report),
        ];

        // Archivos propios del diseño (los de tipografías/íconos ya se
        // escribieron arriba con sus propias rutas).
        $paths = [];
        $ignored = [];
        $this->collect($bundle['design']['data'], $ignored, $paths);
        $pathMap = [];
        foreach (array_keys($paths) as $path) {
            if (!isset($files[$path])) {
                $report['missing'][] = "Archivo no incluido en la exportación: {$path}";
                continue;
            }
            $target = preg_match(self::DESIGN_FILE_PATTERN, $path, $m) ? "{$m[1]}/{$designId}/{$m[2]}" : $path;
            $pathMap[$path] = $this->writeFile($target, $files[$path]);
        }

        $data = $this->remap($bundle['design']['data'], $maps, $pathMap);

        $designLabelShapeId = $bundle['design']['label_shape_id'] ?? null;

        return [
            'data' => $data,
            'label_shape_id' => $designLabelShapeId ? ($maps['label_shapes'][(int) $designLabelShapeId] ?? null) : null,
            'report' => $report,
        ];
    }

    /** Borra lo que escribió import() (si después no se pudo guardar el diseño). */
    public function rollbackFiles(): void
    {
        $disk = Storage::disk('public_uploads');
        foreach ($this->writtenFiles as $path) {
            $disk->delete($path);
            // Carpeta creada para este diseño/tipografía nueva: si quedó vacía, también.
            $dir = dirname($path);
            if (preg_match('#^(pdf-element-images|pdf-backgrounds|fonts/typographies)/\d+$#', $dir) && empty($disk->allFiles($dir))) {
                $disk->deleteDirectory($dir);
            }
        }
        $this->writtenFiles = [];
    }

    private function importLabelShapes(array $shapes, array &$report): array
    {
        $map = [];
        foreach ($shapes as $oldId => $shape) {
            $existing = LabelShape::where('name', $shape['name'] ?? '')->first();
            if ($existing) {
                $map[(int) $oldId] = $existing->id;
                $report['matched'][] = "Forma de etiqueta \"{$existing->name}\" (#{$existing->id})";
                continue;
            }
            $created = LabelShape::create([
                'name' => $shape['name'] ?? 'Forma importada',
                'shape_type' => $shape['shape_type'] ?? 'rect',
                'width_cm' => $shape['width_cm'] ?? 0,
                'height_cm' => $shape['height_cm'] ?? 0,
                'is_system' => (bool) ($shape['is_system'] ?? false),
                'data' => $shape['data'] ?? null,
                'status_id' => 1,
            ]);
            $map[(int) $oldId] = $created->id;
            $report['created'][] = "Forma de etiqueta \"{$created->name}\" (#{$created->id})";
        }

        return $map;
    }

    private function importTypographies(array $typographies, array $files, array &$report): array
    {
        $map = [];
        foreach ($typographies as $oldId => $typography) {
            $existing = Typography::where('name', $typography['name'] ?? '')->first();
            if ($existing) {
                $map[(int) $oldId] = $existing->id;
                $report['matched'][] = "Tipografía \"{$existing->name}\" (#{$existing->id})";
                continue;
            }

            $created = Typography::create(['name' => $typography['name'] ?? 'Tipografía importada', 'status_id' => 1]);
            foreach ($typography['files'] ?? [] as $file) {
                $path = $file['path'] ?? '';
                if (!isset($files[$path])) {
                    $report['missing'][] = "Archivo de la tipografía \"{$created->name}\" no incluido: {$path}";
                    continue;
                }
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $newPath = $this->writeFile("fonts/typographies/{$created->id}/" . uniqid('font_') . ".{$ext}", $files[$path]);
                TypographyFile::create([
                    'typography_id' => $created->id,
                    'file_path' => $newPath,
                    'file_name' => $file['file_name'] ?? basename($path),
                    'file_type' => $file['file_type'] ?? $ext,
                ]);
            }
            $map[(int) $oldId] = $created->id;
            $report['created'][] = "Tipografía \"{$created->name}\" (#{$created->id})";
        }

        return $map;
    }

    /**
     * Íconos de la exportación que NO existen en este entorno (no hay uno con
     * el mismo nombre). Para que el front pregunte qué hacer con cada uno
     * antes de importar. `svg` es el archivo que trae la exportación (data
     * URL, para mostrarlo), o null si no vino.
     */
    public function missingIcons(array $bundle): array
    {
        $files = (array) ($bundle['files'] ?? []);
        $missing = [];
        foreach ((array) ($bundle['refs']['icons'] ?? []) as $oldId => $icon) {
            if (PersonalizationIcon::where('name', $icon['name'] ?? '')->exists()) {
                continue;
            }
            $path = $icon['path'] ?? '';
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = $ext === 'svg' ? 'image/svg+xml' : "image/{$ext}";
            $missing[] = [
                'sourceIconId' => (int) $oldId,
                'name' => $icon['name'] ?? null,
                'file' => isset($files[$path]) ? "data:{$mime};base64,{$files[$path]}" : null,
            ];
        }

        return $missing;
    }

    /**
     * $iconActions[id de origen] = qué hacer con ese ícono (lo manda el front):
     *   ['action' => 'create']                     crearlo con el archivo de la exportación
     *   ['action' => 'use', 'iconId' => N]         usar el ícono N de este entorno
     *   ['action' => 'upload', 'file' => SVG]      crearlo con un SVG subido ahora
     * Sin acción: se busca por nombre y, si no existe, se crea solo cuando
     * $createMissing es true (si no, queda sin ícono y se avisa).
     */
    private function importIcons(array $icons, array $files, array &$report, bool $createMissing = true, array $iconActions = []): array
    {
        $map = [];
        foreach ($icons as $oldId => $icon) {
            $action = $iconActions[$oldId] ?? $iconActions[(string) $oldId] ?? null;
            $tipo = $action['action'] ?? null;

            if ($tipo === 'use') {
                $chosen = PersonalizationIcon::find($action['iconId'] ?? 0);
                if ($chosen) {
                    $map[(int) $oldId] = $chosen->id;
                    $report['matched'][] = "Ícono \"" . ($icon['name'] ?? '') . "\" → \"{$chosen->name}\" (#{$chosen->id}, elegido)";
                    continue;
                }
            }

            if ($tipo === 'upload' && ($action['file'] ?? null) instanceof UploadedFile) {
                $newPath = $this->writeFile(
                    'icons/personalization/' . uniqid('icon_') . '.svg',
                    base64_encode(file_get_contents($action['file']->getRealPath()))
                );
                $created = PersonalizationIcon::create([
                    'name' => $this->nombreIconoLibre($action['name'] ?? $icon['name'] ?? 'Ícono importado'),
                    'icon' => $newPath,
                    'status_id' => 1,
                ]);
                $map[(int) $oldId] = $created->id;
                $report['created'][] = "Ícono \"{$created->name}\" (#{$created->id}, archivo subido)";
                continue;
            }

            $existing = PersonalizationIcon::where('name', $icon['name'] ?? '')->first();
            if ($existing) {
                $map[(int) $oldId] = $existing->id;
                $report['matched'][] = "Ícono \"{$existing->name}\" (#{$existing->id})";
                continue;
            }

            if (!$createMissing && $tipo !== 'create') {
                $report['missing'][] = "Ícono \"" . ($icon['name'] ?? "#{$oldId}") . "\" no existe y no se creó: los elementos que lo usaban quedan sin ícono";
                continue;
            }

            $path = $icon['path'] ?? '';
            $newPath = null;
            if (isset($files[$path])) {
                $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                $newPath = $this->writeFile('icons/personalization/' . uniqid('icon_') . ".{$ext}", $files[$path]);
            } else {
                $report['missing'][] = "Archivo del ícono \"" . ($icon['name'] ?? '') . "\" no incluido: {$path}";
            }
            $created = PersonalizationIcon::create(['name' => $icon['name'] ?? 'Ícono importado', 'icon' => $newPath, 'status_id' => 1]);
            $map[(int) $oldId] = $created->id;
            $report['created'][] = "Ícono \"{$created->name}\" (#{$created->id})";
        }

        return $map;
    }

    /**
     * personalization_icons.name es único desde el ABM de íconos: si el
     * nombre elegido para un SVG subido ya existe, se le agrega " (2)", " (3)"...
     */
    private function nombreIconoLibre(string $name): string
    {
        $candidato = $name;
        for ($i = 2; PersonalizationIcon::withTrashed()->where('name', $candidato)->exists(); $i++) {
            $candidato = "{$name} ({$i})";
        }

        return $candidato;
    }

    /**
     * Los atributos son de los productos: no se crean. Si no hay uno con el
     * mismo nombre, el elemento queda sin atributo (null) y se avisa.
     */
    private function importAttributes(array $attributes, array &$report): array
    {
        $map = [];
        foreach ($attributes as $oldId => $attribute) {
            $query = Attribute::where('name', $attribute['name'] ?? '');
            if (!empty($attribute['type'])) {
                $query->where('type', $attribute['type']);
            }
            $existing = $query->first();
            if ($existing) {
                $map[(int) $oldId] = $existing->id;
                $report['matched'][] = "Atributo \"{$existing->name}\" (#{$existing->id})";
            } else {
                $report['missing'][] = "Atributo \"" . ($attribute['name'] ?? "#{$oldId}") . "\" no existe: los elementos que lo usaban quedan sin atributo";
            }
        }

        return $map;
    }

    /**
     * Cambia ids y rutas en todo `data`. Un id que no se pudo resolver queda
     * en null (no apunta a algo equivocado del destino).
     */
    private function remap(array $node, array $maps, array $pathMap): array
    {
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = $this->remap($value, $maps, $pathMap);
            } elseif (isset(self::ID_KEYS[$key]) && is_numeric($value)) {
                $node[$key] = $maps[self::ID_KEYS[$key]][(int) $value] ?? null;
            } elseif (in_array($key, self::FILE_KEYS, true) && is_string($value) && $value !== '') {
                $node[$key] = $pathMap[$value] ?? null;
            }
        }

        return $node;
    }

    /**
     * Escribe un archivo en public/. Solo rutas relativas simples con
     * extensión de imagen o fuente (nunca .php ni nada ejecutable). Si ya
     * existe con el mismo contenido se reusa; si existe distinto, se escribe
     * con otro nombre al lado. Devuelve la ruta final.
     */
    private function writeFile(string $path, string $base64): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!preg_match('#^[A-Za-z0-9_][A-Za-z0-9_./-]*$#', $path) || str_contains($path, '..') || !in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException("Ruta de archivo no permitida: {$path}");
        }

        $content = base64_decode($base64, true);
        if ($content === false) {
            throw new \InvalidArgumentException("Archivo mal codificado: {$path}");
        }

        $disk = Storage::disk('public_uploads');
        if ($disk->exists($path)) {
            if ($disk->get($path) === $content) {
                return $path;
            }
            $path = dirname($path) . '/' . uniqid('imp_') . '_' . basename($path);
        }

        $disk->put($path, $content);
        $this->writtenFiles[] = $path;

        return $path;
    }
}

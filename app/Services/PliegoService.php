<?php

namespace App\Services;

use App\Models\Pliego;
use App\Models\ProductPdfDesign;
use App\Models\PliegoSlot;
use App\Models\Sale;
use App\Models\SaleProduct;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Lógica del módulo de pliegos (ver PLIEGOS.md):
 *  - qué hojas (páginas de los PDFs ya generados de cada venta) hay
 *    disponibles para armar un pliego,
 *  - validar los lugares que manda el front,
 *  - generar el PDF final del pliego.
 *
 * Una "hoja" es la página N de un archivo
 * storage/app/pdf/planchas/{d-m-Y}/{venta}-{saleProduct}-....pdf — los mismos
 * que lista el módulo de directorios. Cintas, bandas, sellos y diseños
 * acumulables van a PDFs consolidados por día (no por venta), así que no
 * aparecen como hojas de la venta.
 */
class PliegoService
{
    /**
     * Margen lateral (mm, por lado) que se recorta de cada hoja al ubicarla
     * en el pliego: el contenido de un A4 (210 mm) ocupa ~195 mm de ancho
     * (criterio del diseño del módulo). Así un lugar de 200 mm de ancho
     * (pliego de 1 m en 5 columnas) lleva el A4 a tamaño real.
     */
    public const SIDE_TRIM_MM = 7.5;

    /** Estados de venta que aparecen por defecto: Aprobado, En producción. */
    public const DEFAULT_SALE_STATUSES = [1, 2];

    public const SALE_STATUS_EN_PRODUCCION = 2;

    private const TZ = 'America/Argentina/Buenos_Aires';

    // ------------------------------------------------------------------
    // Hojas de una venta (lectura de disco)
    // ------------------------------------------------------------------

    public static function planchasPath(string $folder, ?string $file = null): string
    {
        return storage_path("app/pdf/planchas/{$folder}" . ($file !== null ? "/{$file}" : ''));
    }

    /**
     * Carpetas donde pueden estar los PDFs de una venta: la de la fecha de
     * aprobación (generarPdfSale) y la de creación (generateBulkPdfs).
     */
    private static function candidateFolders(Sale $sale): array
    {
        $folders = [Carbon::parse($sale->created_at)->setTimezone(self::TZ)->format('d-m-Y')];
        $aprobacion = $sale->statusHistory->where('sale_status_id', 1)->sortBy('date')->first();
        if ($aprobacion) {
            $folders[] = Carbon::parse($aprobacion->date)->setTimezone(self::TZ)->format('d-m-Y');
        }
        return array_values(array_unique($folders));
    }

    /** Archivos de hoja válidos (excluye PDFs de líneas de corte y temporales). */
    private static function isSheetFile(string $file): bool
    {
        return str_ends_with(strtolower($file), '.pdf')
            && !preg_match('/\.tmp\d*\.pdf$/i', $file)
            && !str_contains($file, '-CORTE-');
    }

    /**
     * Cantidad de páginas y tamaño (mm) de cada página de un PDF. Cacheado
     * por ruta + fecha de modificación + tamaño, así un PDF regenerado se
     * vuelve a leer.
     *
     * @return array<int, array{w: float, h: float}>|null  índice 1-based, null si no se puede leer
     */
    public static function pdfPages(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }

        $cacheKey = 'pliego-pdf-pages:' . md5($path . '|' . filemtime($path) . '|' . filesize($path));

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($path) {
            try {
                $fpdi = new PliegoPdf();
                $count = $fpdi->setSourceFile($path);
                $pages = [];
                for ($i = 1; $i <= $count; $i++) {
                    $size = $fpdi->getTemplateSize($fpdi->importPage($i));
                    $pages[$i] = ['w' => round((float) $size['width'], 2), 'h' => round((float) $size['height'], 2)];
                }
                return $pages;
            } catch (\Throwable $e) {
                Log::warning('Pliegos: no se pudo leer el PDF', ['path' => $path, 'error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Archivos de hoja de UNA carpeta de planchas, agrupados
     * [saleId => [saleProductId => [archivo, ...]]]. Cacheado por la fecha
     * de modificación del directorio (cambia al agregar/borrar archivos), así
     * que listar cientos de carpetas cuesta un stat + una lectura de caché
     * por carpeta.
     */
    private static function folderIndex(string $folder): array
    {
        $dir = self::planchasPath($folder);
        if (!is_dir($dir)) {
            return [];
        }
        clearstatcache(true, $dir);
        return Cache::remember('pliego-planchas-dir:' . md5($dir . '|' . filemtime($dir)), now()->addDays(7), function () use ($dir) {
            $out = [];
            foreach (scandir($dir) ?: [] as $file) {
                if (preg_match('/^(\d+)-(\d+)-/', $file, $m) && self::isSheetFile($file)) {
                    $out[(int) $m[1]][(int) $m[2]][] = $file;
                }
            }
            return $out;
        });
    }

    /**
     * Ids de las ventas que tienen al menos un PDF de hoja en alguna carpeta
     * de planchas: con esto el listado del panel filtra en SQL las ventas
     * sin PDF, en vez de revisar el disco venta por venta.
     */
    public static function saleIdsWithSheets(): array
    {
        $ids = [];
        foreach (glob(storage_path('app/pdf/planchas') . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $folder = basename($dir);
            if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $folder)) {
                continue;
            }
            foreach (array_keys(self::folderIndex($folder)) as $saleId) {
                $ids[$saleId] = true;
            }
        }
        return array_keys($ids);
    }

    /**
     * Hojas de un producto de una venta, en orden (archivo natural, página).
     *
     * @return array<int, array{key: string, folder: string, file: string, page: int, width_mm: float, height_mm: float}>
     */
    public static function sheetsForSaleProduct(Sale $sale, SaleProduct $saleProduct): array
    {
        $sheets = [];
        foreach (self::candidateFolders($sale) as $folder) {
            $names = self::folderIndex($folder)[(int) $sale->id][(int) $saleProduct->id] ?? [];
            natsort($names);

            foreach ($names as $file) {
                $pages = self::pdfPages(self::planchasPath($folder, $file)) ?? [];
                foreach ($pages as $n => $size) {
                    $sheets[] = [
                        'key' => "{$folder}/{$file}#{$n}",
                        'folder' => $folder,
                        'file' => $file,
                        'page' => $n,
                        'width_mm' => $size['w'],
                        'height_mm' => $size['h'],
                    ];
                }
            }
        }

        $total = count($sheets);
        foreach ($sheets as $i => &$sheet) {
            $sheet['index'] = $i + 1;   // "H1", "H2"... dentro del producto
            $sheet['total'] = $total;
        }
        unset($sheet);

        return $sheets;
    }

    /** URL relativa (API) para ver el PDF de una hoja (lo renderiza el front con pdf.js). */
    public static function viewUrl(string $folder, string $file): string
    {
        return '/api/pdf-directories/' . rawurlencode($folder) . '/view/' . rawurlencode($file);
    }

    /**
     * Hojas ya ubicadas en pliegos (no eliminados), indexadas por clave.
     *
     * @return array<string, array{pliego_id: int, pliego_name: string, position: int}>
     */
    public static function assignedSheets(?array $folders = null): array
    {
        $rows = PliegoSlot::query()
            ->join('pliegos', 'pliegos.id', '=', 'pliego_slots.pliego_id')
            ->whereNull('pliegos.deleted_at')
            ->when($folders !== null, fn($q) => $q->whereIn('pliego_slots.pdf_folder', $folders))
            ->get(['pliego_slots.*', 'pliegos.name as pliego_name']);

        $map = [];
        foreach ($rows as $row) {
            $map[$row->sheetKey()] = [
                'pliego_id' => (int) $row->pliego_id,
                'pliego_name' => $row->pliego_name,
                'position' => (int) $row->position,
            ];
        }
        return $map;
    }

    // ------------------------------------------------------------------
    // Listado de ventas con sus hojas (panel lateral del armado)
    // ------------------------------------------------------------------

    /**
     * Ventas con sus productos y hojas, paginado.
     *
     * Las hojas salen de disco (glob + lectura de cada PDF), así que no se
     * pueden filtrar en SQL "ventas con PDF" ni "todas sus hojas en otro
     * pliego": se recorren las ventas que matchean el SQL de a bloques, en
     * orden, y se corta apenas se completa la página (+1 para saber si hay
     * más). Por eso no hay total ni última página: hay `has_more`.
     *
     * @param array{pliego_id?: ?int, search?: ?string, hide_assigned?: bool, hide_empty?: bool, status_ids?: ?array, from_date?: ?string, to_date?: ?string, user_id?: ?int, order?: string} $filters
     *        status_ids null = todos los estados.
     *        from_date/to_date: fecha de aprobación (o de creación si nunca se
     *        aprobó) — la misma fecha de la carpeta de directorios.
     *        hide_empty (default true): oculta ventas sin ninguna hoja.
     *        order: 'desc' (default, más nuevas primero) | 'asc'.
     * @return array{sales: Collection, available_count: int, has_more: bool}
     */
    public static function availableSales(array $filters, int $page = 1, int $perPage = 20): array
    {
        $pliegoId = $filters['pliego_id'] ?? null;
        $search = trim((string) ($filters['search'] ?? ''));
        $statusIds = array_key_exists('status_ids', $filters) ? $filters['status_ids'] : self::DEFAULT_SALE_STATUSES;
        $from = !empty($filters['from_date']) ? Carbon::parse($filters['from_date'], self::TZ)->startOfDay()->setTimezone('UTC') : null;
        $to = !empty($filters['to_date']) ? Carbon::parse($filters['to_date'], self::TZ)->endOfDay()->setTimezone('UTC') : null;
        $hideAssigned = $filters['hide_assigned'] ?? true;
        $hideEmpty = $filters['hide_empty'] ?? true;
        $dir = ($filters['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query = Sale::query()
            ->with(['client:id,name,lastName', 'status', 'statusHistory', 'products.product:id,name', 'products.variant'])
            // Sin esto, con pocas ventas con PDF se recorrería casi toda la
            // tabla revisando el disco venta por venta.
            ->when($hideEmpty, fn($q) => $q->whereIn('id', self::saleIdsWithSheets() ?: [0]))
            ->when($statusIds !== null, fn($q) => $q->whereIn('sale_status_id', $statusIds))
            ->when($filters['user_id'] ?? null, fn($q, $userId) => $q->where('user_id', $userId))
            ->when($from || $to, function ($q) use ($from, $to) {
                $q->where(function ($q) use ($from, $to) {
                    $q->whereHas('statusHistory', function ($h) use ($from, $to) {
                        $h->where('sale_status_id', 1)
                          ->when($from, fn($h) => $h->where('date', '>=', $from))
                          ->when($to, fn($h) => $h->where('date', '<=', $to));
                    })->orWhere(function ($q) use ($from, $to) {
                        $q->whereDoesntHave('statusHistory', fn($h) => $h->where('sale_status_id', 1))
                          ->when($from, fn($q) => $q->where('created_at', '>=', $from))
                          ->when($to, fn($q) => $q->where('created_at', '<=', $to));
                    });
                });
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    if (ctype_digit($search)) {
                        $q->orWhere('id', (int) $search);
                    }
                    $q->orWhereHas('client', fn($c) => $c->where('name', 'like', "%{$search}%")
                        ->orWhere('lastName', 'like', "%{$search}%")
                        ->orWhereRaw("CONCAT(name, ' ', lastName) LIKE ?", ["%{$search}%"]))
                      ->orWhereHas('products.product', fn($p) => $p->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('created_at', $dir)
            ->orderBy('id', $dir);

        $assigned = self::assignedSheets();
        $skip = max(0, $page - 1) * $perPage;
        $matched = 0;
        $result = [];
        $hasMore = false;

        $query->chunk(50, function ($chunk) use (&$matched, &$result, &$hasMore, $skip, $perPage, $assigned, $pliegoId, $hideAssigned, $hideEmpty) {
            foreach ($chunk as $sale) {
                $entry = self::saleEntry($sale, $assigned, $pliegoId);
                if ($hideEmpty && $entry['sheets_count'] === 0) {
                    continue;
                }
                if ($hideAssigned && $entry['all_assigned_elsewhere']) {
                    continue;
                }
                $matched++;
                if ($matched <= $skip) {
                    continue;
                }
                if (count($result) >= $perPage) {
                    $hasMore = true;
                    return false; // corta el chunk
                }
                $result[] = $entry;
            }
        });

        $sales = collect($result);
        return [
            'sales' => $sales,
            'available_count' => $sales->sum('free_count'),
            'has_more' => $hasMore,
        ];
    }

    /** Una venta con sus productos y hojas, cada hoja con su estado (free/here/assigned). */
    private static function saleEntry(Sale $sale, array $assigned, ?int $pliegoId): array
    {
        $products = $sale->products->map(function (SaleProduct $sp) use ($sale, $assigned, $pliegoId) {
            $sheets = array_map(function ($sheet) use ($assigned, $pliegoId) {
                $a = $assigned[$sheet['key']] ?? null;
                if ($a && $pliegoId && $a['pliego_id'] === (int) $pliegoId) {
                    $sheet['state'] = 'here';
                    $sheet['position'] = $a['position'];
                } elseif ($a) {
                    $sheet['state'] = 'assigned';
                    $sheet['pliego'] = ['id' => $a['pliego_id'], 'name' => $a['pliego_name']];
                } else {
                    $sheet['state'] = 'free';
                }
                $sheet['view_url'] = self::viewUrl($sheet['folder'], $sheet['file']);
                return $sheet;
            }, self::sheetsForSaleProduct($sale, $sp));

            return [
                'sale_product_id' => $sp->id,
                'product_id' => $sp->product_id,
                'name' => $sp->product->name ?? 'Producto',
                'quantity' => $sp->quantity,
                'sheets' => $sheets,
            ];
        })->values();

        $allSheets = $products->flatMap(fn($p) => $p['sheets']);

        return [
            'id' => $sale->id,
            'client' => trim(($sale->client->name ?? '') . ' ' . ($sale->client->lastName ?? '')),
            'date' => Carbon::parse($sale->created_at)->setTimezone(self::TZ)->format('Y-m-d'),
            'sale_status_id' => $sale->sale_status_id,
            'sale_status' => $sale->status->name ?? null,
            'sheets_count' => $allSheets->count(),
            'free_count' => $allSheets->where('state', 'free')->count(),
            'all_assigned_elsewhere' => $allSheets->isNotEmpty() && $allSheets->every(fn($s) => $s['state'] === 'assigned'),
            'products' => $products,
        ];
    }

    // ------------------------------------------------------------------
    // Validación de lugares
    // ------------------------------------------------------------------

    /**
     * Valida los lugares que manda el front para un pliego y los devuelve
     * listos para insertar. Reglas: posición dentro de la grilla, sin
     * repetir posición ni hoja, el archivo existe y la página también, y la
     * hoja no está en OTRO pliego.
     *
     * @param array<int, array{position: int, folder: string, file: string, page: int}> $slots
     * @return array{rows: array, errors: array}
     */
    public static function resolveSlots(array $slots, int $totalSlots, ?int $pliegoId): array
    {
        $rows = [];
        $errors = [];
        $positions = [];
        $keys = [];

        $folders = array_values(array_unique(array_map(fn($s) => (string) ($s['folder'] ?? ''), $slots)));
        $assigned = self::assignedSheets($folders);

        foreach ($slots as $i => $slot) {
            $prefix = "slots.{$i}";
            $position = (int) $slot['position'];
            $folder = (string) $slot['folder'];
            $file = (string) $slot['file'];
            $page = (int) $slot['page'];
            $key = "{$folder}/{$file}#{$page}";

            if ($position < 0 || $position >= $totalSlots) {
                $errors[$prefix][] = "El lugar " . ($position + 1) . " no existe en una grilla de {$totalSlots} lugares.";
                continue;
            }
            if (isset($positions[$position])) {
                $errors[$prefix][] = "El lugar " . ($position + 1) . " está repetido.";
                continue;
            }
            if (isset($keys[$key])) {
                $errors[$prefix][] = "La hoja {$file} (pág. {$page}) está repetida en el pliego.";
                continue;
            }
            if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $folder) || basename($file) !== $file || !self::isSheetFile($file)
                || !preg_match('/^(\d+)-(\d+)-/', $file, $m)) {
                $errors[$prefix][] = "Hoja inválida: {$folder}/{$file}.";
                continue;
            }
            $pages = self::pdfPages(self::planchasPath($folder, $file));
            if ($pages === null) {
                $errors[$prefix][] = "No se encontró el PDF {$folder}/{$file} (¿se regeneró o se eliminó la venta?).";
                continue;
            }
            if (!isset($pages[$page])) {
                $errors[$prefix][] = "El PDF {$file} no tiene página {$page}.";
                continue;
            }
            $a = $assigned[$key] ?? null;
            if ($a && $a['pliego_id'] !== $pliegoId) {
                $errors[$prefix][] = "La hoja {$file} (pág. {$page}) ya está en {$a['pliego_name']}.";
                continue;
            }

            $positions[$position] = true;
            $keys[$key] = true;
            $rows[] = [
                'position' => $position,
                'sale_id' => (int) $m[1],
                'sale_product_id' => (int) $m[2],
                'pdf_folder' => $folder,
                'pdf_file' => $file,
                'page_number' => $page,
            ];
        }

        // El nombre del archivo trae la venta y la línea: confirmar que la línea es de esa venta.
        if ($rows) {
            $pairs = SaleProduct::whereIn('id', array_unique(array_column($rows, 'sale_product_id')))->pluck('sale_id', 'id');
            foreach ($rows as $idx => $row) {
                if ((int) ($pairs[$row['sale_product_id']] ?? 0) !== $row['sale_id']) {
                    $errors["slots"][] = "La hoja {$row['pdf_file']} no corresponde a ninguna venta existente.";
                    unset($rows[$idx]);
                }
            }
        }

        return ['rows' => array_values($rows), 'errors' => $errors];
    }

    // ------------------------------------------------------------------
    // Detalle
    // ------------------------------------------------------------------

    /** Escala a la que entra una hoja en un lugar: nunca agranda. */
    public static function fitScale(float $pageW, float $pageH, float $slotW, float $slotH): float
    {
        $contentW = $pageW > 2 * self::SIDE_TRIM_MM + 1 ? $pageW - 2 * self::SIDE_TRIM_MM : $pageW;
        return min(1, $slotW / $contentW, $slotH / $pageH);
    }

    /** Pliego con sus lugares enriquecidos (venta, cliente, producto, hoja, escala). */
    public static function detail(Pliego $pliego): array
    {
        $pliego->loadMissing(['slots.sale.client:id,name,lastName', 'slots.sale.status', 'slots.saleProduct.product:id,name', 'sheetSize', 'creator:id,name', 'finalizer:id,name']);
        $geom = $pliego->slotGeometry();
        $minScale = 1.0;

        $slots = $pliego->slots->map(function (PliegoSlot $slot) use ($geom, &$minScale) {
            $pages = self::pdfPages(self::planchasPath($slot->pdf_folder, $slot->pdf_file));
            $size = $pages[$slot->page_number] ?? null;
            $scale = null;
            if ($size) {
                $scale = round(self::fitScale($size['w'], $size['h'], $geom['slot_w'], $geom['slot_h']), 4);
                $minScale = min($minScale, $scale);
            }
            $sale = $slot->sale;

            return [
                'id' => $slot->id,
                'position' => $slot->position,
                'key' => $slot->sheetKey(),
                'folder' => $slot->pdf_folder,
                'file' => $slot->pdf_file,
                'page' => $slot->page_number,
                'page_count' => $pages ? count($pages) : null,
                'width_mm' => $size['w'] ?? null,
                'height_mm' => $size['h'] ?? null,
                'scale' => $scale,
                'missing' => $size === null,
                'view_url' => self::viewUrl($slot->pdf_folder, $slot->pdf_file),
                'sale_id' => $slot->sale_id,
                'sale_product_id' => $slot->sale_product_id,
                'client' => $sale ? trim(($sale->client->name ?? '') . ' ' . ($sale->client->lastName ?? '')) : null,
                'sale_status_id' => $sale->sale_status_id ?? null,
                'sale_status' => $sale->status->name ?? null,
                'product' => $slot->saleProduct->product->name ?? null,
            ];
        })->values();

        $data = $pliego->toArray();
        unset($data['slots']);

        return array_merge($data, [
            'total_slots' => $pliego->totalSlots(),
            'filled_slots' => $slots->count(),
            'slot_width_mm' => round($geom['slot_w'], 2),
            'slot_height_mm' => round($geom['slot_h'], 2),
            'min_scale' => round($minScale, 4),
            'missing_count' => $slots->where('missing', true)->count(),
            'slots' => $slots,
        ]);
    }

    /**
     * Ventas con al menos una hoja en el pliego (modal de finalizar).
     */
    public static function involvedSales(Pliego $pliego): Collection
    {
        $pliego->loadMissing('slots');
        $countBySale = $pliego->slots->countBy('sale_id');
        if ($countBySale->isEmpty()) {
            return collect();
        }

        $sales = Sale::with(['client:id,name,lastName', 'status', 'statusHistory', 'products'])
            ->whereIn('id', $countBySale->keys())
            ->orderBy('id')
            ->get();

        return $sales->map(function (Sale $sale) use ($countBySale) {
            $totalSheets = $sale->products->sum(fn($sp) => count(self::sheetsForSaleProduct($sale, $sp)));
            return [
                'id' => $sale->id,
                'client' => trim(($sale->client->name ?? '') . ' ' . ($sale->client->lastName ?? '')),
                'products_count' => $sale->products->count(),
                'sheets_in_pliego' => (int) $countBySale[$sale->id],
                'sheets_total' => $totalSheets,
                'sale_status_id' => $sale->sale_status_id,
                'sale_status' => $sale->status->name ?? null,
                'already_in_production' => (int) $sale->sale_status_id === self::SALE_STATUS_EN_PRODUCCION,
            ];
        })->values();
    }

    // ------------------------------------------------------------------
    // PDF del pliego
    // ------------------------------------------------------------------

    public const MODE_ART = 'art';   // solo el arte (default)
    public const MODE_CUT = 'cut';   // solo las líneas de corte
    public const MODE_BOTH = 'both'; // arte + líneas de corte encima
    public const MODES = [self::MODE_ART, self::MODE_CUT, self::MODE_BOTH];

    /**
     * Dónde y a qué escala va una hoja de $pageW × $pageH mm dentro de un
     * lugar: centrada, achicada solo si no entra.
     *
     * @return array{x: float, y: float, scale: float, w: float, h: float}
     */
    public static function placement(array $box, float $pageW, float $pageH): array
    {
        $scale = self::fitScale($pageW, $pageH, $box['w'], $box['h']);
        $dw = $pageW * $scale;
        $dh = $pageH * $scale;
        return ['x' => $box['x'] + ($box['w'] - $dw) / 2, 'y' => $box['y'] + ($box['h'] - $dh) / 2, 'scale' => $scale, 'w' => $dw, 'h' => $dh];
    }

    /**
     * Genera el PDF del pliego: una página del tamaño del pliego.
     *  - art:  cada hoja centrada en su lugar, recortada a los bordes del
     *          lugar y achicada solo si no entra.
     *  - cut:  solo las líneas de corte (spot color CutContour), mismo
     *          tamaño, para superponer 1 a 1 con el de arte o mandar al plotter.
     *  - both: arte + líneas de corte encima.
     *
     * @return array{path: string, warnings: array}
     * @throws \RuntimeException si falta algún PDF de origen (no se manda a
     *                           imprenta un pliego incompleto sin avisar) o
     *                           si se piden líneas de corte y están deshabilitadas.
     */
    public static function generatePdf(Pliego $pliego, bool $guides = false, string $mode = self::MODE_ART): array
    {
        $pliego->loadMissing('slots');
        $geom = $pliego->slotGeometry();
        $w = $pliego->width_cm * 10;
        $h = $pliego->height_cm * 10;
        $withArt = $mode !== self::MODE_CUT;
        $withCut = $mode !== self::MODE_ART;
        $warnings = [];

        $missing = $pliego->slots->filter(fn($s) => !is_file(self::planchasPath($s->pdf_folder, $s->pdf_file)));
        if ($missing->isNotEmpty()) {
            throw new \RuntimeException('Faltan PDFs de origen: ' . $missing->map(fn($s) => "lugar " . ($s->position + 1) . " ({$s->pdf_file})")->implode(', '));
        }

        $cut = null;
        if ($withCut) {
            $cut = self::pliegoCutLines($pliego);
            if (!$cut['config']['enabled']) {
                throw new \RuntimeException('El pliego no tiene las líneas de corte habilitadas.');
            }
            $warnings = $cut['warnings'];
        }

        $pdf = new PliegoPdf($w > $h ? 'L' : 'P', 'mm', [min($w, $h), max($w, $h)]);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->AddPage($w > $h ? 'L' : 'P', [min($w, $h), max($w, $h)]);

        if ($withArt) {
            foreach ($pliego->slots as $slot) {
                $box = $geom['slots'][$slot->position] ?? null;
                if (!$box) {
                    continue; // quedó fuera de la grilla (no debería: se valida al guardar)
                }
                $pdf->setSourceFile(self::planchasPath($slot->pdf_folder, $slot->pdf_file));
                $tpl = $pdf->importPage($slot->page_number);
                $size = $pdf->getTemplateSize($tpl);
                $pl = self::placement($box, $size['width'], $size['height']);

                $pdf->startClip($box['x'], $box['y'], $box['w'], $box['h']);
                $pdf->useTemplate($tpl, $pl['x'], $pl['y'], $pl['w'], $pl['h']);
                $pdf->endClip();
            }

            if ($guides) {
                $pdf->SetDrawColor(232, 134, 142);
                $pdf->SetLineWidth(0.2);
                foreach ($geom['slots'] as $box) {
                    $pdf->Rect($box['x'], $box['y'], $box['w'], $box['h']);
                }
            }
        }

        if ($cut) {
            $color = CutLineGeometry::parsearColor($cut['config']['color']);
            $widthMm = $cut['config']['width_cm'] * 10;
            foreach ($cut['slots'] as $s) {
                $box = $geom['slots'][$s['position']];
                $pdf->startClip($box['x'], $box['y'], $box['w'], $box['h']);
                $pdf->cutPaths($s['paths'], $color, $widthMm);
                $pdf->endClip();
            }
            $pdf->cutPaths($cut['slot_paths'], $color, $widthMm);
            foreach (array_merge($cut['design_paths'], $cut['shape_paths']) as $group) {
                $pdf->cutPaths($group['paths'], $color, $widthMm);
            }
        }

        $dir = storage_path('app/pdf/pliegos/tmp');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . '/' . uniqid('pliego-' . $pliego->id . '-', true) . '.pdf';
        $pdf->Output('F', $path);

        return ['path' => $path, 'warnings' => $warnings];
    }

    // ------------------------------------------------------------------
    // Líneas de corte
    // ------------------------------------------------------------------

    /**
     * Configuración de líneas de corte del pliego (columna cut_lines), con
     * defaults. Mismo formato que page.cut_lines del editor de PDFs, más
     * las fuentes de líneas propias del pliego (ver PLIEGOS.md):
     *  - labels:       contorno de cada etiqueta de cada hoja, sacado del
     *                  diseño de su venta (default true)
     *  - slots:        contorno de cada lugar del pliego (default false)
     *  - slot_sources: [{position, design_id, page_id}] ese lugar toma las
     *                  líneas de OTRO diseño (cualquiera), aunque esté vacío
     *  - designs:      [{design_id, page_id, x_mm, y_mm, scale, rotation_deg}]
     *                  líneas de cualquier diseño ubicadas libremente
     *  - shapes:       [{type: rect|ellipse|line|path, ...}] líneas creadas
     *                  en el pliego
     */
    public static function cutConfig(Pliego $pliego): array
    {
        $c = is_array($pliego->cut_lines) ? $pliego->cut_lines : [];
        return [
            'enabled' => ($c['enabled'] ?? false) === true,
            'width_cm' => isset($c['width_cm']) && is_numeric($c['width_cm']) && $c['width_cm'] > 0
                ? (float) $c['width_cm'] : CutLineGeometry::DEFAULT_WIDTH_CM,
            'color' => is_array($c['color'] ?? null) ? $c['color'] : ['mode' => 'hex', 'value' => CutLineGeometry::DEFAULT_COLOR_HEX],
            'labels' => ($c['labels'] ?? true) !== false,
            'slots' => ($c['slots'] ?? false) === true,
            'slot_sources' => array_values(array_filter($c['slot_sources'] ?? [], 'is_array')),
            'designs' => array_values(array_filter($c['designs'] ?? [], 'is_array')),
            'shapes' => array_values(array_filter($c['shapes'] ?? [], 'is_array')),
        ];
    }

    /** Memo por request: líneas de una página de diseño (sin venta). */
    private static array $designCutCache = [];

    /**
     * Líneas de corte de una página de CUALQUIER diseño, sin venta detrás:
     * se resuelve igual que el preview del editor (?cut_lines=1), con
     * "NOMBRE APELLIDO" de ejemplo. En mm sobre la hoja del diseño.
     * Se toman aunque la página no tenga cut_lines.enabled (el usuario la
     * eligió a propósito); sí se respeta cut_line "hidden" de cada etiqueta.
     * page_id null = primera página.
     *
     * @return array{available: bool, design_id: int, design_name: ?string, page_id: ?string, width_mm: float, height_mm: float, paths: array, pages: array, reason: ?string}
     */
    public static function designPageCutLines(int $designId, ?string $pageId): array
    {
        $ck = $designId . '|' . ($pageId ?? '');
        if (isset(self::$designCutCache[$ck])) {
            return self::$designCutCache[$ck];
        }

        $fail = fn(string $reason, ?string $name = null, array $pages = []) => [
            'available' => false, 'design_id' => $designId, 'design_name' => $name, 'page_id' => $pageId,
            'width_mm' => 0, 'height_mm' => 0, 'paths' => [], 'pages' => $pages, 'reason' => $reason,
        ];

        $design = ProductPdfDesign::find($designId);
        if (!$design) {
            return self::$designCutCache[$ck] = $fail("No existe el diseño {$designId}.");
        }

        try {
            $pages = EtiquetaService::paginasDeDesign($design);
            $productOrder = (object) [
                'id' => 'pliego-' . $design->id,
                'product_id' => 222222,
                'product' => (object) ['name' => $design->name],
                'variant' => null,
            ];
            $resolved = EtiquetaService::paginasResueltasEnOrdenDeHojas(
                111111, $pages, "pliego-design:{$design->id}", $productOrder,
                'NOMBRE APELLIDO', 'NOMBRE', 'APELLIDO', null, null, now(), true
            );
        } catch (\Throwable $e) {
            Log::error('Pliegos: error resolviendo diseño para líneas de corte', ['design_id' => $designId, 'error' => $e->getMessage()]);
            return self::$designCutCache[$ck] = $fail('No se pudieron calcular las líneas de corte del diseño.', $design->name);
        }

        $pageList = array_map(fn($pg, $i) => [
            'id' => $pg['id'] ?? null,
            'index' => $i + 1,
            'width_mm' => round(((float) ($pg['sheet']['width_cm'] ?? 18.5)) * 10, 2),
            'height_mm' => round(((float) ($pg['sheet']['height_cm'] ?? 29)) * 10, 2),
        ], $resolved, array_keys($resolved));

        $page = null;
        foreach ($resolved as $pg) {
            if ($pageId === null || ($pg['id'] ?? null) === $pageId) {
                $page = $pg;
                break;
            }
        }
        if (!$page) {
            return self::$designCutCache[$ck] = $fail("El diseño \"{$design->name}\" no tiene la página {$pageId}.", $design->name, $pageList);
        }

        return self::$designCutCache[$ck] = [
            'available' => true,
            'design_id' => $design->id,
            'design_name' => $design->name,
            'page_id' => $page['id'] ?? null,
            'width_mm' => round(((float) ($page['sheet']['width_cm'] ?? 18.5)) * 10, 2),
            'height_mm' => round(((float) ($page['sheet']['height_cm'] ?? 29)) * 10, 2),
            'paths' => array_map(fn($t) => self::transformPath($t, 0, 0, 10), CutLineGeometry::trazadosPagina($page)),
            'pages' => $pageList,
            'reason' => null,
        ];
    }

    /**
     * Trazado (mm del pliego) de una línea creada en el pliego. null si es
     * inválida.
     *  - rect:    x_mm, y_mm, w_mm, h_mm, radius_mm (0), rotation_deg (0)
     *  - ellipse: x_mm, y_mm, w_mm, h_mm (caja), rotation_deg (0)
     *  - line:    x1_mm, y1_mm, x2_mm, y2_mm (trazo abierto, ej. un corte recto)
     *  - path:    d = path SVG (M/L/H/V/Z) en mm del pliego
     */
    public static function shapePath(array $shape): ?array
    {
        $n = fn($k, $d = 0.0) => is_numeric($shape[$k] ?? null) ? (float) $shape[$k] : $d;
        $type = $shape['type'] ?? null;

        if ($type === 'line') {
            return [['M', $n('x1_mm'), $n('y1_mm')], ['L', $n('x2_mm'), $n('y2_mm')]];
        }
        if ($type === 'path') {
            return is_string($shape['d'] ?? null) ? CutLineGeometry::parsearPath($shape['d']) : null;
        }

        $x = $n('x_mm');
        $y = $n('y_mm');
        $w = $n('w_mm');
        $h = $n('h_mm');
        if ($w <= 0 || $h <= 0) {
            return null;
        }
        if ($type === 'rect') {
            $t = CutLineGeometry::rectRedondeado($x, $y, $w, $h, max(0, $n('radius_mm')));
        } elseif ($type === 'ellipse') {
            $t = CutLineGeometry::elipse($x + $w / 2, $y + $h / 2, $w / 2, $h / 2);
        } else {
            return null;
        }
        $rot = $n('rotation_deg');
        return $rot ? CutLineGeometry::rotar($t, $x + $w / 2, $y + $h / 2, $rot) : $t;
    }

    /** Memo por request: páginas resueltas + hojas de cada línea de venta. */
    private static array $cutCache = [];

    /**
     * Líneas de corte de UNA hoja, en mm sobre la hoja (origen arriba a la
     * izquierda), calculadas igual que el PDF de corte del editor: se
     * vuelven a resolver las páginas del diseño vinculado a la línea de
     * venta (mismo nombre, ícono, color y layout_groups) y se toma la que
     * corresponde a esa hoja.
     *
     * @return array{available: bool, enabled: bool, paths: array, design_enabled?: bool, reason: ?string}
     *         available false = no se pueden calcular (reason dice por qué);
     *         enabled false = no hay ninguna etiqueta con línea (todas ocultas);
     *         design_enabled = si la página tiene cut_lines.enabled en el
     *         editor (solo informativo, no condiciona).
     */
    public static function sheetCutLines(Sale $sale, SaleProduct $saleProduct, string $key): array
    {
        $ck = $saleProduct->id;
        if (!isset(self::$cutCache[$ck])) {
            try {
                $resolved = ProductPdfResolverService::resolvedPagesInSheetOrder($sale->id, $saleProduct, $sale->fechaAprobacionOCreacion());
                // Se guarda solo lo necesario (tamaño + trazos), no la página
                // resuelta entera: con muchas hojas, las páginas completas
                // ocupan cientos de MB.
                if (is_array($resolved)) {
                    $resolved = array_map(fn($page) => [
                        'sheet' => $page['sheet'] ?? [],
                        'design_enabled' => (bool) CutLineGeometry::configPagina($page),
                        'paths' => array_map(fn($t) => self::transformPath($t, 0, 0, 10), CutLineGeometry::trazadosPagina($page)),
                    ], $resolved);
                }
            } catch (\Throwable $e) {
                Log::error('Pliegos: error resolviendo páginas para líneas de corte', ['sale_product_id' => $ck, 'error' => $e->getMessage()]);
                $resolved = false;
            }
            self::$cutCache[$ck] = ['resolved' => $resolved, 'sheets' => self::sheetsForSaleProduct($sale, $saleProduct)];
        }
        ['resolved' => $resolved, 'sheets' => $sheets] = self::$cutCache[$ck];

        $fail = fn(string $reason) => ['available' => false, 'enabled' => false, 'paths' => [], 'reason' => $reason];

        if ($resolved === null) {
            return $fail('El producto no tiene un diseño del editor vinculado (PDF legacy): no hay líneas de corte.');
        }
        if ($resolved === false) {
            return $fail('No se pudieron calcular las líneas de corte del diseño.');
        }
        $idx = array_search($key, array_column($sheets, 'key'), true);
        if ($idx === false) {
            return $fail('La hoja ya no está entre los PDFs generados de la venta.');
        }
        $page = $resolved[$idx] ?? null;
        $sheet = $sheets[$idx];
        if (count($resolved) !== count($sheets) || !$page
            || abs(((float) ($page['sheet']['width_cm'] ?? 0)) * 10 - $sheet['width_mm']) > 1.5
            || abs(((float) ($page['sheet']['height_cm'] ?? 0)) * 10 - $sheet['height_mm']) > 1.5) {
            return $fail('El diseño vinculado no coincide con el PDF generado (¿cambió el diseño?). Regenerá el PDF de la venta.');
        }

        // A diferencia del PDF de corte del editor, NO se exige
        // page.cut_lines.enabled: en el pliego, si hay líneas lo decide el
        // pliego (cut_lines.enabled / labels), y el editor muestra el
        // contorno de las etiquetas aunque la página no tenga ese flag. Sí se
        // respeta cut_line "hidden" de cada etiqueta (trazadosPagina).
        if (!$page['paths']) {
            return ['available' => true, 'enabled' => false, 'paths' => [], 'design_enabled' => $page['design_enabled'],
                'reason' => 'La página del diseño no tiene etiquetas con línea de corte (o todas están ocultas).'];
        }
        return ['available' => true, 'enabled' => true, 'paths' => $page['paths'], 'design_enabled' => $page['design_enabled'], 'reason' => null];
    }

    /**
     * Líneas de corte del pliego entero, en mm del pliego (origen arriba a
     * la izquierda):
     *  - slots[]:       por lugar, recortadas al lugar (`clip`) en el PDF. La
     *                   fuente es slot_sources[position] si existe (otro
     *                   diseño, aunque el lugar esté vacío); si no, el
     *                   diseño de la venta de esa hoja (si `labels`).
     *  - slot_paths:    contorno de cada lugar (si `slots`).
     *  - design_paths:  diseños ubicados libremente (`designs`).
     *  - shape_paths:   líneas creadas en el pliego (`shapes`).
     *  - warnings:      lo que no se pudo dibujar y por qué.
     * En el JSON nada viene recortado: el front recorta slots[] con su clip.
     */
    public static function pliegoCutLines(Pliego $pliego): array
    {
        $pliego->loadMissing(['slots.sale', 'slots.saleProduct']);
        $config = self::cutConfig($pliego);
        $geom = $pliego->slotGeometry();
        $slots = [];
        $warnings = [];

        $sources = [];
        foreach ($config['slot_sources'] as $src) {
            $sources[(int) ($src['position'] ?? -1)] = $src;
        }
        $bySlot = $pliego->slots->keyBy('position');

        foreach ($geom['slots'] as $position => $box) {
            $src = $sources[$position] ?? null;
            $slot = $bySlot[$position] ?? null;

            if ($src) {
                $d = self::designPageCutLines((int) ($src['design_id'] ?? 0), isset($src['page_id']) ? (string) $src['page_id'] : null);
                if (!$d['available']) {
                    $warnings[] = ['position' => $position, 'source' => 'design', 'reason' => $d['reason']];
                    continue;
                }
                $pl = self::placement($box, $d['width_mm'], $d['height_mm']);
                $slots[] = [
                    'position' => $position,
                    'source' => ['type' => 'design', 'design_id' => $d['design_id'], 'design_name' => $d['design_name'], 'page_id' => $d['page_id']],
                    'clip' => $box,
                    'paths' => array_map(fn($t) => self::transformPath($t, $pl['x'], $pl['y'], $pl['scale']), $d['paths']),
                ];
                continue;
            }

            if (!$slot || !$config['labels']) {
                continue;
            }
            $size = (self::pdfPages(self::planchasPath($slot->pdf_folder, $slot->pdf_file)) ?? [])[$slot->page_number] ?? null;
            if (!$size || !$slot->sale || !$slot->saleProduct) {
                $warnings[] = ['position' => $position, 'source' => 'sale', 'reason' => 'No se encontró la hoja o la venta.'];
                continue;
            }
            $sheet = self::sheetCutLines($slot->sale, $slot->saleProduct, $slot->sheetKey());
            if (!$sheet['enabled']) {
                $warnings[] = ['position' => $position, 'source' => 'sale', 'reason' => $sheet['reason']];
                continue;
            }
            $pl = self::placement($box, $size['w'], $size['h']);
            $slots[] = [
                'position' => $position,
                'source' => ['type' => 'sale', 'sale_id' => $slot->sale_id, 'key' => $slot->sheetKey()],
                'clip' => $box,
                'paths' => array_map(fn($t) => self::transformPath($t, $pl['x'], $pl['y'], $pl['scale']), $sheet['paths']),
            ];
        }

        $slotPaths = [];
        if ($config['slots']) {
            foreach ($geom['slots'] as $b) {
                $slotPaths[] = CutLineGeometry::rectRedondeado($b['x'], $b['y'], $b['w'], $b['h'], 0);
            }
        }

        $designPaths = [];
        foreach ($config['designs'] as $i => $item) {
            $d = self::designPageCutLines((int) ($item['design_id'] ?? 0), isset($item['page_id']) ? (string) $item['page_id'] : null);
            if (!$d['available']) {
                $warnings[] = ['design_index' => $i, 'source' => 'design', 'reason' => $d['reason']];
                continue;
            }
            $x = (float) ($item['x_mm'] ?? 0);
            $y = (float) ($item['y_mm'] ?? 0);
            $k = is_numeric($item['scale'] ?? null) && $item['scale'] > 0 ? (float) $item['scale'] : 1.0;
            $rot = (float) ($item['rotation_deg'] ?? 0);
            $cx = $x + $d['width_mm'] * $k / 2;
            $cy = $y + $d['height_mm'] * $k / 2;
            $designPaths[] = [
                'index' => $i,
                'design_id' => $d['design_id'],
                'design_name' => $d['design_name'],
                'page_id' => $d['page_id'],
                'box' => ['x' => $x, 'y' => $y, 'w' => $d['width_mm'] * $k, 'h' => $d['height_mm'] * $k],
                'paths' => array_map(function ($t) use ($x, $y, $k, $rot, $cx, $cy) {
                    $t = self::transformPath($t, $x, $y, $k);
                    return $rot ? self::roundPath(CutLineGeometry::rotar($t, $cx, $cy, $rot)) : $t;
                }, $d['paths']),
            ];
        }

        $shapePaths = [];
        foreach ($config['shapes'] as $i => $shape) {
            $t = self::shapePath($shape);
            if (!$t) {
                $warnings[] = ['shape_index' => $i, 'source' => 'shape', 'reason' => 'Línea inválida.'];
                continue;
            }
            $shapePaths[] = ['index' => $i, 'paths' => [self::roundPath($t)]];
        }

        return [
            'config' => $config,
            'width_mm' => $pliego->width_cm * 10,
            'height_mm' => $pliego->height_cm * 10,
            'slots' => $slots,
            'slot_paths' => $slotPaths,
            'design_paths' => $designPaths,
            'shape_paths' => $shapePaths,
            'warnings' => $warnings,
        ];
    }

    private static function roundPath(array $trazado): array
    {
        return array_map(fn($seg) => array_map(fn($v) => is_float($v) ? round($v, 3) : $v, $seg), $trazado);
    }

    /** Segmentos: (x, y) → (ox + x·k, oy + y·k). */
    private static function transformPath(array $trazado, float $ox, float $oy, float $k): array
    {
        return array_map(function ($seg) use ($ox, $oy, $k) {
            $out = [$seg[0]];
            for ($i = 1; $i < count($seg); $i += 2) {
                $out[] = round($ox + $seg[$i] * $k, 3);
                $out[] = round($oy + $seg[$i + 1] * $k, 3);
            }
            return $out;
        }, $trazado);
    }

    /** Nombre de archivo seguro para descargar. */
    public static function downloadName(Pliego $pliego, string $mode = self::MODE_ART): string
    {
        $name = preg_replace('/[\\\\\/:*?"<>|]+/', '-', $pliego->name);
        $suffix = [self::MODE_CUT => ' - corte', self::MODE_BOTH => ' - arte y corte'][$mode] ?? '';
        return trim($name) . $suffix . '.pdf';
    }

    /** "Pliego 016": siguiente número libre (cuenta también los eliminados). */
    public static function nextName(): string
    {
        $max = Pliego::withTrashed()->pluck('name')
            ->map(fn($n) => preg_match('/^Pliego\s+(\d+)$/i', $n, $m) ? (int) $m[1] : 0)
            ->max() ?? 0;

        return 'Pliego ' . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }
}

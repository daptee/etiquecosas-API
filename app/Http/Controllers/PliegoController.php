<?php

namespace App\Http\Controllers;

use App\Mail\OrderProductionsMail;
use App\Models\Pliego;
use App\Models\PliegoSlot;
use App\Models\Sale;
use App\Models\SaleProduct;
use App\Models\SaleStatusHistory;
use App\Services\PliegoService;
use App\Traits\ApiResponse;
use App\Traits\Auditable;
use App\Traits\FindObject;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use ZipArchive;

/**
 * Pliegos: planchas de imprenta armadas con las hojas de los PDFs de las
 * ventas. Ver PLIEGOS.md.
 */
class PliegoController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request)
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $userId = $request->query('userId');
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);

        $query = Pliego::query()
            ->with(['sheetSize', 'slots', 'user:id,name,lastName'])
            ->withCount('slots')
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($userId, fn($q) => $q->where('created_by', $userId))
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $map = fn(Pliego $p) => $this->listItem($p);

        if (!$perPage) {
            return $this->success($query->get()->map($map)->values(), 'Pliegos obtenidos');
        }

        $pliegos = $query->paginate($perPage, ['*'], 'page', $page);
        return $this->success(collect($pliegos->items())->map($map)->values(), 'Pliegos obtenidos', [
            'current_page' => $pliegos->currentPage(),
            'last_page' => $pliegos->lastPage(),
            'per_page' => $pliegos->perPage(),
            'total' => $pliegos->total(),
            'from' => $pliegos->firstItem(),
            'to' => $pliegos->lastItem(),
        ]);
    }

    public function show($id)
    {
        $pliego = $this->findObject(Pliego::class, $id);
        return $this->success(PliegoService::detail($pliego), 'Pliego obtenido');
    }

    /** Panel lateral del armado: ventas con sus productos y hojas. */
    public function availableSheets(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pliegoId' => 'nullable|integer',
            'search' => 'nullable|string|max:255',
            'hideAssigned' => 'nullable|boolean',
            'allStatuses' => 'nullable|boolean',
            'statusIds' => 'nullable|array',
            'statusIds.*' => 'integer|exists:sale_status,id',
            'onlyMine' => 'nullable|boolean',
            'userId' => 'nullable|integer|exists:users,id',
            'fromDate' => 'nullable|date',
            'toDate' => 'nullable|date|after_or_equal:fromDate',
            'hideEmpty' => 'nullable|boolean',
            'order' => 'nullable|in:asc,desc',
            'quantity' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        // Estados: por defecto Aprobado + En producción; allStatuses=1 trae
        // todos; statusIds[] elige puntuales.
        if ($request->boolean('allStatuses')) {
            $statusIds = null;
        } else {
            $statusIds = $request->query('statusIds') ?: PliegoService::DEFAULT_SALE_STATUSES;
        }

        // Ventas: por defecto todas; onlyMine=1 = solo las asignadas al
        // usuario logueado; userId = las asignadas a ese usuario.
        $userId = $request->boolean('onlyMine') ? Auth::id() : ($request->query('userId') ? (int) $request->query('userId') : null);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = (int) $request->query('quantity', 20);

        $result = PliegoService::availableSales([
            'pliego_id' => $request->query('pliegoId') ? (int) $request->query('pliegoId') : null,
            'search' => $request->query('search'),
            'hide_assigned' => $request->has('hideAssigned') ? $request->boolean('hideAssigned') : true,
            'hide_empty' => $request->has('hideEmpty') ? $request->boolean('hideEmpty') : true,
            'status_ids' => $statusIds,
            'from_date' => $request->query('fromDate'),
            'to_date' => $request->query('toDate'),
            'user_id' => $userId,
            'order' => $request->query('order', 'desc'),
        ], $page, $perPage);

        $sales = $result['sales'];
        $meta = [
            'available_count' => $result['available_count'],
            'current_page' => $page,
            'per_page' => $perPage,
            'has_more' => $result['has_more'],
        ];

        return $this->success($sales, 'Hojas de ventas obtenidas', $meta);
    }

    public function store(Request $request)
    {
        $validator = $this->validator($request, true);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Pliego', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $columns = (int) $request->columns;
        $rows = (int) $request->rows;
        if ($cutErrors = $this->validateCutLines($request, $columns * $rows)) {
            $this->logAudit(Auth::user(), 'Store Pliego', $request->all(), $cutErrors);
            return $this->validationError($cutErrors);
        }
        $resolved = PliegoService::resolveSlots($request->input('slots', []), $columns * $rows, null);
        if ($resolved['errors']) {
            $this->logAudit(Auth::user(), 'Store Pliego', $request->all(), $resolved['errors']);
            return $this->validationError($resolved['errors']);
        }

        try {
            $pliego = DB::transaction(function () use ($request, $columns, $rows, $resolved) {
                $pliego = Pliego::create([
                    'name' => trim((string) $request->name) ?: PliegoService::nextName(),
                    'sheet_size_id' => $request->sheetSizeId,
                    'width_cm' => $request->widthCm,
                    'height_cm' => $request->heightCm,
                    'columns' => $columns,
                    'rows' => $rows,
                    'margin_mm' => $request->input('marginMm', 0),
                    'gap_mm' => $request->input('gapMm', 0),
                    'cut_lines' => $this->cutLinesInput($request),
                    'status' => Pliego::STATUS_BORRADOR,
                    'created_by' => Auth::id(),
                ]);
                $this->replaceSlots($pliego, $resolved['rows']);
                return $pliego;
            });
        } catch (\Illuminate\Database\QueryException $e) {
            return $this->sheetConflict($e, $request);
        }

        $detail = PliegoService::detail($pliego->fresh());
        $this->logAudit(Auth::user(), 'Store Pliego', $request->all(), ['id' => $pliego->id]);
        return $this->success($detail, 'Pliego creado');
    }

    /**
     * Editar: nombre, hoja, distribución y/o lugares. Todo opcional; si
     * viene `slots` reemplaza TODOS los lugares (los vacíos no se mandan).
     * Si se achica la grilla sin mandar `slots`, los lugares que quedan
     * fuera se compactan en orden y los que no entran se liberan (misma
     * regla que el paso 1 del front).
     */
    public function update(Request $request, $id)
    {
        $pliego = $this->findObject(Pliego::class, $id);

        $validator = $this->validator($request, false);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Pliego', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $columns = (int) $request->input('columns', $pliego->columns);
        $rows = (int) $request->input('rows', $pliego->rows);
        $total = $columns * $rows;

        if ($cutErrors = $this->validateCutLines($request, $total)) {
            $this->logAudit(Auth::user(), 'Update Pliego', $request->all(), $cutErrors);
            return $this->validationError($cutErrors);
        }

        $resolved = null;
        if ($request->has('slots')) {
            $resolved = PliegoService::resolveSlots($request->input('slots', []), $total, $pliego->id);
            if ($resolved['errors']) {
                $this->logAudit(Auth::user(), 'Update Pliego', $request->all(), $resolved['errors']);
                return $this->validationError($resolved['errors']);
            }
        }

        $dropped = 0;
        try {
            DB::transaction(function () use ($request, $pliego, $columns, $rows, $total, $resolved, &$dropped) {
                $pliego->update([
                    'name' => $request->filled('name') ? trim($request->name) : $pliego->name,
                    'sheet_size_id' => $request->has('sheetSizeId') ? $request->sheetSizeId : $pliego->sheet_size_id,
                    'width_cm' => $request->input('widthCm', $pliego->width_cm),
                    'height_cm' => $request->input('heightCm', $pliego->height_cm),
                    'columns' => $columns,
                    'rows' => $rows,
                    'margin_mm' => $request->input('marginMm', $pliego->margin_mm),
                    'gap_mm' => $request->input('gapMm', $pliego->gap_mm),
                    'cut_lines' => $request->has('cutLines') ? $this->cutLinesInput($request) : $pliego->cut_lines,
                ]);

                if ($resolved) {
                    $this->replaceSlots($pliego, $resolved['rows']);
                } elseif ($pliego->slots()->where('position', '>=', $total)->exists()) {
                    $slots = $pliego->slots()->get();
                    $keep = $slots->take($total);
                    $dropped = $slots->count() - $keep->count();
                    $this->replaceSlots($pliego, $keep->values()->map(fn($s, $i) => [
                        'position' => $i,
                        'sale_id' => $s->sale_id,
                        'sale_product_id' => $s->sale_product_id,
                        'pdf_folder' => $s->pdf_folder,
                        'pdf_file' => $s->pdf_file,
                        'page_number' => $s->page_number,
                    ])->all());
                }
            });
        } catch (\Illuminate\Database\QueryException $e) {
            return $this->sheetConflict($e, $request);
        }

        $detail = PliegoService::detail($pliego->fresh());
        $this->logAudit(Auth::user(), 'Update Pliego', $request->all(), ['id' => $pliego->id, 'dropped' => $dropped]);
        return $this->success($detail, 'Pliego actualizado', ['dropped_sheets' => $dropped]);
    }

    /** Datos del modal "Finalizar": ventas con al menos una hoja en el pliego. */
    public function involvedSales($id)
    {
        $pliego = $this->findObject(Pliego::class, $id);
        return $this->success(PliegoService::involvedSales($pliego), 'Ventas del pliego obtenidas');
    }

    /**
     * Finaliza el pliego y pasa a "En producción" las ventas indicadas en
     * `saleIds` (deben tener al menos una hoja en el pliego). `saleIds: []`
     * = "Finalizar sin cambiar estados". Las ventas que ya están en
     * producción se ignoran (no se reenvía el mail).
     */
    public function finalize(Request $request, $id)
    {
        $pliego = $this->findObject(Pliego::class, $id);

        $validator = Validator::make($request->all(), [
            'saleIds' => 'present|array',
            'saleIds.*' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $involvedIds = $pliego->slots()->pluck('sale_id')->map(fn($v) => (int) $v)->unique()->values()->all();
        $saleIds = array_values(array_unique(array_map('intval', $request->input('saleIds', []))));
        $outside = array_diff($saleIds, $involvedIds);
        if ($outside) {
            return $this->validationError(['saleIds' => ['Estas ventas no tienen hojas en el pliego: ' . implode(', ', $outside)]]);
        }

        $changed = DB::transaction(function () use ($pliego, $saleIds) {
            $pliego->update([
                'status' => Pliego::STATUS_FINALIZADO,
                'finalized_at' => Carbon::now(),
                'finalized_by' => Auth::id(),
            ]);

            $sales = Sale::whereIn('id', $saleIds)
                ->where('sale_status_id', '!=', PliegoService::SALE_STATUS_EN_PRODUCCION)
                ->lockForUpdate()
                ->get();

            foreach ($sales as $sale) {
                $sale->sale_status_id = PliegoService::SALE_STATUS_EN_PRODUCCION;
                $sale->save();
                SaleStatusHistory::create([
                    'sale_id' => $sale->id,
                    'sale_status_id' => PliegoService::SALE_STATUS_EN_PRODUCCION,
                    'date' => Carbon::now(),
                ]);
            }
            return $sales;
        });

        // Mismo mail que al pasar una venta a "En producción" desde ventas
        // (fuera de la transacción: un mail fallido no deshace el cambio).
        foreach ($changed as $sale) {
            try {
                $sale->load(['client', 'products.product', 'products.variant', 'shippingMethod', 'locality']);
                if ($sale->client && $sale->client->email) {
                    Mail::to($sale->client->email)->send(new OrderProductionsMail($sale));
                }
            } catch (\Throwable $e) {
                Log::error('Pliegos: error enviando mail de producción', ['sale_id' => $sale->id, 'error' => $e->getMessage()]);
            }
        }

        $this->logAudit(Auth::user(), 'Finalize Pliego', $request->all(), ['id' => $pliego->id, 'sales_to_production' => $changed->pluck('id')]);
        return $this->success(PliegoService::detail($pliego->fresh()), 'Pliego finalizado', [
            'sales_to_production' => $changed->pluck('id')->values(),
        ]);
    }

    /** "Volver a borrador". No revierte el estado de las ventas. */
    public function reopen($id)
    {
        $pliego = $this->findObject(Pliego::class, $id);
        $pliego->update(['status' => Pliego::STATUS_BORRADOR, 'finalized_at' => null, 'finalized_by' => null]);
        $this->logAudit(Auth::user(), 'Reopen Pliego', $id, ['id' => $pliego->id]);
        return $this->success(PliegoService::detail($pliego->fresh()), 'Pliego vuelto a borrador');
    }

    /** Elimina el pliego y libera sus hojas. */
    public function delete($id)
    {
        $pliego = $this->findObject(Pliego::class, $id);
        DB::transaction(function () use ($pliego) {
            $pliego->slots()->delete();
            $pliego->delete();
        });
        $this->logAudit(Auth::user(), 'Delete Pliego', $id, ['id' => $pliego->id, 'name' => $pliego->name]);
        return $this->success(['id' => $pliego->id], 'Pliego eliminado');
    }

    /**
     * PDF del pliego.
     *  - `?mode=art` (default) arte · `cut` solo líneas de corte · `both` arte + líneas.
     *  - `?guides=1` dibuja las separaciones entre lugares (solo arte).
     * Las hojas sin líneas de corte (y por qué) van en el header
     * `X-Pliego-Warnings` (JSON).
     */
    public function download(Request $request, $id)
    {
        $pliego = $this->findObject(Pliego::class, $id);
        $mode = $request->query('mode', PliegoService::MODE_ART);
        if (!in_array($mode, PliegoService::MODES, true)) {
            return $this->validationError(['mode' => ['Valores válidos: ' . implode(', ', PliegoService::MODES)]]);
        }

        try {
            $result = PliegoService::generatePdf($pliego, $request->boolean('guides'), $mode);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('Pliegos: error generando PDF', ['id' => $pliego->id, 'error' => $e->getMessage()]);
            return $this->error('Error al generar el PDF del pliego', 500);
        }

        $this->logAudit(Auth::user(), 'Download Pliego', ['id' => $id, 'mode' => $mode], ['id' => $pliego->id]);
        return response()->download($result['path'], PliegoService::downloadName($pliego, $mode), [
            'Content-Type' => 'application/pdf',
            'X-Pliego-Warnings' => json_encode($result['warnings']),
        ])->deleteFileAfterSend(true);
    }

    /**
     * Líneas de corte del pliego en JSON (mm del pliego), para dibujarlas
     * encima del armado ("Líneas de corte" del front).
     */
    public function cutLines($id)
    {
        $pliego = $this->findObject(Pliego::class, $id);
        return $this->success(PliegoService::pliegoCutLines($pliego), 'Líneas de corte obtenidas');
    }

    /**
     * Líneas de corte de una página de CUALQUIER diseño (mm sobre la hoja
     * del diseño), para elegir/ubicar en el pliego: `?designId=&pageId=`
     * (pageId opcional = primera página). Trae `pages` para elegir página.
     */
    public function designCutLines(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'designId' => 'required|integer',
            'pageId' => 'nullable|string|max:100',
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $result = PliegoService::designPageCutLines((int) $request->query('designId'), $request->query('pageId'));
        if (!$result['available']) {
            return $this->error($result['reason'], 422, $result);
        }
        return $this->success($result, 'Líneas de corte del diseño obtenidas');
    }

    /**
     * Líneas de corte de UNA hoja (mm sobre la hoja), para el armado antes
     * de guardar: `?folder=&file=&page=`.
     */
    public function sheetCutLines(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'folder' => 'required|string|max:10',
            'file' => 'required|string|max:500',
            'page' => 'required|integer|min:1',
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $file = (string) $request->query('file');
        if (basename($file) !== $file || !preg_match('/^(\d+)-(\d+)-/', $file, $m)) {
            return $this->validationError(['file' => ['Hoja inválida.']]);
        }
        $saleProduct = SaleProduct::where('id', (int) $m[2])->where('sale_id', (int) $m[1])->first();
        if (!$saleProduct) {
            return $this->notFound('No se encontró la venta de esa hoja');
        }
        $sale = Sale::find($saleProduct->sale_id);
        $folder = (string) $request->query('folder');
        if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $folder)) {
            return $this->validationError(['folder' => ['Carpeta inválida.']]);
        }
        $page = (int) $request->query('page');
        $size = (PliegoService::pdfPages(PliegoService::planchasPath($folder, $file)) ?? [])[$page] ?? null;
        if (!$size) {
            return $this->notFound('No se encontró la hoja');
        }

        $result = PliegoService::sheetCutLines($sale, $saleProduct, "{$folder}/{$file}#{$page}");
        return $this->success(array_merge(['width_mm' => $size['w'], 'height_mm' => $size['h']], $result), 'Líneas de corte de la hoja obtenidas');
    }

    /** Descarga varios pliegos en un ZIP: `{ "ids": [1, 2] }`. */
    public function downloadMany(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:pliegos,id',
            'guides' => 'nullable|boolean',
            'mode' => 'nullable|in:' . implode(',', PliegoService::MODES),
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $pliegos = Pliego::whereIn('id', $request->ids)->get();
        $files = [];
        $errors = [];
        foreach ($pliegos as $pliego) {
            try {
                $mode = $request->input('mode', PliegoService::MODE_ART);
                $files[] = [PliegoService::generatePdf($pliego, $request->boolean('guides'), $mode)['path'], PliegoService::downloadName($pliego, $mode)];
            } catch (\Throwable $e) {
                $errors[$pliego->name] = $e->getMessage();
            }
        }

        if ($errors) {
            foreach ($files as [$path]) {
                @unlink($path);
            }
            return $this->error('No se pudieron generar algunos pliegos', 422, $errors);
        }

        $zipPath = storage_path('app/pdf/pliegos/tmp/' . uniqid('pliegos-', true) . '.zip');
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return $this->error('No se pudo crear el ZIP', 500);
        }
        $used = [];
        foreach ($files as [$path, $name]) {
            $base = $name;
            $n = 2;
            while (isset($used[$name])) {
                $name = preg_replace('/\.pdf$/', " ({$n}).pdf", $base);
                $n++;
            }
            $used[$name] = true;
            $zip->addFile($path, $name);
        }
        $zip->close();
        foreach ($files as [$path]) {
            @unlink($path);
        }

        $this->logAudit(Auth::user(), 'Download Many Pliegos', $request->all(), ['count' => count($files)]);
        return response()->download($zipPath, 'pliegos-' . Carbon::now('America/Argentina/Buenos_Aires')->format('d-m-Y') . '.zip')
            ->deleteFileAfterSend(true);
    }

    // ------------------------------------------------------------------

    private function validator(Request $request, bool $creating)
    {
        $req = $creating ? 'required' : 'nullable';
        return Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'sheetSizeId' => 'nullable|exists:pdf_sheet_sizes,id',
            'widthCm' => "{$req}|numeric|min:1|max:500",
            'heightCm' => "{$req}|numeric|min:1|max:500",
            'columns' => "{$req}|integer|min:1|max:12",
            'rows' => "{$req}|integer|min:1|max:12",
            'marginMm' => 'nullable|numeric|min:0|max:80',
            'gapMm' => 'nullable|numeric|min:0|max:80',
            'cutLines' => 'nullable|array',
            'cutLines.enabled' => 'nullable|boolean',
            'cutLines.width_cm' => 'nullable|numeric|min:0.001|max:1',
            'cutLines.color' => 'nullable|array',
            'cutLines.color.mode' => 'required_with:cutLines.color|in:hex,cmyk',
            'cutLines.color.value' => 'required_with:cutLines.color|string|max:50',
            'cutLines.labels' => 'nullable|boolean',
            'cutLines.slots' => 'nullable|boolean',
            'cutLines.slot_sources' => 'nullable|array|max:144',
            'cutLines.slot_sources.*.position' => 'required|integer|min:0',
            'cutLines.slot_sources.*.design_id' => 'required|integer',
            'cutLines.slot_sources.*.page_id' => 'nullable|string|max:100',
            'cutLines.designs' => 'nullable|array|max:100',
            'cutLines.designs.*.design_id' => 'required|integer',
            'cutLines.designs.*.page_id' => 'nullable|string|max:100',
            'cutLines.designs.*.x_mm' => 'required|numeric',
            'cutLines.designs.*.y_mm' => 'required|numeric',
            'cutLines.designs.*.scale' => 'nullable|numeric|min:0.01|max:20',
            'cutLines.designs.*.rotation_deg' => 'nullable|numeric|min:-360|max:360',
            'cutLines.shapes' => 'nullable|array|max:500',
            'cutLines.shapes.*.type' => 'required|in:rect,ellipse,line,path',
            'cutLines.shapes.*.x_mm' => 'required_if:cutLines.shapes.*.type,rect,ellipse|numeric',
            'cutLines.shapes.*.y_mm' => 'required_if:cutLines.shapes.*.type,rect,ellipse|numeric',
            'cutLines.shapes.*.w_mm' => 'required_if:cutLines.shapes.*.type,rect,ellipse|numeric|min:0.1',
            'cutLines.shapes.*.h_mm' => 'required_if:cutLines.shapes.*.type,rect,ellipse|numeric|min:0.1',
            'cutLines.shapes.*.radius_mm' => 'nullable|numeric|min:0',
            'cutLines.shapes.*.rotation_deg' => 'nullable|numeric|min:-360|max:360',
            'cutLines.shapes.*.x1_mm' => 'required_if:cutLines.shapes.*.type,line|numeric',
            'cutLines.shapes.*.y1_mm' => 'required_if:cutLines.shapes.*.type,line|numeric',
            'cutLines.shapes.*.x2_mm' => 'required_if:cutLines.shapes.*.type,line|numeric',
            'cutLines.shapes.*.y2_mm' => 'required_if:cutLines.shapes.*.type,line|numeric',
            'cutLines.shapes.*.d' => 'required_if:cutLines.shapes.*.type,path|string|max:20000',
            'slots' => 'nullable|array',
            'slots.*.position' => 'required|integer|min:0',
            'slots.*.folder' => 'required|string|max:10',
            'slots.*.file' => 'required|string|max:500',
            'slots.*.page' => 'required|integer|min:1',
        ]);
    }

    /** cutLines del request → columna cut_lines (null = deshabilitadas). */
    private function cutLinesInput(Request $request): ?array
    {
        $c = $request->input('cutLines');
        if (!is_array($c)) {
            return null;
        }
        return array_filter([
            'enabled' => (bool) ($c['enabled'] ?? false),
            'width_cm' => isset($c['width_cm']) ? (float) $c['width_cm'] : null,
            'color' => $c['color'] ?? null,
            'labels' => array_key_exists('labels', $c) ? (bool) $c['labels'] : null,
            'slots' => array_key_exists('slots', $c) ? (bool) $c['slots'] : null,
            'slot_sources' => isset($c['slot_sources']) ? array_map(fn($x) => array_filter([
                'position' => (int) $x['position'],
                'design_id' => (int) $x['design_id'],
                'page_id' => $x['page_id'] ?? null,
            ], fn($v) => $v !== null), array_values($c['slot_sources'])) : null,
            'designs' => isset($c['designs']) ? array_map(fn($x) => array_filter([
                'design_id' => (int) $x['design_id'],
                'page_id' => $x['page_id'] ?? null,
                'x_mm' => (float) $x['x_mm'],
                'y_mm' => (float) $x['y_mm'],
                'scale' => isset($x['scale']) ? (float) $x['scale'] : null,
                'rotation_deg' => isset($x['rotation_deg']) ? (float) $x['rotation_deg'] : null,
            ], fn($v) => $v !== null), array_values($c['designs'])) : null,
            'shapes' => isset($c['shapes']) ? array_map(fn($x) => array_intersect_key($x, array_flip([
                'type', 'x_mm', 'y_mm', 'w_mm', 'h_mm', 'radius_mm', 'rotation_deg', 'x1_mm', 'y1_mm', 'x2_mm', 'y2_mm', 'd',
            ])), array_values($c['shapes'])) : null,
        ], fn($v) => $v !== null);
    }

    /**
     * Reglas de cutLines que no entran en el Validator: lugar dentro de la
     * grilla y sin repetir, el diseño/página existe, el path se puede leer.
     */
    private function validateCutLines(Request $request, int $totalSlots): array
    {
        $c = $request->input('cutLines');
        if (!is_array($c)) {
            return [];
        }
        $errors = [];
        $seen = [];
        foreach ($c['slot_sources'] ?? [] as $i => $src) {
            $pos = (int) $src['position'];
            if ($pos >= $totalSlots) {
                $errors["cutLines.slot_sources.{$i}.position"][] = 'El lugar ' . ($pos + 1) . " no existe en una grilla de {$totalSlots} lugares.";
            } elseif (isset($seen[$pos])) {
                $errors["cutLines.slot_sources.{$i}.position"][] = 'El lugar ' . ($pos + 1) . ' está repetido.';
            }
            $seen[$pos] = true;
            $d = PliegoService::designPageCutLines((int) $src['design_id'], $src['page_id'] ?? null);
            if (!$d['available']) {
                $errors["cutLines.slot_sources.{$i}.design_id"][] = $d['reason'];
            }
        }
        foreach ($c['designs'] ?? [] as $i => $item) {
            $d = PliegoService::designPageCutLines((int) $item['design_id'], $item['page_id'] ?? null);
            if (!$d['available']) {
                $errors["cutLines.designs.{$i}.design_id"][] = $d['reason'];
            }
        }
        foreach ($c['shapes'] ?? [] as $i => $shape) {
            if (!PliegoService::shapePath($shape)) {
                $errors["cutLines.shapes.{$i}"][] = ($shape['type'] ?? '') === 'path'
                    ? 'El path no se puede leer (solo M, L, H, V y Z).'
                    : 'Línea inválida.';
            }
        }
        return $errors;
    }

    private function replaceSlots(Pliego $pliego, array $rows): void
    {
        $pliego->slots()->delete();
        $now = Carbon::now();
        if ($rows) {
            PliegoSlot::insert(array_map(fn($r) => $r + [
                'pliego_id' => $pliego->id,
                'created_at' => $now,
                'updated_at' => $now,
            ], $rows));
        }
    }

    /** Carrera: otra persona ubicó la misma hoja en otro pliego entre la validación y el insert. */
    private function sheetConflict(\Illuminate\Database\QueryException $e, Request $request)
    {
        if (str_contains($e->getMessage(), 'pliego_slots_sheet_unique')) {
            $this->logAudit(Auth::user(), 'Save Pliego Conflict', $request->all(), $e->getMessage());
            return $this->validationError(['slots' => ['Alguna de las hojas se acaba de ubicar en otro pliego. Actualizá la lista y volvé a intentar.']]);
        }
        throw $e;
    }

    private function listItem(Pliego $p): array
    {
        $data = $p->toArray();
        $data['total_slots'] = $p->totalSlots();
        $data['filled_slots'] = (int) $p->slots_count;
        // Para la miniatura de la tarjeta (el front renderiza cada hoja con pdf.js).
        $data['slots'] = $p->slots->map(fn(PliegoSlot $s) => [
            'position' => $s->position,
            'key' => $s->sheetKey(),
            'folder' => $s->pdf_folder,
            'file' => $s->pdf_file,
            'page' => $s->page_number,
            'sale_id' => $s->sale_id,
            'view_url' => PliegoService::viewUrl($s->pdf_folder, $s->pdf_file),
        ])->values();
        return $data;
    }
}

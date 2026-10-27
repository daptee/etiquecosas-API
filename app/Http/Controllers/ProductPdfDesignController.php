<?php

namespace App\Http\Controllers;

use App\Models\LabelShape;
use App\Models\PersonalizationIcon;
use App\Models\Product;
use App\Models\ProductPdf;
use App\Models\ProductPdfDesign;
use App\Models\ProductPdfDesignProduct;
use App\Models\ProductVariant;
use App\Models\Typography;
use App\Models\TypographyFile;
use App\Services\EtiquetaService;
use App\Services\PdfDesignImportService;
use App\Services\PdfDesignSanitizer;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable;

class ProductPdfDesignController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    /**
     * Placeholder usado en importFromProduct() para generar el PDF de
     * muestra de una temática legacy — se detecta este texto EXACTO en el
     * resultado para marcar ese elemento como `dynamic_field:
     * "nombre_apellido"` en vez de dejarlo como texto fijo literal.
     */
    private const SAMPLE_NOMBRE_APELLIDO = 'NOMBRE APELLIDO';

    /**
     * Ninguna vista legacy "normal" fija su propia tipografía en el
     * .blade.php — la heredan del CSS compartido (public/css/etiquetas.css),
     * que aplica esta fuente a TODO el texto por igual (confirmado: 113 de
     * 115 vistas). Solo un puñado de vistas (ej. stars/TRANSFER *) declaran
     * la suya propia — ver detectLegacyFontFamily().
     */
    private const DEFAULT_LEGACY_FONT_FAMILY = 'Oswald';

    public function index(Request $request)
    {
        $productId = $request->query('productId');
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);

        $query = ProductPdfDesign::with(['products:id,name,sku', 'labelShape', 'generalStatus']);

        if ($productId) {
            $query->whereHas('products', fn($q) => $q->where('products.id', $productId));
        }

        if ($statusId = $request->query('statusId')) {
            $query->where('status_id', $statusId);
        }

        if ($labelShapeId = $request->query('labelShapeId')) {
            $query->where('label_shape_id', $labelShapeId);
        }

        if ($request->filled('isPublished')) {
            $query->where('is_published', $request->boolean('isPublished'));
        }

        $query->orderBy('name', 'asc');

        if (!$perPage) {
            $designs = $query->get();
            $this->resolveSourcePages($designs->all());
            return $this->success($designs, 'Diseños de PDF obtenidos');
        }

        $designs = $query->paginate($perPage, ['*'], 'page', $page);
        $this->resolveSourcePages($designs->items());
        $metaData = [
            'current_page' => $designs->currentPage(),
            'last_page' => $designs->lastPage(),
            'per_page' => $designs->perPage(),
            'total' => $designs->total(),
            'from' => $designs->firstItem(),
            'to' => $designs->lastItem(),
        ];
        return $this->success($designs->items(), 'Diseños de PDF obtenidos', $metaData);
    }

    public function show($id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);
        $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);
        return $this->success($design, 'Diseño de PDF obtenido');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->rules());
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Product Pdf Design', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $data = $this->sanitizeDesignData($request->input('data'));
        if ($layoutErrors = $this->layoutGroupErrors($data)) {
            $this->logAudit(Auth::user(), 'Store Product Pdf Design', $request->all(), $layoutErrors);
            return $this->validationError($layoutErrors);
        }

        $design = ProductPdfDesign::create([
            'label_shape_id' => $request->labelShapeId,
            'name' => $request->name,
            'data' => $data,
            'is_published' => $request->boolean('isPublished'),
            'status_id' => $request->statusId ?? 1,
        ]);

        $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Store Product Pdf Design', $request->all(), $design);
        return $this->success($design, 'Diseño de PDF creado');
    }

    /**
     * Sube un PDF YA GENERADO por este mismo backend (una hoja de etiquetas
     * del sistema viejo, product_pdf/vistas por temática) y arma un diseño
     * nuevo para el editor a partir de lo que detecta ahí: fondos, íconos y
     * texto, con su posición/tamaño/color real. Los íconos quedan con
     * icon_id=1 (placeholder fijo, no hay forma confiable de matchear la
     * imagen real contra el catálogo solo mirando el PDF) y font_id siempre
     * queda null — se completan a mano en el editor. Ver PDF_IMPORTAR_DESDE_PDF.md.
     */
    public function importFromPdf(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pdf' => 'required|file|mimes:pdf|max:20480',
            'name' => 'required|string|max:255',
            'labelShapeId' => 'nullable|exists:label_shapes,id',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Import Pdf Design', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $tmpPath = $request->file('pdf')->getRealPath();

        try {
            $data = $this->sanitizeDesignData(PdfDesignImportService::import($tmpPath));
        } catch (\Throwable $e) {
            return $this->error('No se pudo analizar el PDF: ' . $e->getMessage(), 500);
        }

        $elementCount = count($data['pages'][0]['elements'] ?? []);
        if ($elementCount === 0) {
            return $this->error('No se detectó ningún elemento en el PDF — puede que no sea un PDF generado por este sistema', 422);
        }

        $design = ProductPdfDesign::create([
            'label_shape_id' => $request->labelShapeId,
            'name' => $request->name,
            'data' => $data,
            'is_published' => false,
            'status_id' => 1,
        ]);

        $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Import Pdf Design', ['name' => $request->name], ['designId' => $design->id, 'elementCount' => $elementCount]);
        return $this->success($design, "Diseño importado del PDF ({$elementCount} elementos detectados)");
    }

    /**
     * Para no tener que recrear a mano un PDF que un producto YA tiene en el
     * sistema viejo (tabla product_pdf, vistas de tematica/*): dado un
     * product_id (+ variantId opcional, para elegir la temática si el
     * producto tiene más de una), genera un PDF DE MUESTRA con esa temática
     * (nombre placeholder, sin venta real detrás — mismo motor que usa
     * ProductPdfResolverService::resolveLegacy() para una venta real) y lo
     * analiza con PdfDesignImportService, igual que importFromPdf(). Si la
     * temática trae varias vistas (ej. principal + vinilo), cada una se
     * importa como una página distinta del mismo diseño. El diseño queda
     * SIN PUBLICAR pero YA VINCULADO al producto/variante — no afecta la
     * generación real de ninguna venta hasta que se revise (íconos/fuentes
     * quedan sin matchear, igual que en importFromPdf) y se publique a mano,
     * ya que ProductPdfResolverService solo usa vínculos cuyo diseño esté
     * is_published. Ver PDF_IMPORTAR_DESDE_PRODUCTO.md.
     */
    public function importFromProduct(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'productId' => 'required|exists:products,id',
            'variantId' => 'nullable|exists:product_variants,id',
            'name' => 'nullable|string|max:255',
            'labelShapeId' => 'nullable|exists:label_shapes,id',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Import Pdf Design From Product', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $productId = $request->productId;
        $variantId = $request->variantId;

        $productPdf = ProductPdf::where('product_id', $productId)->first();
        if (!$productPdf) {
            return $this->error('Este producto no tiene un PDF legacy asociado (tabla product_pdf)', 422);
        }

        $tematicasGuardadas = $productPdf->data['tematicas'] ?? [];
        if (empty($tematicasGuardadas)) {
            return $this->error('Este producto no tiene temáticas cargadas en su PDF legacy', 422);
        }

        if ($variantId) {
            $variant = ProductVariant::find($variantId);
            // El rule "exists" de abajo valida contra la tabla cruda, sin el
            // global scope de SoftDeletes — una variante borrada pasa la
            // validación pero ProductVariant::find() (sí soft-delete-aware)
            // devuelve null, así que hay que distinguir este caso del de
            // "la variante existe pero no tiene atributos" para no confundir.
            if (!$variant) {
                return $this->error('La variante elegida no existe o fue eliminada', 422);
            }
            $tematicaId = $variant->variant['attributesvalues'][0]['id'] ?? null;
            if (!$tematicaId) {
                return $this->error('No se pudo determinar la temática de la variante elegida', 422);
            }
            $tematicaCoincidente = collect($tematicasGuardadas)->firstWhere('id', $tematicaId);
            if (!$tematicaCoincidente) {
                return $this->error('La variante elegida no tiene una temática asociada en este producto', 422);
            }
        } elseif (count($tematicasGuardadas) === 1) {
            $tematicaCoincidente = $tematicasGuardadas[0];
            $tematicaId = $tematicaCoincidente['id'] ?? null;
        } else {
            return $this->error('Este producto tiene ' . count($tematicasGuardadas) . ' temáticas distintas — hay que elegir una variante (variantId) para saber cuál importar', 422);
        }

        $product = Product::find($productId);
        $productOrder = (object) [
            'id' => 'import-' . uniqid(),
            'product' => (object) ['name' => $product->name],
        ];

        $ventaIdMuestra = random_int(900000000, 999999999);

        // Las temáticas "PERSONALIZABLE" (vistas tematica/*-and-*/PERSONALIZABLE*,
        // ej. "Maxi - Spot") no tienen un nombre real en attribute_values/tematicas
        // detrás — en una venta real, el cliente elige un color al comprar
        // (customColor), y generarEtiquetas() usa ESE color (CASO 1/1B). Sin
        // customColor cae al CASO 3 ("tematica con nombre"), que para estas
        // vistas rompe: busca un attribute_values.id con el mismo número que
        // $tematicaId (acá es solo un índice interno de product_pdf.data.
        // tematicas[], no un attribute_values.id real) y arma $plantilla con
        // datos que esa vista ni espera. Para la MUESTRA (sin venta real
        // detrás) alcanza con un color de relleno cualquiera, así entra al
        // mismo camino (CASO 1B) que usaría una venta real.
        $urlsMuestra = is_array($tematicaCoincidente['pdf-url'] ?? null)
            ? $tematicaCoincidente['pdf-url']
            : (($tematicaCoincidente['pdf-url'] ?? null) ? [$tematicaCoincidente['pdf-url']] : []);
        $esPersonalizable = collect($urlsMuestra)->contains(fn($u) => str_contains($u, '/PERSONALIZABLE'));
        // "PERSONALIZABLE SIN ICONO" es la variante lisa (sin ícono) de esta
        // misma temática — si el pdf-url YA apunta ahí, el producto no tiene
        // ícono para elegir en una venta real, así que no hay que simularle uno.
        $yaEsSinIcono = collect($urlsMuestra)->contains(fn($u) => str_contains($u, 'SIN ICONO'));
        $colorMuestra = $esPersonalizable ? ['#CCCCCC'] : null;
        // Con $customIcon en null, generarEtiquetas() cambia la vista a la
        // variante "SIN ICONO" (ver $getViews) — para que la muestra SÍ
        // traiga un ícono (y el importador pueda detectarlo), hace falta un
        // archivo real como el que elegiría un cliente en una venta real;
        // cualquiera del catálogo sirve, es solo geometría/placeholder.
        $iconMuestra = ($esPersonalizable && !$yaEsSinIcono) ? $this->pickSampleIconPath() : null;

        try {
            $samplePaths = EtiquetaService::generarEtiquetas(
                $ventaIdMuestra,
                $tematicaId,
                [self::SAMPLE_NOMBRE_APELLIDO],
                $productOrder,
                $tematicaCoincidente,
                $colorMuestra,
                $iconMuestra,
                now(),
                ['NOMBRE']
            );
        } catch (\Throwable $e) {
            return $this->error('No se pudo generar el PDF de muestra: ' . $e->getMessage(), 500);
        }

        if (empty($samplePaths)) {
            return $this->error('No se pudo generar ningún PDF de muestra para esta temática — revisá que las vistas de tematica/* existan', 500);
        }

        // El PDF de muestra solo trae la GEOMETRÍA (dónde hay un ícono y de
        // qué tamaño) — para saber EXACTAMENTE qué archivo va en cada celda,
        // hay que leer la vista Blade de esta temática: cada celda referencia
        // un índice fijo tipo $plantilla['imagen'][N] (ver
        // resources/views/tematica/principal/UNICORNIO.blade.php), y
        // $imagenes[N] (mismo orden que arma EtiquetaService::generarEtiquetas
        // vía scandir) es el archivo real. Reconstruyendo esa secuencia acá
        // podemos asignarle a cada elemento `icon` detectado SU archivo
        // verdadero (color y dibujo exactos), no una adivinanza por nombre.
        $urlPdf = $tematicaCoincidente['pdf-url'] ?? null;
        $viewPaths = is_array($urlPdf) ? $urlPdf : ($urlPdf ? [$urlPdf] : []);
        $tematicaLimpia = strtoupper(EtiquetaService::limpiarNombreArchivo($tematicaCoincidente['name'] ?? ''));
        $imagenes = $this->buildTematicaImagenes($tematicaCoincidente['name'] ?? '');

        // Estos mismos archivos YA están cargados en personalization_icons
        // (ver database/sql/personalization_icons_tematicas.sql, uno por
        // archivo de public/icons/Tematicas/*) — así que el ícono exacto se
        // referencia por icon_id del catálogo, NUNCA copiando el SVG como
        // archivo propio del elemento (`custom_icon_path`): un mismo ícono de
        // catálogo se puede reusar en miles de diseños sin duplicar el archivo.
        $catalogIconIdByPath = PersonalizationIcon::where('icon', 'like', 'icons/Tematicas/%')
            ->pluck('id', 'icon')
            ->all();

        $pages = [];
        $exactIconIds = []; // elId => personalization_icons.id
        foreach ($samplePaths as $idx => $path) {
            try {
                $imported = PdfDesignImportService::import($path);
            } catch (\Throwable $e) {
                // Esta vista puntual no se pudo analizar — se sigue con las demás.
                continue;
            } finally {
                @unlink($path);
            }

            $iconSequence = isset($viewPaths[$idx]) ? $this->extractIconFileSequence($viewPaths[$idx]) : [];

            // La tipografía, a diferencia del ícono, no varía celda por celda
            // dentro de una misma vista — está fija en el .blade.php (ver
            // detectLegacyFontFamily()), así que se resuelve una sola vez por
            // vista/página y se aplica a TODO el texto de esa página.
            $fontFamily = isset($viewPaths[$idx]) ? $this->detectLegacyFontFamily($viewPaths[$idx]) : self::DEFAULT_LEGACY_FONT_FAMILY;
            $fontId = $this->resolveTypographyIdForFamily($fontFamily);

            // Las vistas "*-and-spot*"/"spot-and-*"/"spot/PERSONALIZABLE*"
            // traen una fila de celdas circulares (`circulo-personaje`, ver
            // la vista) que el fondo detectado NO reproduce como círculo
            // (nada en la vista le pone un color de fondo propio — el
            // "fondo" que sale es, en realidad, el color del PROPIO ícono).
            // Si hay un label_shape circular cargado, se lo asignamos a esas
            // celdas puntuales para que el editor las muestre/recorte como
            // círculo en vez de rectángulo.
            $spotCellCount = isset($viewPaths[$idx]) ? $this->detectSpotCellCount($viewPaths[$idx]) : null;
            $spotShapeId = $spotCellCount ? $this->resolveSpotLabelShapeId() : null;

            foreach ($imported['pages'] ?? [] as $page) {
                $page['name'] = 'Página ' . (count($pages) + 1);
                $iconPos = 0;
                foreach ($page['elements'] as &$el) {
                    if (($el['type'] ?? null) === 'icon') {
                        $fileIdx = $iconSequence[$iconPos] ?? null;
                        $file = $fileIdx !== null ? ($imagenes[$fileIdx] ?? null) : null;
                        if ($file && is_file($file)) {
                            $catalogPath = "icons/Tematicas/{$tematicaLimpia}/" . basename($file);
                            $catalogId = $catalogIconIdByPath[$catalogPath] ?? null;
                            if ($catalogId) {
                                $exactIconIds[$el['id']] = $catalogId;
                            }
                        }
                        $iconPos++;
                    } elseif (($el['type'] ?? null) === 'text' && $fontId) {
                        $el['font_id'] = $fontId;
                    }
                }
                unset($el);

                if ($spotShapeId && $spotCellCount) {
                    $this->applySpotLabelShape($page, $spotCellCount, $spotShapeId);
                }

                $pages[] = $page;
            }
        }

        if (empty($pages)) {
            return $this->error('No se detectó ningún elemento en el/los PDF de muestra generados', 422);
        }

        // Para los íconos que NO se pudieron matchear por archivo exacto
        // (vista sin el patrón $plantilla['imagen'][N], o temática sin
        // carpeta de íconos), se completa con el mismo fallback de siempre:
        // buscar en el catálogo nuevo un ícono con nombre parecido.
        $tematicaIconIds = $this->resolveIconIdsForTematica($tematicaCoincidente['name'] ?? '');
        $fallbackIdx = 0;
        foreach ($pages as &$page) {
            foreach ($page['elements'] as &$el) {
                if (($el['type'] ?? null) !== 'icon') {
                    continue;
                }
                if (isset($exactIconIds[$el['id']])) {
                    $el['icon_id'] = $exactIconIds[$el['id']];
                } elseif (!empty($tematicaIconIds)) {
                    $el['icon_id'] = $tematicaIconIds[$fallbackIdx % count($tematicaIconIds)];
                    $fallbackIdx++;
                }
            }
            unset($el);
        }
        unset($page);

        // El texto del nombre en una venta real NO es literal — es
        // `dynamic_field: "nombre_apellido"` (ver EtiquetaService::
        // resolverElementoDesign). El PDF de muestra usa el placeholder
        // "NOMBRE APELLIDO" (self::SAMPLE_NOMBRE_APELLIDO) para que,
        // detectándolo por contenido exacto, el elemento se guarde YA
        // marcado como dinámico — así sale igual que en una venta real, no
        // como un texto fijo que diga literalmente "NOMBRE APELLIDO".
        foreach ($pages as &$page) {
            foreach ($page['elements'] as &$el) {
                if (($el['type'] ?? null) === 'text' && ($el['content'] ?? null) === self::SAMPLE_NOMBRE_APELLIDO) {
                    $el['value_mode'] = 'dynamic';
                    $el['dynamic_field'] = 'nombre_apellido';
                }
            }
            unset($el);
        }
        unset($page);

        // El texto de "PEDIDO # ..." trae el número de la VENTA DE MUESTRA
        // ($ventaIdMuestra) literal, tal cual lo dibujó el PDF de prueba —
        // hay que reemplazarlo por el placeholder {{numero_pedido}} (mismo
        // mecanismo de siempre para texto fijo con partes dinámicas, ver
        // EtiquetaService::resolverElementoDesign, rama "esquema anterior
        // por placeholders") para que en una venta real se vea el número de
        // ESA venta, no siempre el mismo número de muestra fijo. A
        // diferencia del nombre, acá NO se usa dynamic_field/value_mode
        // "dynamic": ese mecanismo reemplaza TODO el contenido del
        // elemento, perdiendo el prefijo fijo "PEDIDO #" que es parte del
        // diseño — el placeholder entre texto fijo sí lo preserva.
        foreach ($pages as &$page) {
            $sheetWidthCm = (float) ($page['sheet']['width_cm'] ?? 21);
            $sheetHeightCm = (float) ($page['sheet']['height_cm'] ?? 29.7);
            foreach ($page['elements'] as &$el) {
                if (($el['type'] ?? null) === 'text' && !empty($el['content']) && str_contains($el['content'], (string) $ventaIdMuestra)) {
                    $el['content'] = str_replace((string) $ventaIdMuestra, '{{numero_pedido}}', $el['content']);
                    // El placeholder "{{numero_pedido}}" es más largo que el
                    // número de muestra que reemplazó — la geometría (ancho,
                    // max_chars_per_line) se había calculado ANTES de este
                    // reemplazo, a partir del texto más corto, así que podía
                    // quedar de menos y el texto terminaba partido en 2
                    // líneas (max_lines ya pide 1 sola). Se reajusta acá,
                    // manteniendo el mismo centro/rotación.
                    $el = PdfDesignImportService::resizeTextElementForContent($el, $el['content'], $sheetWidthCm, $sheetHeightCm);
                }
            }
            unset($el);
        }
        unset($page);

        $data = $this->sanitizeDesignData(['pages' => $pages]);

        $design = ProductPdfDesign::create([
            'label_shape_id' => $request->labelShapeId,
            'name' => $request->name ?: ($product->name . ' (importado)'),
            'data' => $data,
            'is_published' => false,
            'status_id' => 1,
        ]);

        try {
            ProductPdfDesignProduct::create([
                'product_pdf_design_id' => $design->id,
                'product_id' => $productId,
                'theme_key' => $variantId,
                'page_id' => null,
                'sort_order' => 0,
            ]);
        } catch (QueryException $e) {
            // Ya había un vínculo idéntico (mismo producto+variante+diseño) — el diseño ya quedó creado igual.
        }

        $elementCount = array_sum(array_map(fn($p) => count($p['elements'] ?? []), $pages));
        $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Import Pdf Design From Product', $request->all(), ['designId' => $design->id, 'elementCount' => $elementCount, 'pages' => count($pages)]);
        return $this->success($design, "Diseño importado del PDF legacy ({$elementCount} elementos en " . count($pages) . " página(s))");
    }

    public function update(Request $request, $id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);

        $rules = $this->rules();
        $rules['name'] = 'nullable|string|max:255';
        $rules['data'] = 'nullable|array';

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Product Pdf Design', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $data = $design->data;
        if (is_array($request->input('data'))) {
            $data = $this->sanitizeDesignData($request->input('data'));
            if ($layoutErrors = $this->layoutGroupErrors($data)) {
                $this->logAudit(Auth::user(), 'Update Product Pdf Design', $request->all(), $layoutErrors);
                return $this->validationError($layoutErrors);
            }
        }

        $design->update([
            'label_shape_id' => $request->input('labelShapeId', $design->label_shape_id),
            'name' => $request->input('name', $design->name),
            'data' => $data,
            'is_published' => $request->has('isPublished') ? $request->boolean('isPublished') : $design->is_published,
            'status_id' => $request->input('statusId', $design->status_id),
        ]);

        $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Update Product Pdf Design', $request->all(), $design);
        return $this->success($design, 'Diseño de PDF actualizado');
    }

    public function toggleStatus($id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);
        $design->update([
            'status_id' => $design->status_id === 1 ? 2 : 1,
        ]);
        $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Toggle Product Pdf Design Status', $id, $design);
        return $this->success($design, 'Estado actualizado');
    }

    public function delete($id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);
        $design->delete();
        $this->logAudit(Auth::user(), 'Delete Product Pdf Design', $id, $design);
        return $this->success($design, 'Diseño de PDF eliminado');
    }

    /**
     * Vincula este diseño a un producto (con la variante/temática que lo
     * selecciona en ese producto puntual). Un mismo diseño puede vincularse
     * a varios productos distintos.
     */
    public function attachProduct(Request $request, $id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);

        $validator = Validator::make($request->all(), [
            'productId' => 'required|exists:products,id',
            'themeKey' => 'nullable|integer',
            // id de data.pages[].id de ESTE diseño — null = usa todas sus páginas.
            'pageId' => 'nullable|string|max:255',
            'sortOrder' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Attach Product to Pdf Design', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        try {
            $link = ProductPdfDesignProduct::create([
                'product_pdf_design_id' => $design->id,
                'product_id' => $request->productId,
                'theme_key' => $request->themeKey,
                'page_id' => $request->pageId,
                'sort_order' => $request->sortOrder ?? 0,
            ]);
        } catch (QueryException $e) {
            return $this->validationError(['themeKey' => ['Ya existe un vínculo idéntico (mismo producto, variante, diseño y página)']]);
        }

        $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Attach Product to Pdf Design', $request->all(), $link);
        return $this->success($design, 'Producto vinculado al diseño');
    }

    /**
     * Vincula este diseño a un producto para VARIAS temáticas/variantes a la
     * vez (un theme_key por vínculo). Si alguna combinación producto+theme_key
     * ya estaba vinculada a otro diseño, esa puntual se salta (no aborta el
     * resto) y se informa en "skipped".
     */
    public function bulkAttachProducts(Request $request, $id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);

        $validator = Validator::make($request->all(), [
            'productId' => 'required|exists:products,id',
            'themeKeys' => 'required|array|min:1',
            'themeKeys.*' => 'nullable|integer',
            // Misma página (o diseño entero) para TODAS las temáticas de esta llamada.
            'pageId' => 'nullable|string|max:255',
            'sortOrder' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Bulk Attach Products to Pdf Design', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $created = [];
        $skipped = [];

        foreach ($request->themeKeys as $themeKey) {
            try {
                $link = ProductPdfDesignProduct::create([
                    'product_pdf_design_id' => $design->id,
                    'product_id' => $request->productId,
                    'theme_key' => $themeKey,
                    'page_id' => $request->pageId,
                    'sort_order' => $request->sortOrder ?? 0,
                ]);
                $created[] = $link;
            } catch (QueryException $e) {
                $skipped[] = [
                    'themeKey' => $themeKey,
                    'reason' => 'Ya existe un vínculo idéntico (mismo producto, variante, diseño y página)',
                ];
            }
        }

        $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Bulk Attach Products to Pdf Design', $request->all(), [
            'created' => count($created),
            'skipped' => $skipped,
        ]);

        return $this->success($design, 'Vínculos creados', [
            'created' => count($created),
            'skipped' => $skipped,
        ]);
    }

    /**
     * Desvincula este diseño de un producto para VARIAS temáticas/variantes a
     * la vez. Para desvincular el vínculo "sin variante" (theme_key null),
     * incluí `null` dentro de themeKeys.
     */
    public function bulkDetachProducts(Request $request, $id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);

        $validator = Validator::make($request->all(), [
            'productId' => 'required|exists:products,id',
            'themeKeys' => 'required|array|min:1',
            'themeKeys.*' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Bulk Detach Products from Pdf Design', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $themeKeys = $request->themeKeys;
        $hasNull = in_array(null, $themeKeys, true);
        $nonNullKeys = array_values(array_filter($themeKeys, fn($k) => $k !== null));

        $deletedCount = ProductPdfDesignProduct::where('product_pdf_design_id', $design->id)
            ->where('product_id', $request->productId)
            ->where(function ($q) use ($nonNullKeys, $hasNull) {
                if (!empty($nonNullKeys)) {
                    $q->whereIn('theme_key', $nonNullKeys);
                }
                if ($hasNull) {
                    $q->orWhereNull('theme_key');
                }
            })
            ->delete();

        $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Bulk Detach Products from Pdf Design', $request->all(), ['deleted' => $deletedCount]);

        return $this->success($design, 'Vínculos eliminados', ['deleted' => $deletedCount]);
    }

    /**
     * Igual que bulkAttachProducts, pero para VARIOS productos a la vez, cada
     * uno con su propia lista de temáticas/variantes.
     */
    public function bulkAttachMany(Request $request, $id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);

        $validator = Validator::make($request->all(), [
            'links' => 'required|array|min:1',
            'links.*.productId' => 'required|exists:products,id',
            'links.*.themeKeys' => 'required|array|min:1',
            'links.*.themeKeys.*' => 'nullable|integer',
            // Misma página (o diseño entero) para todas las temáticas de ESE producto.
            'links.*.pageId' => 'nullable|string|max:255',
            'links.*.sortOrder' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Bulk Attach Many Products to Pdf Design', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $created = [];
        $skipped = [];

        foreach ($request->links as $link) {
            foreach ($link['themeKeys'] as $themeKey) {
                try {
                    $created[] = ProductPdfDesignProduct::create([
                        'product_pdf_design_id' => $design->id,
                        'product_id' => $link['productId'],
                        'theme_key' => $themeKey,
                        'page_id' => $link['pageId'] ?? null,
                        'sort_order' => $link['sortOrder'] ?? 0,
                    ]);
                } catch (QueryException $e) {
                    $skipped[] = [
                        'productId' => $link['productId'],
                        'themeKey' => $themeKey,
                        'reason' => 'Ya existe un vínculo idéntico (mismo producto, variante, diseño y página)',
                    ];
                }
            }
        }

        $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Bulk Attach Many Products to Pdf Design', $request->all(), [
            'created' => count($created),
            'skipped' => $skipped,
        ]);

        return $this->success($design, 'Vínculos creados', [
            'created' => count($created),
            'skipped' => $skipped,
        ]);
    }

    /**
     * Igual que bulkDetachProducts, pero para VARIOS productos a la vez, cada
     * uno con su propia lista de temáticas/variantes a desvincular.
     */
    public function bulkDetachMany(Request $request, $id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);

        $validator = Validator::make($request->all(), [
            'links' => 'required|array|min:1',
            'links.*.productId' => 'required|exists:products,id',
            'links.*.themeKeys' => 'required|array|min:1',
            'links.*.themeKeys.*' => 'nullable|integer',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Bulk Detach Many Products from Pdf Design', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $deletedCount = 0;

        foreach ($request->links as $link) {
            $themeKeys = $link['themeKeys'];
            $hasNull = in_array(null, $themeKeys, true);
            $nonNullKeys = array_values(array_filter($themeKeys, fn($k) => $k !== null));

            $deletedCount += ProductPdfDesignProduct::where('product_pdf_design_id', $design->id)
                ->where('product_id', $link['productId'])
                ->where(function ($q) use ($nonNullKeys, $hasNull) {
                    if (!empty($nonNullKeys)) {
                        $q->whereIn('theme_key', $nonNullKeys);
                    }
                    if ($hasNull) {
                        $q->orWhereNull('theme_key');
                    }
                })
                ->delete();
        }

        $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Bulk Detach Many Products from Pdf Design', $request->all(), ['deleted' => $deletedCount]);

        return $this->success($design, 'Vínculos eliminados', ['deleted' => $deletedCount]);
    }

    /**
     * Quita el vínculo entre este diseño y un producto (por el id del vínculo,
     * no del producto, porque un mismo producto podría estar vinculado más de
     * una vez con distintos theme_key).
     */
    public function detachProduct($id, $linkId)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);
        $link = ProductPdfDesignProduct::where('product_pdf_design_id', $design->id)->find($linkId);

        if (!$link) {
            return $this->notFound('El vínculo no existe');
        }

        $link->delete();

        $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Detach Product from Pdf Design', ['designId' => $id, 'linkId' => $linkId], $design);
        return $this->success($design, 'Producto desvinculado del diseño');
    }

    /**
     * Genera un PDF de muestra con el mismo motor que se usa en la generación
     * real (EtiquetaService::generarEtiquetasDesdeDesign), para que el editor
     * del front pueda previsualizar el diseño sin necesidad de una venta real.
     */
    public function preview(Request $request, $id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);

        $firstName = $request->query('firstName', 'NOMBRE');
        $lastName = $request->query('lastName', 'APELLIDO');
        $nombre = trim("{$firstName} {$lastName}");

        // Sin esto, cualquier ícono que dependa de la personalización del
        // cliente (editable_field:"icon" o dynamic_attribute_id) sale en
        // blanco en el preview, porque no hay ninguna venta real detrás.
        // ?icon= simula el ícono "libre" (customization_data.icon.icon);
        // ?variantId= carga una variante real para que también resuelvan los
        // íconos por atributo (dynamic_attribute_id).
        $customIcon = $request->query('icon');
        $customColor = $request->query('color');
        $variantId = $request->query('variantId');

        // Mismo motivo que arriba: sin esto, un elemento de texto con
        // dynamic_field "fecha"/"numero_pedido" sale vacío en el preview.
        // ?fecha= (cualquier formato que entienda Carbon::parse) y
        // ?numeroPedido= simulan esos datos de una venta real.
        $fechaPreview = $request->query('fecha') ? Carbon::parse($request->query('fecha')) : now();
        $numeroPedidoPreview = (int) $request->query('numeroPedido', 111111);
        // Para {{id_producto}}: no hay producto comprado detrás del preview.
        $idProductoPreview = (int) $request->query('idProducto', 222222);

        $productOrder = (object)[
            'id' => 'preview-' . $design->id,
            'product_id' => $idProductoPreview,
            'product' => (object)['name' => $design->name],
            'variant' => $variantId ? ProductVariant::find($variantId) : null,
        ];

        // ?format=layout: mismo cálculo que el PDF, pero devuelve las
        // posiciones de los layout_groups en JSON (para comparar contra el
        // editor), sin renderizar nada.
        if ($request->query('format') === 'layout') {
            try {
                $layout = EtiquetaService::calcularLayoutDesdeDesign(
                    $numeroPedidoPreview,
                    $design,
                    $productOrder,
                    $nombre,
                    $customColor,
                    $customIcon,
                    $fechaPreview,
                    $firstName,
                    $lastName
                );
            } catch (\Throwable $e) {
                return $this->error('Error calculando el layout: ' . $e->getMessage(), 500);
            }

            return response()->json($layout);
        }

        try {
            $paths = EtiquetaService::generarEtiquetasDesdeDesign(
                $numeroPedidoPreview,
                $design,
                $productOrder,
                [$nombre],
                $customColor,
                $customIcon,
                $fechaPreview,
                [$firstName],
                [$lastName],
                // ?cut_lines=1: PDF solo con las líneas de corte, sin el arte.
                $request->boolean('cut_lines')
            );
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error generando la vista previa',
                'error' => $e->getMessage(),
            ], 500);
        }

        if (empty($paths[0]) || !file_exists($paths[0])) {
            // generarEtiquetasDesdeDesign() atrapa la excepción real acá
            // adentro (no la relanza, para no tirar abajo la generación de
            // una venta real con varios nombres por uno solo que falle) —
            // EtiquetaService::$lastRenderError guarda ese mensaje para este
            // caso (preview = un solo nombre), en vez de devolver un genérico.
            return response()->json([
                'message' => 'No se pudo generar la vista previa',
                'error' => EtiquetaService::$lastRenderError ?? 'Sin más detalle — revisar storage/logs/laravel.log',
            ], 500);
        }

        return response()->file($paths[0]);
    }

    /**
     * Sube una imagen para usar como fondo de una hoja del diseño
     * (data.pages[].sheet.background_image). Solo sube el archivo y devuelve
     * la ruta — el front la guarda donde corresponda dentro del `data` al
     * hacer el POST de actualización del diseño.
     */
    public function uploadBackgroundImage(Request $request, $id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);

        $validator = Validator::make($request->all(), [
            'image' => 'required|file|mimes:jpg,jpeg,png,webp,svg|max:8192',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Upload Pdf Design Background Image', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $file = $request->file('image');
        $path = 'pdf-backgrounds/' . $design->id . '/' . uniqid('bg_') . '.' . $file->getClientOriginalExtension();
        Storage::disk('public_uploads')->put($path, file_get_contents($file));

        $this->logAudit(Auth::user(), 'Upload Pdf Design Background Image', ['designId' => $id], ['path' => $path]);
        return $this->success(['path' => $path], 'Imagen de fondo subida');
    }

    /**
     * Sube una imagen para usar en UN elemento puntual del diseño — como
     * fondo de una etiqueta (data.pages[].elements[].background_image) o como
     * ícono custom (data.pages[].elements[].custom_icon_path). A diferencia de
     * subir un ícono por POST /icons, esto NO crea nada en personalization_icons
     * — es una imagen propia de este diseño, no aparece en GET /api/icons.
     * Solo sube el archivo y devuelve la ruta — el front la guarda en el
     * campo que corresponda dentro del `data` al actualizar el diseño.
     */
    public function uploadElementImage(Request $request, $id)
    {
        $design = $this->findObject(ProductPdfDesign::class, $id);

        $validator = Validator::make($request->all(), [
            'image' => 'required|file|mimes:jpg,jpeg,png,webp,svg|max:8192',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Upload Pdf Design Element Image', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $file = $request->file('image');
        $path = 'pdf-element-images/' . $design->id . '/' . uniqid('el_') . '.' . $file->getClientOriginalExtension();
        Storage::disk('public_uploads')->put($path, file_get_contents($file));

        $this->logAudit(Auth::user(), 'Upload Pdf Design Element Image', ['designId' => $id], ['path' => $path]);
        return $this->success(['path' => $path], 'Imagen subida');
    }

    private function rules(): array
    {
        return [
            'labelShapeId' => 'nullable|exists:label_shapes,id',
            'name' => 'required|string|max:255',
            'data' => 'required|array',
            'data.pages' => 'required|array|min:1',
            'data.pages.*.elements' => 'nullable|array',
            'isPublished' => 'nullable|boolean',
            'statusId' => 'nullable|exists:general_statuses,id',
        ];
    }

    /**
     * Los archivos de ícono legacy de una temática, en el mismo orden (y la
     * misma carpeta) que usa `EtiquetaService::generarEtiquetas()` CASO 3 —
     * necesario para que el índice `N` de `$plantilla['imagen'][N]` en la
     * vista Blade (ver `extractIconFileSequence()`) apunte al archivo
     * correcto. Array vacío si la temática no tiene carpeta de íconos.
     */
    private function buildTematicaImagenes(string $tematicaName): array
    {
        if ($tematicaName === '') {
            return [];
        }
        $tematicaLimpia = strtoupper(EtiquetaService::limpiarNombreArchivo($tematicaName));
        $iconosPath = storage_path("app/pdf/Iconos/Tematicas/{$tematicaLimpia}");
        if (!is_dir($iconosPath)) {
            return [];
        }
        $imagenes = [];
        foreach (scandir($iconosPath) as $file) {
            if (preg_match('/\.(png|jpg|jpeg|svg)$/i', $file)) {
                $imagenes[] = $iconosPath . DIRECTORY_SEPARATOR . $file;
            }
        }
        return $imagenes;
    }

    /**
     * Cada celda de las vistas `tematica/principal/*.blade.php` referencia un
     * ÍNDICE FIJO del array de íconos, ej. `{{ $plantilla['imagen'][2] }}`
     * (ver resources/views/tematica/principal/UNICORNIO.blade.php) — no es
     * aleatorio ni se arma en el momento, está literalmente escrito en la
     * vista. Leyendo el archivo .blade.php y extrayendo esos índices EN EL
     * ORDEN EN QUE APARECEN (que es el mismo orden en que dompdf dibuja las
     * celdas: de arriba hacia abajo, cada sección/tabla en orden) se puede
     * saber EXACTAMENTE qué archivo de ícono le corresponde a cada celda
     * detectada en el PDF, en vez de adivinar. Si la vista no existe o no usa
     * este patrón (ej. una vista "sin dibujo" sin íconos), devuelve [].
     */
    private function extractIconFileSequence(string $viewPath): array
    {
        $viewPath = str_starts_with($viewPath, 'tematica/') ? $viewPath : "tematica/{$viewPath}";
        $bladePath = resource_path('views/' . $viewPath . '.blade.php');
        if (!is_file($bladePath)) {
            return [];
        }
        preg_match_all("/\\\$plantilla\[['\"]imagen['\"]\]\[(\d+)\]/", file_get_contents($bladePath), $m);
        return array_map('intval', $m[1] ?? []);
    }

    /**
     * Íconos reales del catálogo nuevo (personalization_icons) que coinciden
     * con los archivos de ícono legacy de esta temática por NOMBRE (el
     * nombre de archivo sin extensión ni número final, ej. "unicornio 3.svg"
     * -> "unicornio", contra personalization_icons.name, sin mayúsculas).
     * Es el fallback cuando `extractIconFileSequence()` no pudo determinar
     * el archivo EXACTO de una celda puntual — por eso solo matchea por
     * nombre (aproximado), no reproduce el ícono/color original. Devuelve
     * los ids únicos encontrados, en el orden en que aparecen los archivos,
     * para repartirlos round-robin. Array vacío si no hay carpeta o ningún
     * archivo matchea — ahí el caller se queda con el placeholder.
     */
    private function resolveIconIdsForTematica(string $tematicaName): array
    {
        $imagenes = $this->buildTematicaImagenes($tematicaName);
        if (empty($imagenes)) {
            return [];
        }

        $catalogByName = PersonalizationIcon::pluck('id', 'name')
            ->mapWithKeys(fn($id, $name) => [mb_strtolower(trim($name)) => $id])
            ->all();

        $iconIds = [];
        foreach ($imagenes as $file) {
            $baseName = pathinfo($file, PATHINFO_FILENAME);
            $baseName = preg_replace('/\s*\d+$/', '', $baseName); // "unicornio 3" -> "unicornio"
            $key = mb_strtolower(trim($baseName));
            $iconId = $catalogByName[$key] ?? null;
            if ($iconId !== null && !in_array($iconId, $iconIds, true)) {
                $iconIds[] = $iconId;
            }
        }

        return $iconIds;
    }

    /**
     * Nombre de familia tipográfica que efectivamente se le aplica al texto
     * de una vista legacy (`tematica/{$viewPath}.blade.php`). Casi ninguna
     * vista fija la suya propia: heredan `Oswald` del CSS compartido
     * (public/css/etiquetas.css), que se aplica por igual a TODO el texto de
     * las 113 de 115 vistas "normales" — por eso ese es el default. Las
     * pocas vistas que SÍ declaran la suya (ej.
     * `stars/TRANSFER MAYUSCULA.blade.php` -> Antonio,
     * `stars/TRANSFER BOLD.blade.php` -> TitanOne) lo hacen en una regla
     * para la clase `.name` (el texto del cliente) — se prioriza esa por
     * sobre cualquier otra declaración de la vista (algunas, como las de
     * "stars/*", además fijan por separado una tipografía totalmente
     * distinta — Helvetica, sin archivo propio — para texto secundario tipo
     * "PEDIDO #", que NO queremos confundir con la del nombre).
     */
    /**
     * Un path de ícono real y existente del catálogo (`icons/Tematicas/...`),
     * relativo a public_path() tal como lo espera `generarEtiquetas()` CASO 1
     * (`$customIconPath = public_path($customIcon)`) — cualquiera sirve, es
     * solo para que la vista "PERSONALIZABLE" (con ícono) tenga ALGO real
     * para dibujar en el PDF de muestra; no importa cuál, el admin lo va a
     * reemplazar a mano en el editor de todos modos. Null si el catálogo
     * está vacío o ninguno de sus archivos existe en disco (no debería
     * pasar en un ambiente normal, pero así no rompe la muestra).
     */
    private function pickSampleIconPath(): ?string
    {
        $candidatos = PersonalizationIcon::where('icon', 'like', 'icons/Tematicas/%')
            ->orderBy('id')
            ->limit(20)
            ->pluck('icon');

        foreach ($candidatos as $icon) {
            if (is_file(public_path($icon))) {
                return $icon;
            }
        }

        return null;
    }

    private function detectLegacyFontFamily(string $viewPath): string
    {
        $viewPath = str_starts_with($viewPath, 'tematica/') ? $viewPath : "tematica/{$viewPath}";
        $bladePath = resource_path('views/' . $viewPath . '.blade.php');
        if (!is_file($bladePath)) {
            return self::DEFAULT_LEGACY_FONT_FAMILY;
        }

        $content = file_get_contents($bladePath);
        // Los bloques @font-face solo DECLARAN una fuente disponible (varias
        // vistas declaran 'Lora' sin usarla nunca en ningún lado) — no dicen
        // cuál se aplica al texto, así que se descartan antes de buscar.
        $withoutFontFace = preg_replace('/@font-face\s*\{[^}]*\}/s', '', $content);

        if (preg_match("/\.name\s*\{[^}]*font-family:\s*['\"]([^'\"]+)['\"]/s", $withoutFontFace, $m)) {
            return trim($m[1]);
        }
        if (preg_match_all("/font-family:\s*['\"]([^'\"]+)['\"]/", $withoutFontFace, $m) && !empty($m[1])) {
            return trim($m[1][0]);
        }

        return self::DEFAULT_LEGACY_FONT_FAMILY;
    }

    /**
     * Tipografía del catálogo nuevo (`typographies`) que corresponde a una
     * familia detectada en una vista legacy — por nombre exacto (sin
     * mayúsculas) primero y, si no hay ninguna cargada con ese nombre, por el
     * nombre de archivo original que se subió (`typography_files.file_name`,
     * ej. "Oswald-Regular.ttf") como aproximación. Null si no hay match en
     * ningún lado — ahí el texto queda con `font_id: null` como hasta ahora,
     * para asignar a mano en el editor (la tipografía real puede no estar
     * cargada todavía en el catálogo).
     */
    private function resolveTypographyIdForFamily(string $family): ?int
    {
        $family = trim($family);
        if ($family === '') {
            return null;
        }

        $typography = Typography::whereRaw('LOWER(name) = ?', [mb_strtolower($family)])->first();
        if ($typography) {
            return $typography->id;
        }

        $file = TypographyFile::where('file_name', 'like', "%{$family}%")->first();
        return $file?->typography_id;
    }

    /**
     * Cantidad de celdas circulares ("$plantilla['imagen']" + nombre, sin
     * fondo de color propio) que arma una vista legacy — viene literal en
     * su `.blade.php`: `<div class="circulo-principal"> @for ($i = 0; $i <
     * N; $i++) <div class="circulo-personaje">` (ver las vistas "spot",
     * "spot-and-maxi", "maxi-and-spot" y "spot-and-super-mini"). El número
     * varía por vista (20, 35, 10, ...), así que se lee del archivo en vez
     * de asumir un valor fijo.
     * Null si la vista no tiene esta sección.
     */
    private function detectSpotCellCount(string $viewPath): ?int
    {
        $viewPath = str_starts_with($viewPath, 'tematica/') ? $viewPath : "tematica/{$viewPath}";
        $bladePath = resource_path('views/' . $viewPath . '.blade.php');
        if (!is_file($bladePath)) {
            return null;
        }

        $content = file_get_contents($bladePath);
        if (!preg_match('/circulo-principal["\'][\s\S]{0,200}?@for\s*\(\s*\$i\s*=\s*0\s*;\s*\$i\s*<\s*(\d+)/', $content, $m)) {
            return null;
        }

        return (int) $m[1];
    }

    /**
     * Id del label_shape circular que representa estas celdas "spot" — por
     * nombre exacto ("Spot Grande", el que ya está cargado en el catálogo).
     * Null si todavía no existe esa forma — ahí no se toca nada (mismo
     * criterio que el resto de los matcheos de este importador: si no hay
     * nada para asignar, se deja como venía).
     */
    private function resolveSpotLabelShapeId(): ?int
    {
        return LabelShape::where('name', 'Spot Grande')->value('id');
    }

    /**
     * Le asigna `label_shape_id` (círculo) al elemento `background` de cada
     * una de las celdas "spot" detectadas — identificadas por ser la
     * grilla de `data.pages[].grids[]` cuya cantidad de celdas (cols×rows)
     * coincide EXACTO con la que arma esta vista puntual
     * (detectSpotCellCount()). El "fondo" que detecta el importador para
     * estas celdas es, en realidad, el color del PROPIO ícono (la vista no
     * les pinta fondo) — queda chico, pegado solo al ícono, con el nombre
     * aparte y mal acomodado. El círculo real tiene que englobar ícono +
     * nombre juntos (ver ejemplo real adjunto por el usuario: la etiqueta
     * final es UNA pieza circular con el dibujo arriba y el nombre abajo,
     * no un círculo chico pegado solo al ícono) — así que para cada celda
     * se recalcula el fondo desde cero: un cuadrado (para que el círculo
     * salga redondo, no ovalado) centrado en el CENTRO de la caja que
     * engloba ícono+texto, con un margen chico alrededor.
     */
    private function applySpotLabelShape(array &$page, int $spotCellCount, int $spotShapeId): void
    {
        $grids = $page['grids'] ?? [];
        $matchingGrid = null;
        foreach ($grids as $grid) {
            if (((int) $grid['cols']) * ((int) $grid['rows']) === $spotCellCount) {
                $matchingGrid = $grid;
                break;
            }
        }
        if (!$matchingGrid) {
            return;
        }

        $indexById = [];
        foreach ($page['elements'] as $index => $el) {
            $indexById[$el['id']] = $index;
        }

        $cellsIdLists = [$matchingGrid['template']];
        foreach ($matchingGrid['members'] as $member) {
            $ids = array_column(array_filter($member), 'id');
            if (!empty($ids)) {
                $cellsIdLists[] = $ids;
            }
        }

        // El nombre de esta celda ("NOMBRE APELLIDO") NO forma parte de la
        // grilla — la vista no le pinta fondo (ver nota de arriba), así que
        // en import() queda como texto SUELTO (PdfDesignImportService::
        // mergeLooseTextLines), nunca llega a asociarse a esta celda vía
        // template/members. Hay que encontrarlo aparte: el texto suelto más
        // cercano al ícono de la celda (en línea recta, centro a centro) es
        // el suyo — cada texto se "reclama" una sola vez para no asignarle
        // el mismo nombre a dos íconos distintos.
        $griddedIds = [];
        foreach ($grids as $g) {
            foreach ($g['template'] as $id) {
                $griddedIds[$id] = true;
            }
            foreach ($g['members'] as $member) {
                foreach (array_filter($member) as $entry) {
                    $griddedIds[$entry['id']] = true;
                }
            }
        }
        $looseTextIndexes = [];
        foreach ($page['elements'] as $index => $el) {
            if (($el['type'] ?? null) === 'text' && !isset($griddedIds[$el['id']])) {
                $looseTextIndexes[] = $index;
            }
        }

        $padding = 0.3; // cm de aire entre ícono+texto y el borde del círculo
        foreach ($cellsIdLists as $ids) {
            $backgroundIndex = null;
            $iconBox = null; // [minX, minY, maxX, maxY]
            foreach ($ids as $id) {
                $index = $indexById[$id] ?? null;
                if ($index === null) {
                    continue;
                }
                $el = $page['elements'][$index];
                if (($el['type'] ?? null) === 'background') {
                    $backgroundIndex = $index;
                    continue;
                }
                if (($el['type'] ?? null) !== 'icon') {
                    continue;
                }
                $iconBox = [$el['x_cm'], $el['y_cm'], $el['x_cm'] + $el['width_cm'], $el['y_cm'] + $el['height_cm']];
            }

            if ($backgroundIndex === null || !$iconBox) {
                continue;
            }
            $iconCenterX = ($iconBox[0] + $iconBox[2]) / 2;
            $iconCenterY = ($iconBox[1] + $iconBox[3]) / 2;

            // Texto suelto sin reclamar más cercano a ESTE ícono.
            $bestTextIndex = null;
            $bestDistance = INF;
            $bestKey = null;
            foreach ($looseTextIndexes as $key => $textIndex) {
                $t = $page['elements'][$textIndex];
                $textCenterX = $t['x_cm'] + $t['width_cm'] / 2;
                $textCenterY = $t['y_cm'] + $t['height_cm'] / 2;
                $distance = ($textCenterX - $iconCenterX) ** 2 + ($textCenterY - $iconCenterY) ** 2;
                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                    $bestTextIndex = $textIndex;
                    $bestKey = $key;
                }
            }

            $unionBox = $iconBox;
            if ($bestTextIndex !== null) {
                unset($looseTextIndexes[$bestKey]);
                $t = $page['elements'][$bestTextIndex];
                // El texto se recentra horizontalmente bajo el ícono (su
                // ancho/alto, estimados por cantidad de caracteres, se
                // mantienen — solo se corrige la posición) — mismo eje
                // vertical que en el PDF real de referencia.
                $textCenterX = $t['x_cm'] + $t['width_cm'] / 2;
                $page['elements'][$bestTextIndex]['x_cm'] = round($t['x_cm'] + ($iconCenterX - $textCenterX), 2);
                $t = $page['elements'][$bestTextIndex];
                $textBox = [$t['x_cm'], $t['y_cm'], $t['x_cm'] + $t['width_cm'], $t['y_cm'] + $t['height_cm']];
                $unionBox = [
                    min($iconBox[0], $textBox[0]),
                    min($iconBox[1], $textBox[1]),
                    max($iconBox[2], $textBox[2]),
                    max($iconBox[3], $textBox[3]),
                ];
            }

            $centerX = ($unionBox[0] + $unionBox[2]) / 2;
            $centerY = ($unionBox[1] + $unionBox[3]) / 2;
            $side = max($unionBox[2] - $unionBox[0], $unionBox[3] - $unionBox[1]) + $padding * 2;

            $page['elements'][$backgroundIndex]['x_cm'] = round($centerX - $side / 2, 2);
            $page['elements'][$backgroundIndex]['y_cm'] = round($centerY - $side / 2, 2);
            $page['elements'][$backgroundIndex]['width_cm'] = round($side, 2);
            $page['elements'][$backgroundIndex]['height_cm'] = round($side, 2);
            $page['elements'][$backgroundIndex]['label_shape_id'] = $spotShapeId;
            $page['elements'][$backgroundIndex]['color'] = ['mode' => 'hex', 'value' => '#FFFFFF'];
        }
    }

    /**
     * Nunca se persiste HTML/markup ejecutable proveniente del editor: el texto
     * de cada elemento (en cada página) se limpia y solo se aceptan tipos/campos
     * reconocidos.
     */
    /**
     * Solo para el listado: completa sheet/elements de las páginas "prestadas"
     * (data.pages[].source = {designId, pageId}) con el contenido de la página
     * fuente, para que el front arme las miniaturas sin pedir cada diseño
     * fuente aparte. Los diseños fuente que no vinieron en el listado se
     * traen en una sola consulta. Si la fuente ya no existe, sheet/elements
     * van en null. No se persiste nada: al guardar el front sigue mandando
     * solo source.
     */
    private function resolveSourcePages(array $designs): void
    {
        $sourceIds = [];
        foreach ($designs as $design) {
            foreach ($design->data['pages'] ?? [] as $page) {
                if (isset($page['source']['designId'])) {
                    $sourceIds[(int) $page['source']['designId']] = true;
                }
            }
        }
        if (!$sourceIds) {
            return;
        }

        $sourceDesigns = [];
        foreach ($designs as $design) {
            $sourceDesigns[$design->id] = $design;
        }
        $missing = array_diff(array_keys($sourceIds), array_keys($sourceDesigns));
        if ($missing) {
            ProductPdfDesign::whereIn('id', $missing)->get(['id', 'data'])
                ->each(function ($d) use (&$sourceDesigns) {
                    $sourceDesigns[$d->id] = $d;
                });
        }

        // Índice designId => pageId => página, armado antes de modificar nada
        // para no resolver contra páginas ya resueltas de este mismo listado.
        $pagesIndex = [];
        foreach (array_keys($sourceIds) as $designId) {
            if (!isset($sourceDesigns[$designId])) {
                continue;
            }
            foreach ($sourceDesigns[$designId]->data['pages'] ?? [] as $page) {
                if (isset($page['id'])) {
                    $pagesIndex[$designId][$page['id']] = $page;
                }
            }
        }

        foreach ($designs as $design) {
            $data = $design->data;
            if (empty($data['pages']) || !is_array($data['pages'])) {
                continue;
            }
            $changed = false;
            foreach ($data['pages'] as $i => $page) {
                if (!isset($page['source']['designId'])) {
                    continue;
                }
                $sourcePage = $pagesIndex[(int) $page['source']['designId']][$page['source']['pageId'] ?? ''] ?? null;
                $data['pages'][$i]['sheet'] = $sourcePage['sheet'] ?? null;
                $data['pages'][$i]['elements'] = $sourcePage['elements'] ?? null;
                $changed = true;
            }
            if ($changed) {
                $design->data = $data;
            }
        }
    }

    private function sanitizeDesignData(array $data): array
    {
        if (!empty($data['pages']) && is_array($data['pages'])) {
            $data['pages'] = array_map(function ($page) {
                if (!empty($page['elements']) && is_array($page['elements'])) {
                    $page['elements'] = PdfDesignSanitizer::sanitizeElements($page['elements']);
                }
                if (!empty($page['sheet']) && is_array($page['sheet'])) {
                    $page['sheet'] = PdfDesignSanitizer::sanitizeSheet($page['sheet']);
                }
                if (!empty($page['layout_groups']) && is_array($page['layout_groups'])) {
                    $page['layout_groups'] = PdfDesignSanitizer::sanitizeLayoutGroups($page['layout_groups']);
                }
                return $page;
            }, $data['pages']);
        }

        return $data;
    }

    /**
     * Errores de data.pages[].layout_groups (ya saneados) en el formato de
     * errores de validación: "data.pages.{i}.layout_groups.{j}" => [mensaje].
     * Vacío = todos los grupos son válidos.
     */
    private function layoutGroupErrors(array $data): array
    {
        $errors = [];

        foreach ($data['pages'] ?? [] as $pageIdx => $page) {
            if (!is_array($page) || !array_key_exists('layout_groups', $page) || $page['layout_groups'] === null) {
                continue;
            }
            if (!is_array($page['layout_groups'])) {
                $errors["data.pages.{$pageIdx}.layout_groups"] = ['layout_groups tiene que ser una lista.'];
                continue;
            }

            $groupErrors = PdfDesignSanitizer::validateLayoutGroups($page['layout_groups'], $page['elements'] ?? []);
            foreach ($groupErrors as $groupIdx => $message) {
                $errors["data.pages.{$pageIdx}.layout_groups.{$groupIdx}"] = [$message];
            }
        }

        return $errors;
    }
}

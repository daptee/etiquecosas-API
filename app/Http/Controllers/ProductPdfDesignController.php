<?php

namespace App\Http\Controllers;

use App\Models\AttributeValue;
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
     * Mismo mecanismo que SAMPLE_NOMBRE_APELLIDO, pero para las vistas
     * `tematica/stars/TRANSFER *` ("Nombres transfer con relieve"): a
     * diferencia de maxi/vinilo (nombre Y apellido, hasta 2 renglones), este
     * producto solo pide UN nombre de hasta 15 letras y SIEMPRE en un solo
     * renglón — un placeholder de 2 palabras ahí simula un caso que no
     * existe en una venta real y el nombre terminaba partido en 2 líneas
     * (ver mergeLooseTextLines/rotatedTextElement). 15 letras, sin espacios,
     * para ejercitar el ancho real con el nombre más largo posible.
     */
    private const SAMPLE_NOMBRE_SOLO = 'NOMBREDEEJEMPLO';

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
            $data = $this->sanitizeDesignData($this->withFills(PdfDesignImportService::import($tmpPath)));
        } catch (\Throwable $e) {
            return $this->error('No se pudo analizar el PDF: ' . $e->getMessage(), 500);
        }

        // Mismo motivo que en importFromProduct(): withFills()/sanitizeDesignData()
        // todavía modifican los elementos (agregan `fill`, fijan
        // `editable_by_customer` explícito) DESPUÉS de que import() calculó
        // el `sig` de cada celda de grilla — hay que recalcularlo acá, al
        // final, o "Generar grilla" nunca vuelve a tocar ninguna celda
        // importada.
        foreach ($data['pages'] as &$page) {
            $page = PdfDesignImportService::resignGrids($page);
        }
        unset($page);

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

        $resolved = $this->resolveTematicaForProduct($productId, $variantId);
        if (isset($resolved['error'])) {
            return $this->error($resolved['error'], 422);
        }
        $tematicaCoincidente = $resolved['tematicaCoincidente'];
        $tematicaId = $resolved['tematicaId'];
        $variantAttributeValueName = $resolved['variantAttributeValueName'];

        $product = Product::find($productId);
        if (!$product) {
            // Mismo caso que la variante de arriba: el rule "exists" valida
            // contra la tabla cruda, sin el global scope de SoftDeletes — un
            // producto borrado pasa la validación pero Product::find() (sí
            // soft-delete-aware) devuelve null.
            return $this->error('El producto elegido no existe o fue eliminado', 422);
        }
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

        // "stars/TRANSFER *" (ver SAMPLE_NOMBRE_SOLO) es la única familia de
        // vistas que pide un solo nombre corto en vez de nombre+apellido —
        // ahí no hay que simular un firstName/lastName que nunca va a existir.
        $esSoloNombre = collect($urlsMuestra)->contains(fn($u) => str_starts_with(ltrim($u, '/'), 'stars/'));
        $nombreMuestra = $esSoloNombre ? self::SAMPLE_NOMBRE_SOLO : self::SAMPLE_NOMBRE_APELLIDO;
        $firstNamesMuestra = $esSoloNombre ? [] : ['NOMBRE'];

        try {
            $samplePaths = EtiquetaService::generarEtiquetas(
                $ventaIdMuestra,
                $tematicaId,
                [$nombreMuestra],
                $productOrder,
                $tematicaCoincidente,
                $colorMuestra,
                $iconMuestra,
                now(),
                $firstNamesMuestra
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
            // vista/página y se aplica a TODO el texto de esa página. Única
            // excepción: "stars/TRANSFER *" fija esta tipografía SOLO para
            // `.name` (el texto del cliente) y declara otra totalmente
            // distinta (Helvetica, sin archivo propio) para "PEDIDO #"/
            // "Nombre Transfer -" (ver detectLegacyFontFamily) — ahí, más
            // abajo, no se aplica a ciegas a todo el texto de la página.
            $fontFamily = isset($viewPaths[$idx]) ? $this->detectLegacyFontFamily($viewPaths[$idx]) : self::DEFAULT_LEGACY_FONT_FAMILY;
            $fontId = $this->resolveTypographyIdForFamily($fontFamily);
            if ($esSoloNombre && !$fontId) {
                // "stars/TRANSFER *" es la única familia donde el catálogo
                // nuevo suele cargar la tipografía con el MISMO nombre que
                // ya usa esta temática en product_pdf.data.tematicas[].
                // typography ("Bold"/"Mayúscula"/"Cursiva" — ver los
                // attribute_values del atributo "Tipografía", cuyo metadata
                // apunta a esa misma typography), en vez del nombre del
                // archivo/CSS (@font-face) que detecta detectLegacyFontFamily
                // ("TitanOne"/"Antonio") — se prueba como alternativa cuando
                // el nombre del archivo no matcheó nada.
                $fontId = $this->resolveTypographyIdForFamily($tematicaCoincidente['typography'] ?? '');
            }

            // Las vistas "*-and-spot*"/"spot-and-*"/"spot/PERSONALIZABLE*"
            // traen una fila de celdas circulares (`circulo-personaje`, ver
            // la vista) que el fondo detectado NO reproduce como círculo
            // (nada en la vista le pone un color de fondo propio — el
            // "fondo" que sale es, en realidad, el color del PROPIO ícono).
            // Si hay un label_shape circular cargado, se lo asignamos a esas
            // celdas puntuales para que el editor las muestre/recorte como
            // círculo en vez de rectángulo.
            $spotCellCount = isset($viewPaths[$idx]) ? $this->detectSpotCellCount($viewPaths[$idx]) : null;
            $spotShape = $spotCellCount ? $this->resolveSpotLabelShape() : null;

            foreach ($imported['pages'] ?? [] as $page) {
                $page['name'] = 'Página ' . (count($pages) + 1);
                $iconFileIdxByElementId = $this->mapIconElementsToSequenceIndexes($page['elements'], $iconSequence);
                foreach ($page['elements'] as &$el) {
                    if (($el['type'] ?? null) === 'icon') {
                        $fileIdx = $iconFileIdxByElementId[$el['id']] ?? null;
                        $file = $fileIdx !== null ? ($imagenes[$fileIdx] ?? null) : null;
                        if ($file && is_file($file)) {
                            $catalogPath = "icons/Tematicas/{$tematicaLimpia}/" . basename($file);
                            $catalogId = $catalogIconIdByPath[$catalogPath] ?? null;
                            if ($catalogId) {
                                $exactIconIds[$el['id']] = $catalogId;
                            }
                        }
                    } elseif (($el['type'] ?? null) === 'text' && $fontId) {
                        if (!$esSoloNombre || ($el['content'] ?? null) === $nombreMuestra) {
                            $el['font_id'] = $fontId;
                        }
                    }
                }
                unset($el);

                if ($spotShape && $spotCellCount) {
                    $this->applySpotLabelShape($page, $spotCellCount, $spotShape, $viewPaths[$idx] ?? null);
                }

                $this->applyMaxiLabelShape($page, $viewPaths[$idx] ?? null);

                $this->applySuperMiniLabelShape($page, $viewPaths[$idx] ?? null);

                $this->applyViniloLabelShape($page, $viewPaths[$idx] ?? null);

                $this->applyViniloTextSizeRules($page);

                $this->applyLegacyTextSizeRules($page, $viewPaths[$idx] ?? null);

                if ($esSoloNombre) {
                    $this->applyStarsDecoration($page, $tematicaCoincidente);
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
        // `dynamic_field: "nombre_apellido"` (o "nombre", ver más abajo) (ver
        // EtiquetaService::resolverElementoDesign). El PDF de muestra usa un
        // placeholder fijo (self::SAMPLE_NOMBRE_APELLIDO/SAMPLE_NOMBRE_SOLO)
        // para que, detectándolo por contenido exacto, el elemento se guarde
        // YA marcado como dinámico — así sale igual que en una venta real, no
        // como un texto fijo que diga literalmente el placeholder.
        foreach ($pages as &$page) {
            foreach ($page['elements'] as &$el) {
                if (($el['type'] ?? null) !== 'text') {
                    continue;
                }
                if (($el['content'] ?? null) === self::SAMPLE_NOMBRE_APELLIDO) {
                    $el['value_mode'] = 'dynamic';
                    $el['dynamic_field'] = 'nombre_apellido';
                } elseif (($el['content'] ?? null) === self::SAMPLE_NOMBRE_SOLO) {
                    $el['value_mode'] = 'dynamic';
                    $el['dynamic_field'] = 'nombre';

                    // "stars/TRANSFER *" calcula el tamaño de letra del
                    // nombre por cantidad de caracteres EN PHP (ver
                    // EtiquetaService::getFontSize), no por clases CSS como
                    // el resto de las vistas (applyLegacyTextSizeRules) — acá
                    // no hay nada que leer de un .blade.php, los umbrales son
                    // los mismos literales de esa función. Sin esto quedaba
                    // un único tamaño fijo grabado (el medido para el nombre
                    // de prueba de 15 letras — el más chico de la escala),
                    // en vez de escalar hasta 78px para un nombre corto, como
                    // hace la vista legacy.
                    $rules = $this->buildStarsFontSizeRules($tematicaCoincidente['typography'] ?? '');
                    $el['font_size_rules'] = $rules;
                    $el['length_rules_enabled'] = true;
                    $el['length_rules'] = $rules;
                    $el['font_size_px'] = end($rules)['font_size_px'];

                    // El contenedor medido (7.62cm, el nombre de prueba de 15
                    // letras a 32px) queda corto para el editor: el badge
                    // "(X) Nombre" que marca un texto dinámico no entra en
                    // ese ancho y el ícono termina arriba de la palabra en
                    // vez de al lado. Se agranda a 11.75cm (pedido a mano, no
                    // hay un cálculo real detrás — es espacio de UI, no de
                    // impresión) manteniendo el mismo CENTRO visual que ya
                    // tenía, para no correr el nombre de lugar en la hoja.
                    $centerXCm = $el['x_cm'] + $el['width_cm'] / 2;
                    $el['width_cm'] = 11.75;
                    $el['x_cm'] = round($centerXCm - $el['width_cm'] / 2, 2);
                }
            }
            unset($el);
            if ($esSoloNombre) {
                $this->applyStarsNameClearance($page);
            }
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

            // Tiene que correr DESPUÉS del reemplazo de arriba: recién ahí el
            // contenido trae el placeholder "{{numero_pedido}}" por el que
            // se identifica este texto puntual (antes trae el número de
            // venta de muestra tal cual, sin nada reconocible), y recién
            // ahí su geometría es la DEFINITIVA (post resizeTextElementForContent).
            $this->applyViniloOrderNumberClearance($page);

            $this->bumpLowLineHeight($page);
        }
        unset($page);

        $data = $this->sanitizeDesignData($this->withFills(['pages' => $pages]));

        // Recalcula el `sig` de cada celda de las grillas a partir del
        // estado YA DEFINITIVO de los elementos (ver PdfDesignImportService::
        // resignGrids) — tiene que ser el ÚLTIMO paso antes de guardar, DESPUÉS
        // de sanitizeDesignData()/withFills(): ambos modifican todavía los
        // elementos (withFills() agrega `fill` a cada uno; sanitizeElements()
        // fija `editable_by_customer` explícito en false donde no estaba
        // seteado) — si el resignGrids corriera antes (como pasaba hasta
        // ahora), esos cambios posteriores desincronizaban el `sig` grabado
        // del real, y el editor trataba TODAS las celdas de la grilla
        // importada como "ya modificadas a mano" desde el instante cero:
        // "Generar grilla" no volvía a tocar ninguna celda existente al
        // cambiar filas/columnas/separación (quedaba como si la edición de
        // la grilla no se aplicara).
        foreach ($data['pages'] as &$page) {
            $page = PdfDesignImportService::resignGrids($page);
        }
        unset($page);

        $design = ProductPdfDesign::create([
            'label_shape_id' => $request->labelShapeId
                ?: $this->detectLabelShapeIdFromViewPath($viewPaths[0] ?? null)
                ?: $this->detectUniformLabelShapeId($data['pages']),
            'name' => ($request->name ?: $product->name)
                . ($variantAttributeValueName ? ' - ' . $variantAttributeValueName : '')
                . ' (importado)',
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

    /**
     * Resuelve la temática del PDF legacy (product_pdf.data.tematicas) para
     * un producto+variante — misma lógica para importar de cero
     * (importFromProduct) y para re-resolver íconos de un diseño YA
     * importado (fixImportedIcons), sin duplicarla en los dos lugares.
     * Variante null solo es válida si el producto tiene UNA sola temática
     * cargada. Devuelve ['error' => string] si no se pudo resolver.
     */
    private function resolveTematicaForProduct(int $productId, $variantId): array
    {
        $productPdf = ProductPdf::where('product_id', $productId)->first();
        if (!$productPdf) {
            return ['error' => 'Este producto no tiene un PDF legacy asociado (tabla product_pdf)'];
        }

        $tematicasGuardadas = $productPdf->data['tematicas'] ?? [];
        if (empty($tematicasGuardadas)) {
            return ['error' => 'Este producto no tiene temáticas cargadas en su PDF legacy'];
        }

        $variantAttributeValueName = null;

        if ($variantId) {
            $variant = ProductVariant::find($variantId);
            // El rule "exists" del caller valida contra la tabla cruda, sin
            // el global scope de SoftDeletes — una variante borrada pasa la
            // validación pero ProductVariant::find() (sí soft-delete-aware)
            // devuelve null, así que hay que distinguir este caso del de
            // "la variante existe pero no tiene atributos" para no confundir.
            if (!$variant) {
                return ['error' => 'La variante elegida no existe o fue eliminada'];
            }
            $tematicaId = $variant->variant['attributesvalues'][0]['id'] ?? null;
            if (!$tematicaId) {
                return ['error' => 'No se pudo determinar la temática de la variante elegida'];
            }
            $tematicaCoincidente = collect($tematicasGuardadas)->firstWhere('id', $tematicaId);
            if (!$tematicaCoincidente) {
                return ['error' => 'La variante elegida no tiene una temática asociada en este producto'];
            }
            $variantAttributeValueName = AttributeValue::find($tematicaId)?->value;
        } elseif (count($tematicasGuardadas) === 1) {
            $tematicaCoincidente = $tematicasGuardadas[0];
            $tematicaId = $tematicaCoincidente['id'] ?? null;
        } else {
            return ['error' => 'Este producto tiene ' . count($tematicasGuardadas) . ' temáticas distintas — hay que elegir una variante (variantId) para saber cuál importar'];
        }

        return [
            'tematicaCoincidente' => $tematicaCoincidente,
            'tematicaId' => $tematicaId,
            'variantAttributeValueName' => $variantAttributeValueName,
        ];
    }

    /**
     * Re-resuelve SOLO el `icon_id` de los elementos `icon` de un diseño YA
     * importado (vinculado a este producto+variante vía
     * ProductPdfDesignProduct.theme_key = variantId), sin tocar nada más del
     * diseño. Pensado para corregir, sin reimportar de cero, los diseños de
     * vinilo "x2"/"x3" (varias columnas) importados ANTES del fix de
     * mapIconElementsToSequenceIndexes(): extractIconFileSequence() lee el
     * .blade.php crudo y solo trae UNA vuelta de la secuencia de íconos (el
     * cuerpo del `@for` de columnas nunca usa el índice de iteración), así
     * que con el mapeo viejo (por posición de aparición) solo la primera
     * columna quedaba bien — de ahí en más caía al fallback por nombre
     * aproximado (round-robin), con íconos salteados/repetidos sin relación
     * con la celda real.
     *
     * A propósito NO reimporta el PDF de cero (no vuelve a tocar fondos,
     * textos ni posiciones): solo pisa `icon_id` usando la geometría
     * (x_cm/y_cm) YA guardada en el diseño, así que preserva cualquier
     * edición manual hecha desde que se importó.
     */
    public function fixImportedIcons(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'productId' => 'required|exists:products,id',
            'variantId' => 'nullable|exists:product_variants,id',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Fix Imported Icons', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $productId = $request->productId;
        $variantId = $request->variantId;

        $link = ProductPdfDesignProduct::where('product_id', $productId)
            ->when($variantId, fn($q) => $q->where('theme_key', $variantId))
            ->when(!$variantId, fn($q) => $q->whereNull('theme_key'))
            ->first();
        if (!$link) {
            return $this->error('No hay un diseño importado vinculado a este producto' . ($variantId ? ' y esta variante' : ''), 422);
        }

        $design = ProductPdfDesign::find($link->product_pdf_design_id);
        if (!$design) {
            return $this->error('El diseño vinculado ya no existe', 422);
        }

        $resolved = $this->resolveTematicaForProduct($productId, $variantId);
        if (isset($resolved['error'])) {
            return $this->error($resolved['error'], 422);
        }
        $tematicaCoincidente = $resolved['tematicaCoincidente'];

        $urlPdf = $tematicaCoincidente['pdf-url'] ?? null;
        $viewPaths = is_array($urlPdf) ? $urlPdf : ($urlPdf ? [$urlPdf] : []);
        $tematicaLimpia = strtoupper(EtiquetaService::limpiarNombreArchivo($tematicaCoincidente['name'] ?? ''));
        $imagenes = $this->buildTematicaImagenes($tematicaCoincidente['name'] ?? '');

        $catalogIconIdByPath = PersonalizationIcon::where('icon', 'like', 'icons/Tematicas/%')
            ->pluck('id', 'icon')
            ->all();

        $data = $design->data;
        if (empty($data['pages'])) {
            return $this->error('El diseño vinculado no tiene páginas', 422);
        }

        $fixedCount = 0;
        // OJO: iterar "$data['pages'] ?? []" por referencia acá sería un
        // error — el operador "??" arma un array TEMPORAL cuando la clave sí
        // existe pero no es null, y "&$page" terminaría modificando esa
        // copia descartable, nunca "$data['pages']" real (el `update()` de
        // más abajo guardaría los íconos sin corregir). Por eso arriba se
        // valida que "pages" exista ANTES del loop, en vez de usar "??" acá.
        foreach ($data['pages'] as $idx => &$page) {
            $iconSequence = isset($viewPaths[$idx]) ? $this->extractIconFileSequence($viewPaths[$idx]) : [];
            if (empty($iconSequence)) {
                continue;
            }

            $iconFileIdxByElementId = $this->mapIconElementsToSequenceIndexes($page['elements'], $iconSequence);
            foreach ($page['elements'] as &$el) {
                if (($el['type'] ?? null) !== 'icon') {
                    continue;
                }
                $fileIdx = $iconFileIdxByElementId[$el['id']] ?? null;
                $file = $fileIdx !== null ? ($imagenes[$fileIdx] ?? null) : null;
                if (!$file || !is_file($file)) {
                    continue;
                }
                $catalogPath = "icons/Tematicas/{$tematicaLimpia}/" . basename($file);
                $catalogId = $catalogIconIdByPath[$catalogPath] ?? null;
                if ($catalogId && $catalogId !== ($el['icon_id'] ?? null)) {
                    $el['icon_id'] = $catalogId;
                    $fixedCount++;
                }
            }
            unset($el);
        }
        unset($page);

        if ($fixedCount === 0) {
            $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);
            return $this->success($design, 'No se encontró ningún ícono para corregir (ya estaban bien, o esta vista no tiene el patrón $plantilla[imagen][N])');
        }

        // Recalcula el `sig` de cada celda de grilla: el `icon_id` que
        // acabamos de pisar es parte de esa firma (ver elementSignature()) —
        // si no se recalcula, el editor trata estas celdas como
        // "modificadas a mano" desde antes del fix y "Generar grilla" deja
        // de tocarlas.
        foreach ($data['pages'] as &$page) {
            $page = PdfDesignImportService::resignGrids($page);
        }
        unset($page);

        $design->update(['data' => $this->sanitizeDesignData($data)]);
        $design->load(['products:id,name,sku', 'labelShape', 'generalStatus']);

        $this->logAudit(Auth::user(), 'Fix Imported Icons', $request->all(), ['designId' => $design->id, 'fixedCount' => $fixedCount]);
        return $this->success($design, "Se corrigieron {$fixedCount} ícono(s) del diseño");
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
            // Fondo del elemento (ver PDF_FONDO_ELEMENTOS.md). El contenido
            // lo termina de limpiar PdfDesignSanitizer::sanitizeFill().
            'data.pages.*.elements.*.fill' => 'nullable|array',
            'data.pages.*.elements.*.fill.type' => 'required_with:data.pages.*.elements.*.fill|in:' . implode(',', PdfDesignSanitizer::ALLOWED_FILL_TYPES),
            'data.pages.*.elements.*.fill.color' => 'required_if:data.pages.*.elements.*.fill.type,color|array',
            'data.pages.*.elements.*.fill.color.mode' => 'nullable|in:hex,cmyk',
            'data.pages.*.elements.*.fill.color.value' => 'required_with:data.pages.*.elements.*.fill.color|string|max:64',
            'data.pages.*.elements.*.fill.image' => 'required_if:data.pages.*.elements.*.fill.type,image|string|starts_with:pdf-element-images/',
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
    /**
     * Mapea cada elemento `icon` detectado (geometría real del PDF) al índice
     * de `$plantilla['imagen'][N]` que le corresponde, a partir de la
     * secuencia extraída del .blade.php por `extractIconFileSequence()`.
     *
     * Esa secuencia trae UNA sola vuelta — el cuerpo del `@for ($i = 0; $i <
     * $plantilla['columna']; $i++)` de las vistas de vinilo nunca usa `$i`
     * (ver resources/views/tematica/vinilo/*.blade.php), así que cada
     * columna repite EXACTAMENTE el mismo ícono fila por fila. Un mapeo
     * directo por posición de aparición (`$iconSequence[$posición]`) solo
     * cubre la primera columna: a partir de la celda número `count(
     * $iconSequence)` ya no hay índice para esa posición y el ícono exacto
     * se pierde (cae al fallback por nombre aproximado, round-robin) —
     * pasaba con cualquier temática vendida "x2"/"x3" (ver
     * product_pdf.data.tematicas[].number-columns > 1), no con "x1".
     *
     * Agrupando los íconos por columna (mismo x_cm) y repitiendo la
     * secuencia fila a fila (y_cm ascendente) DENTRO de cada columna se
     * reproduce el ícono real sea cual sea el orden en que
     * PdfDesignImportService fue devolviendo los elementos.
     */
    private function mapIconElementsToSequenceIndexes(array $elements, array $iconSequence): array
    {
        if (empty($iconSequence)) {
            return [];
        }

        $iconEls = array_values(array_filter($elements, fn($el) => ($el['type'] ?? null) === 'icon'));
        if (empty($iconEls)) {
            return [];
        }

        // El x_cm/y_cm del FONDO de la celda (mismo group_id que el ícono,
        // ver PdfDesignImportService::import()) es un ancla mucho más
        // confiable que el x_cm del propio ícono para agrupar por columna:
        // cada imagen real tiene su propio ancho intrínseco y la celda la
        // centra (".imagen"/"personajeAlto" en vinilo.css), así que el x_cm
        // del ÍCONO varía unas décimas de una fila a otra DENTRO de la misma
        // columna visual (ej. 2.49, 2.52, 2.59, 2.61, 2.65cm) — suficiente
        // para que agrupar por esa coordenada parta una sola columna en
        // varios grupos chicos y mezcle filas de columnas distintas. El
        // fondo, en cambio, es un rect de tamaño fijo siempre en la misma
        // grilla de x, así que agrupa sin ambigüedad.
        $backgroundsByGroupId = [];
        foreach ($elements as $el) {
            if (($el['type'] ?? null) === 'background' && !empty($el['group_id'])) {
                $backgroundsByGroupId[$el['group_id']] = $el;
            }
        }

        $columns = [];
        foreach ($iconEls as $el) {
            $anchor = $backgroundsByGroupId[$el['group_id'] ?? null] ?? $el;
            // Clave entera (x_cm * 10, redondeado) en vez de un float con 1
            // decimal: un array key float se trunca silenciosamente a int en
            // PHP, así que 2.5 y 2.9 terminarían pisándose en la misma
            // clave `2` (mismo footgun ya resuelto en bumpLowLineHeight()).
            $columns[(int) round((float) $anchor['x_cm'] * 10)][] = ['icon' => $el, 'y_cm' => (float) $anchor['y_cm']];
        }
        ksort($columns);

        $map = [];
        foreach ($columns as $colEls) {
            usort($colEls, fn($a, $b) => $a['y_cm'] <=> $b['y_cm']);
            foreach (array_values($colEls) as $row => $entry) {
                $map[$entry['icon']['id']] = $iconSequence[$row % count($iconSequence)];
            }
        }

        return $map;
    }

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
     * El interlineado real del nombre en las celdas "spot" — viene literal
     * en el CSS de estas vistas: `.circulo-texto { line-height: 0.7; }`
     * (igual en "spot", "spot-and-maxi", "maxi-and-spot" y
     * "spot-and-super-mini", "SIN ICONO" incluidas). Medirlo de las líneas
     * reales del PDF de muestra (lo que hace `PdfDesignImportService::
     * textsToTextElement` para el resto del texto, ver
     * PDF_IMPORTAR_DESDE_PDF.md) es una estimación — y en esta fila
     * puntual, por la particularidad de dompdf que afecta la primera celda
     * de una fila (ver más abajo, donde se normaliza por moda), puede salir
     * bastante lejos del real. Leer el valor directo del CSS es exacto, así
     * que tiene prioridad sobre la medición cuando está disponible. Null si
     * la vista no declara esta regla (se sigue con el valor medido).
     */
    private function detectSpotLineHeight(string $viewPath): ?float
    {
        $viewPath = str_starts_with($viewPath, 'tematica/') ? $viewPath : "tematica/{$viewPath}";
        $bladePath = resource_path('views/' . $viewPath . '.blade.php');
        if (!is_file($bladePath)) {
            return null;
        }

        $content = file_get_contents($bladePath);
        if (!preg_match('/\.circulo-texto\s*\{[^}]*?line-height:\s*([\d.]+)/', $content, $m)) {
            return null;
        }

        return (float) $m[1];
    }

    /**
     * El label_shape circular que representa estas celdas "spot" — por
     * nombre exacto ("Spot", el que ya está cargado en el catálogo, 3×3cm
     * — el mismo tamaño que declara `.circulo-personaje` en el CSS de estas
     * vistas). Null si todavía no existe esa forma — ahí no se toca nada
     * (mismo criterio que el resto de los matcheos de este importador: si
     * no hay nada para asignar, se deja como venía).
     */
    private function resolveSpotLabelShape(): ?LabelShape
    {
        return LabelShape::where('name', 'Spot')->first();
    }

    /**
     * El `label_shape_id` del DISEÑO entero (`product_pdf_designs.label_shape_id`,
     * distinto del de cada elemento de fondo) — por nombre de carpeta de la
     * vista (`$viewPaths[0]`, ej. `"maxi-and-super-mini/PERSONALIZABLE"`): se
     * parte el primer segmento (antes de la `/`) por `"-and-"` y se usa la
     * PRIMERA palabra que matchee una forma conocida — en un combo, la que
     * aparece primero en el nombre "gana" (ej. `"maxi-and-super-mini"` →
     * Maxi Combinada; `"spot-and-super-mini"`/`"spot-and-maxi"` → Spot). Para
     * una vista sin combo (`"super-mini"`, `"spot"`, `"maxi"`) hay un solo
     * segmento, así que no hay ambigüedad. Null si el primer segmento no
     * matchea ninguna forma conocida, o si esa forma todavía no existe en el
     * catálogo.
     */
    private function detectLabelShapeIdFromViewPath(?string $viewPath): ?int
    {
        if (!$viewPath) {
            return null;
        }
        $viewPath = str_starts_with($viewPath, 'tematica/') ? substr($viewPath, strlen('tematica/')) : $viewPath;
        $folder = explode('/', $viewPath)[0] ?? '';

        $shapeNameBySegment = [
            'maxi' => 'Maxi Combinada',
            'super-maxi' => 'Super Maxi',
            'super-mini' => 'Super Mini',
            'spot' => 'Spot',
            'vinilo' => 'Vinilo',
        ];

        foreach (explode('-and-', $folder) as $segment) {
            if (isset($shapeNameBySegment[$segment])) {
                $shape = LabelShape::where('name', $shapeNameBySegment[$segment])->first();
                if ($shape) {
                    return $shape->id;
                }
            }
        }

        return null;
    }

    /**
     * Respaldo de `detectLabelShapeIdFromViewPath()` para cuando el nombre de
     * la vista no matchea ninguna forma conocida: si TODOS los fondos a los
     * que `applySpotLabelShape()`/`applyMaxiLabelShape()`/
     * `applySuperMiniLabelShape()` les asignaron una forma (en cualquier
     * página) coinciden en la MISMA, se usa esa. Si hay dos o más formas
     * distintas mezcladas (no debería pasar si el nombre ya resolvió algo),
     * se deja sin asignar (null) antes que adivinar.
     */
    private function detectUniformLabelShapeId(array $pages): ?int
    {
        $shapeIds = [];
        foreach ($pages as $page) {
            foreach ($page['elements'] ?? [] as $el) {
                if (($el['type'] ?? null) === 'background' && !empty($el['label_shape_id'])) {
                    $shapeIds[$el['label_shape_id']] = true;
                }
            }
        }

        return count($shapeIds) === 1 ? (int) array_key_first($shapeIds) : null;
    }

    /**
     * El label_shape "Maxi Combinada" del catálogo (id=11, `css_class:
     * "etiqueta-maxi-combinada"`, 5.2×1.9cm) representa el combo ícono+texto
     * de las vistas "maxi" como UNA sola etiqueta física — este tamaño SÍ es
     * el real de `.etiqueta-maxi` en `maxi-and-spot/PERSONALIZABLE.blade.php`
     * y vistas combo equivalentes (a diferencia del catálogo "Maxi", id 2,
     * que corresponde a una celda sin relación en `principal/SKATE.blade.php`
     * — ver docblock de `applyMaxiLabelShape()`). Null si todavía no existe
     * esa forma (mismo criterio que `resolveSpotLabelShape()`).
     */
    private function resolveMaxiCombinadaLabelShape(): ?LabelShape
    {
        return LabelShape::where('name', 'Maxi Combinada')->first();
    }

    /**
     * El label_shape "Super Mini" del catálogo (id=5, `css_class:
     * "etiqueta-super-mini"`, 2.90×1.15cm). A diferencia de "Maxi", acá el
     * tamaño del catálogo SÍ coincide exacto con el real de todas las vistas
     * que dibujan esta etiqueta (confirmado contra el CSS: `.etiqueta-super-
     * mini` en `public/css/etiquetas.css`/`tematica/principal/SKATE.blade.php`
     * y `.super-mini` en las vistas combo, las dos declaran `width: 2.9cm;
     * height: 1.15cm;`) — mismo criterio que `resolveSpotLabelShape()`.
     */
    private function resolveSuperMiniLabelShape(): ?LabelShape
    {
        return LabelShape::where('name', 'Super Mini')->first();
    }

    /**
     * Le asigna `label_shape_id` (rectángulo "Super Mini") al elemento
     * `background` de cada celda "super mini" detectada en esta vista — sin
     * reposicionar ni redimensionar nada (a diferencia de `applySpotLabelShape()`
     * / `applyMaxiLabelShape()`): acá el fondo que ya detecta el importador
     * ES la etiqueta real, del mismo tamaño que el catálogo (ver
     * `resolveSuperMiniLabelShape()`), así que no hace falta reconstruir nada,
     * solo etiquetarlo.
     *
     * El nombre de la clase HTML varía según la vista: las que dibujan ESTA
     * etiqueta como tema único (`tematica/super-mini/*`) y el combo triple
     * (`maxi-and-super-maxi-and-super-mini`) usan `etiqueta-super-mini`; los
     * combos de a dos (`maxi-and-super-mini`, `spot-and-super-mini`) la
     * dibujan con una clase corta sin el prefijo, `super-mini` (mismo tamaño
     * en CSS, ver docblock de `resolveSuperMiniLabelShape()`).
     *
     * A diferencia de `applySpotLabelShape()`, la grilla NO se identifica
     * por cantidad de celdas (`countRepeatedClassOccurrences()`): varias de
     * estas vistas (`tematica/super-mini/*`, la sección "super mini" del
     * combo triple) repiten la fila de 6 celdas escribiendo 6 `<td>` sueltos
     * a mano en vez de un `@for` de columnas, y la cantidad de FILAS sale de
     * una variable PHP en runtime (`$plantilla['filas']`), no de un número
     * literal — nada de eso se puede contar leyendo el .blade.php en
     * estático. En cambio, se aprovecha que el tamaño real de esta etiqueta
     * coincide EXACTO con el del catálogo (ver `resolveSuperMiniLabelShape()`):
     * alcanza con confirmar que la vista use la clase en algún lado (para no
     * etiquetar por error una coincidencia de tamaño en una vista sin
     * relación) y asignarle la forma a CUALQUIER grilla cuya celda mida
     * 2.90×1.15cm — no hace falta saber cuántas filas/columnas tiene.
     */
    private function applySuperMiniLabelShape(array &$page, ?string $viewPath): void
    {
        if (!$viewPath || empty($page['grids'])) {
            return;
        }
        $superMiniShape = $this->resolveSuperMiniLabelShape();
        if (!$superMiniShape) {
            return;
        }

        $normalizedViewPath = str_starts_with($viewPath, 'tematica/') ? $viewPath : "tematica/{$viewPath}";
        $bladePath = resource_path('views/' . $normalizedViewPath . '.blade.php');
        if (!is_file($bladePath)) {
            return;
        }
        $content = file_get_contents($bladePath);
        if (!preg_match('/class=["\'][^"\']*\bsuper-mini\b[^"\']*["\']/', $content)) {
            return;
        }

        $targetWidth = round((float) $superMiniShape->width_cm, 1);
        $targetHeight = round((float) $superMiniShape->height_cm, 1);

        $indexById = [];
        foreach ($page['elements'] as $index => $el) {
            $indexById[$el['id']] = $index;
        }

        foreach ($page['grids'] as $grid) {
            $templateBgIndex = null;
            foreach ($grid['template'] as $id) {
                $index = $indexById[$id] ?? null;
                if ($index !== null && ($page['elements'][$index]['type'] ?? null) === 'background') {
                    $templateBgIndex = $index;
                    break;
                }
            }
            if ($templateBgIndex === null) {
                continue;
            }
            $templateBg = $page['elements'][$templateBgIndex];
            if (round((float) $templateBg['width_cm'], 1) !== $targetWidth || round((float) $templateBg['height_cm'], 1) !== $targetHeight) {
                continue;
            }

            $cellIdLists = [$grid['template']];
            foreach ($grid['members'] as $member) {
                $cellIdLists[] = array_column(array_filter($member), 'id');
            }

            foreach ($cellIdLists as $ids) {
                foreach ($ids as $id) {
                    $index = $indexById[$id] ?? null;
                    if ($index === null || ($page['elements'][$index]['type'] ?? null) !== 'background') {
                        continue;
                    }
                    $page['elements'][$index]['label_shape_id'] = $superMiniShape->id;
                }
            }
        }
    }

    /**
     * El label_shape "Vinilo" del catálogo (id=8, rect, 5.40×1.80cm,
     * `data.corner_radius_cm: 0.61`) — coincide exacto con `.icon-cuadro` de
     * `public/css/vinilo.css` (`width: 5.4cm; height: 1.8cm; border-radius:
     * 23px`, ≈0.61cm), la única clase que usan todas las vistas
     * `tematica/vinilo/*.blade.php`.
     */
    private function resolveViniloLabelShape(): ?LabelShape
    {
        return LabelShape::where('name', 'Vinilo')->first();
    }

    /**
     * Le asigna `label_shape_id` (rectángulo "Vinilo") a cada celda de las
     * vistas `tematica/vinilo/*` — mismo criterio que
     * `applySuperMiniLabelShape()` (el fondo que ya detecta el importador ES
     * la etiqueta real, del mismo tamaño que el catálogo, solo hay que
     * etiquetarlo). Sin esto el importado quedaba SIN forma asignada, así
     * que al generar el PDF no había de dónde sacar el `corner_radius_cm`
     * (ver `resolved_shape_corner_radius_cm` en RENDER.blade.php) y la
     * etiqueta salía con las esquinas rectas en vez del redondeado real.
     *
     * A diferencia de `applySuperMiniLabelShape()`, acá no hace falta buscar
     * una clase CSS puntual en el HTML (`.icon-cuadro` no tiene "vinilo" en
     * el nombre) — alcanza con que la vista sea del directorio `vinilo/`
     * (`$viewPath` ya viene de ahí para cualquier producto con esta
     * temática) para saber que CUALQUIER grilla de esta página es esta
     * forma.
     */
    /**
     * Reproduce EtiquetaService::getFontSize() — único caso en todo el
     * sistema donde el tamaño de letra del nombre se calcula en PHP por
     * cantidad de caracteres (5 escalones), no por clases CSS como el resto
     * de las vistas (ver applyLegacyTextSizeRules). Los umbrales/tamaños son
     * LITERALES de esa función — no hay ningún .blade.php/CSS que leer acá,
     * por eso van a mano en vez de extraerse de un archivo.
     */
    private function buildStarsFontSizeRules(string $typography): array
    {
        $tiers = strtoupper($typography) === 'BOLD'
            ? [5 => 78, 6 => 70, 9 => 46, 11 => 36, 15 => 32]
            : [5 => 90, 6 => 82, 9 => 68, 11 => 52, 15 => 46];

        $rules = [];
        foreach ($tiers as $maxChars => $fontSizePx) {
            $rules[] = ['max_chars' => $maxChars, 'font_size_px' => $fontSizePx];
        }

        return $rules;
    }

    /**
     * Post-proceso específico de `tematica/stars/TRANSFER *` (ver
     * SAMPLE_NOMBRE_SOLO/$esSoloNombre en importFromProduct): esta vista
     * tiene 2 elementos decorativos que el importador genérico interpreta
     * mal porque ninguno llega como imagen `Do` (dompdf los dibuja como
     * trazos vectoriales planos, ver PdfDesignImportService::import, o — si
     * el archivo no se pudo cargar al generar el PDF de muestra — como el
     * ícono "roto" típico del renderer):
     *
     * - Las 4 "estrellitas" de color (indicador de la combinación de
     *   colores elegida, `$plantilla['images'][N]`) se identifican por
     *   TAMAÑO/orden, no por si vienen solas o emparejadas con un ícono —
     *   se reconvierten a `icon` apuntando al ícono real del catálogo
     *   (`icons/Tematicas/STARS/star-*.svg`, el color ya viene pintado en el
     *   archivo). A diferencia de buildTematicaImagenes() (que escanea una
     *   carpeta con el nombre de la temática, pensado para vinilo/principal/
     *   etc.), acá la carpeta del ícono SIEMPRE es "STARS" sin importar cómo
     *   se llame la temática (una combinación de colores, ej. "Verde agua,
     *   celeste, rojo..."); el archivo exacto de cada estrella viene directo
     *   en $tematicaCoincidente['images'], en el mismo orden (izquierda a
     *   derecha, por x_cm) en que la vista las dibuja.
     * - El logo de Etiquecosas (el único `icon` real que detecta la vista:
     *   `<img class="logo" src="{{ public_path('icons/etiquecosas.svg') }}">`)
     *   no es elegible por el cliente ni varía por tematica — siempre el
     *   mismo archivo fijo, así que se le asigna por `custom_icon_path` en
     *   vez de dejarlo con el icon_id de relleno de un ícono cualquiera del
     *   catálogo editable. Se identifica por tamaño (1.3x1.3cm, el único
     *   más grande que cualquier estrella), NUNCA tomando "el primer ícono
     *   que sobra" — eso agarraba cualquier estrella al azar si la cantidad
     *   de fondos de estrella detectados no coincidía exactamente con
     *   count(images) (ver nota en el código). El rect que queda agrupado
     *   debajo (fragmento mal interpretado del propio SVG de 2 colores del
     *   logo — la vista no le pone ningún fondo real) se descarta.
     */
    private function applyStarsDecoration(array &$page, array $tematicaCoincidente): void
    {
        $images = $tematicaCoincidente['images'] ?? [];
        if (empty($images)) {
            return;
        }

        $backgrounds = array_values(array_filter($page['elements'], fn($el) => ($el['type'] ?? null) === 'background'));
        if (count($backgrounds) < count($images) + 1) {
            // Tienen que estar los fondos de las N estrellas Y el del logo —
            // si falta alguno, mejor no tocar nada que adivinar mal.
            return;
        }

        // El logo (1.3x1.3cm, ver ".logo { width:1.3cm; height:1.3cm }") es
        // siempre más grande que cualquier estrella (24px=~0.63cm, ver
        // ".star-img") — se identifica por tamaño (el más cercano a 1.3cm),
        // nunca por "el primer ícono que sobra": si alguna vez no entran
        // exactamente `count($images)` fondos de estrella (ver más abajo,
        // ej. una estrella vino emparejada con un ícono roto en vez de
        // sola), ESE criterio terminaba agarrando cualquier estrella al
        // azar y la convertía en el logo.
        usort($backgrounds, fn($a, $b) =>
            abs((float) $a['width_cm'] - 1.3) <=> abs((float) $b['width_cm'] - 1.3)
        );
        $logoBackground = $backgrounds[0];
        $starBackgrounds = array_values(array_filter($backgrounds, fn($el) => $el['id'] !== $logoBackground['id']));

        // Las estrellas pueden llegar sueltas (sin ícono emparejado, el caso
        // más común) o con un ícono-fragmento emparejado por group_id (ej.
        // si el SVG de la estrella no se pudo cargar al generar el PDF de
        // muestra y el renderer dibujó el ícono roto típico — un cuadrado
        // con una marca adentro) — en ambos casos el FONDO reclasificado de
        // abajo es el que pasa a ser el ícono real; si había un ícono
        // emparejado, sobra y se descarta.
        $starIdByElementId = [];
        $obsoleteIconIds = [];
        if (count($starBackgrounds) === count($images)) {
            usort($starBackgrounds, fn($a, $b) => $a['x_cm'] <=> $b['x_cm']);

            $catalogIconIdByPath = PersonalizationIcon::where('icon', 'like', 'icons/Tematicas/STARS/%')
                ->pluck('id', 'icon')
                ->all();

            foreach ($starBackgrounds as $i => $bg) {
                $catalogPath = 'icons/Tematicas/STARS/' . basename($images[$i]);
                $catalogId = $catalogIconIdByPath[$catalogPath] ?? null;
                if ($catalogId) {
                    $starIdByElementId[$bg['id']] = $catalogId;
                }
                if (!empty($bg['group_id'])) {
                    foreach ($page['elements'] as $el) {
                        if (($el['type'] ?? null) === 'icon' && ($el['group_id'] ?? null) === $bg['group_id']) {
                            $obsoleteIconIds[] = $el['id'];
                        }
                    }
                }
            }

            foreach ($page['elements'] as &$el) {
                if (isset($starIdByElementId[$el['id']])) {
                    $el['type'] = 'icon';
                    $el['z_index'] = 1;
                    unset($el['color'], $el['fill'], $el['border'], $el['group_id']);
                    $el['icon_id'] = $starIdByElementId[$el['id']];
                    // ".star { transform: rotate(-22.5deg) translateY(-50%); }"
                    // — las 4 comparten el mismo ángulo fijo (no están en
                    // abanico, solo una fila con distinto `left`), así que se
                    // copia literal de ese CSS, no hay nada que medir del PDF.
                    $el['rotation_deg'] = -22.5;
                }
            }
            unset($el);
        }

        // El logo de Etiquecosas (el único `icon` real que detecta la
        // vista) no es elegible por el cliente ni varía por temática —
        // siempre el mismo archivo fijo, así que se le asigna por
        // `custom_icon_path` en vez de dejarlo con el icon_id de relleno de
        // un ícono cualquiera del catálogo editable. Se usa la caja del
        // FONDO (cuadrada) en vez de la del ícono emparejado: ese ícono solo
        // capta la tinta de una PARTE del SVG (ej. el texto "etiquecosas",
        // sin el círculo que lo rodea), así que su propia caja sale angosta
        // y no cuadrada — con la del fondo el logo se ve redondo/completo.
        $logoGroupId = $logoBackground['group_id'] ?? null;
        foreach ($page['elements'] as &$el) {
            $isLogoIcon = ($el['type'] ?? null) === 'icon'
                && $logoGroupId !== null
                && ($el['group_id'] ?? null) === $logoGroupId;
            if ($isLogoIcon) {
                unset($el['icon_id'], $el['group_id']);
                $el['custom_icon_path'] = 'icons/etiquecosas.svg';
                $el['x_cm'] = $logoBackground['x_cm'];
                $el['y_cm'] = $logoBackground['y_cm'];
                $el['width_cm'] = $logoBackground['width_cm'];
                $el['height_cm'] = $logoBackground['height_cm'];
            }
        }
        unset($el);

        $discardIds = $obsoleteIconIds;
        $discardIds[] = $logoBackground['id'];

        $page['elements'] = array_values(array_filter(
            $page['elements'],
            fn($el) => !in_array($el['id'] ?? null, $discardIds, true)
        ));
    }

    /**
     * Los 4 nombres (girados -90°, ver mergeLooseTextLines/
     * rotatedTextElement) quedaban pegados contra la fila de arriba
     * (estrellas, logo, "PEDIDO #"/"Nombre Transfer -"): su caja SIN GIRAR
     * mide bastante más que el nombre de prueba de 15 letras (ver el
     * ensanche a 11.75cm en importFromProduct, que corre DESPUÉS de
     * applyStarsDecoration — por eso esto va aparte y no ahí, tiene que
     * correr con el ancho YA ensanchado) y el centro quedó calculado para
     * ESE ancho — al girar, el borde superior real (visual) termina más
     * arriba de lo que midió rotatedTextElement. Se calcula el borde
     * inferior real de todo lo que NO es un nombre (estrellas/logo ya
     * reclasificados por applyStarsDecoration, "PEDIDO #"/"Nombre Transfer
     * -" ya con su rotation_deg=180) y se empuja cada nombre hacia abajo lo
     * que haga falta para no pisarlo — un simple `y_cm += delta` alcanza:
     * desplazar la caja ANTES de girarla mueve su centro (y por lo tanto el
     * AABB ya girado) ese mismo delta en Y absoluto, sea cual sea el ángulo.
     */
    private function applyStarsNameClearance(array &$page): void
    {
        $topRowBottomCm = 0;
        foreach ($page['elements'] as $el) {
            $isNameText = ($el['type'] ?? null) === 'text' && ($el['dynamic_field'] ?? null) === 'nombre';
            if ($isNameText) {
                continue;
            }
            $topRowBottomCm = max($topRowBottomCm, (float) ($el['y_cm'] ?? 0) + (float) ($el['height_cm'] ?? 0));
        }

        $marginCm = 0.3;
        $targetTopCm = $topRowBottomCm + $marginCm;

        foreach ($page['elements'] as &$el) {
            if (($el['type'] ?? null) !== 'text' || ($el['dynamic_field'] ?? null) !== 'nombre') {
                continue;
            }
            $angleRad = deg2rad((float) ($el['rotation_deg'] ?? 0));
            $halfHeightVisualCm = ((float) $el['width_cm'] / 2) * abs(sin($angleRad))
                + ((float) $el['height_cm'] / 2) * abs(cos($angleRad));
            $centerYCm = (float) $el['y_cm'] + (float) $el['height_cm'] / 2;
            $currentTopCm = $centerYCm - $halfHeightVisualCm;
            if ($currentTopCm < $targetTopCm) {
                $el['y_cm'] = round($el['y_cm'] + ($targetTopCm - $currentTopCm), 2);
            }
        }
        unset($el);
    }

    private function applyViniloLabelShape(array &$page, ?string $viewPath): void
    {
        if (!$viewPath || empty($page['grids'])) {
            return;
        }
        $normalizedViewPath = str_starts_with($viewPath, 'tematica/') ? $viewPath : "tematica/{$viewPath}";
        if (!str_starts_with($normalizedViewPath, 'tematica/vinilo/')) {
            return;
        }
        $viniloShape = $this->resolveViniloLabelShape();
        if (!$viniloShape) {
            return;
        }

        $targetWidth = round((float) $viniloShape->width_cm, 1);
        $targetHeight = round((float) $viniloShape->height_cm, 1);

        $indexById = [];
        foreach ($page['elements'] as $index => $el) {
            $indexById[$el['id']] = $index;
        }

        foreach ($page['grids'] as $grid) {
            $templateBgIndex = null;
            foreach ($grid['template'] as $id) {
                $index = $indexById[$id] ?? null;
                if ($index !== null && ($page['elements'][$index]['type'] ?? null) === 'background') {
                    $templateBgIndex = $index;
                    break;
                }
            }
            if ($templateBgIndex === null) {
                continue;
            }
            $templateBg = $page['elements'][$templateBgIndex];
            if (round((float) $templateBg['width_cm'], 1) !== $targetWidth || round((float) $templateBg['height_cm'], 1) !== $targetHeight) {
                continue;
            }

            $cellIdLists = [$grid['template']];
            foreach ($grid['members'] as $member) {
                $cellIdLists[] = array_column(array_filter($member), 'id');
            }

            foreach ($cellIdLists as $ids) {
                foreach ($ids as $id) {
                    $index = $indexById[$id] ?? null;
                    if ($index === null || ($page['elements'][$index]['type'] ?? null) !== 'background') {
                        continue;
                    }
                    $page['elements'][$index]['label_shape_id'] = $viniloShape->id;
                }
            }
        }
    }

    /**
     * `tematica/vinilo/*` NO usa el esquema de clases `p.normal/small/
     * extra-small-text-size` por CANTIDAD DE CARACTERES que sí tienen
     * maxi/super-maxi/super-mini (ver extractTextSizeTiersFromView()) — en
     * su lugar, las 24 vistas eligen entre exactamente 2 tamaños fijos
     * según la CANTIDAD DE PALABRAS del nombre (`@switch($cant)` en
     * cualquier `tematica/vinilo/*.blade.php`): `.texto2` (`font-size:
     * 1.05em`, 1 línea vía `formatName()`) para 1, 2 o 5+ palabras, y
     * `.textoAplanado` (`font-size: 0.9em; line-height: 0.9em`, 2 líneas
     * fijas) para exactamente 3 o 4.
     *
     * Ese esquema no es "más caracteres → más chico" de forma monótona (un
     * nombre de 5+ palabras VUELVE a `.texto2`, más grande, aunque sea más
     * largo que uno de 3-4) — no se puede representar exacto con
     * `font_size_rules`/`line_height_rules` (una escalera ordenada por
     * `max_chars`). Se arma una aproximación de 2 niveles con los mismos
     * umbrales ya usados en el resto del import (16 / 26, ver
     * `applyLegacyTextSizeRules()`): nombres cortos con el tamaño grande
     * (medido tal cual lo dibujó dompdf en la muestra — la muestra usa
     * siempre "NOMBRE APELLIDO", 2 palabras, así que el `font_size_px` ya
     * importado ES el de `.texto2`) y nombres más largos con el chico,
     * escalado por la MISMA proporción real entre los dos `em` del CSS
     * (0.9/1.05) en vez de adivinar un píxel aparte. El interlineado del
     * nivel chico sale directo del `line-height: 0.9em` de `.textoAplanado`
     * (al ser relativo al propio font-size, equivale a un multiplicador
     * unitless de 0.9 sin importar el tamaño real en px).
     */
    private function applyViniloTextSizeRules(array &$page): void
    {
        $viniloShape = $this->resolveViniloLabelShape();
        if (!$viniloShape) {
            return;
        }

        $hasViniloBackground = false;
        foreach ($page['elements'] as $el) {
            if (($el['type'] ?? null) === 'background' && ($el['label_shape_id'] ?? null) === $viniloShape->id) {
                $hasViniloBackground = true;
                break;
            }
        }
        if (!$hasViniloBackground) {
            return;
        }

        $smallToNormalRatio = 0.9 / 1.05;

        foreach ($page['elements'] as &$el) {
            if (($el['type'] ?? null) !== 'text') {
                continue;
            }
            // El texto "PEDIDO # ..." es el único rotado en estas vistas —
            // no tiene nada que ver con el nombre del comprador.
            if ((int) round((float) ($el['rotation_deg'] ?? 0)) !== 0) {
                continue;
            }
            if (empty($el['font_size_px'])) {
                continue;
            }

            $normalFontSizePx = (int) $el['font_size_px'];
            $smallFontSizePx = max(1, (int) round($normalFontSizePx * $smallToNormalRatio));

            $el['font_size_rules'] = [
                ['max_chars' => 16, 'font_size_px' => $normalFontSizePx],
                ['max_chars' => 26, 'font_size_px' => $smallFontSizePx],
            ];
            $el['length_rules_enabled'] = true;
            $el['length_rules'] = $el['font_size_rules'];
            $el['font_size_px'] = $smallFontSizePx;

            $el['line_height_rules'] = [
                ['max_chars' => 16, 'line_height' => $el['line_height'] ?? 1.15],
                ['max_chars' => 26, 'line_height' => 0.9],
            ];
            $el['line_height_rules_enabled'] = true;
            // `line_height_tiers` es el campo EDITABLE que muestra el panel
            // del editor — ver nota igual en applyLegacyTextSizeRules().
            $el['line_height_tiers'] = $el['line_height_rules'];
        }
        unset($el);
    }

    /**
     * El texto "PEDIDO # ..." de `tematica/vinilo/*` sale rotado -90°
     * (`.texto3 { transform: rotate(270deg) }`), corrido al margen
     * izquierdo de la hoja para quedar FUERA de la columna de etiquetas —
     * pero el ancho de su caja (antes de rotar) depende del contenido
     * real (dígitos del pedido de muestra), y el rectángulo YA ROTADO
     * puede terminar invadiendo el borde izquierdo de esa columna aunque
     * sea por unos milímetros (se ve "tocando" la etiqueta de al lado).
     *
     * Se corre la caja en el eje X (sin tocar ancho/alto/rotación) lo
     * necesario para que el rectángulo rotado quede a `$gapCm` del borde
     * izquierdo REAL de la columna "Vinilo" (el x_cm mínimo entre los
     * fondos que `applyViniloLabelShape()` ya etiquetó, en vez de un
     * número fijo a ciegas que se desactualiza si cambia el tamaño de
     * letra o la cantidad de dígitos del pedido).
     *
     * No recibe `$viewPath` (a diferencia de `applyViniloLabelShape()`):
     * tiene que correr DESPUÉS de reemplazar el número de venta de muestra
     * por el placeholder `{{numero_pedido}}` (ver más abajo en
     * `importFromProduct()`), momento en el que ya no se tiene a mano el
     * `$viewPath` de cada página por separado — en cambio, usa como señal
     * de "esta página es Vinilo" que ya tenga algún fondo con el
     * `label_shape_id` de esa forma (lo puso `applyViniloLabelShape()` antes).
     */
    private function applyViniloOrderNumberClearance(array &$page): void
    {
        $viniloShape = $this->resolveViniloLabelShape();
        if (!$viniloShape) {
            return;
        }

        $columnLeftX = null;
        foreach ($page['elements'] as $el) {
            if (($el['type'] ?? null) === 'background' && ($el['label_shape_id'] ?? null) === $viniloShape->id) {
                $columnLeftX = $columnLeftX === null ? (float) $el['x_cm'] : min($columnLeftX, (float) $el['x_cm']);
            }
        }
        if ($columnLeftX === null) {
            return;
        }

        $gapCm = 0.2;

        foreach ($page['elements'] as &$el) {
            if (($el['type'] ?? null) !== 'text' || (int) round((float) ($el['rotation_deg'] ?? 0)) === 0) {
                continue;
            }
            if (!str_contains($el['content'] ?? '', 'numero_pedido')) {
                continue;
            }

            $width = (float) ($el['width_cm'] ?? 0);
            $height = (float) ($el['height_cm'] ?? 0);
            $theta = deg2rad((float) $el['rotation_deg']);
            // Ancho real del rectángulo ya rotado (fórmula estándar de la
            // caja envolvente de un rectángulo rotado alrededor de su
            // propio centro).
            $rotatedHalfWidth = abs($width / 2 * cos($theta)) + abs($height / 2 * sin($theta));
            $centerX = (float) $el['x_cm'] + $width / 2;
            $rightEdge = $centerX + $rotatedHalfWidth;

            $maxAllowedRightEdge = $columnLeftX - $gapCm;
            if ($rightEdge > $maxAllowedRightEdge) {
                $el['x_cm'] = round($el['x_cm'] - ($rightEdge - $maxAllowedRightEdge), 2);
            }
        }
        unset($el);
    }

    /**
     * Varios contenedores legacy (`.etiqueta-vertical-content-container p`,
     * `.etiqueta-super-mini-content-container p.normal/extra-small-text-size`,
     * etc. — ver `public/css/etiquetas.css`) declaran un interlineado
     * reconocido como demasiado apretado para el editor: a pedido, cualquier
     * interlineado que el import deje en alguno de estos valores (tal cual
     * venía midiendo del CSS/PDF de muestra) se sube al reemplazo indicado:
     *
     * | Mide | Se sube a |
     * |---|---|
     * | 0.7 | 0.90 |
     * | 0.75 | 0.95 |
     * | 0.8 | 1.2 |
     * | 0.9 | 1.3 |
     *
     * Tanto el `line_height` base del elemento (el que mide directo del PDF
     * renderizado, para contenedores como "vertical" que no tienen reglas
     * por longitud) como cada nivel de `line_height_rules`/
     * `line_height_tiers` que haya quedado en alguno de esos valores (ver
     * `applyLegacyTextSizeRules()`/`applyViniloTextSizeRules()`). Corre
     * DESPUÉS de esas dos, que son las que dejan `line_height`/
     * `line_height_rules` en su valor final.
     */
    private function bumpLowLineHeight(array &$page): void
    {
        // Cada valor legacy "demasiado apretado" (tal cual venía midiendo del
        // CSS/PDF de muestra) y a qué se sube, a pedido.
        // Pares [valor-que-mide => reemplazo], NO un array asociativo: con
        // 0.8/0.75/0.7/0.9 como índice, PHP trunca cada clave float a int
        // (todas terminan siendo la misma clave 0, pisándose entre sí).
        $replacements = [
            [0.7, 0.90],
            [0.75, 0.95],
            [0.8, 1.2],
            [0.9, 1.3],
        ];
        $resolve = function ($value) use ($replacements) {
            foreach ($replacements as [$from, $to]) {
                if (abs((float) $value - $from) < 0.001) {
                    return $to;
                }
            }
            return null;
        };

        foreach ($page['elements'] as &$el) {
            if (($el['type'] ?? null) !== 'text') {
                continue;
            }
            if (isset($el['line_height']) && ($replacement = $resolve($el['line_height'])) !== null) {
                $el['line_height'] = $replacement;
            }
            foreach (['line_height_rules', 'line_height_tiers'] as $field) {
                if (empty($el[$field]) || !is_array($el[$field])) {
                    continue;
                }
                foreach ($el[$field] as &$rule) {
                    if (isset($rule['line_height']) && ($replacement = $resolve($rule['line_height'])) !== null) {
                        $rule['line_height'] = $replacement;
                    }
                }
                unset($rule);
            }
        }
        unset($el);
    }

    /**
     * Prácticamente todas las vistas legacy achican la letra a mano con CSS
     * cuando el nombre completo es largo — un patrón idéntico en las ~115
     * vistas: `$fontClass = strlen($nombre) > 20 ? 'extra-small-text-size' :
     * (strlen($nombre) > 16 ? 'small-text-size' : 'normal-text-size')` (ver
     * EtiquetaService::generarEtiquetas), clase que se le agrega al texto y
     * que cada vista resuelve con SU PROPIO tamaño en pt según el
     * contenedor (ej. `.circulo-personaje p.normal-text-size { font-size:
     * 12pt }`, `.etiqueta-maxi-text p.small-text-size { font-size: 12pt }`
     * — cada contenedor tiene los suyos). El importador reproduce esto leyendo
     * esas 3 reglas DIRECTO del .blade.php, para cualquier contenedor de
     * cualquier temática (no hace falta hardcodear vista por vista): devuelve
     * `[containerClass => ['normal' => pt, 'small' => pt, 'extra_small' =>
     * pt]]` con lo que encuentre (un contenedor puede no tener los 3
     * niveles).
     */
    private function extractTextSizeTiersFromView(string $content): array
    {
        $tiers = [];
        if (preg_match_all(
            '/\.([\w-]+)\s+p\.(normal|small|extra-small)-text-size\s*\{[^}]*?font-size:\s*([\d.]+)pt/i',
            $content,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                $tier = str_replace('-', '_', $m[2]);
                $tiers[$m[1]][$tier] = (float) $m[3];
            }
        }

        return $tiers;
    }

    /**
     * Mismo patrón que `extractTextSizeTiersFromView()` pero para el
     * `line-height` de cada nivel (ej. `.etiqueta-super-mini-content-
     * container p.normal-text-size { font-size: 9pt; line-height: 0.8 }`) —
     * acá SÍ hace falta capturar el bloque `{...}` completo (no solo
     * `font-size`), porque un nivel puede NO declarar `line-height` (ej.
     * `small-text-size` de "Super Mini" solo trae `font-size`, sin
     * `line-height` propio — ese nivel queda afuera del resultado y
     * `applyLegacyTextSizeRules()` usa `extractBaseLineHeightFromView()`
     * para ese nivel puntual, NO el `line_height` del elemento). Devuelve
     * `[containerClass => ['normal' => 0.8, 'extra_small' => 0.8]]` con lo
     * que encuentre.
     */
    private function extractLineHeightTiersFromView(string $content): array
    {
        $tiers = [];
        if (preg_match_all(
            '/\.([\w-]+)\s+p\.(normal|small|extra-small)-text-size\s*\{([^}]*)\}/i',
            $content,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                if (!preg_match('/line-height:\s*([\d.]+)/i', $m[3], $lh)) {
                    continue;
                }
                $tier = str_replace('-', '_', $m[2]);
                $tiers[$m[1]][$tier] = (float) $lh[1];
            }
        }

        return $tiers;
    }

    /**
     * El `line-height` "de base" de un contenedor (`.container p { ...
     * line-height: X }`, SIN sufijo `.normal/.small/.extra-small-text-size`)
     * — es lo que de verdad termina usando un nivel que no declara su propio
     * `line-height` (ej. `small-text-size` de "Super Mini"): por cascada CSS,
     * `.container p.small-text-size` sin `line-height` propio no "no tiene
     * line-height", cae al de `.container p` (MENOS específico, pero el
     * único que sí lo declara) — es el mismo valor real que usa el PDF
     * legacy para ese nivel, a diferencia de usar el `line_height` ya
     * medido/guardado del elemento de muestra (un artefacto de la
     * importación, no necesariamente el mismo número). Devuelve
     * `[containerClass => lineHeight]`.
     */
    private function extractBaseLineHeightFromView(string $content): array
    {
        $baseLineHeights = [];
        if (preg_match_all(
            '/\.([\w-]+)\s+p\s*\{[^}]*?line-height:\s*([\d.]+)/i',
            $content,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                $baseLineHeights[$m[1]] = (float) $m[2];
            }
        }

        return $baseLineHeights;
    }

    /**
     * Mismo patrón que `extractTextSizeTiersFromView()` pero para las reglas
     * GENÉRICAS sin contenedor (`.normal-text-size { font-size: 12pt
     * !important }`, `.small-text-size`, `.extra-small-text-size` — ver
     * `public/css/etiquetas.css`), que valen para CUALQUIER `<p>` con esa
     * clase que no tenga una regla más específica tipo `.container
     * p.normal-text-size`. El `(?<!p)` antes del punto evita matchear la
     * mitad de un selector contenedor real (`.etiqueta-maxi-content-
     * container p.normal-text-size` también contiene literalmente
     * ".normal-text-size {" — pero ahí el caracter previo es "p", así que
     * se descarta).
     */
    private function extractGenericTextSizeTiers(string $content): array
    {
        $tiers = [];
        if (preg_match_all(
            '/(?<!p)\.(normal|small|extra-small)-text-size\s*\{[^}]*?font-size:\s*([\d.]+)pt/i',
            $content,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                $tier = str_replace('-', '_', $m[1]);
                $tiers[$tier] = (float) $m[2];
            }
        }

        return $tiers;
    }

    /**
     * Mismo patrón que `extractGenericTextSizeTiers()` pero para el
     * `line-height` de cada nivel genérico — igual criterio que
     * `extractLineHeightTiersFromView()` (un nivel sin `line-height` propio
     * queda afuera, para que `applyLegacyTextSizeRules()` use el `line-
     * height` BASE del contenedor en su lugar).
     */
    private function extractGenericLineHeightTiers(string $content): array
    {
        $tiers = [];
        if (preg_match_all(
            '/(?<!p)\.(normal|small|extra-small)-text-size\s*\{([^}]*)\}/i',
            $content,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $m) {
                if (!preg_match('/line-height:\s*([\d.]+)/i', $m[2], $lh)) {
                    continue;
                }
                $tier = str_replace('-', '_', $m[1]);
                $tiers[$tier] = (float) $lh[1];
            }
        }

        return $tiers;
    }

    /**
     * `width`/`height` en cm de la clase CSS de la forma "externa" (ej.
     * `.etiqueta-super-mini { width: 2.9cm; height: 1.15cm; ... }`, no su
     * `-content-container` interno) — usado para resolver qué grilla
     * importada corresponde a cada clase por GEOMETRÍA (ver
     * applyLegacyTextSizeRules()/applySuperMiniLabelShape(), mismo criterio).
     */
    private function extractClassBoxDimensions(string $content, string $className): ?array
    {
        if (!preg_match('/\.' . preg_quote($className, '/') . '\s*\{([^}]*)\}/i', $content, $blockMatch)) {
            return null;
        }
        if (
            !preg_match('/width:\s*([\d.]+)cm/i', $blockMatch[1], $widthMatch)
            || !preg_match('/height:\s*([\d.]+)cm/i', $blockMatch[1], $heightMatch)
        ) {
            return null;
        }

        return [(float) $widthMatch[1], (float) $heightMatch[1]];
    }

    /**
     * Cuántas veces se repite una celda de clase `$className` en la vista —
     * generaliza `detectSpotCellCount()` (que solo sabe leer el `@for`
     * simple de la fila "spot") a CUALQUIER contenedor, incluidos los que
     * arman la repetición con un `@for` de filas anidado dentro de uno de
     * columnas (ej. la tabla "maxi": `@for ($row...) @for ($col...) <div
     * class="etiqueta-maxi-text">`, 6×3 = 18 celdas). Se ubica la primera
     * aparición literal de `class="$className"` en el archivo y se cuentan
     * los `@for`/`@endfor` anteriores a esa posición con una pila simple —
     * lo que quede sin cerrar en la pila al llegar ahí son los bucles que
     * ENGLOBAN esa clase, y el total de celdas es el producto de sus
     * límites. Null si la clase no aparece o no está dentro de ningún `@for`.
     */
    private function countRepeatedClassOccurrences(string $content, string $className): ?int
    {
        if (!preg_match('/class=["\']' . preg_quote($className, '/') . '(?=["\'\s])/', $content, $classMatch, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $targetOffset = $classMatch[0][1];

        preg_match_all(
            '/@for\s*\(\s*\$\w+\s*=\s*0\s*;\s*\$\w+\s*<\s*(\d+)\s*;[^)]*\)|@endfor/',
            $content,
            $loopMatches,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        );

        $stack = [];
        foreach ($loopMatches as $m) {
            if ($m[0][1] > $targetOffset) {
                break;
            }
            if (str_starts_with($m[0][0], '@endfor')) {
                array_pop($stack);
            } else {
                $stack[] = (int) $m[1][0];
            }
        }

        return empty($stack) ? null : array_product($stack);
    }

    /**
     * Arma `font_size_rules`/`length_rules`/`length_rules_enabled` (y, si el
     * CSS trae `line-height` por nivel, también `line_height_rules`/
     * `line_height_rules_enabled`) para el texto de cada grilla cuya
     * cantidad de celdas coincide con la de un contenedor que, según el CSS
     * de esta vista, varía su tamaño de letra según la longitud del nombre
     * (ver extractTextSizeTiersFromView()/extractLineHeightTiersFromView()/
     * countRepeatedClassOccurrences()) — así el editor ya arranca con
     * "Tamaño por longitud"/"Interlineado por longitud" activados y el mismo
     * criterio que usaba la vista legacy, en vez de un valor fijo único para
     * cualquier nombre. Un contenedor con un solo nivel definido (no varía
     * nunca) se ignora: no hay nada que configurar. Si dos grillas distintas
     * tienen la MISMA cantidad de celdas (ambiguo) se usa la primera sin
     * reclamar, igual que ya hace `applySpotLabelShape()` para la fila
     * "spot".
     *
     * Las 3 reglas pueden estar INLINE en el `<style>` del propio
     * `.blade.php` (la mayoría de las vistas "-and-"/"PERSONALIZABLE", ej.
     * `.etiqueta-maxi-text p.normal-text-size`) o, para vistas más simples
     * como `tematica/super-mini/*.blade.php`, venir del CSS COMPARTIDO que
     * esa vista solo incluye (`{!! file_get_contents(public_path('css/
     * etiquetas.css')) !!}`, ej. `.etiqueta-super-mini-content-container
     * p.normal-text-size`) — si la vista no define nada inline para una
     * clase puntual, se la busca también ahí antes de descartarla.
     */
    private function applyLegacyTextSizeRules(array &$page, ?string $viewPath): void
    {
        if (!$viewPath || empty($page['grids'])) {
            return;
        }
        $viewPath = str_starts_with($viewPath, 'tematica/') ? $viewPath : "tematica/{$viewPath}";
        $bladePath = resource_path('views/' . $viewPath . '.blade.php');
        if (!is_file($bladePath)) {
            return;
        }
        $content = file_get_contents($bladePath);

        $tiersByClass = $this->extractTextSizeTiersFromView($content);
        $lineHeightTiersByClass = $this->extractLineHeightTiersFromView($content);
        $baseLineHeightByClass = $this->extractBaseLineHeightFromView($content);

        $sharedCssPath = public_path('css/etiquetas.css');
        $sharedContent = null;
        if (is_file($sharedCssPath)) {
            $sharedContent = file_get_contents($sharedCssPath);
            foreach ($this->extractTextSizeTiersFromView($sharedContent) as $className => $tiers) {
                if (!isset($tiersByClass[$className])) {
                    $tiersByClass[$className] = $tiers;
                }
            }
            foreach ($this->extractLineHeightTiersFromView($sharedContent) as $className => $tiers) {
                if (!isset($lineHeightTiersByClass[$className])) {
                    $lineHeightTiersByClass[$className] = $tiers;
                }
            }
            foreach ($this->extractBaseLineHeightFromView($sharedContent) as $className => $lineHeight) {
                if (!isset($baseLineHeightByClass[$className])) {
                    $baseLineHeightByClass[$className] = $lineHeight;
                }
            }
        }

        // "Vertical" (`.etiqueta-vertical-content-container p`, ver
        // `public/css/etiquetas.css`) es el ÚNICO contenedor de los 4 que
        // arma `generarEtiquetas()` que NO tiene su propia regla `.container
        // p.normal/small/extra-small-text-size` — el `<p>` de esta forma
        // usa igual la clase `$fontClass` (normal/small/extra-small-text-
        // size, por longitud del nombre), pero por CSS cascade cae en las
        // reglas GENÉRICAS sin contenedor (`.normal-text-size { font-size:
        // 12pt !important }`, etc., un poco más arriba en el mismo
        // `etiquetas.css`) que `extractTextSizeTiersFromView()` no detecta
        // (busca selectores ".algo p.tier-text-size", no ".tier-text-size"
        // suelta). Sin este fallback, "Vertical" quedaba sin `font_size_
        // rules`/`line_height_rules` pese a variar por longitud en el
        // legacy igual que los demás.
        if (!isset($tiersByClass['etiqueta-vertical-content-container'])) {
            $genericTiers = $this->extractGenericTextSizeTiers($content)
                ?: ($sharedContent !== null ? $this->extractGenericTextSizeTiers($sharedContent) : []);
            if (count($genericTiers) >= 2) {
                $tiersByClass['etiqueta-vertical-content-container'] = $genericTiers;
                $genericLineHeightTiers = $this->extractGenericLineHeightTiers($content)
                    ?: ($sharedContent !== null ? $this->extractGenericLineHeightTiers($sharedContent) : []);
                if ($genericLineHeightTiers) {
                    $lineHeightTiersByClass['etiqueta-vertical-content-container'] = $genericLineHeightTiers;
                }
            }
        }

        if (empty($tiersByClass)) {
            return;
        }

        $elementsById = [];
        foreach ($page['elements'] as $index => $el) {
            $elementsById[$el['id']] = $index;
        }

        $pxFor = fn(float $pt) => (int) round($pt / 0.75);
        $claimedGridIndexes = [];
        // Los umbrales van siempre los reales del `$fontClass` legacy (16 y
        // 20 — ver EtiquetaService::generarEtiquetas), en el mismo orden que
        // los 3 niveles posibles. El último usa 26 (no 999 ni null) como
        // "para el resto": un número real y editable en el panel del editor,
        // en vez de un sentinela que no significa nada ahí.
        $tierMaxChars = ['normal' => 16, 'small' => 20, 'extra_small' => 26];

        $gridHasText = function (array $grid) use ($elementsById, $page): bool {
            foreach ($grid['template'] as $id) {
                if (($page['elements'][$elementsById[$id] ?? null]['type'] ?? null) === 'text') {
                    return true;
                }
            }
            return false;
        };

        foreach ($tiersByClass as $className => $tiers) {
            if (count($tiers) < 2) {
                continue; // un solo tamaño: no hay nada que varíe según longitud.
            }

            $gridIndex = null;

            // Prioridad 1: geometría. `$className` es siempre el contenedor
            // INTERNO de una forma (ej. "etiqueta-super-mini-content-
            // container") — su forma EXTERNA (sacando el sufijo) trae un
            // `width`/`height` en cm fijos en el CSS (ver `.etiqueta-super-
            // mini { width: 2.9cm; height: 1.15cm }`), igual que
            // `applySuperMiniLabelShape()`. Esto resuelve vistas tipo
            // `tematica/principal/*` que combinan maxi+vertical+super-maxi+
            // super-mini SIN ningún `@for` (todo `<td>` repetido a mano): ahí
            // `countRepeatedClassOccurrences()` no cuenta nada y las 3+
            // clases con niveles ("maxi", "super-maxi", "super-mini") dejan
            // más de una grilla sin reclamar con texto, así que el fallback
            // de "única grilla candidata" de más abajo tampoco alcanza a
            // resolver ninguna. La tolerancia de 0.1cm absorbe una vista
            // puntual como "vertical", cuyo elemento medido difiere en 0.05cm
            // del `width`/`height` declarado en el CSS (padding/borde).
            $baseClassName = preg_replace('/-content-container$/', '', $className);
            $boxDimensions = $this->extractClassBoxDimensions($content, $baseClassName)
                ?? ($sharedContent !== null ? $this->extractClassBoxDimensions($sharedContent, $baseClassName) : null);
            if ($boxDimensions) {
                [$targetWidth, $targetHeight] = $boxDimensions;
                foreach ($page['grids'] as $idx => $grid) {
                    if (in_array($idx, $claimedGridIndexes, true) || !$gridHasText($grid)) {
                        continue;
                    }
                    $templateBgIndex = null;
                    foreach ($grid['template'] as $id) {
                        $i = $elementsById[$id] ?? null;
                        if ($i !== null && ($page['elements'][$i]['type'] ?? null) === 'background') {
                            $templateBgIndex = $i;
                            break;
                        }
                    }
                    if ($templateBgIndex === null) {
                        continue;
                    }
                    $templateBg = $page['elements'][$templateBgIndex];
                    if (abs((float) $templateBg['width_cm'] - $targetWidth) <= 0.1 && abs((float) $templateBg['height_cm'] - $targetHeight) <= 0.1) {
                        $gridIndex = $idx;
                        break;
                    }
                }
            }

            $count = $gridIndex === null ? $this->countRepeatedClassOccurrences($content, $className) : null;

            if ($gridIndex !== null) {
                // ya resuelto por geometría.
            } elseif ($count) {
                // Una misma cantidad de celdas puede coincidir con MÁS de una
                // grilla (ej. la tabla "maxi" arma una grilla para el fondo
                // del ícono y otra, aparte, para el fondo del texto — ambas
                // 6×3, ninguna es "la" grilla de texto per se) — de las
                // candidatas sin reclamar se prefiere la que REALMENTE tiene
                // un elemento de texto adentro.
                foreach ($page['grids'] as $idx => $grid) {
                    if (in_array($idx, $claimedGridIndexes, true)) {
                        continue;
                    }
                    if (((int) $grid['cols']) * ((int) $grid['rows']) !== $count) {
                        continue;
                    }
                    if ($gridHasText($grid)) {
                        $gridIndex = $idx;
                        break;
                    }
                }
            } elseif (preg_match('/class=["\']' . preg_quote($className, '/') . '(?=["\'\s])/', $content)) {
                // `countRepeatedClassOccurrences()` no pudo contar, pero la
                // clase SÍ aparece en ESTA vista (ej. un `@for` con límite
                // variable en vez de un número literal, como `tematica/
                // super-mini/*.blade.php`: `@for ($i = 0; $i <
                // $plantilla['filas']; $i++)`) — si la página tiene una única
                // grilla sin reclamar con texto adentro, es la única
                // candidata posible, no hace falta el conteo para elegirla.
                // El chequeo de que la clase aparezca acá es clave: sin él,
                // una clase de OTRA forma sin relación (ej. "Super Maxi",
                // que convive en el mismo `etiquetas.css` compartido pero
                // esta vista puntual ni usa) también caería acá y se robaría
                // la única grilla disponible con SUS tamaños, no los de la
                // clase real de esta vista.
                $unclaimedWithText = [];
                foreach ($page['grids'] as $idx => $grid) {
                    if (!in_array($idx, $claimedGridIndexes, true) && $gridHasText($grid)) {
                        $unclaimedWithText[] = $idx;
                    }
                }
                if (count($unclaimedWithText) === 1) {
                    $gridIndex = $unclaimedWithText[0];
                }
            }
            if ($gridIndex === null) {
                continue;
            }
            $claimedGridIndexes[] = $gridIndex;

            // Si un nombre se pasa del último umbral definido y ninguna
            // regla matchea, `resolverTamanoFuente` cae al tamaño base del
            // elemento — por eso ese tamaño base se deja en el de la ÚLTIMA
            // regla (no en "normal"), para que un nombre larguísimo siga
            // respetando el último tamaño ya elegido en vez de volver a
            // agrandarse.
            $rules = [];
            $ruleTierKeys = [];
            foreach ($tierMaxChars as $tierKey => $maxChars) {
                if (!isset($tiers[$tierKey])) {
                    continue;
                }
                $rules[] = ['max_chars' => $maxChars, 'font_size_px' => $pxFor($tiers[$tierKey])];
                $ruleTierKeys[] = $tierKey;
            }
            if (count($rules) < 2) {
                continue;
            }
            $fallbackFontSizePx = end($rules)['font_size_px'];

            // Mismos umbrales, para el interlineado — solo si el CSS real
            // define al menos un `line-height` puntual para esta clase (ej.
            // "Super Mini": `normal`/`extra_small` sí, `small` no). El nivel
            // que no lo define usa el `line-height` BASE del contenedor
            // (`.container p { line-height: X }`, sin sufijo de nivel — ver
            // extractBaseLineHeightFromView()): por cascada CSS, es lo que
            // ese nivel usa en realidad en el PDF legacy. Si ni siquiera el
            // contenedor define un base (no debería pasar en la práctica),
            // se cae al `line_height` ya resuelto del elemento de muestra,
            // como último recurso.
            $lineHeightTiers = $lineHeightTiersByClass[$className] ?? [];
            $hasLineHeightOverride = (bool) array_intersect_key($lineHeightTiers, array_flip($ruleTierKeys));
            $baseLineHeight = $baseLineHeightByClass[$className] ?? null;

            $textIds = array_filter($page['grids'][$gridIndex]['template'], fn($id) => ($elementsById[$id] ?? null) !== null && ($page['elements'][$elementsById[$id]]['type'] ?? null) === 'text');
            foreach ($page['grids'][$gridIndex]['members'] as $member) {
                foreach (array_filter($member) as $entry) {
                    $idx = $elementsById[$entry['id']] ?? null;
                    if ($idx !== null && ($page['elements'][$idx]['type'] ?? null) === 'text') {
                        $textIds[] = $entry['id'];
                    }
                }
            }

            foreach ($textIds as $textId) {
                $index = $elementsById[$textId];
                $page['elements'][$index]['font_size_rules'] = $rules;
                $page['elements'][$index]['length_rules_enabled'] = true;
                $page['elements'][$index]['length_rules'] = $rules;
                $page['elements'][$index]['font_size_px'] = $fallbackFontSizePx;

                if ($hasLineHeightOverride) {
                    $fallbackLineHeight = $baseLineHeight ?? ($page['elements'][$index]['line_height'] ?? 1.15);
                    $lineHeightRules = [];
                    foreach ($ruleTierKeys as $i => $tierKey) {
                        $lineHeightRules[] = [
                            'max_chars' => $rules[$i]['max_chars'],
                            'line_height' => $lineHeightTiers[$tierKey] ?? $fallbackLineHeight,
                        ];
                    }
                    $page['elements'][$index]['line_height_rules_enabled'] = true;
                    $page['elements'][$index]['line_height_rules'] = $lineHeightRules;
                    // `line_height_rules` es el formato "wire" que el backend
                    // de verdad lee al generar el PDF (EtiquetaService::
                    // resolverInterlineado) — `line_height_tiers` es el campo
                    // EDITABLE que el panel del editor muestra/edita (ver
                    // useDesignEditor.ts, PdfDesignHelper.resolveLineHeightRulesPayload
                    // arma el primero A PARTIR del segundo recién al guardar).
                    // Sin este segundo campo, el editor mostraba el toggle
                    // prendido pero la tabla de reglas vacía — y si el admin
                    // guardaba así sin tocar nada, el próximo save mandaba
                    // `line_height_rules` vacío y perdía esto.
                    $page['elements'][$index]['line_height_tiers'] = $lineHeightRules;
                }
            }
        }
    }

    /**
     * Las vistas "maxi" (`maxi-and-spot`, `spot-and-maxi`,
     * `maxi-and-super-mini`, etc. — ver `.etiqueta-maxi` en el CSS de esas
     * vistas) dibujan el ícono y el nombre como DOS celdas de tabla
     * ADYACENTES, cada una con su propio fondo: una blanca angosta (38% del
     * ancho) para el ícono, una del color de la temática (62%) para el
     * nombre, pegadas sin espacio entre sí — entre las dos arman, en el PDF
     * real, UNA sola etiqueta de dos tonos. `detectGrids()` no tiene forma
     * de saber eso: arma dos grillas SEPARADAS del mismo cols×rows, una con
     * fondo+ícono y otra con fondo+texto, ninguna de las dos es por sí sola
     * "la" etiqueta completa.
     *
     * El catálogo tiene una forma para esto, "Maxi Combinada"
     * (`resolveMaxiCombinadaLabelShape()`, `css_class: "etiqueta-maxi-
     * combinada"`, 5.2×1.9cm — el tamaño REAL de esta etiqueta de dos tonos,
     * a diferencia de "Maxi" (id 2), que es la celda de OTRA vista sin
     * relación, ver nota más abajo). Acá se fusionan ambas grillas en una
     * sola, celda por celda: el fondo del texto pasa a cubrir la etiqueta
     * completa (ícono + texto) y a llevar esa forma.
     *
     * La zona blanca del ícono YA NO es un elemento `background` aparte
     * (ver `PDF_ESCALA_ICONO_Y_COLOR_DE_LA_COMPRA.md`, `icon_scale_pct`):
     * antes eran DOS elementos con el mismo `group_id` (fondo blanco +
     * ícono) y, si el admin los desagrupaba en el editor (a mano o sin
     * querer), el fondo podía quedar movido de su lugar y el ícono se
     * dibujaba solo, sin su zona blanca detrás — mal. Ahora es UN solo
     * elemento `icon`: su caja pasa a ser la zona blanca entera (antes era
     * del fondo separado), con `fill` blanco (mismo mecanismo genérico que
     * ya pinta fondo de cualquier ícono/texto/forma, ver RENDER.blade.php) y
     * `icon_scale_pct` para que el DIBUJO del ícono se vea más chico que su
     * caja, con aire a los costados, sin agrandar ni mover la caja (que es
     * la que define la zona blanca y el punto de agarre para mover/agrupar).
     * No hay nada que desagrupar: no hay dos elementos para esa zona.
     *
     * El nombre se recentra a mano en la zona que le queda libre — SIN usar
     * `layout_groups` (ver nota más abajo).
     *
     * Por qué el nombre NO se recentra con `layout_groups` (como se probó
     * primero, igual que el resto del importador para celdas con un fondo
     * en común): esa herramienta trata ícono+texto como UN bloque único y
     * centra ESE bloque en la etiqueta entera — como el bloque es más
     * angosto que la etiqueta completa, el ícono terminaba corrido de su
     * lugar fijo en la zona blanca (se recentraba junto con el texto, en vez
     * de quedarse quieto). Acá el ícono nunca se mueve de su zona; el nombre
     * solo (`reflow_with_icon_id`/`reflow_container_id`/
     * `reflow_icon_zone_width_cm`, ver `EtiquetaService::
     * ajustarPorIconoAusente`) se recentra en la zona libre a mano, y en
     * TODA la etiqueta si la venta real no trae ningún ícono (ahí la zona
     * del ícono ya está oculta, ver nota de `hidden_with_icon_id` más
     * abajo).
     *
     * El TAMAÑO de la etiqueta fusionada es el que mide EL PDF de muestra
     * (la unión de los dos fondos detectados), no el de "Maxi Combinada" del
     * catálogo — en la práctica deberían coincidir (la forma se armó
     * midiendo esta misma vista), pero por las dudas de que algún PDF de
     * muestra puntual mida distinto (otra tipografía, otro ancho de nombre),
     * se prioriza lo medido. `label_shape_id` igual apunta siempre a "Maxi
     * Combinada": no tiene `outline_svg` (es un rect sin recorte propio), así
     * que no distorsiona nada aunque el tamaño real difiera un poco.
     *
     * Nota histórica: el catálogo tiene OTRA forma, "Maxi" (id 2,
     * `resolveMaxiLabelShape()`, 4.4×2.4cm) — corresponde al `.etiqueta-maxi`
     * de `principal/SKATE.blade.php`/`public/css/etiquetas.css`, donde esa
     * clase es apenas la celda/espaciado de una grilla con ícono+nombre
     * centrados chicos adentro, sin fondo de color propio — nada que ver con
     * la etiqueta de dos tonos de esta vista pese a compartir nombre de
     * clase. No se usa acá.
     *
     * No pasa nada si la vista no tiene este patrón (vuelve sin tocar nada).
     *
     * Si `$viewPath` es una vista "PERSONALIZABLE" (ver nota en
     * `PDF_IMPORTAR_DESDE_PRODUCTO.md`: estas vistas no tienen ícono/color
     * real detrás, el cliente los elige al comprar), de paso el ícono y el
     * fondo ya fusionado quedan marcados `editable_by_customer`/
     * `editable_field` (`"icon"`/`"color"`) — así una venta real ya sale con
     * el ícono/color que eligió el cliente (`EtiquetaService::
     * resolverElementoDesign`) sin que haya que tocar nada a mano en el
     * editor. `icon_id`/`color` quedan igual de fallback (si la venta no
     * trae ninguno). El texto NO se marca editable por color: la vista
     * legacy lo pinta siempre blanco fijo (`color:#FFF` en el `<p>`,
     * independiente de `$plantilla['colores']`, que solo pinta el fondo).
     */
    private function applyMaxiLabelShape(array &$page, ?string $viewPath = null): void
    {
        if (empty($page['grids']) || count($page['grids']) < 2) {
            return;
        }
        $isPersonalizable = $viewPath && str_contains($viewPath, 'PERSONALIZABLE');

        $elementsById = [];
        foreach ($page['elements'] as $index => $el) {
            $elementsById[$el['id']] = $index;
        }

        $templateTypes = function (array $grid) use ($elementsById, $page): array {
            return array_map(
                fn($id) => $page['elements'][$elementsById[$id] ?? null]['type'] ?? null,
                $grid['template']
            );
        };

        $iconGridIndex = null;
        $textGridIndex = null;
        foreach ($page['grids'] as $i => $grid) {
            if ($templateTypes($grid) !== ['background', 'icon']) {
                continue;
            }
            foreach ($page['grids'] as $j => $other) {
                if ($i === $j || $templateTypes($other) !== ['background', 'text']) {
                    continue;
                }
                if ((int) $grid['cols'] !== (int) $other['cols'] || (int) $grid['rows'] !== (int) $other['rows']) {
                    continue;
                }
                // Tienen que ser la misma grilla partida en dos: cada celda
                // de una tiene que tener su par en la misma posición en la
                // otra.
                $sharedKeys = array_intersect(array_keys($grid['members']), array_keys($other['members']));
                if (count($sharedKeys) !== count($grid['members']) || count($sharedKeys) !== count($other['members'])) {
                    continue;
                }
                $iconGridIndex = $i;
                $textGridIndex = $j;
                break 2;
            }
        }
        if ($iconGridIndex === null) {
            return;
        }

        $iconGrid = $page['grids'][$iconGridIndex];
        $textGrid = $page['grids'][$textGridIndex];
        $maxiCombinadaShape = $this->resolveMaxiCombinadaLabelShape();

        $cellKeys = array_merge(['template'], array_keys($iconGrid['members']));
        $cellPairs = [['iconIds' => $iconGrid['template'], 'textIds' => $textGrid['template']]];
        foreach ($iconGrid['members'] as $key => $member) {
            $cellPairs[] = [
                'iconIds' => array_column($member, 'id'),
                'textIds' => array_column($textGrid['members'][$key], 'id'),
            ];
        }

        $resizedBackgroundBoxes = []; // slot => [x_cm, y_cm], para recalcular gap_x_cm/gap_y_cm al final.
        $iconBgIdsToRemove = []; // las zonas blancas separadas desaparecen del diseño (ver docblock).
        foreach ($cellPairs as $cellOffset => $pair) {
            $slot = $cellKeys[$cellOffset];
            $iconBgIndex = $elementsById[$pair['iconIds'][0]];
            $iconIndex = $elementsById[$pair['iconIds'][1]];
            $textBgIndex = $elementsById[$pair['textIds'][0]];
            $textIndex = $elementsById[$pair['textIds'][1]];

            $iconBg = $page['elements'][$iconBgIndex];
            $icon = $page['elements'][$iconIndex];
            $textBg = $page['elements'][$textBgIndex];
            $text = $page['elements'][$textIndex];

            // El tamaño de la etiqueta fusionada es el que REALMENTE mide
            // este PDF de muestra (la unión de los dos fondos detectados) —
            // ver docblock sobre por qué no se usa directo el tamaño de
            // catálogo de "Maxi Combinada".
            $left = min($iconBg['x_cm'], $textBg['x_cm']);
            $top = min($iconBg['y_cm'], $textBg['y_cm']);
            $width = max($iconBg['x_cm'] + $iconBg['width_cm'], $textBg['x_cm'] + $textBg['width_cm']) - $left;
            $height = max($iconBg['y_cm'] + $iconBg['height_cm'], $textBg['y_cm'] + $textBg['height_cm']) - $top;

            $page['elements'][$textBgIndex]['x_cm'] = round($left, 2);
            $page['elements'][$textBgIndex]['y_cm'] = round($top, 2);
            $page['elements'][$textBgIndex]['width_cm'] = round($width, 2);
            $page['elements'][$textBgIndex]['height_cm'] = round($height, 2);
            if ($maxiCombinadaShape) {
                $page['elements'][$textBgIndex]['label_shape_id'] = $maxiCombinadaShape->id;
            }

            $groupId = $icon['group_id'] ?? ('group-imported-' . uniqid());
            $page['elements'][$textBgIndex]['group_id'] = $groupId;

            // El ícono pasa a ocupar TODA la zona blanca (antes era la caja
            // de `iconBg`) — ni se mueve ni se recorta: `icon_scale_pct`
            // (ver PDF_ESCALA_ICONO_Y_COLOR_DE_LA_COMPRA.md) es lo que lo
            // dibuja más chico ADENTRO de esa caja, con aire a los costados
            // (mismo padding de siempre, 0.3cm por lado — el real de la
            // vista, `.etiqueta-maxi img { height: 1.2cm }`, deja casi todo
            // el alto disponible, así que el margen es solo horizontal).
            // `object-fit: contain` en RENDER.blade.php preserva la
            // proporción del ícono sea cual sea el alto de la caja, así que
            // no hace falta acotar también por altura para que no se pase.
            $iconHorizontalPaddingCm = 0.3;
            $targetIconWidthCm = max(0.2, $iconBg['width_cm'] - 2 * $iconHorizontalPaddingCm);
            $iconScalePct = (int) round(100 * min(1, $targetIconWidthCm / $iconBg['width_cm']));
            $iconScalePct = max(10, min(100, $iconScalePct));

            $page['elements'][$iconIndex]['x_cm'] = round($iconBg['x_cm'], 2);
            $page['elements'][$iconIndex]['y_cm'] = round($iconBg['y_cm'], 2);
            $page['elements'][$iconIndex]['width_cm'] = round($iconBg['width_cm'], 2);
            $page['elements'][$iconIndex]['height_cm'] = round($iconBg['height_cm'], 2);
            $page['elements'][$iconIndex]['icon_scale_pct'] = $iconScalePct;
            $page['elements'][$iconIndex]['fill'] = ['type' => 'color', 'color' => ['mode' => 'hex', 'value' => '#FFFFFF']];

            // El nombre se centra (horizontal Y vertical) en la zona a la
            // derecha del ícono, SIN usar `layout_groups` (ver docblock).
            $iconZoneWidthCm = $iconBg['width_cm'];
            $textZoneLeft = $iconBg['x_cm'] + $iconZoneWidthCm;
            $textZoneWidth = ($left + $width) - $textZoneLeft;
            $page['elements'][$textIndex]['x_cm'] = round($textZoneLeft + ($textZoneWidth - $text['width_cm']) / 2, 2);
            $page['elements'][$textIndex]['y_cm'] = round($top + ($height - $text['height_cm']) / 2, 2);
            $page['elements'][$textIndex]['reflow_with_icon_id'] = $icon['id'];
            $page['elements'][$textIndex]['reflow_container_id'] = $textBg['id'];
            $page['elements'][$textIndex]['reflow_icon_zone_width_cm'] = $iconZoneWidthCm;

            // El fondo grande y el ícono comparten grupo (clickear/mover
            // cualquiera mueve la celda entera) y se renumeran para que el
            // ícono (con su `fill` blanco) siga dibujándose ARRIBA del fondo
            // grande en su zona, sin importar el orden en que hayan quedado
            // en `elements` (un `z_index` no entero, ej. 0.5, no lo respeta
            // dompdf — hay que usar enteros).
            $page['elements'][$textBgIndex]['z_index'] = 0;
            $page['elements'][$iconIndex]['z_index'] = 1;
            $page['elements'][$iconIndex]['group_id'] = $groupId;
            $page['elements'][$textIndex]['z_index'] = 2;

            if ($isPersonalizable) {
                $page['elements'][$iconIndex]['editable_by_customer'] = true;
                $page['elements'][$iconIndex]['editable_field'] = 'icon';
                $page['elements'][$textBgIndex]['editable_by_customer'] = true;
                $page['elements'][$textBgIndex]['editable_field'] = 'color';

                // Si el cliente no personaliza ningún ícono (o elige
                // explícitamente "Sin dibujo", ver EtiquetaService::
                // resolverElementoDesign), el ícono real de la venta queda
                // vacío — este elemento (fondo blanco + dibujo, ya fusionados
                // en uno) tiene que desaparecer ENTERO (apunta a sí mismo)
                // para que el nombre se pueda recentrar en toda la etiqueta
                // sin quedar un rectángulo blanco de más (y el propio texto,
                // blanco, invisible encima suyo). Ver EtiquetaService::
                // ajustarPorIconoAusente (ahora también mira elementos
                // `icon`, no solo `background`).
                $page['elements'][$iconIndex]['hidden_with_icon_id'] = $icon['id'];
            }

            $resizedBackgroundBoxes[$slot] = ['x_cm' => round($left, 2), 'y_cm' => round($top, 2)];
            $iconBgIdsToRemove[] = $iconBg['id'];
        }

        // El template/members de la grilla de texto pasan a tener también el
        // ícono (ya con su `fill` blanco, ocupando toda la zona que antes
        // era de `iconBg`) — mismo orden fondo→ícono→texto que arma el resto
        // del importador, así "Generar grilla" ya sabe clonar la celda
        // completa (3 piezas, ya no 4: la zona blanca dejó de ser un
        // elemento aparte).
        array_splice($page['grids'][$textGridIndex]['template'], 1, 0, [$iconGrid['template'][1]]);
        foreach ($page['grids'][$textGridIndex]['members'] as $key => &$member) {
            array_splice($member, 1, 0, [$iconGrid['members'][$key][1]]);
        }
        unset($member);

        array_splice($page['grids'], $iconGridIndex, 1);
        $textGridIndex = $iconGridIndex < $textGridIndex ? $textGridIndex - 1 : $textGridIndex;

        // Las zonas blancas separadas ya no existen como elemento propio
        // (ver docblock) — se las saca del todo de `elements`.
        $page['elements'] = array_values(array_filter(
            $page['elements'],
            fn($el) => !in_array($el['id'] ?? null, $iconBgIdsToRemove, true)
        ));

        // El `gap_x_cm`/`gap_y_cm` de esta grilla se calculó en
        // PdfDesignImportService::detectGrids() con el ancho/alto del fondo
        // de SOLO el texto (3.22×1.9cm en el ejemplo) — quedó desactualizado
        // apenas acá arriba cada celda se agrandó para cubrir también la
        // zona del ícono. Mismo problema y mismo fix que ya tiene
        // `applySpotLabelShape()` para "spot": se recalcula con el
        // ancho/alto que realmente ocupa la celda molde ya fusionada
        // (fondo+ícono+texto) y el PROMEDIO de posición por columna/fila.
        $freshElementsById = [];
        foreach ($page['elements'] as $index => $el) {
            $freshElementsById[$el['id']] = $index;
        }
        $templateMinX = INF;
        $templateMinY = INF;
        $templateMaxX = -INF;
        $templateMaxY = -INF;
        foreach ($page['grids'][$textGridIndex]['template'] as $id) {
            $index = $freshElementsById[$id] ?? null;
            if ($index === null) {
                continue;
            }
            $el = $page['elements'][$index];
            $templateMinX = min($templateMinX, $el['x_cm']);
            $templateMinY = min($templateMinY, $el['y_cm']);
            $templateMaxX = max($templateMaxX, $el['x_cm'] + $el['width_cm']);
            $templateMaxY = max($templateMaxY, $el['y_cm'] + $el['height_cm']);
        }
        $fallbackWidth = $maxiCombinadaShape ? (float) $maxiCombinadaShape->width_cm : 5.2;
        $fallbackHeight = $maxiCombinadaShape ? (float) $maxiCombinadaShape->height_cm : 1.9;
        $spanXTemplate = $templateMaxX > $templateMinX ? $templateMaxX - $templateMinX : $fallbackWidth;
        $spanYTemplate = $templateMaxY > $templateMinY ? $templateMaxY - $templateMinY : $fallbackHeight;

        $sumXByCol = [];
        $countXByCol = [];
        $sumYByRow = [];
        $countYByRow = [];
        foreach ($resizedBackgroundBoxes as $slot => $box) {
            [$col, $row] = $slot === 'template' ? [0, 0] : array_map('intval', explode(',', $slot));
            $sumXByCol[$col] = ($sumXByCol[$col] ?? 0) + $box['x_cm'];
            $countXByCol[$col] = ($countXByCol[$col] ?? 0) + 1;
            $sumYByRow[$row] = ($sumYByRow[$row] ?? 0) + $box['y_cm'];
            $countYByRow[$row] = ($countYByRow[$row] ?? 0) + 1;
        }
        $avgXByCol = [];
        foreach ($sumXByCol as $col => $sum) {
            $avgXByCol[$col] = $sum / $countXByCol[$col];
        }
        $avgYByRow = [];
        foreach ($sumYByRow as $row => $sum) {
            $avgYByRow[$row] = $sum / $countYByRow[$row];
        }
        ksort($avgXByCol, SORT_NUMERIC);
        ksort($avgYByRow, SORT_NUMERIC);
        $avgXValues = array_values($avgXByCol);
        $avgYValues = array_values($avgYByRow);
        if (count($avgXValues) > 1) {
            $page['grids'][$textGridIndex]['gap_x_cm'] = round($avgXValues[1] - $avgXValues[0] - $spanXTemplate, 2);
        }
        if (count($avgYValues) > 1) {
            $page['grids'][$textGridIndex]['gap_y_cm'] = round($avgYValues[1] - $avgYValues[0] - $spanYTemplate, 2);
        }
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
     * se recalcula el fondo desde cero, centrado en el CENTRO de la caja
     * que engloba ícono+texto. El TAMAÑO de ese cuadrado NO se calcula a
     * partir de ícono+texto (podía salir más grande que el real, ver
     * `.circulo-personaje { width: 3cm; height: 3cm }` en el CSS de estas
     * vistas) — se usa directo el tamaño del label_shape del catálogo, que
     * es ese mismo valor fijo.
     *
     * Si `$viewPath` es una vista "PERSONALIZABLE" (sin ícono/color real
     * detrás — el cliente los elige al comprar, ver nota en
     * `PDF_IMPORTAR_DESDE_PRODUCTO.md`), el ícono y el nombre de esta celda
     * quedan marcados `editable_by_customer`/`editable_field`
     * (`"icon"`/`"color"`): en esta vista el color de `$plantilla` pinta el
     * NOMBRE (`.circulo-texto`, `color:{{ $plantilla['colores'] }}`), no un
     * fondo propio (el círculo no tiene background en el CSS legacy), así
     * que acá el campo editable es el texto, no el fondo blanco sintético
     * que arma este método para que el editor tenga algo que recortar.
     */
    private function applySpotLabelShape(array &$page, int $spotCellCount, LabelShape $spotShape, ?string $viewPath = null): void
    {
        $isPersonalizable = $viewPath && str_contains($viewPath, 'PERSONALIZABLE');
        $cssLineHeight = $viewPath ? $this->detectSpotLineHeight($viewPath) : null;
        $grids = $page['grids'] ?? [];
        $matchingGridIndex = null;
        foreach ($grids as $gridIndex => $grid) {
            if (((int) $grid['cols']) * ((int) $grid['rows']) === $spotCellCount) {
                $matchingGridIndex = $gridIndex;
                break;
            }
        }
        if ($matchingGridIndex === null) {
            return;
        }
        $matchingGrid = $page['grids'][$matchingGridIndex];

        $indexById = [];
        foreach ($page['elements'] as $index => $el) {
            $indexById[$el['id']] = $index;
        }

        // Cada entrada trae, además de los ids ya conocidos (fondo+ícono), en
        // qué "casillero" de la grilla vive esa celda ('template' o una
        // clave de `members`) — hace falta para, más abajo, agregar ahí
        // mismo el texto que se le encuentre a cada ícono (ver nota
        // siguiente: el texto nunca llegó a formar parte de la grilla).
        $cellsIdLists = [['slot' => 'template', 'ids' => $matchingGrid['template']]];
        foreach ($matchingGrid['members'] as $memberKey => $member) {
            $ids = array_column(array_filter($member), 'id');
            if (!empty($ids)) {
                $cellsIdLists[] = ['slot' => $memberKey, 'ids' => $ids];
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

        $resizedBackgroundBoxes = []; // [x_cm, y_cm] por celda, para recalcular gap_x_cm/gap_y_cm al final.
        $cellComponents = []; // índices de fondo/ícono/texto por celda, para poder correrlos en bloque al normalizar alineación.
        foreach ($cellsIdLists as $cell) {
            $slot = $cell['slot'];
            $backgroundIndex = null;
            $iconIndex = null;
            $iconBox = null; // [minX, minY, maxX, maxY]
            foreach ($cell['ids'] as $id) {
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
                $iconIndex = $index;
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
            $side = (float) $spotShape->width_cm; // tamaño FIJO del catálogo (ver nota arriba) — no se calcula a partir de ícono+texto.

            $page['elements'][$backgroundIndex]['x_cm'] = round($centerX - $side / 2, 2);
            $page['elements'][$backgroundIndex]['y_cm'] = round($centerY - $side / 2, 2);
            $page['elements'][$backgroundIndex]['width_cm'] = round($side, 2);
            $page['elements'][$backgroundIndex]['height_cm'] = round($side, 2);
            $page['elements'][$backgroundIndex]['label_shape_id'] = $spotShape->id;
            $page['elements'][$backgroundIndex]['color'] = ['mode' => 'hex', 'value' => '#FFFFFF'];

            if ($isPersonalizable) {
                $page['elements'][$iconIndex]['editable_by_customer'] = true;
                $page['elements'][$iconIndex]['editable_field'] = 'icon';
            }

            // El ANCHO de esta caja de texto quedaba calculado "lo justo"
            // para el renglón más largo del PDF de origen (ver
            // PdfDesignImportService::textsToTextElement) — bien para un
            // nombre real (ya viene partido en 2 renglones cortos), pero el
            // editor dibuja esta caja con ESE ancho real (CSS), no con
            // `max_chars_per_line` (ese límite solo lo usa el backend al
            // generar el PDF real, `EtiquetaService::dividirEnRenglones`).
            // El marcador de la variable sin venta real detrás
            // ("(x)Nombre y apellido", mucho más largo que cualquier
            // nombre) se envuelve dentro de esa caja angosta por su cuenta
            // y termina partido en 3+ renglones feos (hasta la "(x)" sola
            // en su propio renglón). Se agranda la caja al ancho disponible
            // DEL CÍRCULO (no al del texto real) — sigue sin salirse del
            // círculo (es justo lo que entra adentro) y le da mucho más
            // lugar al marcador para acomodarse en 2 renglones en vez de 3.
            if ($bestTextIndex !== null) {
                $textEl = $page['elements'][$bestTextIndex];
                $availableWidthCm = round(max(1.0, $side - 0.3), 2);
                if ($availableWidthCm > $textEl['width_cm']) {
                    $textCenterX = $textEl['x_cm'] + $textEl['width_cm'] / 2;
                    $page['elements'][$bestTextIndex]['width_cm'] = $availableWidthCm;
                    $page['elements'][$bestTextIndex]['x_cm'] = round($textCenterX - $availableWidthCm / 2, 2);
                    $textEl = $page['elements'][$bestTextIndex];
                }

                // `max_chars_per_line` en sí también se recalcula geométrico
                // (cuántos caracteres entran de verdad en ese ancho a este
                // tamaño de fuente) en vez de "lo justo para el nombre de
                // muestra" — nunca se achica, así que un nombre real largo
                // se sigue partiendo bien.
                $fontSizePt = ((float) ($textEl['font_size_px'] ?? 12)) * 0.75;
                $geometricMaxChars = max(6, (int) floor($availableWidthCm * 28.3465 / ($fontSizePt * 0.6)));
                $page['elements'][$bestTextIndex]['max_chars_per_line'] = max(
                    (int) ($textEl['max_chars_per_line'] ?? 6),
                    $geometricMaxChars
                );

                // El tamaño de letra según longitud del nombre
                // (`font_size_rules`/`length_rules`) para esta celda circular
                // se arma más abajo, en `applyLegacyTextSizeRules()` — es el
                // mismo mecanismo genérico que usa CUALQUIER contenedor de
                // CUALQUIER temática que la vista legacy achique a mano con
                // CSS (`.circulo-personaje p.normal-text-size`/`p.small-text-
                // size`, en este caso), no algo puntual de "spot".

                if ($isPersonalizable) {
                    $page['elements'][$bestTextIndex]['editable_by_customer'] = true;
                    $page['elements'][$bestTextIndex]['editable_field'] = 'color';
                }
            }

            $resizedBackgroundBoxes[$slot] = ['x_cm' => $page['elements'][$backgroundIndex]['x_cm'], 'y_cm' => $page['elements'][$backgroundIndex]['y_cm']];
            $cellComponents[$slot] = ['background' => $backgroundIndex, 'icon' => $iconIndex, 'text' => $bestTextIndex];

            // "Relación" ícono+nombre (ver PdfDesignImportService::
            // buildIconTextLayoutGroup) — mismo criterio que el resto del
            // importador, centrada ahora en el círculo ya redimensionado.
            if ($bestTextIndex !== null && $iconIndex !== null) {
                $page['layout_groups'][] = PdfDesignImportService::buildIconTextLayoutGroup(
                    $page['elements'][$iconIndex],
                    $page['elements'][$bestTextIndex],
                    $page['elements'][$backgroundIndex]['id']
                );
            }

            // La grilla (data.pages[].grids[]) se armó en
            // PdfDesignImportService::import() ANTES de que el texto
            // suelto de esta celda se asociara con su ícono (recién acá
            // arriba) — sin esto, el texto queda totalmente afuera de la
            // grilla: al usar "Generar grilla" en el editor para cambiar
            // cols/filas/separación, el fondo+ícono se regeneran bien pero
            // el texto no se mueve ni se toca (el editor ni se entera de
            // que existe). Se agrega al mismo casillero (`template` o
            // `members[...]`) que ya tienen el fondo+ícono de esta celda.
            if ($bestTextIndex !== null) {
                $textEl = $page['elements'][$bestTextIndex];
                if ($slot === 'template') {
                    $page['grids'][$matchingGridIndex]['template'][] = $textEl['id'];
                } else {
                    $page['grids'][$matchingGridIndex]['members'][$slot][] = [
                        'id' => $textEl['id'],
                        'sig' => PdfDesignImportService::elementSignature($textEl),
                    ];
                }
            }
        }

        // Cada celda se centró según la posición REAL de SU PROPIO ícono en
        // el PDF original — y esa posición puede traer un corrimiento
        // mínimo (1-2mm) sin ningún significado real, no es que la celda
        // haya sido diseñada más arriba o más abajo a propósito. Es una
        // particularidad conocida de cómo dompdf generó el PDF original:
        // el primer elemento de una fila puede cachear las métricas de
        // fuente de forma levemente distinta al resto (ver nota sobre
        // `line_height` en PDF_IMPORTAR_DESDE_PDF.md) y terminar un par de
        // mm más arriba/abajo que sus vecinos. Sin corregir esto, una fila
        // de celdas por lo demás idénticas queda con la primera columna
        // visiblemente desalineada del resto. Se corrige llevando cada
        // celda a la posición MEDIANA de su fila (eje Y) y columna (eje X)
        // — fondo, ícono y texto se mueven juntos, en bloque.
        $rowYs = [];
        $colXs = [];
        foreach ($resizedBackgroundBoxes as $slot => $box) {
            [$col, $row] = $slot === 'template' ? [0, 0] : array_map('intval', explode(',', $slot));
            $rowYs[$row][] = $box['y_cm'];
            $colXs[$col][] = $box['x_cm'];
        }
        $median = function (array $values) {
            sort($values);
            $count = count($values);
            $mid = intdiv($count, 2);
            return $count % 2 === 0 ? ($values[$mid - 1] + $values[$mid]) / 2 : $values[$mid];
        };
        $canonicalYByRow = array_map($median, $rowYs);
        $canonicalXByCol = array_map($median, $colXs);

        foreach ($cellComponents as $slot => $components) {
            [$col, $row] = $slot === 'template' ? [0, 0] : array_map('intval', explode(',', $slot));
            $box = $resizedBackgroundBoxes[$slot];
            $deltaX = round($canonicalXByCol[$col] - $box['x_cm'], 2);
            $deltaY = round($canonicalYByRow[$row] - $box['y_cm'], 2);
            if ($deltaX === 0.0 && $deltaY === 0.0) {
                continue;
            }
            foreach (['background', 'icon', 'text'] as $role) {
                $index = $components[$role];
                if ($index === null) {
                    continue;
                }
                $page['elements'][$index]['x_cm'] = round($page['elements'][$index]['x_cm'] + $deltaX, 2);
                $page['elements'][$index]['y_cm'] = round($page['elements'][$index]['y_cm'] + $deltaY, 2);
            }
            $resizedBackgroundBoxes[$slot] = [
                'x_cm' => $page['elements'][$components['background']]['x_cm'],
                'y_cm' => $page['elements'][$components['background']]['y_cm'],
            ];
        }

        // La misma particularidad de dompdf que corrige el bloque de arriba
        // (posición) también afecta, en la o las celdas que le toque, el
        // `line_height`/`height_cm` MEDIDO del nombre — acá sí puede salir
        // una diferencia grande (no 1-2mm): una columna entera con un
        // interlineado visiblemente más separado que el resto, aunque sea
        // exactamente el mismo texto/tamaño de fuente. Como estas celdas son
        // todas iguales por diseño, se normalizan todas al mismo
        // `line_height`/`height_cm` — ideal el real del CSS
        // (`detectSpotLineHeight()`, `.circulo-texto { line-height: 0.7 }`),
        // si no se pudo leer la vista, a la moda (el valor que más se repite
        // entre todas las celdas del renglón — no la media/mediana: acá el
        // objetivo es "la mayoría tiene razón, las pocas distintas están mal
        // medidas", no promediar un valor que legítimamente varía de celda a
        // celda).
        $moda = function (array $values) {
            $counts = array_count_values(array_map(fn($v) => (string) $v, $values));
            arsort($counts);
            return (float) array_key_first($counts);
        };
        $lineHeights = [];
        $textHeights = [];
        foreach ($cellComponents as $components) {
            $textIndex = $components['text'];
            if ($textIndex === null) {
                continue;
            }
            $lineHeights[] = $page['elements'][$textIndex]['line_height'] ?? null;
            $textHeights[] = $page['elements'][$textIndex]['height_cm'] ?? null;
        }
        $lineHeights = array_filter($lineHeights, fn($v) => $v !== null);
        $textHeights = array_filter($textHeights, fn($v) => $v !== null);
        if (count($lineHeights) > 1) {
            // El CSS real (`.circulo-texto { line-height: 0.7 }`) tiene
            // prioridad sobre la moda de lo medido — es exacto, no una
            // estimación, y la particularidad de dompdf de arriba puede
            // hacer que hasta la moda se aleje del real si afecta a más de
            // una celda.
            $modalLineHeight = $cssLineHeight ?? $moda($lineHeights);
            $modalTextHeight = $moda($textHeights);
            foreach ($cellComponents as $components) {
                $textIndex = $components['text'];
                if ($textIndex === null) {
                    continue;
                }
                $page['elements'][$textIndex]['line_height'] = $modalLineHeight;
                $page['elements'][$textIndex]['height_cm'] = $modalTextHeight;
            }
        }

        // El `gap_x_cm`/`gap_y_cm` de esta grilla se calculó en
        // PdfDesignImportService::detectGrids() con la posición de los
        // fondos ORIGINALES (chicos, pegados al ícono) — ya quedó
        // desactualizado apenas acá arriba se agrandaron a los `$side`cm
        // fijos del catálogo. Si no se corrige, "Generar grilla" en el
        // editor reacomoda las columnas/filas usando un espacio mucho más
        // grande que el real entre los fondos ya redimensionados, y las
        // celdas terminan empujadas fuera de la hoja.
        //
        // No alcanza con tomar los x/y únicos de TODOS los fondos (como
        // hace detectGrids() con las celdas originales, perfectamente
        // alineadas): acá cada fondo se recentró según la caja propia de
        // SU ícono+texto, así que dos celdas de la misma columna pueden
        // quedar con un x_cm levemente distinto entre sí. Promediando el
        // x de cada columna (y el y de cada fila) por separado, usando la
        // posición "col,row" de cada celda en la grilla, el resultado no
        // se arruina por esas variaciones chicas.
        //
        // El "ancho de celda" que usa el editor al regenerar columnas NO es
        // el lado del círculo (`$side`): `applyGridParams` mide el
        // rectángulo que engloba TODOS los elementos de la celda molde
        // (fondo + ícono + texto) y avanza cada columna/fila esa medida +
        // el gap. El texto del nombre es más ANCHO que el círculo de 3cm
        // (se centra en él pero sobresale a los costados) aunque entra
        // bien en su alto — por eso el paso horizontal real de la celda
        // molde es más ancho que 3cm, y si el gap se calculara contra 3cm
        // quedaba de más: la columna ya no caía en el lugar original.
        $templateIds = $page['grids'][$matchingGridIndex]['template'];
        $templateMinX = INF;
        $templateMinY = INF;
        $templateMaxX = -INF;
        $templateMaxY = -INF;
        foreach ($templateIds as $id) {
            $index = $indexById[$id] ?? null;
            if ($index === null) {
                continue;
            }
            $el = $page['elements'][$index];
            $templateMinX = min($templateMinX, $el['x_cm']);
            $templateMinY = min($templateMinY, $el['y_cm']);
            $templateMaxX = max($templateMaxX, $el['x_cm'] + $el['width_cm']);
            $templateMaxY = max($templateMaxY, $el['y_cm'] + $el['height_cm']);
        }
        $side = (float) $spotShape->width_cm;
        $spanXTemplate = $templateMaxX > $templateMinX ? $templateMaxX - $templateMinX : $side;
        $spanYTemplate = $templateMaxY > $templateMinY ? $templateMaxY - $templateMinY : $side;
        $sumXByCol = [];
        $countXByCol = [];
        $sumYByRow = [];
        $countYByRow = [];
        foreach ($resizedBackgroundBoxes as $slot => $box) {
            [$col, $row] = $slot === 'template' ? [0, 0] : array_map('intval', explode(',', $slot));
            $sumXByCol[$col] = ($sumXByCol[$col] ?? 0) + $box['x_cm'];
            $countXByCol[$col] = ($countXByCol[$col] ?? 0) + 1;
            $sumYByRow[$row] = ($sumYByRow[$row] ?? 0) + $box['y_cm'];
            $countYByRow[$row] = ($countYByRow[$row] ?? 0) + 1;
        }
        $avgXByCol = [];
        foreach ($sumXByCol as $col => $sum) {
            $avgXByCol[$col] = $sum / $countXByCol[$col];
        }
        $avgYByRow = [];
        foreach ($sumYByRow as $row => $sum) {
            $avgYByRow[$row] = $sum / $countYByRow[$row];
        }
        ksort($avgXByCol, SORT_NUMERIC);
        ksort($avgYByRow, SORT_NUMERIC);
        $avgXValues = array_values($avgXByCol);
        $avgYValues = array_values($avgYByRow);
        if (count($avgXValues) > 1) {
            $page['grids'][$matchingGridIndex]['gap_x_cm'] = round($avgXValues[1] - $avgXValues[0] - $spanXTemplate, 2);
        }
        if (count($avgYValues) > 1) {
            $page['grids'][$matchingGridIndex]['gap_y_cm'] = round($avgYValues[1] - $avgYValues[0] - $spanYTemplate, 2);
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
     * Diseños importados: cada elemento sale con `fill` (derivado de
     * color/background_image en las etiquetas, `none` en el resto). Las
     * etiquetas conservan color/background_image para el formato viejo.
     */
    private function withFills(array $data): array
    {
        foreach ($data['pages'] ?? [] as $pageIdx => $page) {
            foreach ($page['elements'] ?? [] as $elIdx => $el) {
                if (is_array($el) && !isset($el['fill'])) {
                    $data['pages'][$pageIdx]['elements'][$elIdx]['fill'] = PdfDesignSanitizer::fillDelElemento($el);
                }
            }
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

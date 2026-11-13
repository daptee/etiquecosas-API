<?php

namespace App\Http\Controllers;

use App\Models\PdfTheme;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable;

/**
 * ABM de temáticas para el editor de PDF: nombre + paleta de colores (array
 * de hex) + un set de íconos del catálogo (personalization_icons). Un
 * producto (a nivel general) o una variante puntual pueden asociarse
 * opcionalmente a una — ver Product::theme()/ProductVariant::theme(). No
 * tiene relación con la tabla legacy `tematicas` del sistema viejo.
 * Ver PDF_TEMATICAS.md.
 */
class PdfThemeController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    public function index(Request $request)
    {
        $perPage = $request->query('quantity');
        $page = $request->query('page', 1);
        $search = $request->query('search');

        $query = PdfTheme::with('icons:id,name,icon');
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }
        $query->orderBy('name', 'asc');

        if (!$perPage) {
            $themes = $query->get();
            return $this->success($themes, 'Temáticas obtenidas');
        }

        $themes = $query->paginate($perPage, ['*'], 'page', $page);
        $metaData = [
            'current_page' => $themes->currentPage(),
            'last_page' => $themes->lastPage(),
            'per_page' => $themes->perPage(),
            'total' => $themes->total(),
            'from' => $themes->firstItem(),
            'to' => $themes->lastItem(),
        ];
        return $this->success($themes->items(), 'Temáticas obtenidas', $metaData);
    }

    public function store(Request $request)
    {
        if ($request->has('statusId')) {
            $request->merge(['statusId' => (int) $request->input('statusId')]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:pdf_themes',
            'colors' => 'nullable|array',
            'colors.*' => 'string|max:20',
            'iconIds' => 'nullable|array',
            'iconIds.*' => 'integer|exists:personalization_icons,id',
            'statusId' => 'nullable|exists:general_statuses,id',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Pdf Theme', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $theme = PdfTheme::create([
            'name' => $request->name,
            'colors' => $request->colors ?? [],
            'status_id' => $request->statusId ?? 1,
        ]);

        $this->syncThemeIcons($theme, $request->iconIds ?? []);

        $theme->load('icons:id,name,icon');
        $this->logAudit(Auth::user(), 'Store Pdf Theme', $request->all(), $theme);
        return $this->success($theme, 'Temática creada');
    }

    public function update(Request $request, $id)
    {
        $theme = $this->findObject(PdfTheme::class, $id);
        if ($request->has('statusId')) {
            $request->merge(['statusId' => (int) $request->input('statusId')]);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', Rule::unique('pdf_themes')->ignore($theme->id)],
            'colors' => 'nullable|array',
            'colors.*' => 'string|max:20',
            'iconIds' => 'nullable|array',
            'iconIds.*' => 'integer|exists:personalization_icons,id',
            'statusId' => 'nullable|exists:general_statuses,id',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Pdf Theme', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        $theme->name = $request->name;
        $theme->colors = $request->colors ?? [];
        $theme->status_id = $request->statusId ?? $theme->status_id;
        $theme->save();

        if ($request->has('iconIds')) {
            $this->syncThemeIcons($theme, $request->iconIds ?? []);
        }

        $theme->load('icons:id,name,icon');
        $this->logAudit(Auth::user(), 'Update Pdf Theme', $request->all(), $theme);
        return $this->success($theme, 'Temática actualizada');
    }

    public function delete($id)
    {
        $theme = $this->findObject(PdfTheme::class, $id);
        $theme->delete();
        $this->logAudit(Auth::user(), 'Delete Pdf Theme', $id, $theme);
        return $this->success($theme, 'Temática eliminada');
    }

    /**
     * Reemplaza los íconos asociados, en el orden recibido (sort_order =
     * posición en el array) — simple attach/sync vía la tabla pivote.
     */
    private function syncThemeIcons(PdfTheme $theme, array $iconIds): void
    {
        $sync = [];
        foreach (array_values($iconIds) as $position => $iconId) {
            $sync[$iconId] = ['sort_order' => $position];
        }
        $theme->icons()->sync($sync);
    }

    /**
     * Para el editor de PDF: dado un producto (y opcionalmente una variante),
     * resuelve y devuelve la temática aplicable — la de la VARIANTE tiene
     * prioridad sobre la del producto general si ambas están seteadas — con
     * sus colores e íconos, para que el editor los ofrezca como sugerencia
     * al elegir colores/íconos de un elemento. No aplica nada solo: es
     * información para que el front arme la UI de sugerencias.
     */
    public function suggestForProduct(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'productId' => 'required|exists:products,id',
            'variantId' => 'nullable|exists:product_variants,id',
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $themeId = null;
        if ($request->variantId) {
            $variant = ProductVariant::find($request->variantId);
            $themeId = $variant->theme_id ?? null;
        }
        if (!$themeId) {
            $product = Product::find($request->productId);
            $themeId = $product->theme_id ?? null;
        }

        if (!$themeId) {
            return $this->success(null, 'Este producto/variante no tiene una temática asociada');
        }

        $theme = PdfTheme::with('icons:id,name,icon')->find($themeId);
        if (!$theme) {
            return $this->success(null, 'Este producto/variante no tiene una temática asociada');
        }

        return $this->success($theme, 'Temática sugerida obtenida');
    }
}

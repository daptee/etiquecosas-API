<?php

namespace App\Http\Controllers;

use App\Models\Typography;
use App\Models\TypographyFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Traits\FindObject;
use App\Traits\ApiResponse;
use App\Traits\Auditable;

class TypographyController extends Controller
{
    use FindObject, ApiResponse, Auditable;

    private const ALLOWED_EXTENSIONS = ['ttf', 'otf', 'woff', 'woff2', 'eot'];

    public function index(Request $request)
    {
        $search   = $request->query('search');
        $statusId = $request->query('statusId');
        $perPage  = $request->query('quantity');
        $page     = $request->query('page', 1);

        $query = Typography::with(['files', 'generalStatus']);

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($statusId) {
            $query->where('status_id', $statusId);
        }

        $query->orderBy('name', 'asc');

        if (!$perPage) {
            $typographies = $query->get();
            $this->logAudit(Auth::user(), 'Get Typographies List', $request->all(), $typographies->first());
            return $this->success($typographies, 'Tipografías obtenidas');
        }

        $typographies = $query->paginate($perPage, ['*'], 'page', $page);
        $metaData = [
            'current_page' => $typographies->currentPage(),
            'last_page'    => $typographies->lastPage(),
            'per_page'     => $typographies->perPage(),
            'total'        => $typographies->total(),
            'from'         => $typographies->firstItem(),
            'to'           => $typographies->lastItem(),
        ];
        return $this->success($typographies->items(), 'Tipografías obtenidas', $metaData);
    }

    public function show($id)
    {
        $typography = $this->findObject(Typography::class, $id);
        $typography->load(['files', 'generalStatus']);
        return $this->success($typography, 'Tipografía obtenida');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255|unique:typographies',
            'statusId' => 'nullable|in:1,2',
            'files'    => 'nullable|array',
            'files.*'  => 'required|file|max:10240',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Typography', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $ext = strtolower($file->getClientOriginalExtension());
                if (!in_array($ext, self::ALLOWED_EXTENSIONS)) {
                    return $this->validationError([
                        'files' => ["Extensión no permitida: {$ext}. Permitidas: " . implode(', ', self::ALLOWED_EXTENSIONS)],
                    ]);
                }
            }
        }

        $typography = Typography::create([
            'name'      => $request->name,
            'status_id' => $request->statusId ?? 1,
        ]);

        if ($request->hasFile('files')) {
            $this->storeFiles($typography, $request->file('files'));
        }

        $typography->load(['files', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Store Typography', $request->all(), $typography);
        return $this->success($typography, 'Tipografía creada');
    }

    public function update(Request $request, $id)
    {
        $typography = $this->findObject(Typography::class, $id);

        $validator = Validator::make($request->all(), [
            'name'     => 'nullable|string|max:255|unique:typographies,name,' . $typography->id,
            'statusId' => 'nullable|in:1,2',
            'files'    => 'nullable|array',
            'files.*'  => 'file|max:10240',
        ]);
        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Typography', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $ext = strtolower($file->getClientOriginalExtension());
                if (!in_array($ext, self::ALLOWED_EXTENSIONS)) {
                    return $this->validationError([
                        'files' => ["Extensión no permitida: {$ext}. Permitidas: " . implode(', ', self::ALLOWED_EXTENSIONS)],
                    ]);
                }
            }
        }

        $typography->update([
            'name'      => $request->input('name', $typography->name),
            'status_id' => $request->input('statusId', $typography->status_id),
        ]);

        if ($request->hasFile('files')) {
            $this->storeFiles($typography, $request->file('files'));
        }

        $typography->load(['files', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Update Typography', $request->all(), $typography);
        return $this->success($typography, 'Tipografía actualizada');
    }

    public function toggleStatus($id)
    {
        $typography = $this->findObject(Typography::class, $id);
        $typography->update([
            'status_id' => $typography->status_id === 1 ? 2 : 1,
        ]);
        $typography->load(['files', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Toggle Typography Status', $id, $typography);
        return $this->success($typography, 'Estado actualizado');
    }

    public function delete($id)
    {
        $typography = $this->findObject(Typography::class, $id);
        $this->deleteAllFiles($typography);
        $typography->delete();
        $this->logAudit(Auth::user(), 'Delete Typography', $id, $typography);
        return $this->success($typography, 'Tipografía eliminada');
    }

    /**
     * Sube uno o más archivos de fuente a una tipografía existente.
     * Multipart: files[] (array de archivos)
     */
    public function uploadFiles(Request $request, $id)
    {
        $typography = $this->findObject(Typography::class, $id);

        $validator = Validator::make($request->all(), [
            'files'   => 'required|array|min:1',
            'files.*' => 'required|file|max:10240',
        ]);
        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        foreach ($request->file('files') as $file) {
            $ext = strtolower($file->getClientOriginalExtension());
            if (!in_array($ext, self::ALLOWED_EXTENSIONS)) {
                return $this->validationError([
                    'files' => ["Extensión no permitida: {$ext}. Permitidas: " . implode(', ', self::ALLOWED_EXTENSIONS)],
                ]);
            }
        }

        $this->storeFiles($typography, $request->file('files'));

        $typography->load(['files', 'generalStatus']);
        $this->logAudit(Auth::user(), 'Upload Typography Files', ['id' => $id], $typography);
        return $this->success($typography, 'Archivos subidos correctamente');
    }

    /**
     * Elimina un archivo de fuente puntual de una tipografía.
     */
    public function deleteFile($id, $fileId)
    {
        $typography = $this->findObject(Typography::class, $id);
        $file = TypographyFile::where('id', $fileId)
            ->where('typography_id', $typography->id)
            ->firstOrFail();

        if (Storage::disk('public_uploads')->exists($file->file_path)) {
            Storage::disk('public_uploads')->delete($file->file_path);
        }

        $file->delete();

        $this->logAudit(Auth::user(), 'Delete Typography File', ['typography_id' => $id, 'file_id' => $fileId], $file);
        return $this->success(null, 'Archivo eliminado correctamente');
    }

    private function storeFiles(Typography $typography, array $files): void
    {
        foreach ($files as $file) {
            $ext      = strtolower($file->getClientOriginalExtension());
            $original = $file->getClientOriginalName();
            $path     = 'fonts/typographies/' . $typography->id . '/' . uniqid('font_') . '.' . $ext;

            Storage::disk('public_uploads')->put($path, file_get_contents($file));

            TypographyFile::create([
                'typography_id' => $typography->id,
                'file_path'     => $path,
                'file_name'     => $original,
                'file_type'     => $ext,
            ]);
        }
    }

    private function deleteAllFiles(Typography $typography): void
    {
        foreach ($typography->files as $file) {
            if (Storage::disk('public_uploads')->exists($file->file_path)) {
                Storage::disk('public_uploads')->delete($file->file_path);
            }
        }
    }
}

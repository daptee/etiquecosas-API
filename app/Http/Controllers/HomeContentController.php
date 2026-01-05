<?php

namespace App\Http\Controllers;

use App\Models\HomeContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Traits\ApiResponse;
use App\Traits\Auditable;

class HomeContentController extends Controller
{
    use ApiResponse, Auditable;

    /**
     * Muestra el contenido de la home
     */
    public function show()
    {
        $homeContent = HomeContent::first();

        if (!$homeContent) {
            $this->logAudit(Auth::user(), 'Get Home Content', [], 'No content found');
            return $this->success(null, 'No hay contenido configurado');
        }

        $this->logAudit(Auth::user(), 'Get Home Content', [], $homeContent);
        return $this->success($homeContent, 'Contenido obtenido');
    }

    /**
     * Crea el contenido de la home (solo se puede crear una vez)
     */
    public function store(Request $request)
    {
        // Verificar que no exista ya un registro
        $existingContent = HomeContent::first();
        if ($existingContent) {
            $this->logAudit(Auth::user(), 'Store Home Content', $request->all(), 'Content already exists');
            return $this->error('Ya existe contenido configurado. Use el método de actualización.', 400);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|json',
        ]);

        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Store Home Content', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        // Decodificar el JSON para procesarlo
        $contentData = json_decode($request->content, true);

        // Procesar archivos si existen
        $contentData = $this->processFiles($request, $contentData);

        // Crear el registro (no hacer json_encode porque el modelo ya tiene el cast)
        $homeContent = HomeContent::create([
            'content' => $contentData,
        ]);

        $this->logAudit(Auth::user(), 'Store Home Content', $request->all(), $homeContent);
        return $this->success($homeContent, 'Contenido de home creado');
    }

    /**
     * Actualiza el contenido de la home
     */
    public function update(Request $request)
    {
        $homeContent = HomeContent::first();

        if (!$homeContent) {
            $this->logAudit(Auth::user(), 'Update Home Content', $request->all(), 'No content found');
            return $this->error('No existe contenido configurado. Use el método de creación.', 404);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|json',
        ]);

        if ($validator->fails()) {
            $this->logAudit(Auth::user(), 'Update Home Content', $request->all(), $validator->errors());
            return $this->validationError($validator->errors());
        }

        // Decodificar el JSON nuevo y el existente
        $newContentData = json_decode($request->content, true);
        $oldContentData = $homeContent->content;

        // Detectar y eliminar archivos que ya no están en el nuevo contenido
        $this->deleteRemovedFiles($oldContentData, $newContentData);

        // Procesar nuevos archivos si existen
        $newContentData = $this->processFiles($request, $newContentData);

        // Actualizar el registro (no hacer json_encode porque el modelo ya tiene el cast)
        $homeContent->content = $newContentData;
        $homeContent->save();

        $this->logAudit(Auth::user(), 'Update Home Content', $request->all(), $homeContent);
        return $this->success($homeContent, 'Contenido de home actualizado');
    }

    /**
     * Procesa los archivos subidos y los asocia al contenido
     *
     * @param Request $request
     * @param array $contentData
     * @return array
     */
    private function processFiles(Request $request, array $contentData)
    {
        // Procesar bloques usando índices del array
        if (isset($contentData['blocks']) && is_array($contentData['blocks'])) {
            foreach ($contentData['blocks'] as $blockIndex => $block) {
                if (isset($block['images']) && is_array($block['images'])) {
                    foreach ($block['images'] as $imageIndex => $image) {
                        // Buscar archivo usando el índice del array (block_0, block_1, block_2...)
                        $fileKey = "block_{$blockIndex}_image_{$imageIndex}";

                        if ($request->hasFile($fileKey)) {
                            $file = $request->file($fileKey);
                            $fileName = 'home/blocks/' . uniqid('block_') . '.' . $file->getClientOriginalExtension();

                            if (Storage::disk('public_uploads')->put($fileName, file_get_contents($file))) {
                                // Eliminar el archivo antiguo si existe
                                if (!empty($image['url']) && Storage::disk('public_uploads')->exists($image['url'])) {
                                    Storage::disk('public_uploads')->delete($image['url']);
                                }

                                // Actualizar la URL en el contenido
                                $contentData['blocks'][$blockIndex]['images'][$imageIndex]['url'] = $fileName;
                            }
                        }
                    }
                }
            }
        }

        // Procesar categorías (si tienen imágenes en el futuro)
        // La estructura actual no incluye imágenes en categorías, pero está preparada para futuras extensiones

        return $contentData;
    }

    /**
     * Compara el contenido antiguo con el nuevo y elimina archivos que ya no se usan
     *
     * @param array $oldContent
     * @param array $newContent
     * @return void
     */
    private function deleteRemovedFiles(array $oldContent, array $newContent)
    {
        $oldUrls = $this->extractFileUrls($oldContent);
        $newUrls = $this->extractFileUrls($newContent);

        // Archivos que están en el contenido antiguo pero no en el nuevo
        $urlsToDelete = array_diff($oldUrls, $newUrls);

        foreach ($urlsToDelete as $url) {
            if (Storage::disk('public_uploads')->exists($url)) {
                Storage::disk('public_uploads')->delete($url);
            }
        }
    }

    /**
     * Extrae todas las URLs de archivos del contenido
     *
     * @param array $content
     * @return array
     */
    private function extractFileUrls(array $content)
    {
        $urls = [];

        // Extraer URLs de bloques
        if (isset($content['blocks']) && is_array($content['blocks'])) {
            foreach ($content['blocks'] as $block) {
                if (isset($block['images']) && is_array($block['images'])) {
                    foreach ($block['images'] as $image) {
                        if (isset($image['url']) && !empty($image['url'])) {
                            // Solo agregar URLs que sean rutas de almacenamiento (no URLs externas)
                            if (strpos($image['url'], 'http') !== 0) {
                                $urls[] = $image['url'];
                            }
                        }
                    }
                }
            }
        }

        return array_unique($urls);
    }
}

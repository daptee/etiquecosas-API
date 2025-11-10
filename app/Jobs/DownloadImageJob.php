<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use App\Models\ProductImage;

class DownloadImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $url;
    public $productId;
    public $index;

    public function __construct(string $url, int $productId, int $index)
    {
        $this->url = $url;
        $this->productId = $productId;
        $this->index = $index;
    }

    public function handle()
    {
        try {
            $response = Http::get($this->url);

            if ($response->successful()) {
                // Obtener nombre original del link
                $path = parse_url($this->url, PHP_URL_PATH);
                $filename = basename($path);

                $folder = public_path('images/products');

                // Crear carpeta si no existe
                if (!is_dir($folder)) {
                    mkdir($folder, 0777, true);
                }

                // Evitar colisiones con "-copy", "-copy2", etc.
                $finalName = $filename;
                $counter = 1;

                while (file_exists($folder . '/' . $finalName)) {
                    $info = pathinfo($filename);
                    $name = $info['filename'];
                    $ext = isset($info['extension']) ? '.' . $info['extension'] : '';

                    $finalName = $name . "-copy" . ($counter > 1 ? $counter : '') . $ext;
                    $counter++;
                }

                // Guardar archivo físico en public/images/products/
                file_put_contents($folder . '/' . $finalName, $response->body());

                // Crear registro en DB
                ProductImage::create([
                    'product_id' => $this->productId,
                    'img'        => "images/products/{$finalName}", // así quedará como URL relativa
                    'is_main'    => $this->index === 0, // primera imagen = main
                ]);

                echo "✅ Guardada {$finalName} en DB y public/images/products\n";
            } else {
                echo "❌ Error descargando imagen {$this->url}\n";
            }
        } catch (\Exception $e) {
            echo "⚠️ Excepción en {$this->url}: {$e->getMessage()}\n";
        }
    }
}

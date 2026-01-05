# Feed de Catálogo para Facebook/Meta

## Descripción

Este script genera automáticamente un feed XML con todos los productos de la tienda en el formato requerido por Facebook/Meta para sincronizar el catálogo.

## URL del Feed

El feed XML está disponible públicamente en:

```
https://etiquecosas.ar/api/feed-facebook.xml
```

## Configuración

### 1. Productos Incluidos

El feed incluye:
- ✅ Todos los productos (excepto los eliminados con soft delete)
- ✅ Solo información básica del producto
- ❌ No incluye personalizaciones (para productos personalizables)
- ❌ No incluye variaciones (para productos con variantes)

### 2. Campos del Feed

Cada producto incluye los siguientes campos según las especificaciones de Meta:

| Campo | Descripción | Fuente |
|-------|-------------|--------|
| `g:id` | ID único del producto | `products.id` |
| `g:title` | Nombre del producto | `products.name` |
| `g:description` | Descripción del producto | `products.shortDescription` o `products.description` |
| `g:link` | URL del producto | `https://www.etiquecosas.com.ar/productos/{slug}` |
| `g:image_link` | URL de la imagen principal | `https://api.etiquecosas.com.ar/public/{img}` (prioriza `is_main = true`) |
| `g:availability` | Disponibilidad | Basado en `product_stock_status_id` |
| `g:price` | Precio con moneda | `products.discounted_price` o `products.price` + "ARS" |
| `g:brand` | Marca | "Etiquecosas" |
| `g:condition` | Condición | "new" |

### 3. Lógica de Precios

El controlador maneja precios de la siguiente manera:

1. **Precio con descuento**: Si existe `discounted_price` y está dentro del rango de fechas válido (`discounted_start` y `discounted_end`)
2. **Precio regular**: Si no hay descuento activo, usa `price`
3. **Formato**: Siempre incluye 2 decimales y la moneda "ARS" (ej: `4500.00 ARS`)

### 4. Lógica de Disponibilidad

Mapeo de estados de stock:

- `product_stock_status_id = 1` → `in stock`
- `product_stock_status_id = 2` → `out of stock`
- `product_stock_status_id = 3` → `preorder`
- **Default**: `in stock` (para productos digitales/personalizables)

### 5. Imágenes

El sistema busca imágenes en el siguiente orden:

1. Primera imagen con `is_main = true`
2. Primera imagen disponible
3. Imagen por defecto: `https://etiquecosas.ar/img/default-product.jpg`

**Rutas de imágenes**:
- Si `img` contiene URL completa (http/https), se usa directamente
- Si no, se construye: `https://api.etiquecosas.com.ar/public/{img}`

## Ajustes Necesarios

Antes de usar en producción, revisa y ajusta:

### A. Productos Incluidos

El feed incluye todos los productos de la tabla `products` que no hayan sido eliminados (soft delete). No se filtra por estado, por lo que incluye productos en cualquier estado (activo, inactivo, borrador, etc.).

**Nota**: Si necesitas filtrar por estado específico en el futuro, puedes agregar el filtro en la línea 19 del controlador.

### B. Estados de Stock

En [FacebookFeedController.php:125-135](app/Http/Controllers/FacebookFeedController.php#L125-L135):

```php
if ($product->product_stock_status_id == 1) {
    return 'in stock';
}
```

**Acción**: Verificar los IDs de tu tabla `product_stock_statuses` y ajustar el mapeo si es necesario.

### C. URLs Configuradas

Las siguientes URLs ya están configuradas:
- **Productos**: `https://www.etiquecosas.com.ar/productos/{slug}`
- **Imágenes**: `https://api.etiquecosas.com.ar/public/{img}`

Estas URLs apuntan a la web pública y la API respectivamente.

## Actualización Automática (Recomendado)

Para que Meta mantenga el catálogo sincronizado, se recomienda configurar un cron job o tarea programada.

### Opción 1: Laravel Scheduler

1. Crear comando Artisan:

```php
php artisan make:command CacheFacebookFeed
```

2. En `app/Console/Commands/CacheFacebookFeed.php`:

```php
public function handle()
{
    // Generar y cachear el feed
    $controller = new \App\Http\Controllers\FacebookFeedController();
    $xml = $controller->generateFeed()->getContent();

    // Guardar en public
    file_put_contents(public_path('feed-facebook.xml'), $xml);

    $this->info('Facebook feed cached successfully!');
}
```

3. Registrar en `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Actualizar feed cada 6 horas
    $schedule->command('cache:facebook-feed')->everySixHours();
}
```

### Opción 2: Cron Directo

```bash
# Actualizar cada 6 horas
0 */6 * * * cd /path/to/project && php artisan cache:facebook-feed
```

## Configuración en Facebook Business

1. Ir a **Commerce Manager** → **Catálogos**
2. Seleccionar tu catálogo o crear uno nuevo
3. Ir a **Fuentes de datos** → **Agregar fuente de datos**
4. Seleccionar **Feed de datos**
5. Ingresar la URL: `https://etiquecosas.ar/api/feed-facebook.xml`
6. Configurar frecuencia de actualización (recomendado: cada 6-24 horas)

## Validación del Feed

Para validar que el feed está funcionando correctamente:

1. **Acceder manualmente**: Abrir `https://etiquecosas.ar/api/feed-facebook.xml` en el navegador
2. **Verificar estructura XML**: Debe mostrar el XML correctamente formateado
3. **Facebook Debugger**: Usar [Facebook Business Debug Tool](https://business.facebook.com/Commerce/diagnostics)

## Troubleshooting

### Error: No se muestran productos

- Verificar que existen productos en la tabla `products` que no estén eliminados (soft delete)
- Revisar que los productos tienen imágenes asociadas
- Comprobar que las relaciones (images, status, stockStatus) están cargadas

### Error: Imágenes no se muestran

- Verificar que las URLs de imágenes son accesibles públicamente
- Comprobar que la ruta de almacenamiento es correcta
- Asegurar que el campo `img` en `product_images` tiene valores

### Error: Precios incorrectos

- Revisar la lógica de descuentos en [FacebookFeedController.php:150-165](app/Http/Controllers/FacebookFeedController.php#L150-L165)
- Verificar que los campos `price` y `discounted_price` tienen valores numéricos

## Mantenimiento

### Campos Adicionales Opcionales

Si Meta requiere campos adicionales en el futuro, pueden agregarse en el método `generateFeed()`:

```php
// Ejemplo: agregar categoría
if ($product->categories->count() > 0) {
    $category = $product->categories->first()->name;
    $item->addChild('g:product_type', htmlspecialchars($category, ENT_XML1, 'UTF-8'), 'http://base.google.com/ns/1.0');
}

// Ejemplo: agregar GTIN/EAN
if ($product->sku) {
    $item->addChild('g:gtin', $product->sku, 'http://base.google.com/ns/1.0');
}
```

## Recursos

- [Documentación de Meta - Especificaciones del Feed](https://www.facebook.com/business/help/120325381656392)
- [Google Merchant Center Feed Specification](https://support.google.com/merchants/answer/7052112)
- [Facebook Commerce Manager](https://business.facebook.com/commerce/)

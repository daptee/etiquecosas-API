# Carrito abandonado — endpoint para el front

## Contexto

El backend detecta carritos abandonados (ventas que quedan en estado "Pendiente de pago" sin actividad) y manda hasta 2 mails de recuperación. El botón principal de esos mails ("Terminar mi pedido") ya no apunta al home: apunta a una página que tiene que armar el front, usando un `uid` que identifica ese carrito puntual.

```
{FRONT_URL}/carrito-recuperado/{uid}
```

Ejemplo real:
```
https://etiquecosas.com.ar/carrito-recuperado/4574e9f0-b693-483a-9d13-311678be422d
```

El `uid` es un UUID v4 random (no es el ID de la venta ni de ningún otro registro — es opaco a propósito, para no exponer IDs secuenciales).

Con ese `uid`, el front pega al endpoint de abajo para traer el detalle del carrito y renderizar la página (mostrar los productos, permitir ir al checkout, etc).

## Endpoint

```
GET /api/v1/abandoned-cart/{uid}
```

- **Público**, no requiere autenticación ni headers especiales.
- `{uid}` es el mismo valor que viene en el link del mail.

### Respuesta OK — 200

```json
{
  "message": "Carrito obtenido correctamente",
  "data": {
    "sale_id": 94500,
    "subtotal": "59488.02",
    "shipping_cost": "10500.00",
    "shipping_method": { "id": 2, "name": "Correo" },
    "total": "69988.02",
    "products": [
      {
        "id": 1,
        "sale_id": 94500,
        "product_id": 59035,
        "variant_id": 1572,
        "customization_data": "{\"color\":null,\"icon\":null,\"form\":{\"name\":\"BERNABÉ\",\"lastName\":\"MOLLEA\",\"text\":\"\",\"list\":\"\"}}",
        "quantity": 1,
        "unit_price": "10890.00",
        "comment": null,
        "product": {
          "id": 59035,
          "sku": "ESM-59035",
          "name": "Etiquetas SUPER MAXI",
          "slug": "etiquetas-super-maxi-59035",
          "price": 12708.08,
          "discounted_price": null,
          "discount_percentage": null,
          "product_stock_status_id": 1,
          "stock_quantity": 0,
          "images": [
            { "id": 1435, "img": "images/products/super-maxi-1.jpg", "is_main": true, "position": 0 }
          ]
          /* ...resto de los campos del producto (mismo shape que /v1/products/{id}) */
        },
        "variant": {
          "id": 1572,
          "product_id": 59035,
          "img": "images/product_variants/img_690b85e23b799.jpg",
          "variant": {
            "name": null,
            "price": "0",
            "attributesvalues": [
              { "id": 143, "value": "Fútbol", "attribute": { "id": 10, "name": "Temática" } }
            ]
            /* ...resto de los campos de la variante */
          }
        }
      }
    ],
    "coupon": null
  },
  "metaData": null
}
```

**Notas sobre los campos:**

- `products` es un array con un item por cada producto agregado al carrito (mismo shape que devuelve el resto de la API para líneas de venta: `product` trae el producto completo, `variant` la variante elegida si corresponde).
- `customization_data` viene como **string JSON** (no como objeto) — hay que hacer `JSON.parse()`. Contiene la personalización cargada por el cliente (nombre, color, ícono, etc., según el producto).
- Las imágenes (`product.images[].img`, `variant.img`) son **rutas relativas**, no URLs completas. Hay que armarlas como `https://api.etiquecosas.com.ar/public/{img}` (mismo criterio que ya usan en el resto del sitio para imágenes de producto).
- `coupon`: si este carrito ya recibió el segundo mail (con el cupón de descuento `ETIQUECARRITO`), viene el objeto del cupón (`code`, `type`, `value`, etc.). Si todavía no le tocó ese mail, viene `null`. Si viene, el front puede mostrarlo prellenado o auto-aplicarlo en el checkout.

### Respuesta — Carrito no encontrado (404)

```json
{ "message": "Carrito no encontrado" }
```

El `uid` no existe. No debería pasar con un link real salido de un mail nuestro, pero puede pasar si alguien pega un link mal copiado o viejo/inválido.

### Respuesta — Carrito ya no disponible (410)

```json
{ "message": "Este carrito ya no está disponible" }
```

La venta detrás de este `uid` ya no está en estado "Pendiente de pago" — el cliente ya terminó la compra (por este medio o por otro), o la venta se canceló. El front debería mostrar un mensaje tipo "esta compra ya se completó / ya no está disponible" en vez de intentar reconstruir el carrito.

## Resumen para el front

1. Tomar el `{uid}` de la URL `/carrito-recuperado/{uid}`.
2. Pegarle a `GET /api/v1/abandoned-cart/{uid}`.
3. Si es 200 → mostrar los productos del carrito y permitir continuar la compra (y, si viene `coupon`, ofrecer aplicarlo).
4. Si es 404 o 410 → mostrar un mensaje de que el carrito no está disponible (con un link al sitio para empezar de cero).

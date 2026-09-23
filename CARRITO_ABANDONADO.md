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
    "coupons": [
      { "id": 4, "code": "ETIQUEVIP", "type": "Porcentaje", "value": "10.00", "pivot": { "discount_amount": "5948.80" } }
    ],
    "coupon": null,
    "client_mail": "lorena.terraneo@gmail.com",
    "client_name": "Lorena",
    "client_lastname": "Terraneo",
    "client_phone": "3584265710",
    "channel_id": 1,
    "shipping_address": "Rosario de Santa Fe 101. 2 B. Torre 1. Bosque chico",
    "shipping_locality_id": 790,
    "shipping_postal_code": "5800",
    "customer_notes": null
  },
  "metaData": null
}
```

**Notas sobre los campos:**

- `products` es un array con un item por cada producto agregado al carrito (mismo shape que devuelve el resto de la API para líneas de venta: `product` trae el producto completo, `variant` la variante elegida si corresponde).
- `customization_data` viene como **string JSON** (no como objeto) — hay que hacer `JSON.parse()`. Contiene la personalización cargada por el cliente (nombre, color, ícono, etc., según el producto).
- Las imágenes (`product.images[].img`, `variant.img`) son **rutas relativas**, no URLs completas. Hay que armarlas como `https://api.etiquecosas.com.ar/public/{img}` (mismo criterio que ya usan en el resto del sitio para imágenes de producto).
- `coupons` (array): los cupones que el cliente **ya tenía aplicados** en el carrito antes de abandonarlo (puede venir vacío `[]` si no había ninguno). Si viene alguno, el front lo tiene que reaplicar al recrear la venta.
- `coupon` (objeto, no array): el cupón **exclusivo de recuperación** (`ETIQUECARRITO`) — distinto de los anteriores. Viene solo si este carrito ya recibió el segundo mail (Impacto 2, con el 15% off). Si todavía no le tocó ese mail, viene `null`.
  - **Importante:** `ETIQUECARRITO` no es un cupón de uso general — al validarlo con `PATCH /v1/coupons/validate` hay que mandar también `sale_id` (el mismo de esta venta). El backend rechaza el cupón (400) si falta `sale_id`, o si esa venta nunca recibió el Impacto 2. Los demás cupones no necesitan `sale_id`.
- `client_mail`, `client_name`, `client_lastname`, `client_phone`, `channel_id`, `shipping_address`, `shipping_locality_id`, `shipping_postal_code`, `customer_notes`: datos para recrear la venta sin pedirle el formulario de nuevo al cliente — ver la sección de abajo.

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

## Completar la compra

Cuando el cliente confirma desde `/carrito-recuperado/{uid}`, el front llama a `POST /v1/sales` igual que en un checkout normal, usando los datos que le dio `GET /v1/abandoned-cart/{uid}` (`client_mail`, `client_name`, `client_lastname`, `client_phone`, `channel_id`, `shipping_address`, `shipping_locality_id`, `shipping_postal_code`, `customer_notes`, `products`, `coupons`, etc.), dejando que el cliente edite lo que quiera antes de confirmar (agregar/sacar productos, cambiar cantidad, cambiar el envío, aplicar otro cupón).

La única diferencia con un checkout normal: hay que mandar el campo `sale_id` del body de `POST /v1/sales` con el `sale_id` que devolvió el `GET /v1/abandoned-cart/{uid}` (el de la venta original) — **siempre**, haya cambiado algo o no.

```json
POST /api/v1/sales
{
  "client_mail": "...",
  "client_name": "...",
  ...
  "sale_id": 94500,   // 👈 el sale_id de la venta original (carrito abandonado), siempre
  "products": [ ... ]
}
```

**El backend decide qué hacer comparando el carrito que llega contra la venta original** (productos con sus cantidades, método/dirección de envío, y cupones aplicados — el cupón exclusivo `ETIQUECARRITO` no cuenta para esta comparación, aplicarlo solo no se considera "un cambio"):

- **Si no cambió nada:** no se crea ninguna venta nueva. Se reutiliza la venta **original**: la respuesta de `POST /v1/sales` es esa misma venta (mismo `id`), ahora marcada con `is_recovered_cart: true`. Con esa venta se sigue al flujo de pago de siempre.
- **Si cambió algo** (otro producto, otra cantidad, otro envío, otro cupón que no sea `ETIQUECARRITO`): se crea una venta **nueva e independiente**, asociada a la original vía `sale_id`. Es esa venta nueva la que sigue el flujo de pago (puede terminar aprobada, rechazada, etc.).
  - En este caso es la venta **original** (`sale_id: 94500` en el ejemplo) la que queda marcada con `is_recovered_cart: true` — no la nueva. Así siempre se puede identificar, desde la venta original, cuál fue la que disparó una recuperación de carrito.
  - La venta original **no cambia de estado** en ningún caso: sigue "Pendiente de pago" salvo que avance por otro motivo.

En resumen, **`is_recovered_cart` siempre queda en la venta original** que recibió el mail — nunca en la nueva. Si querés saber qué pasó con la compra después de una recuperación con cambios, hay que mirar la venta asociada (`childSales` de la original).

Si esa venta original tenía un `abandoned_cart_log` (vino de un mail de carrito abandonado), cuando la venta que efectivamente sigue el flujo de pago (la original reusada, o la nueva) se apruebe, ese log se marca como convertido — el reporte de carritos abandonados no pierde el dato aunque técnicamente se haya usado o creado otra venta.

Si el `sale_id` que mandás **no** está en estado "Pendiente de pago" (por ejemplo, esa venta ya se había cancelado o aprobado), se crea una venta nueva normal, sin marcar nada como recuperado. Por eso conviene chequear el 410 del `GET` antes de dejar avanzar al cliente (ver más abajo).

## Resumen para el front

1. Tomar el `{uid}` de la URL `/carrito-recuperado/{uid}`.
2. Pegarle a `GET /api/v1/abandoned-cart/{uid}`.
3. Si es 200 → mostrar los productos del carrito y permitir continuar la compra (y, si viene `coupon`, ofrecer aplicarlo).
4. Si es 404 o 410 → mostrar un mensaje de que el carrito no está disponible (con un link al sitio para empezar de cero).
5. Al confirmar, llamar a `POST /v1/sales` como un checkout normal, pero **mandando siempre `sale_id` = el `sale_id` que devolvió el GET**. El backend decide solo si reutiliza esa misma venta (no cambió nada) o crea una nueva asociada (cambió algo) — la respuesta trae la venta con la que hay que seguir al pago en cualquiera de los dos casos.

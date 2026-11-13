# Front: el texto que no entra en "Máximo de líneas" hay que ocultarlo, no amontonarlo

## El bug

En la config de un elemento de texto, `max_lines` (cantidad máxima de renglones) tiene que hacer esto: si el texto no entra en esa cantidad de líneas, lo que sobra (la "4ta línea" en adelante, si el máximo es 3) **no se tiene que ver**.

Hoy `PdfDesignHelper.wrapByCharsPerLine()` (`utils/PdfDesignHelper.ts`, líneas ~592-605) hace lo contrario a propósito — está comentado así en el propio código:

```ts
for (let i = 0; i < chunks.length; i++) {
    if (lines.length === maxLines - 1) {
        // This is the last allowed line — it absorbs every remaining chunk, uncapped.
        cur = cur ? `${cur} ${chunks.slice(i).join(' ')}` : chunks.slice(i).join(' ');
        break;
    }
    ...
}
```

Al llegar al último renglón permitido, en vez de cortar cuando ya no entra más texto, pega TODO lo que queda (sin límite de ancho) en ese renglón — no se pierde nada, pero se ve un renglón gigante que se sale de la etiqueta. Esto es lo que se estaba viendo en el editor.

## El fix (ya aplicado igual en el backend)

Mismo archivo que genera el PDF real (`EtiquetaService::dividirEnRenglones()` en `app/Services/EtiquetaService.php`) tenía exactamente este mismo bug — ya se corrigió ahí. El criterio, para que el editor y el PDF final coincidan:

En el último renglón permitido, se sigue agregando una palabra a la vez mientras entre en `maxCharsPerLine`; apenas una palabra no entra, se corta ahí **y se descarta esa palabra y todas las que siguen** (no se agregan a ningún renglón).

Traducido al mismo bucle de `wrapByCharsPerLine()`:

```ts
for (let i = 0; i < chunks.length; i++) {
    if (lines.length === maxLines - 1) {
        // Último renglón permitido: se arma igual que cualquier otro (respeta
        // maxCharsPerLine) pero ya no se corta a uno nuevo — lo que no entra
        // se descarta, no se ve.
        for (; i < chunks.length; i++) {
            const candidate = cur ? `${cur} ${chunks[i]}` : chunks[i];
            if (cur !== '' && candidate.length > maxCharsPerLine) break;
            cur = candidate;
        }
        break;
    }
    const candidate = cur ? `${cur} ${chunks[i]}` : chunks[i];
    if (cur === '' || candidate.length <= maxCharsPerLine) {
        cur = candidate;
    } else {
        lines.push(cur);
        cur = chunks[i];
    }
}
if (cur) lines.push(cur);
```

(Mismo criterio que ya tiene el resto de la función: una palabra sola más larga que `maxCharsPerLine` igual se deja entera si el renglón estaba vacío — no se corta a la mitad de una palabra.)

## Dónde probarlo

`wrapByCharsPerLine()` se usa en varios lugares de `PdfDesignHelper.ts`/`PdfLayoutHelper.ts` (preview del editor, cálculo de layout_groups) — con este cambio alcanza, no hace falta tocar nada en esos llamadores. Para probar: un texto con `max_lines: 3` y un nombre/contenido largo tiene que mostrar solo esos 3 renglones (cortados en una palabra completa, sin pasarse de `max_chars_per_line`) y perder el resto, en vez de un 3er renglón gigante.

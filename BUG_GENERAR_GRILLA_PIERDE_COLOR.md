# Bug: "Generar grilla" le borra el color propio a las celdas al reescalar

**Para**: quien mantiene el editor (`etiquecosas-admin`, carpeta `composables/productPdfDesigns/useDesignEditor.ts`).

---

## Resumen

Un diseño con una grilla de celdas que tienen **cada una un color de fondo distinto a propósito** (ej. una fila de etiquetas con 3 colores ciclando: violeta, fucsia, rosa, violeta, fucsia, rosa...) pierde esos colores —y los iguala todos al de la celda molde (`template`)— apenas el admin cambia **filas** o **columnas** de esa grilla desde el panel "Generar grilla". El texto da la impresión de "no cambiar" (en realidad es un campo dinámico "De la compra", siempre muestra el mismo placeholder, cambie o no), pero el color sí cambia a ojos vista.

---

## Cómo reproducirlo

1. Abrir un diseño con una grilla cuyas celdas tengan colores de fondo distintos entre sí (cualquier diseño importado desde una temática "COLOR RANGE" del sistema legacy, o uno armado a mano con colores por celda).
2. Seleccionar una celda de la grilla → panel "Generar grilla".
3. Cambiar el valor de "Filas" (sumar o restar alguno).
4. Las celdas que ya existían, que no deberían haberse tocado más que en su cantidad, aparecen todas con el color de la celda molde — se perdió el color propio de cada una.

---

## Causa — `applyGridParams`, `composables/productPdfDesigns/useDesignEditor.ts`

Al reescalar, para cada celda que **ya existía y sigue intacta** (su firma/`sig` coincide con la guardada — es decir, el admin no la tocó a mano), el código la **borra y la vuelve a crear clonando TODAS las propiedades de la celda molde** (`template`), color incluido:

```ts
// useDesignEditor.ts (aprox. líneas 1054-1070)
for (const { c, r } of PdfDesignHelper.gridSlots(cols, rows)) {
    const key = `${c},${r}`;
    const was = previous[key];
    ...
    members[key] = template.map((el, i) => {
        if (was && !was[i]) return null; // la celda "ya modificada a mano" no se toca
        const copy: PdfDesignElement = {
            ...PdfDesignHelper.deepClone(el),
            id: uid('el'),
            x_cm: ...,
            y_cm: ...,
            group_id: groupId
        };
        cell.push(copy);
        return { id: copy.id, sig: elementSignature(copy) };
    });
    ...
}
```

El problema: no distingue **"esta celda ya existía en esta posición, solo hay que recalcular su x/y"** de **"esta celda es nueva (recién aparece por sumar filas/columnas), no hay nada previo que conservar"**. Siempre clona el elemento ENTERO de `template` — color, contenido, todo — en ambos casos.

---

## Comportamiento esperado

- **Celda que ya existía** en esa posición antes del resize (había algo ahí, coincide en `was[i]`): debería conservar su **color propio** (y cualquier otra propiedad que no sea puramente posicional) — solo actualizarle `x_cm`/`y_cm`/`width_cm`/`height_cm` al nuevo lugar que le toca en la grilla reacomodada.
- **Celda nueva** (no había nada en esa posición antes — apareció porque se sumó una fila/columna): ahí sí tiene sentido clonar el color (y todo lo demás) de la celda molde, porque no hay ningún dato previo que conservar.

---

## Por qué no se puede arreglar desde el backend

El importador (`etiquecosas-API`, `PdfDesignImportService`) ya arma la grilla (`data.pages[].grids[]`) con el formato correcto y cada celda con su color real — se probó y confirmó que los datos que llegan al editor son correctos. El problema aparece recién en el momento de reescalar, adentro del editor — no hay ninguna combinación de datos que el backend pueda mandar para evitar que `applyGridParams` clone el color del template sobre una celda que ya tenía el suyo propio. El fix tiene que ser en esa función.

/*
 * Etiquecosas: separa las líneas de corte en su propia capa.
 *
 * Illustrator no respeta las capas de un PDF que no generó él y abre todo
 * en una sola capa. Este script rearma las 2 capas del PDF que devuelve
 * GET /product-pdf-designs/{id}/preview?cut_lines=1:
 *   - "Líneas de corte" (arriba): todos los trazos con el color directo
 *     CutContour.
 *   - "Arte" (abajo): todo lo demás.
 *
 * Uso: abrir el PDF en Illustrator y ejecutar Archivo > Secuencias de
 * comandos > Otra secuencia de comandos… y elegir este archivo. Se aplica a
 * todos los documentos abiertos (Illustrator abre cada página del PDF como
 * un documento aparte).
 */
#target illustrator

(function () {
    var SPOT_NAME = 'CutContour';
    var LAYER_ART = 'Arte';
    var LAYER_CUT = 'Líneas de corte';

    if (app.documents.length === 0) {
        alert('Abrí primero el PDF en Illustrator.');
        return;
    }

    function esLineaDeCorte(path) {
        if (!path.stroked) return false;
        var c = path.strokeColor;
        return c && c.typename === 'SpotColor' && c.spot && c.spot.name === SPOT_NAME;
    }

    function buscarCapa(doc, nombre) {
        for (var i = 0; i < doc.layers.length; i++) {
            if (doc.layers[i].name === nombre) return doc.layers[i];
        }
        return null;
    }

    function procesar(doc) {
        // Desbloquear y mostrar todo para poder mover los elementos.
        for (var l = 0; l < doc.layers.length; l++) {
            doc.layers[l].locked = false;
            doc.layers[l].visible = true;
        }

        var corte = buscarCapa(doc, LAYER_CUT) || doc.layers.add();
        corte.name = LAYER_CUT;

        // Juntar primero: doc.pathItems es una colección viva y cambia al mover.
        var aMover = [];
        for (var i = 0; i < doc.pathItems.length; i++) {
            var p = doc.pathItems[i];
            if (!esLineaDeCorte(p)) continue;
            p.locked = false;
            p.hidden = false;
            // Un contorno con varios subtrazados llega como trazado compuesto.
            var item = p.parent.typename === 'CompoundPathItem' ? p.parent : p;
            var repetido = false;
            for (var k = 0; k < aMover.length; k++) {
                if (aMover[k] === item) { repetido = true; break; }
            }
            if (!repetido) aMover.push(item);
        }

        for (var j = 0; j < aMover.length; j++) {
            aMover[j].move(corte, ElementPlacement.PLACEATEND);
        }

        // Todo lo que no es corte va a una sola capa "Arte".
        var arte = buscarCapa(doc, LAYER_ART);
        for (var m = doc.layers.length - 1; m >= 0; m--) {
            var capa = doc.layers[m];
            if (capa === corte || capa === arte) continue;
            if (!arte) {
                arte = capa;
                arte.name = LAYER_ART;
                continue;
            }
            while (capa.pageItems.length > 0) {
                capa.pageItems[0].move(arte, ElementPlacement.PLACEATEND);
            }
            capa.remove();
        }
        if (!arte) {
            arte = doc.layers.add();
            arte.name = LAYER_ART;
        }

        // Orden: corte arriba, arte abajo.
        corte.zOrder(ZOrderMethod.BRINGTOFRONT);
        arte.zOrder(ZOrderMethod.SENDTOBACK);

        return aMover.length;
    }

    var resumen = [];
    for (var d = 0; d < app.documents.length; d++) {
        var doc = app.documents[d];
        var n = procesar(doc);
        resumen.push(doc.name + ': ' + n + (n === 1 ? ' línea' : ' líneas'));
    }

    alert('Capas listas.\n\n' + resumen.join('\n')
        + '\n\nSi algún documento tiene 0 líneas, esa página no tiene líneas de corte '
        + 'o el PDF no se descargó con cut_lines=1.');
})();

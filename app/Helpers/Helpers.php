<?php use Illuminate\Database\Eloquent\ModelNotFoundException;
if (!function_exists('findModelOrFail')) {
    function findObject(string $modelClass, $id)
    {
        $model = app($modelClass)->find($id);
        if (!$model) {
            abort(404, 'El objeto no existe');
        }
        return $model;
    }
}

if (!function_exists('groupCompoundSurnameParts')) {
    /**
     * Agrupa partículas de apellidos compuestos con la palabra siguiente
     * para que no se separen al dividir en líneas.
     * Ej: ["DE", "LA", "CRUZ"] -> ["DE LA CRUZ"]
     *     ["MARIA", "DE", "LOS", "SANTOS"] -> ["MARIA", "DE LOS SANTOS"]
     */
    function groupCompoundSurnameParts(array $words): array
    {
        $particles = ['DE', 'DEL', 'DE LA', 'DE LOS', 'DE LAS', 'DI', 'LA', 'LAS', 'LOS', 'EL', 'Y', 'VAN', 'VON', 'BIN', 'BTE'];
        $grouped = [];
        $i = 0;
        $total = count($words);

        while ($i < $total) {
            // Intentar casar partículas compuestas de dos tokens primero (ej: "DE LA", "DE LOS")
            $matched = false;
            if ($i + 1 < $total) {
                $twoToken = $words[$i] . ' ' . $words[$i + 1];
                if (in_array($twoToken, $particles, true) && $i + 2 < $total) {
                    // Unir la partícula de dos tokens con la siguiente palabra
                    $grouped[] = $twoToken . ' ' . $words[$i + 2];
                    $i += 3;
                    $matched = true;
                }
            }

            if (!$matched) {
                if (in_array($words[$i], $particles, true) && $i + 1 < $total) {
                    // Unir la partícula de un token con la siguiente palabra
                    $grouped[] = $words[$i] . ' ' . $words[$i + 1];
                    $i += 2;
                } else {
                    $grouped[] = $words[$i];
                    $i++;
                }
            }
        }

        return $grouped;
    }
}

if (!function_exists('formatName')) {
    /**
     * Divide un nombre en varias líneas respetando un límite de caracteres.
     * Los apellidos compuestos (ej: "de la Cruz") no se dividen entre líneas.
     */
    function formatName($name, $maxLines = 3, $maxCharsPerLine = 10)
    {
        $words = explode(' ', mb_strtoupper($name));
        $tokens = groupCompoundSurnameParts($words);

        $lines = [];
        $currentLine = '';

        foreach ($tokens as $token) {
            // Si agrego el token supera el límite de caracteres y aún no llegué a la penúltima línea
            if (strlen($currentLine . ' ' . $token) > $maxCharsPerLine && count($lines) < $maxLines - 1) {
                $lines[] = trim($currentLine);
                $currentLine = $token;
            } else {
                $currentLine .= ($currentLine ? ' ' : '') . $token;
            }
        }

        // Agrego la última línea
        $lines[] = trim($currentLine);

        // Si excede el número máximo de líneas, recorto a maxLines
        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] .= '…'; // opcional: indica que se cortó
        }

        return implode('<br>', $lines);
    }
}

if (!function_exists('formatNameExactLines')) {
    /**
     * Divide un nombre en exactamente N líneas, forzando el texto a distribuirse.
     * Si el nombre es muy corto, agrega líneas vacías.
     * Si es muy largo, lo distribuye en las líneas disponibles.
     * Los apellidos compuestos (ej: "de la Cruz") no se dividen entre líneas.
     */
    function formatNameExactLines($name, $exactLines = 2)
    {
        $name = mb_strtoupper(trim($name));
        $words = explode(' ', $name);
        $tokens = groupCompoundSurnameParts($words);
        $totalTokens = count($tokens);

        // Si solo hay un token, lo ponemos en la primera línea
        if ($totalTokens === 1) {
            $lines = [$name];
            // Rellenar con líneas vacías hasta completar exactLines
            while (count($lines) < $exactLines) {
                $lines[] = '&nbsp;';
            }
            return implode('<br>', $lines);
        }

        // Distribuir tokens en exactamente N líneas
        $lines = [];
        $tokensPerLine = ceil($totalTokens / $exactLines);

        for ($i = 0; $i < $exactLines; $i++) {
            $start = $i * $tokensPerLine;
            $lineTokens = array_slice($tokens, $start, $tokensPerLine);

            if (!empty($lineTokens)) {
                $lines[] = implode(' ', $lineTokens);
            } else {
                $lines[] = '&nbsp;'; // Línea vacía
            }
        }

        return implode('<br>', $lines);
    }
}

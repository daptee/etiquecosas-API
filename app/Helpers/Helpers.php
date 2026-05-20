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
     *
     * Si se pasa $firstName, el corte entre renglones respeta la separación
     * nombre/apellido: nunca se mezcla parte del nombre con parte del apellido
     * en el mismo renglón. Si nombre+apellido entra en una sola línea, se deja así.
     * Si hay que cortar, el nombre va en el primer renglón y el apellido en el segundo
     * (o se distribuye respetando esa separación).
     */
    function formatName($name, $maxLines = 3, $maxCharsPerLine = 10, $firstName = null)
    {
        $nameUpper = mb_strtoupper(trim($name), 'UTF-8');

        // Si se conoce el firstName, usamos lógica de corte nombre/apellido
        if ($firstName !== null && $firstName !== '') {
            $firstNameUpper = mb_strtoupper(trim($firstName), 'UTF-8');
            $lastNameUpper  = mb_strtoupper(trim(mb_substr($nameUpper, mb_strlen($firstNameUpper, 'UTF-8'), null, 'UTF-8'), ' '), 'UTF-8');

            // Si el nombre completo entra en una línea, devolver sin corte
            if (mb_strlen($nameUpper, 'UTF-8') <= $maxCharsPerLine) {
                return $nameUpper;
            }

            // Si hay apellido, cortar entre nombre y apellido
            if ($lastNameUpper !== '') {
                $lines = [$firstNameUpper, $lastNameUpper];
                if (count($lines) > $maxLines) {
                    $lines = array_slice($lines, 0, $maxLines);
                    $lines[$maxLines - 1] .= '…';
                }
                return implode('<br>', $lines);
            }
        }

        // Lógica original: corte por tokens respetando límite de caracteres
        $words  = explode(' ', $nameUpper);
        $tokens = groupCompoundSurnameParts($words);

        $lines       = [];
        $currentLine = '';

        foreach ($tokens as $token) {
            // Si agrego el token supera el límite de caracteres y aún no llegué a la penúltima línea
            if (mb_strlen($currentLine . ' ' . $token, 'UTF-8') > $maxCharsPerLine && count($lines) < $maxLines - 1) {
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
            $lines[$maxLines - 1] .= '…';
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
     *
     * Si se pasa $firstName, el corte respeta la separación nombre/apellido:
     * el nombre va en el primer renglón y el apellido en el segundo.
     */
    function formatNameExactLines($name, $exactLines = 2, $firstName = null)
    {
        $nameUpper = mb_strtoupper(trim($name), 'UTF-8');

        // Si se conoce el firstName, usamos lógica de corte nombre/apellido
        if ($firstName !== null && $firstName !== '') {
            $firstNameUpper = mb_strtoupper(trim($firstName), 'UTF-8');
            $lastNameUpper  = mb_strtoupper(trim(mb_substr($nameUpper, mb_strlen($firstNameUpper, 'UTF-8'), null, 'UTF-8'), ' '), 'UTF-8');

            if ($lastNameUpper !== '') {
                $lines = [$firstNameUpper, $lastNameUpper];
                while (count($lines) < $exactLines) {
                    $lines[] = '&nbsp;';
                }
                return implode('<br>', array_slice($lines, 0, $exactLines));
            }
        }

        // Lógica original
        $words       = explode(' ', $nameUpper);
        $tokens      = groupCompoundSurnameParts($words);
        $totalTokens = count($tokens);

        // Si solo hay un token, lo ponemos en la primera línea
        if ($totalTokens === 1) {
            $lines = [$nameUpper];
            while (count($lines) < $exactLines) {
                $lines[] = '&nbsp;';
            }
            return implode('<br>', $lines);
        }

        // Distribuir tokens en exactamente N líneas
        $lines         = [];
        $tokensPerLine = ceil($totalTokens / $exactLines);

        for ($i = 0; $i < $exactLines; $i++) {
            $start      = $i * $tokensPerLine;
            $lineTokens = array_slice($tokens, $start, $tokensPerLine);

            if (!empty($lineTokens)) {
                $lines[] = implode(' ', $lineTokens);
            } else {
                $lines[] = '&nbsp;';
            }
        }

        return implode('<br>', $lines);
    }
}

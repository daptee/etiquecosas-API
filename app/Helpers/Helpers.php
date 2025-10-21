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

if (!function_exists('formatName')) {
    /**
     * Divide un nombre en varias líneas respetando un límite de caracteres.
     */
    function formatName($name, $maxLines = 3, $maxCharsPerLine = 10)
    {
        $words = explode(' ', mb_strtoupper($name));
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            // Si agrego la palabra supera el límite de caracteres y aún no llegué a la penúltima línea
            if (strlen($currentLine . ' ' . $word) > $maxCharsPerLine && count($lines) < $maxLines - 1) {
                $lines[] = trim($currentLine);
                $currentLine = $word;
            } else {
                $currentLine .= ($currentLine ? ' ' : '') . $word;
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

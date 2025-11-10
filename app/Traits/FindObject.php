<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\ModelNotFoundException;

trait FindObject
{
    public function findObject(string $modelClass, $id)
    {
        $object = app($modelClass)->find($id);

        if (!$object) {
            abort(response()->json([
                'message' => 'El objeto no existe'
            ], 404));
        }

        return $object;
    }

}

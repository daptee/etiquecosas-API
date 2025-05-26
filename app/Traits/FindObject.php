<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\ModelNotFoundException;

trait FindObject
{
    public function findObject(string $modelClass, $id)
    {
        $object = app($modelClass)->find($id);
        if (!$object) {
            abort(404, 'El objeto no existe');
        }

        return $object;
    }
}

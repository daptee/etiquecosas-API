<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\ModelNotFoundException;

trait FindObject
{
    public function findObject(string $modelClass, $id)
    {
        $model = app($modelClass)->find($id);

        if (!$model) {
            abort(404, 'El objeto no existe');
        }

        return $model;
    }
}

<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;

if(!function_exists('findModelOrFail'))
{    
    function findObject(string $modelClass, $id)
    {
        $model = app($modelClass)->find($id);
        if(!$model)
        {
            abort(404, 'El objeto no existe');
        }
        return $model;
    }
}
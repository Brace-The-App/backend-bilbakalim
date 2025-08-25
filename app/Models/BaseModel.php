<?php

namespace App\Models;

use App\Http\Custom\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

abstract class BaseModel extends Model
{
    

    protected $columns = [];
    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();
        foreach ($this->columns as $convention => $actual) {
            if (array_key_exists($actual, $attributes)) {
                $attributes[$convention] = $attributes[$actual];
                unset($attributes[$actual]);
            }
        }
        return $attributes;
    }

    // public function getAttribute($key)
    // {
    //     if (array_key_exists($key, $this->columns)) {
    //         $key = $this->columns[$key];
    //     }
    //     return parent::getAttributeValue($key);
    // }

    // public function setAttribute($key, $value)
    // {
    //     if (array_key_exists($key, $this->columns)) {
    //         $key = $this->columns[$key];
    //     }
    //     return parent::setAttribute($key, $value);
    // }
}

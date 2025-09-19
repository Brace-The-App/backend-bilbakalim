<?php

namespace App\Models;

use App\Http\Custom\Response;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

trait ModelTrait
{
    public function find($id, $throwIfNotFound = false)
    {
        $query = $this->query();
        // Account ID kontrolü kaldırıldı - User modeli ile çalışıyor

        $item = $query->find($id);

        #throw
        if ($throwIfNotFound) {
            if (!$item) {
                throw new HttpResponseException(
                    Response::error(['id' => ["This " . $id . " id is not found." . $this::class]], null, JsonResponse::HTTP_NOT_FOUND)
                );
            }
        }

        return $item;
    }

    public function create($data)
    {
        $query = $this->query();
        // Account ID kontrolü kaldırıldı - User modeli ile çalışıyor

        return $query->create($data);
    }

    public function getFields()
    {
        if (isset($this->layoutFields)) {
            $layoutFields = Arr::mapWithKeys($this->layoutFields, function (string $value, string $key) {
                if (is_numeric($key)) return [$value => ""];
                else if (is_string($key)) return [$key => ""];
            });

            if (!empty($layoutFields))
                $layoutFields = array_keys($layoutFields);

            return $layoutFields;
        }

        return [];
    }
}

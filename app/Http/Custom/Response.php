<?php

namespace App\Http\Custom;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class Response
{
    private $status, $message, $data;

    public static function withData($status, $message, $resourceData = null, $httpCode = \Symfony\Component\HttpFoundation\Response::HTTP_OK, $additionalMeta = [])
    {
        $data = [];
        $meta = [
            'status' => $status,
            'message' => $message
        ];

        if (!empty($additionalMeta)) {
            $meta += $additionalMeta;
        }

        if ($resourceData instanceof AnonymousResourceCollection) {
            $resourceCollection = $resourceData->response()->getData(true);
            if (isset($resourceCollection['data'])) $data = $resourceCollection['data'];
            if (isset($resourceCollection['meta']['current_page'])) {
                $meta['pagination'] = [
                    "total" => $resourceCollection['meta']['total'],
                    "current_page" => $resourceCollection['meta']['current_page'],
                    "total_page" => $resourceCollection['meta']['last_page'],
                    "per_page" => $resourceCollection['meta']['per_page'],
                ];
            }
        } else {
            $data = $resourceData;
        }

        if (is_array($data) && !empty($data)) $meta['data_type'] = "list";
        elseif (is_object($data) && !empty($data)) $meta['data_type'] = "item";
        elseif (empty($data)) $meta['data_type'] = "empty";

        return response()->json([
            'meta' => $meta,
            'data' => $data
        ], $httpCode);
    }

    public static function withoutData($status, $message)
    {
        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => new \stdClass()
        ], \Symfony\Component\HttpFoundation\Response::HTTP_UNAUTHORIZED);
    }

    public static function forbidden($message = null)
    {
        $data = [];
        $meta = [
            'status' => false,
            'message' => $message ?? "Forbidden"
        ];

        return response()->json([
            'meta' => $meta,
            'data' => $data
        ], \Symfony\Component\HttpFoundation\Response::HTTP_FORBIDDEN);
    }

    public static function deleted($message = null)
    {
        $data = [];
        $meta = [
            'status' => true,
            'message' => $message ?? "Deleted"
        ];

        return response()->json([
            'meta' => $meta,
            'data' => $data
        ], \Symfony\Component\HttpFoundation\Response::HTTP_ACCEPTED);
    }

    public static function unauthenticated($message = null)
    {
        $data = [];
        $meta = [
            'status' => false,
            'message' => $message ?? "Unauthenticated"
        ];

        return response()->json([
            'meta' => $meta,
            'data' => $data
        ], \Symfony\Component\HttpFoundation\Response::HTTP_UNAUTHORIZED);
    }

    public static function error($errors, $message = null, $httpCode = JsonResponse::HTTP_BAD_REQUEST)
    {
        $meta = [
            'status' => false,
            'message' => $message ?? "error"
        ];

        return response()->json([
            'meta' => $meta,
            'errors' => $errors
        ], $httpCode);
    }

    public static function notFound($message = null, $column = 'id', $throw = false)
    {
        $meta = [
            'status' => false,
            'message' => $message ?? "error"
        ];

        if ($throw) {
            throw new HttpResponseException(
                response()->json([
                    'meta' => $meta,
                    'errors' => [$column  => ["not found."]]
                ], JsonResponse::HTTP_NOT_FOUND)
            );
        }
        return response()->json([
            'meta' => $meta,
            'errors' => [$column  => ["not found."]]
        ], JsonResponse::HTTP_NOT_FOUND);
    }
}

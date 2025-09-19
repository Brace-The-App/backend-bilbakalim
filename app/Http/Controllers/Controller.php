<?php

namespace App\Http\Controllers;

use App\Http\Custom\Response;
use App\Http\Repositories\BaseRepository;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Str;

/**
 * @OA\Info(
 *     title="BilBakalim API",
 *     version="1.0.0",
 *     description="BilBakalim Quiz Application API Documentation",
 *     @OA\Contact(
 *         email="info@bilbakalim.com",
 *         name="BilBakalim Support"
 *     ),
 *     @OA\License(
 *         name="MIT",
 *         url="https://opensource.org/licenses/MIT"
 *     )
 * )
 * 
 * @OA\Server(
 *     url="http://localhost",
 *     description="Local Development Server"
 * )
 * 
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Enter token in format: Bearer {token}"
 * )
 * 
 * @OA\Tag(
 *     name="Auth",
 *     description="Authentication endpoints"
 * )
 * 
 * @OA\Tag(
 *     name="Categories",
 *     description="Category management endpoints"
 * )
 * 
 * @OA\Tag(
 *     name="Questions",
 *     description="Question management endpoints"
 * )
 * 
 */

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    private $repo = null;
    private $response = null;

    public function __construct(BaseRepository $repo = null)
    {
        $this->repo = $repo;
        $this->response = new Response();
    }

    public function generateCode()
    {
        $nextId = $this->repo->nextId();
        $prefix = isset($this->repo->codePrefix) ? $this->repo->codePrefix : null;
        $code = $prefix . Str::padLeft($nextId, 5, '0');
        return $this->response->withData(true, "Code is generated.", ['code' => $code]);
    }
}

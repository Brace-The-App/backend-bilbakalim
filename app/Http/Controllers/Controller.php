<?php

namespace App\Http\Controllers;

use App\Http\Custom\Response;
use App\Http\Repositories\BaseRepository;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Str;

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

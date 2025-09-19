<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Custom\Response;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Categories",
 *     description="Category management endpoints"
 * )
 */
class CategoryController extends Controller
{
    private $response = null;

    public function __construct()
    {
        $this->response = new Response();
    }

    /**
     * @OA\Get(
     *     path="/api/categories",
     *     summary="Get Categories",
     *     description="Get all categories with pagination and filters",
     *     operationId="getCategories",
     *     tags={"Categories"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search in category names",
     *         required=false,
     *         @OA\Schema(type="string", example="Sports")
     *     ),
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Categories retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Kategoriler başarılı bir şekilde listelendi."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Category::withCount('questions');

        // Search filter
        if ($request->has('search') && $request->search) {
            $query->where(function($q) use ($request) {
                $q->where('name->tr', 'like', '%' . $request->search . '%')
                  ->orWhere('name->en', 'like', '%' . $request->search . '%');
            });
        }

        // Active filter
        if ($request->has('is_active') && $request->is_active !== null) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Order by sort_order and name
        $query->orderBy('sort_order')->orderBy('name');

        // Pagination
        $perPage = $request->get('per_page', 15);
        $categories = $query->paginate($perPage);

        return $this->response->withData(
            true,
            "Kategoriler başarılı bir şekilde listelendi.",
            $categories
        );
    }

    /**
     * Get Category Detail
     *
     * Get single category with questions count
     *
     * @urlParam id integer required Category ID. Example: 1
     *
     * @return JsonResponse
     */
    public function show($id)
    {
        $category = Category::withCount('questions')->find($id);

        if (!$category) {
            return $this->response->withData(
                false,
                "Kategori bulunamadı.",
                []
            );
        }

        return $this->response->withData(
            true,
            "Kategori detayı başarılı bir şekilde getirildi.",
            $category
        );
    }

    /**
     * Create Category
     *
     * Create a new category
     *
     * @bodyParam name object required Category names in different languages. Example: {"tr": "Spor", "en": "Sports"}
     * @bodyParam description object required Category descriptions. Example: {"tr": "Spor soruları", "en": "Sports questions"}
     * @bodyParam icon string required Category icon. Example: sports
     * @bodyParam color_code string required Category color code. Example: #FF5722
     * @bodyParam sort_order integer required Sort order. Example: 1
     * @bodyParam is_active boolean required Active status. Example: true
     *
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|array',
            'name.tr' => 'required|string|max:255',
            'name.en' => 'nullable|string|max:255',
            'description' => 'required|array',
            'description.tr' => 'required|string',
            'description.en' => 'nullable|string',
            'icon' => 'required|string|max:255',
            'color_code' => 'required|string|max:7',
            'sort_order' => 'required|integer|min:0',
            'is_active' => 'required|boolean'
        ]);

        $category = Category::create($request->all());

        return $this->response->withData(
            true,
            "Kategori başarılı bir şekilde oluşturuldu.",
            $category
        );
    }

    /**
     * Update Category
     *
     * Update an existing category
     *
     * @urlParam id integer required Category ID. Example: 1
     * @bodyParam name object Category names in different languages. Example: {"tr": "Spor", "en": "Sports"}
     * @bodyParam description object Category descriptions. Example: {"tr": "Spor soruları", "en": "Sports questions"}
     * @bodyParam icon string Category icon. Example: sports
     * @body_code string Category color code. Example: #FF5722
     * @bodyParam sort_order integer Sort order. Example: 1
     * @bodyParam is_active boolean Active status. Example: true
     *
     * @return JsonResponse
     */
    public function update(Request $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return $this->response->withData(
                false,
                "Kategori bulunamadı.",
                []
            );
        }

        $request->validate([
            'name' => 'sometimes|array',
            'name.tr' => 'required_with:name|string|max:255',
            'name.en' => 'nullable|string|max:255',
            'description' => 'sometimes|array',
            'description.tr' => 'required_with:description|string',
            'description.en' => 'nullable|string',
            'icon' => 'sometimes|string|max:255',
            'color_code' => 'sometimes|string|max:7',
            'sort_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean'
        ]);

        $category->update($request->all());

        return $this->response->withData(
            true,
            "Kategori başarılı bir şekilde güncellendi.",
            $category
        );
    }

    /**
     * Delete Category
     *
     * Delete a category
     *
     * @urlParam id integer required Category ID. Example: 1
     *
     * @return JsonResponse
     */
    public function destroy($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return $this->response->withData(
                false,
                "Kategori bulunamadı.",
                []
            );
        }

        // Check if category has questions
        if ($category->questions()->count() > 0) {
            return $this->response->withData(
                false,
                "Bu kategoriye ait sorular bulunduğu için silinemez.",
                []
            );
        }

        $category->delete();

        return $this->response->withData(
            true,
            "Kategori başarılı bir şekilde silindi.",
            []
        );
    }
}

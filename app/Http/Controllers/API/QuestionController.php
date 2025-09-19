<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Custom\Response;
use App\Models\Question;
use App\Models\Category;
use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Questions",
 *     description="Question management endpoints"
 * )
 */
class QuestionController extends Controller
{
    private $response = null;

    public function __construct()
    {
        $this->response = new Response();
    }

    /**
     * @OA\Get(
     *     path="/api/questions",
     *     summary="Get Questions",
     *     description="Get all questions with pagination and filters",
     *     operationId="getQuestions",
     *     tags={"Questions"},
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
     *         description="Search in question text",
     *         required=false,
     *         @OA\Schema(type="string", example="What is")
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter by category",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="question_level",
     *         in="query",
     *         description="Filter by difficulty level",
     *         required=false,
     *         @OA\Schema(type="string", enum={"easy","medium","hard"}, example="easy")
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
     *         description="Questions retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sorular başarılı bir şekilde listelendi."),
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
        $query = Question::with('category');

        // Search filter
        if ($request->has('search') && $request->search) {
            $query->where(function($q) use ($request) {
                $q->where('question->tr', 'like', '%' . $request->search . '%')
                  ->orWhere('question->en', 'like', '%' . $request->search . '%');
            });
        }

        // Category filter
        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        // Level filter
        if ($request->has('question_level') && $request->question_level) {
            $query->where('question_level', $request->question_level);
        }

        // Active filter
        if ($request->has('is_active') && $request->is_active !== null) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Order by created_at desc
        $query->orderBy('created_at', 'desc');

        // Pagination
        $perPage = $request->get('per_page', 15);
        $questions = $query->paginate($perPage);

        return $this->response->withData(
            true,
            "Sorular başarılı bir şekilde listelendi.",
            $questions
        );
    }

    /**
     * Get Question Detail
     *
     * Get single question with category
     *
     * @urlParam id integer required Question ID. Example: 1
     *
     * @return JsonResponse
     */
    public function show($id)
    {
        $question = Question::with('category')->find($id);

        if (!$question) {
            return $this->response->withData(
                false,
                "Soru bulunamadı.",
                []
            );
        }

        return $this->response->withData(
            true,
            "Soru detayı başarılı bir şekilde getirildi.",
            $question
        );
    }

    /**
     * Create Question
     *
     * Create a new question
     *
     * @bodyParam question object required Question text in different languages. Example: {"tr": "Türkiye'nin başkenti nedir?", "en": "What is the capital of Turkey?"}
     * @bodyParam answer_1 object required First answer option. Example: {"tr": "İstanbul", "en": "Istanbul"}
     * @bodyParam answer_2 object required Second answer option. Example: {"tr": "Ankara", "en": "Ankara"}
     * @bodyParam answer_3 object required Third answer option. Example: {"tr": "İzmir", "en": "Izmir"}
     * @bodyParam answer_4 object required Fourth answer option. Example: {"tr": "Bursa", "en": "Bursa"}
     * @bodyParam correct_answer integer required Correct answer (1-4). Example: 2
     * @bodyParam category_id integer required Category ID. Example: 1
     * @bodyParam question_level string required Difficulty level. Example: easy
     * @bodyParam coin_value integer required Coin value. Example: 10
     * @bodyParam is_active boolean required Active status. Example: true
     *
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'question' => 'required|array',
            'question.tr' => 'required|string',
            'question.en' => 'nullable|string',
            'answer_1' => 'required|array',
            'answer_1.tr' => 'required|string',
            'answer_1.en' => 'nullable|string',
            'answer_2' => 'required|array',
            'answer_2.tr' => 'required|string',
            'answer_2.en' => 'nullable|string',
            'answer_3' => 'required|array',
            'answer_3.tr' => 'required|string',
            'answer_3.en' => 'nullable|string',
            'answer_4' => 'required|array',
            'answer_4.tr' => 'required|string',
            'answer_4.en' => 'nullable|string',
            'correct_answer' => 'required|integer|in:1,2,3,4',
            'category_id' => 'required|exists:categories,id',
            'question_level' => 'required|in:easy,medium,hard',
            'coin_value' => 'required|integer|min:1',
            'is_active' => 'required|boolean'
        ]);

        $question = Question::create($request->all());

        // Socket.IO'ya bildir
        $webhook = new WebhookController();
        $webhook->questionCreated($question, $question->category_id);

        return $this->response->withData(
            true,
            "Soru başarılı bir şekilde oluşturuldu.",
            $question->load('category')
        );
    }

    /**
     * Update Question
     *
     * Update an existing question
     *
     * @urlParam id integer required Question ID. Example: 1
     * @bodyParam question object Question text in different languages. Example: {"tr": "Türkiye'nin başkenti nedir?", "en": "What is the capital of Turkey?"}
     * @bodyParam answer_1 object First answer option. Example: {"tr": "İstanbul", "en": "Istanbul"}
     * @bodyParam answer_2 object Second answer option. Example: {"tr": "Ankara", "en": "Ankara"}
     * @bodyParam answer_3 object Third answer option. Example: {"tr": "İzmir", "en": "Izmir"}
     * @bodyParam answer_4 object Fourth answer option. Example: {"tr": "Bursa", "en": "Bursa"}
     * @bodyParam correct_answer integer Correct answer (1-4). Example: 2
     * @bodyParam category_id integer Category ID. Example: 1
     * @bodyParam question_level string Difficulty level. Example: easy
     * @bodyParam coin_value integer Coin value. Example: 10
     * @bodyParam is_active boolean Active status. Example: true
     *
     * @return JsonResponse
     */
    public function update(Request $request, $id)
    {
        $question = Question::find($id);

        if (!$question) {
            return $this->response->withData(
                false,
                "Soru bulunamadı.",
                []
            );
        }

        $request->validate([
            'question' => 'sometimes|array',
            'question.tr' => 'required_with:question|string',
            'question.en' => 'nullable|string',
            'answer_1' => 'sometimes|array',
            'answer_1.tr' => 'required_with:answer_1|string',
            'answer_1.en' => 'nullable|string',
            'answer_2' => 'sometimes|array',
            'answer_2.tr' => 'required_with:answer_2|string',
            'answer_2.en' => 'nullable|string',
            'answer_3' => 'sometimes|array',
            'answer_3.tr' => 'required_with:answer_3|string',
            'answer_3.en' => 'nullable|string',
            'answer_4' => 'sometimes|array',
            'answer_4.tr' => 'required_with:answer_4|string',
            'answer_4.en' => 'nullable|string',
            'correct_answer' => 'sometimes|integer|in:1,2,3,4',
            'category_id' => 'sometimes|exists:categories,id',
            'question_level' => 'sometimes|in:easy,medium,hard',
            'coin_value' => 'sometimes|integer|min:1',
            'is_active' => 'sometimes|boolean'
        ]);

        $question->update($request->all());

        // Socket.IO'ya bildir
        $webhook = new WebhookController();
        $webhook->questionUpdated($question, $question->category_id);

        return $this->response->withData(
            true,
            "Soru başarılı bir şekilde güncellendi.",
            $question->load('category')
        );
    }

    /**
     * Delete Question
     *
     * Delete a question
     *
     * @urlParam id integer required Question ID. Example: 1
     *
     * @return JsonResponse
     */
    public function destroy($id)
    {
        $question = Question::find($id);

        if (!$question) {
            return $this->response->withData(
                false,
                "Soru bulunamadı.",
                []
            );
        }

        $categoryId = $question->category_id;
        $question->delete();

        // Socket.IO'ya bildir
        $webhook = new WebhookController();
        $webhook->questionDeleted($id, $categoryId);

        return $this->response->withData(
            true,
            "Soru başarılı bir şekilde silindi.",
            []
        );
    }

    /**
     * Get Questions by Category
     *
     * Get questions filtered by category
     *
     * @urlParam category_id integer required Category ID. Example: 1
     * @queryParam page integer Page number for pagination. Example: 1
     * @queryParam per_page integer Items per page. Example: 10
     * @queryParam question_level string Filter by difficulty level. Example: easy
     * @queryParam is_active boolean Filter by active status. Example: true
     *
     * @return JsonResponse
     */
    public function byCategory($categoryId, Request $request)
    {
        $category = Category::find($categoryId);

        if (!$category) {
            return $this->response->withData(
                false,
                "Kategori bulunamadı.",
                []
            );
        }

        $query = Question::where('category_id', $categoryId)->with('category');

        // Level filter
        if ($request->has('question_level') && $request->question_level) {
            $query->where('question_level', $request->question_level);
        }

        // Active filter
        if ($request->has('is_active') && $request->is_active !== null) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Order by created_at desc
        $query->orderBy('created_at', 'desc');

        // Pagination
        $perPage = $request->get('per_page', 15);
        $questions = $query->paginate($perPage);

        return $this->response->withData(
            true,
            "Kategoriye ait sorular başarılı bir şekilde listelendi.",
            $questions
        );
    }
}

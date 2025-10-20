<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LandingAbout;
use App\Models\LandingNews;
use App\Models\LandingTestimonial;
use App\Models\LandingFeature;
use App\Models\LandingBenefit;
use App\Models\LandingFaq;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Landing Page",
 *     description="Landing sayfası için API endpoint'leri"
 * )
 */

class LandingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/landing/about",
     *     summary="Uygulama hakkında bilgilerini getir",
     *     description="Landing sayfası için uygulama hakkında bilgilerini döndürür",
     *     tags={"Landing Page"},
     *     @OA\Response(
     *         response=200,
     *         description="Başarılı response",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Uygulama hakkında bilgileri başarıyla getirildi."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Uygulama Hakkında"),
     *                     @OA\Property(property="description", type="string", example="Bu uygulama hakkında açıklama"),
     *                     @OA\Property(property="img", type="string", example="/uploads/landing/about/image.jpg"),
     *                     @OA\Property(property="created_at", type="string", format="datetime"),
     *                     @OA\Property(property="updated_at", type="string", format="datetime")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function about(): JsonResponse
    {
        $abouts = LandingAbout::latest()->get();
        
        return response()->json([
            'success' => true,
            'data' => $abouts,
            'message' => 'Uygulama hakkında bilgileri başarıyla getirildi.'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/landing/news",
     *     summary="Haberleri getir",
     *     description="Landing sayfası için haberleri döndürür",
     *     tags={"Landing Page"},
     *     @OA\Response(
     *         response=200,
     *         description="Başarılı response",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Haberler başarıyla getirildi."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Yeni Haber"),
     *                     @OA\Property(property="description", type="string", example="Haber açıklaması"),
     *                     @OA\Property(property="img", type="string", example="/uploads/landing/news/image.jpg"),
     *                     @OA\Property(property="created_at", type="string", format="datetime"),
     *                     @OA\Property(property="updated_at", type="string", format="datetime")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function news(): JsonResponse
    {
        $news = LandingNews::latest()->get();
        
        return response()->json([
            'success' => true,
            'data' => $news,
            'message' => 'Haberler başarıyla getirildi.'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/landing/testimonials",
     *     summary="Kullanıcı yorumlarını getir",
     *     description="Landing sayfası için kullanıcı yorumlarını döndürür",
     *     tags={"Landing Page"},
     *     @OA\Response(
     *         response=200,
     *         description="Başarılı response",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Kullanıcı yorumları başarıyla getirildi."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="user_name", type="string", example="Ahmet Yılmaz"),
     *                     @OA\Property(property="comment", type="string", example="Harika bir uygulama!"),
     *                     @OA\Property(property="profile_img", type="string", example="/uploads/landing/testimonials/profile.jpg"),
     *                     @OA\Property(property="created_at", type="string", format="datetime"),
     *                     @OA\Property(property="updated_at", type="string", format="datetime")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function testimonials(): JsonResponse
    {
        $testimonials = LandingTestimonial::latest()->get();
        
        return response()->json([
            'success' => true,
            'data' => $testimonials,
            'message' => 'Kullanıcı yorumları başarıyla getirildi.'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/landing/features",
     *     summary="Özellikleri getir",
     *     description="Landing sayfası için uygulama özelliklerini döndürür",
     *     tags={"Landing Page"},
     *     @OA\Response(
     *         response=200,
     *         description="Başarılı response",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Özellikler başarıyla getirildi."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Hızlı Quiz"),
     *                     @OA\Property(property="description", type="string", example="Hızlı ve eğlenceli quiz deneyimi"),
     *                     @OA\Property(property="created_at", type="string", format="datetime"),
     *                     @OA\Property(property="updated_at", type="string", format="datetime")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function features(): JsonResponse
    {
        $features = LandingFeature::latest()->get();
        
        return response()->json([
            'success' => true,
            'data' => $features,
            'message' => 'Özellikler başarıyla getirildi.'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/landing/benefits",
     *     summary="Avantajları getir",
     *     description="Landing sayfası için uygulama avantajlarını döndürür",
     *     tags={"Landing Page"},
     *     @OA\Response(
     *         response=200,
     *         description="Başarılı response",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Avantajlar başarıyla getirildi."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Ücretsiz Kullanım"),
     *                     @OA\Property(property="description", type="string", example="Temel özellikler tamamen ücretsiz"),
     *                     @OA\Property(property="created_at", type="string", format="datetime"),
     *                     @OA\Property(property="updated_at", type="string", format="datetime")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function benefits(): JsonResponse
    {
        $benefits = LandingBenefit::latest()->get();
        
        return response()->json([
            'success' => true,
            'data' => $benefits,
            'message' => 'Avantajlar başarıyla getirildi.'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/landing/faqs",
     *     summary="Sıkça sorulan soruları getir",
     *     description="Landing sayfası için sıkça sorulan soruları döndürür",
     *     tags={"Landing Page"},
     *     @OA\Response(
     *         response=200,
     *         description="Başarılı response",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sıkça sorulan sorular başarıyla getirildi."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="question", type="string", example="Uygulama nasıl kullanılır?"),
     *                     @OA\Property(property="answer", type="string", example="Uygulamayı kullanmak çok kolay..."),
     *                     @OA\Property(property="created_at", type="string", format="datetime"),
     *                     @OA\Property(property="updated_at", type="string", format="datetime")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function faqs(): JsonResponse
    {
        $faqs = LandingFaq::latest()->get();
        
        return response()->json([
            'success' => true,
            'data' => $faqs,
            'message' => 'Sıkça sorulan sorular başarıyla getirildi.'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/landing/all",
     *     summary="Tüm landing sayfası verilerini getir",
     *     description="Landing sayfası için tüm verileri (about, news, testimonials, features, benefits, faqs) tek seferde döndürür",
     *     tags={"Landing Page"},
     *     @OA\Response(
     *         response=200,
     *         description="Başarılı response",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tüm landing sayfası verileri başarıyla getirildi."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="about",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="title", type="string", example="Uygulama Hakkında"),
     *                         @OA\Property(property="description", type="string", example="Bu uygulama hakkında açıklama"),
     *                         @OA\Property(property="img", type="string", example="/uploads/landing/about/image.jpg"),
     *                         @OA\Property(property="created_at", type="string", format="datetime"),
     *                         @OA\Property(property="updated_at", type="string", format="datetime")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="news",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="title", type="string", example="Yeni Haber"),
     *                         @OA\Property(property="description", type="string", example="Haber açıklaması"),
     *                         @OA\Property(property="img", type="string", example="/uploads/landing/news/image.jpg"),
     *                         @OA\Property(property="created_at", type="string", format="datetime"),
     *                         @OA\Property(property="updated_at", type="string", format="datetime")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="testimonials",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="user_name", type="string", example="Ahmet Yılmaz"),
     *                         @OA\Property(property="comment", type="string", example="Harika bir uygulama!"),
     *                         @OA\Property(property="profile_img", type="string", example="/uploads/landing/testimonials/profile.jpg"),
     *                         @OA\Property(property="created_at", type="string", format="datetime"),
     *                         @OA\Property(property="updated_at", type="string", format="datetime")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="features",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="title", type="string", example="Hızlı Quiz"),
     *                         @OA\Property(property="description", type="string", example="Hızlı ve eğlenceli quiz deneyimi"),
     *                         @OA\Property(property="created_at", type="string", format="datetime"),
     *                         @OA\Property(property="updated_at", type="string", format="datetime")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="benefits",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="title", type="string", example="Ücretsiz Kullanım"),
     *                         @OA\Property(property="description", type="string", example="Temel özellikler tamamen ücretsiz"),
     *                         @OA\Property(property="created_at", type="string", format="datetime"),
     *                         @OA\Property(property="updated_at", type="string", format="datetime")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="faqs",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="question", type="string", example="Uygulama nasıl kullanılır?"),
     *                         @OA\Property(property="answer", type="string", example="Uygulamayı kullanmak çok kolay..."),
     *                         @OA\Property(property="created_at", type="string", format="datetime"),
     *                         @OA\Property(property="updated_at", type="string", format="datetime")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function all(): JsonResponse
    {
        $data = [
            'about' => LandingAbout::latest()->get(),
            'news' => LandingNews::latest()->get(),
            'testimonials' => LandingTestimonial::latest()->get(),
            'features' => LandingFeature::latest()->get(),
            'benefits' => LandingBenefit::latest()->get(),
            'faqs' => LandingFaq::latest()->get(),
        ];
        
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Tüm landing sayfası verileri başarıyla getirildi.'
        ]);
    }
}

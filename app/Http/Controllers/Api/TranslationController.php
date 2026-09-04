<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTranslationRequest;
use App\Models\Translation;
use App\Services\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TranslationController extends Controller
{
    public function __construct(
        protected TranslationService $translationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['locale', 'tag', 'key', 'content']);
        $results = $this->translationService->search($filters, (int) $request->get('per_page', 50));

        return response()->json($results);
    }

    public function store(StoreTranslationRequest $request): JsonResponse
    {
        $translation = $this->translationService->create($request->validated());

        return response()->json([
            'message' => 'Translation created successfully.',
            'data' => $translation,
        ], Response::HTTP_CREATED);
    }

    public function show(Translation $translation): JsonResponse
    {
        return response()->json([
            'data' => $translation->load(['locale:id,code', 'tags:id,name']),
        ]);
    }

    public function update(Request $request, Translation $translation): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['sometimes', 'string', 'exists:locales,code'],
            'key' => ['sometimes', 'string', 'max:191'],
            'content' => ['sometimes', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
        ]);

        $updated = $this->translationService->update($translation, $validated);

        return response()->json([
            'message' => 'Translation updated successfully.',
            'data' => $updated,
        ]);
    }

    public function export(Request $request, string $locale): JsonResponse
    {
        $tag = $request->query('tag');
        $data = $this->translationService->export($locale, $tag);

        return response()->json($data)
            ->header('Cache-Control', 'public, max-age=3600, stale-while-revalidate=60');
    }
}

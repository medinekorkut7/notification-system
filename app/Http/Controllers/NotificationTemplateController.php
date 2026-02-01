<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTemplateRequest;
use App\Http\Requests\UpdateTemplateRequest;
use App\Models\NotificationTemplate;
use App\Services\NotificationTemplateCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 25);
        $templates = NotificationTemplate::query()->orderBy('name')->paginate($perPage);

        return response()->json([
            'data' => $templates->items(),
            'meta' => [
                'current_page' => $templates->currentPage(),
                'per_page' => $templates->perPage(),
                'total' => $templates->total(),
            ],
        ]);
    }

    public function show(string $templateId): JsonResponse
    {
        $template = app(NotificationTemplateCache::class)->getOrFail($templateId);

        return response()->json($template);
    }

    public function store(StoreTemplateRequest $request): JsonResponse
    {
        $template = NotificationTemplate::query()->create($request->validated());
        $cache = app(NotificationTemplateCache::class);
        $cache->forget($template->id);
        $cache->forgetRecent(10);

        return response()->json($template, 201);
    }

    public function update(UpdateTemplateRequest $request, string $templateId): JsonResponse
    {
        $template = NotificationTemplate::query()->findOrFail($templateId);

        $data = $request->validated();
        if (isset($data['name'])) {
            $request->validate([
                'name' => ['string', 'max:128', Rule::unique('notification_templates', 'name')->ignore($template->id)],
            ]);
        }

        $template->update($data);
        $cache = app(NotificationTemplateCache::class);
        $cache->forget($template->id);
        $cache->forgetRecent(10);

        return response()->json($template);
    }

    public function destroy(string $templateId): JsonResponse
    {
        $template = NotificationTemplate::query()->findOrFail($templateId);
        $template->delete();
        $cache = app(NotificationTemplateCache::class);
        $cache->forget($templateId);
        $cache->forgetRecent(10);

        return response()->json(['message' => 'Template deleted.']);
    }
}

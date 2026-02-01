<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreviewTemplateRequest;
use App\Services\NotificationTemplateCache;
use App\Services\TemplateRenderer;
use Illuminate\Http\JsonResponse;

class TemplatePreviewController extends Controller
{
    public function __invoke(PreviewTemplateRequest $request): JsonResponse
    {
        $template = app(NotificationTemplateCache::class)->getOrFail($request->string('template_id'));
        $variables = array_merge(
            $template->default_variables ?? [],
            $request->input('variables', [])
        );

        $content = app(TemplateRenderer::class)->render($template->content, $variables);

        return response()->json([
            'template_id' => $template->id,
            'content' => $content,
            'variables' => $variables,
        ]);
    }
}

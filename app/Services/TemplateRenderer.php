<?php

namespace App\Services;

class TemplateRenderer
{
    public function render(string $content, array $variables): string
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            $replacements['{{' . $key . '}}'] = (string) $value;
        }

        return strtr($content, $replacements);
    }
}

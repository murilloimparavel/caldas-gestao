<?php

namespace App\Support\Assistant;

final class AssistantSensitiveContentRedactor
{
    public function redact(string $content): string
    {
        $content = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/iu', '[email ocultado]', $content) ?? $content;
        $content = preg_replace('/(?<!\d)(?:\+?\d{1,3}[\s.-]?)?(?:\(?\d{2}\)?[\s.-]?)?\d{4,5}[\s.-]?\d{4}(?!\d)/u', '[telefone ocultado]', $content) ?? $content;
        $content = preg_replace('/(?<!\d)\d{3}\.?\d{3}\.?\d{3}-?\d{2}(?!\d)/u', '[documento ocultado]', $content) ?? $content;

        return $content;
    }
}

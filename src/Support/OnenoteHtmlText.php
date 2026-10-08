<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppMsgraph\Support;

final class OnenoteHtmlText
{
    public static function toPlainText(string $html, string $notebook, string $section, string $title): string
    {
        $withoutScripts = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html) ?? $html;
        $withoutStyles = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $withoutScripts) ?? $withoutScripts;
        $text = html_entity_decode(strip_tags($withoutStyles), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim("Notizbuch: {$notebook}\nAbschnitt: {$section}\nSeite: {$title}\n\n".trim($text));
    }
}

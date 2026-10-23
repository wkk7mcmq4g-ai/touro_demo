<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * A value with a coloured dot in front, using PHPRunner's colours from `optionvalues`.
 * PHPRunner painted the whole cell; a dot keeps the text readable on any colour.
 */
class ColorDot
{
    public static function render(?string $value, ?string $color): ?HtmlString
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = e($value);

        if ($color === null) {
            return new HtmlString($text);
        }

        return new HtmlString(
            '<span style="display:inline-flex;align-items:center;gap:6px;white-space:nowrap">'
            .'<span aria-hidden="true" style="width:10px;height:10px;border-radius:9999px;flex:none;'
            .'box-shadow:inset 0 0 0 1px rgba(0,0,0,.15);background:'.e($color).'"></span>'
            .$text.'</span>'
        );
    }
}

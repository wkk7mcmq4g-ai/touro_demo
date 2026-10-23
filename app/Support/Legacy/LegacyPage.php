<?php

namespace App\Support\Legacy;

use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Renders a PHPRunner view page (layout exported from the project into
 * resources/legacy/pages/<table>.json) as plain HTML for a record, so documents such as
 * the vouchers come out with the same content and layout as in PHPRunner.
 *
 * Supported: the page's forms (header, above/below grid, grid), cells with
 * colspan/rowspan, alignment, borders, bold/colour/font, and the items field value,
 * field label, rich text and image. Menus, buttons and links are not printed.
 */
class LegacyPage
{
    private const FORMS = ['top', 'above-grid', 'grid', 'below-grid'];

    /** @param  array<string, mixed>  $definition  one table's export */
    public function __construct(private array $definition, private string $page) {}

    public static function load(string $table, string $page): self
    {
        $path = resource_path("legacy/pages/{$table}.json");
        $definition = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        if (! isset($definition['pages'][$page])) {
            throw new RuntimeException("No PHPRunner page {$table}/{$page}.");
        }

        return new self($definition, $page);
    }

    /** @param  array<string, mixed>  $record */
    public function render(array $record): string
    {
        $page = $this->definition['pages'][$this->page];
        $html = '';

        foreach (self::FORMS as $formName) {
            $form = $page['forms'][$formName] ?? null;
            if ($form && ($table = $this->form($form, $page['items'], $record, $formName === 'grid'))) {
                $html .= $table;
            }
        }

        return $html;
    }

    private function form(array $form, array $items, array $record, bool $isGrid): ?string
    {
        $rows = '';
        $hasContent = false;
        $columns = 1;

        foreach ($form['grid'] ?? [] as $row) {
            $columns = max($columns, array_sum(array_map(fn (array $c): int => (int) ($c['colspan'] ?? 1), $row['cells'])));
        }

        foreach ($form['grid'] ?? [] as $row) {
            $cells = '';
            $rowHasContent = false;
            foreach ($row['cells'] as $ref) {
                $cell = $form['cells'][$ref['cell']] ?? [];
                $content = implode(' ', array_filter(array_map(fn (string $id): string => $this->item($items[$id] ?? [], $record), $cell['items'] ?? [])));
                $filled = trim(strip_tags($content, '<img>')) !== '';
                $hasContent = $hasContent || $filled;
                $rowHasContent = $rowHasContent || $filled;

                $span = (($ref['colspan'] ?? 1) > 1 ? ' colspan="'.(int) $ref['colspan'].'"' : '')
                    .(($ref['rowspan'] ?? 1) > 1 ? ' rowspan="'.(int) $ref['rowspan'].'"' : '');
                $cells .= '<td'.$span.' style="'.$this->cellStyle($cell).'">'.$content.'</td>';
            }
            // Empty rows are PHPRunner spacers.
            $rows .= $rowHasContent ? "<tr>{$cells}</tr>" : '<tr class="lp-spacer"><td colspan="'.$columns.'"></td></tr>';
        }

        if (! $hasContent) {
            return null;
        }

        $class = $isGrid ? 'lp-grid lp-cols-'.$columns : 'lp-block';

        return "<table class=\"{$class}\">{$rows}</table>";
    }

    private function item(array $item, array $record): string
    {
        $style = $this->itemStyle($item);
        $wrap = fn (string $html): string => $style === '' ? $html : "<div style=\"{$style}\">{$html}</div>";

        return match ($item['type'] ?? null) {
            'edit_field' => $wrap(nl2br(e($this->value($item['field'] ?? '', $record)))),
            'edit_field_label' => $wrap(e($this->definition['labels'][$item['field'] ?? ''] ?? (string) ($item['field'] ?? ''))),
            'text' => $wrap($this->richText((string) ($item['label']['text'] ?? ''))),
            'image' => $this->image((string) ($item['image']['image'] ?? '')),
            default => '',
        };
    }

    private function value(string $field, array $record): string
    {
        $value = $record[$field] ?? null;

        if ($value === null || $value === '') {
            return '';
        }

        return match ($this->definition['formats'][$field] ?? null) {
            'Short Date' => Carbon::parse($value)->format('d/m/Y'),
            'Time' => substr((string) $value, 0, 5),
            'Number' => number_format((float) $value, 2, ',', '.'),
            'Database File' => '',
            default => (string) $value,
        };
    }

    /** Quill HTML from PHPRunner's text items; scripts and handlers are not kept. */
    private function richText(string $html): string
    {
        $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';

        return strip_tags($html, '<p><br><strong><b><em><i><u><span><h1><h2><h3><h4><h5><h6><ul><ol><li><a><sub><sup>');
    }

    private function image(string $file): string
    {
        $path = resource_path('legacy/images/'.basename($file));

        if ($file === '' || ! is_file($path)) {
            return '';
        }

        $mime = str_ends_with(strtolower($file), '.png') ? 'image/png' : 'image/jpeg';

        return '<img src="data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path)).'" alt="">';
    }

    private function cellStyle(array $cell): string
    {
        $style = [];
        if (isset($cell['align'])) {
            $style[] = 'text-align:'.$this->css($cell['align']);
        }
        foreach ($cell['border'] ?? [] as $border) {
            $style[] = 'border-'.$this->css($border['side'] ?? 'bottom').':'.$this->css($border['width'] ?? '1px').' solid '.$this->css($border['color'] ?? '#000');
        }

        return implode(';', array_merge($style, $this->fontStyle($cell)));
    }

    private function itemStyle(array $item): string
    {
        $style = $this->fontStyle($item);
        if (isset($item['padding']) && is_array($item['padding'])) {
            // Screen paddings (up to 450px in PHPRunner) would push text off an A4 page.
            foreach ($item['padding'] as $side => $size) {
                $style[] = 'padding-'.$this->css((string) $side).':'.min(20, (int) $size).'px';
            }
        }

        return implode(';', $style);
    }

    /** @return list<string> */
    private function fontStyle(array $props): array
    {
        $style = [];
        if (! empty($props['bold'])) {
            $style[] = 'font-weight:bold';
        }
        if (! empty($props['color'])) {
            $style[] = 'color:'.$this->css($props['color']);
        }
        if (! empty($props['font-size'])) {
            $style[] = 'font-size:'.$this->css($props['font-size']);
        }

        return $style;
    }

    private function css(mixed $value): string
    {
        return (string) preg_replace('/[^#a-zA-Z0-9.%\- ]/', '', (string) $value);
    }
}

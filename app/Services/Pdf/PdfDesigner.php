<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Pdf;

class PdfDesigner
{
    public const BOLD = 'bold';
    public const BUSINESS = 'business';
    public const CLEAN = 'clean';
    public const CREATIVE = 'creative';
    public const ELEGANT = 'elegant';
    public const HIPSTER = 'hipster';
    public const MODERN = 'modern';
    public const PLAIN = 'plain';
    public const PLAYFUL = 'playful';
    public const CUSTOM = 'custom';
    public const CALM = 'calm';

    public const DELIVERY_NOTE = 'delivery_note';
    public const STATEMENT = 'statement';
    public const PURCHASE_ORDER = 'purchase_order';

    public string $template;

    public function __construct(public PdfService $service)
    {
    }

    public function build(): self
    {
        /*If the design is custom*/
        if ($this->service->config->design->is_custom) {
            $this->template = $this->composeFromPartials(json_decode(json_encode($this->service->config->design->design), true));
        } else {
            $this->template = file_get_contents(config('ninja.designs.base_path') . strtolower($this->service->config->design->name) . '.html');
        }

        // Remove NULL bytes
        $this->template = str_replace("\0", '', $this->template);

        // Remove UTF-7 BOM
        $this->template = preg_replace('/^\\+ADw-/', '', $this->template);

        return $this;
    }

    public function buildFromPartials(array $partials): self
    {

        $this->template = $this->composeFromPartials($partials);

        return $this;

    }

    /**
     * If the user has implemented a custom design, then we need to rebuild the design at this point
     */

    /**
     * Returns the custom HTML design as
     * a string
     * @param  array $partials
     * @return string
     */
    private function composeFromPartials(array $partials): string
    {
        $html = '';

        $html .= $partials['includes'];
        $html .= $partials['header'];
        $html .= $partials['body'];
        $html .= $partials['footer'];

        return $html;
    }
}

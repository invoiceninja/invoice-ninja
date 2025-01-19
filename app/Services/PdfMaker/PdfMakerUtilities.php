<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\PdfMaker;

use DOMDocument;
use DOMXPath;

trait PdfMakerUtilities
{
    private function initializeDomDocument()
    {
        $document = new DOMDocument();

        $document->validateOnParse = true;
        @$document->loadHTML(mb_convert_encoding($this->design->html(), 'HTML-ENTITIES', 'UTF-8'));
        // @$document->loadHTML(htmlspecialchars_decode(htmlspecialchars($this->design->html(), ENT_QUOTES, 'UTF-8')));

        $this->document = $document;
        $this->xpath = new DOMXPath($document);
    }

    public function getSection(string $selector, string $section = null)
    {
        $element = $this->document->getElementById($selector);

        if ($section) {
           return $element->getAttribute($section);
        }
        
        return $element->nodeValue;
    }

    public function getSectionNode(string $selector)
    {
        return $this->document->getElementById($selector);
    }

    public function updateElementProperties(array $elements)
    {
        foreach ($elements as $element) {
            if (isset($element['tag'])) {
                $node = $this->document->getElementsByTagName($element['tag'])->item(0);
            } elseif (! is_null($this->document->getElementById($element['id']))) {
                $node = $this->document->getElementById($element['id']);
            } else {
                continue;
            }

            if (isset($element['properties'])) {
                foreach ($element['properties'] as $property => $value) {
                    $this->updateElementProperty($node, $property, $value);
                }
            }

            if (isset($element['elements'])) {
                $this->createElementContent($node, $element['elements']);
            }
        }
    }

    public function updateElementProperty($element, string $attribute, ?string $value)
    {
        // We have exception for "hidden" property.
        // hidden="true" or hidden="false" will both hide the element,
        // that's why we have to create an exception here for this rule.

        if ($attribute == 'hidden' && ($value == false || $value == 'false')) {
            return $element;
        }

        $element->setAttribute($attribute, $value);

        if ($element->getAttribute($attribute) === $value) {
            return $element;
        }

        return $element;
    }

    public function createElementContent($element, $children)
    {
        foreach ($children as $child) {
            $contains_html = false;
            $contains_markdown = false;
            $child['content'] = $child['content'] ?? '';

            $lines = explode("\n", $child['content']);
            $contains_markdown = false;

            foreach ($lines as $line) {
                $trimmed = ltrim($line);
                if (empty($trimmed)) {
                    continue;
                }

                $first_char = substr($trimmed, 0, 1);

                if (
                    $first_char === '#' ||    // Headers
                    $first_char === '>' ||    // Blockquotes
                    $first_char === '-' ||    // Lists
                    $first_char === '*' ||    // Lists/Bold
                    $first_char === '_' ||    // Italic
                    $first_char === '`' ||    // Code
                    $first_char === '[' ||    // Links
                    str_contains($trimmed, '**') // Bold (special case)
                ) {
                    $contains_markdown = true;
                    break;
                }
            }

            if (isset($this->data['process_markdown']) && $this->data['process_markdown'] && $contains_markdown &&$child['element'] !== 'script') {
                $child['content'] = str_replace('<br>', "\r", $child['content']);
                $child['content'] = $this->commonmark->convert($child['content']); //@phpstan-ignore-line
            }

            if (isset($child['is_empty']) && $child['is_empty'] === true) {
                continue;
            }

            $contains_html = str_contains($child['content'], '<') && str_contains($child['content'], '>');

            if ($contains_html) {
                // If the element contains the HTML, we gonna display it as is. Backend is going to
                // encode it for us, preventing any errors on the processing stage.
                // Later, we decode this using Javascript so it looks like it's normal HTML being injected.
                // To get all elements that need frontend decoding, we use 'data-state' property.

                $_child = $this->document->createElement($child['element'], '');
                $_child->setAttribute('data-state', 'encoded-html');
                $_child->nodeValue = htmlspecialchars($child['content']);
            } else {
                // .. in case string doesn't contain any HTML, we'll just return
                $_child = $this->document->createElement($child['element'], htmlspecialchars($child['content']));
            }

            $element->appendChild($_child);

            if (isset($child['properties'])) {
                foreach ($child['properties'] as $property => $value) {
                    $this->updateElementProperty($_child, $property, $value);
                }
            }

            if (isset($child['elements'])) {
                $this->createElementContent($_child, $child['elements']);
            }
        }
    }

    public function updateVariables(array $variables)
    {
        $html = strtr($this->getCompiledHTML(), $variables['labels']);

        $html = strtr($html, $variables['values']);

        //old block
        @$this->document->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

        //new block
        // $html = htmlspecialchars_decode($html, ENT_QUOTES | ENT_HTML5);
        // $html = str_ireplace(['<br>'], '<br/>', $html);
        // @$this->document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        //continues
        $this->document->saveHTML();
    }

    public function updateVariable(string $element, string $variable, string $value)
    {
        $element = $this->document->getElementById($element);

        $original = $element->nodeValue;

        $element->nodeValue = '';

        $replaced = strtr($original, [$variable => $value]);

        $element->appendChild(
            $this->document->createTextNode($replaced)
        );

        return $element;
    }

    public function getEmptyElements(array &$elements, array $variables)
    {
        foreach ($elements as &$element) {
            if (isset($element['elements'])) {
                $this->getEmptyChildrens($element['elements'], $variables);
            }
        }
    }

    public function getEmptyChildrens(array &$children, array $variables)
    {
        foreach ($children as $key => &$child) {
            if (isset($child['content']) && isset($child['show_empty']) && $child['show_empty'] === false) {
                $value = strtr($child['content'], $variables['values']);
                if ($value === '' || $value === '&nbsp;' || $value === ' ') {
                    $child['is_empty'] = true;
                }
            }

            if (isset($child['elements'])) {
                $this->getEmptyChildrens($child['elements'], $variables);
            }
        }
    }
}

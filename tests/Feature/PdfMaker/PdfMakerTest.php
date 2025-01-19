<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature\PdfMaker;

use App\Services\PdfMaker\Design;
use App\Services\PdfMaker\PdfMaker;
use Tests\TestCase;

class PdfMakerTest extends TestCase
{
    public $state = [
        'template' => [],
        'variables' => [
            'labels' => [],
            'values' => [],
        ],
    ];

    public function testDesignLoadsCorrectly()
    {
        $design = new Design('example', ['custom_path' => base_path('tests/Feature/PdfMaker/')]);
        $maker = new PdfMaker($this->state);

        $maker->design($design);

        $this->assertInstanceOf(Design::class, $maker->design);
    }

    public function testHtmlDesignLoadsCorrectly()
    {
        $design = new Design('example', ['custom_path' => base_path('tests/Feature/PdfMaker/')]);

        $maker = new PdfMaker($this->state);

        $maker
            ->design($design)
            ->build();

        $this->assertStringContainsString('Template: Example', $maker->getCompiledHTML());
    }

    public function testGetSectionUtility()
    {
        $design = new Design('example', ['custom_path' => base_path('tests/Feature/PdfMaker/')]);

        $maker = new PdfMaker($this->state);

        $maker
            ->design($design)
            ->build();

        $this->assertEquals('table', $maker->getSectionNode('product-table')->nodeName);
    }

    public function testTableAttributesAreInjected()
    {
        $state = [
            'template' => [
                'product-table' => [
                    'id' => 'product-table',
                    'properties' => [
                        'class' => 'my-awesome-class',
                        'style' => 'margin-top: 10px;',
                        'script' => '',
                    ],
                ],
            ],
            'variables' => [
                'labels' => [],
                'values' => [],
            ],
        ];

        $design = new Design('example', ['custom_path' => base_path('tests/Feature/PdfMaker/')]);
        $maker = new PdfMaker($state);

        $maker
            ->design($design)
            ->build();

        $this->assertStringContainsString('my-awesome-class', $maker->getSection('product-table', 'class'));
        // $this->assertStringContainsString('margin-top: 10px', $maker->getSection('product-table', 'style'));
        // $this->assertStringContainsString('console.log(1)', $maker->getSection('product-table', 'script'));
    }

    public function testVariablesAreReplaced()
    {
        $state = [
            'template' => [
                'product-table' => [
                    'id' => 'product-table',
                ],
            ],
            'variables' => [
                'labels' => [],
                'values' => [
                    '$company.name' => 'Invoice Ninja',
                ],
            ],
        ];

        $design = new Design('example', ['custom_path' => base_path('tests/Feature/PdfMaker/')]);
        $maker = new PdfMaker($state);

        $maker
            ->design($design)
            ->build();

        $this->assertStringContainsString('Invoice Ninja', $maker->getCompiledHTML());
        $this->assertStringContainsString('Invoice Ninja', $maker->getSection('header'));
    }

    public function testElementContentIsGenerated()
    {
        $state = [
            'template' => [
                'product-table' => [
                    'id' => 'product-table',
                    'properties' => [],
                    'elements' => [
                        ['element' => 'thead', 'content' => '', 'elements' => [
                            ['element' => 'th', 'content' => 'Company'],
                            ['element' => 'th', 'content' => 'Contact'],
                            ['element' => 'th', 'content' => 'Country', 'properties' => [
                                'colspan' => 3,
                            ]],
                        ]],
                        ['element' => 'tr', 'content' => '', 'elements' => [
                            ['element' => 'td', 'content' => '$company'],
                            ['element' => 'td', 'content' => '$email'],
                            ['element' => 'td', 'content' => '$country', 'elements' => [
                                ['element' => 'a', 'content' => 'Click here for a link', 'properties' => [
                                    'href' => 'https://github.com/invoiceninja/invoiceninja',
                                ]],
                            ]],
                        ]],
                    ],
                ],
            ],
            'variables' => [
                'labels' => [],
                'values' => [
                    '$company' => 'Invoice Ninja',
                    '$email' => 'contact@invoiceninja.com',
                    '$country' => 'UK',
                ],
            ],
        ];

        $design = new Design('example', ['custom_path' => base_path('tests/Feature/PdfMaker/')]);
        $maker = new PdfMaker($state);

        $maker
            ->design($design)
            ->build();

        $compiled = 'contact@invoiceninja.com';

        $this->assertStringContainsString($compiled, $maker->getCompiledHTML());
    }

    public function testConditionalRenderingOfElements()
    {
        $design1 = new Design('example', ['custom_path' => base_path('tests/Feature/PdfMaker/')]);

        $maker1 = new PdfMaker([
            'template' => [
                'header' => [
                    'id' => 'header',
                    'properties' => [],
                ],
            ],
        ]);

        $maker1
            ->design($design1)
            ->build();

        $output1 = $maker1->getCompiledHTML();

        $this->assertStringContainsString('<div id="header">', $output1);

        $design2 = new Design('example', ['custom_path' => base_path('tests/Feature/PdfMaker/')]);
        $maker2 = new PdfMaker([
            'template' => [
                'header' => [
                    'id' => 'header',
                    'properties' => ['hidden' => 'true'],
                ],
            ],
        ]);

        $maker2
            ->design($design2)
            ->build();

        $output2 = $maker2->getCompiledHTML();

        $this->assertStringContainsString('<div id="header" hidden="true">$company.name</div>', $output2);

        $this->assertNotSame($output1, $output2);
    }

    public function testGeneratingPdf()
    {
        $state = [
            'template' => [
                'header' => [
                    'id' => 'header',
                    'properties' => ['class' => 'text-white bg-blue-600 p-2'],
                ],
                'product-table' => [
                    'id' => 'product-table',
                    'properties' => ['class' => 'table-auto'],
                    'elements' => [
                        ['element' => 'thead', 'content' => '', 'elements' => [
                            ['element' => 'tr', 'content' => '', 'elements' => [
                                ['element' => 'th', 'content' => 'Title', 'properties' => ['class' => 'px-4 py-2']],
                                ['element' => 'th', 'content' => 'Author', 'properties' => ['class' => 'px-4 py-2']],
                                ['element' => 'th', 'content' => 'Views', 'properties' => ['class' => 'px-4 py-2']],
                            ]],
                        ]],
                        ['element' => 'tbody', 'content' => '', 'elements' => [
                            ['element' => 'tr', 'content' => '', 'elements' => [
                                ['element' => 'td', 'content' => 'An amazing guy', 'properties' => ['class' => 'border px-4 py-2']],
                                ['element' => 'td', 'content' => 'David Bomba', 'properties' => ['class' => 'border px-4 py-2']],
                                ['element' => 'td', 'content' => '1M', 'properties' => ['class' => 'border px-4 py-2']],
                            ]],
                            ['element' => 'tr', 'content' => '', 'elements' => [
                                ['element' => 'td', 'content' => 'Flutter master', 'properties' => ['class' => 'border px-4 py-2']],
                                ['element' => 'td', 'content' => 'Hillel Coren', 'properties' => ['class' => 'border px-4 py-2']],
                                ['element' => 'td', 'content' => '1M', 'properties' => ['class' => 'border px-4 py-2']],
                            ]],
                            ['element' => 'tr', 'content' => '', 'elements' => [
                                ['element' => 'td', 'content' => 'Bosssssssss', 'properties' => ['class' => 'border px-4 py-2']],
                                ['element' => 'td', 'content' => 'Shalom Stark', 'properties' => ['class' => 'border px-4 py-2']],
                                ['element' => 'td', 'content' => '1M', 'properties' => ['class' => 'border px-4 py-2']],
                            ]],
                            ['element' => 'tr', 'content' => '', 'order' => 4, 'elements' => [
                                ['element' => 'td', 'content' => 'Three amazing guys', 'properties' => ['class' => 'border px-4 py-2', 'colspan' => '100%']],
                            ]],
                        ]],
                    ],
                ],
            ],
            'variables' => [
                'labels' => [],
                'values' => [
                    '$title' => 'Invoice Ninja',
                ],
            ],
        ];

        $design = new Design('example', ['custom_path' => base_path('tests/Feature/PdfMaker/')]);
        $maker = new PdfMaker($state);

        $maker
            ->design($design)
            ->build();

        $this->assertTrue(true);
    }

    public function testGetSectionHTMLWorks()
    {
        $design = new Design('example', ['custom_path' => base_path('tests/Feature/PdfMaker/')]);

        $html = $design
            ->document()
            ->getSectionHTML('product-table');

        $this->assertStringContainsString('id="product-table"', $html);
    }

    public function testWrapperHTMLWorks()
    {
        $design = new Design('example', ['custom_path' => base_path('tests/Feature/PdfMaker/')]);

        $state = [
            'template' => [
                'product-table' => [
                    'id' => 'product-table',
                    'elements' => [
                        ['element' => 'p', 'content' => 'Example paragraph'],
                    ],
                ],
            ],
            'variables' => [
                'labels' => [],
                'values' => [],
            ],
            'options' => [
                'all_pages_header' => true,
                'all_pages_footer' => true,
            ],
        ];

        $maker = new PdfMaker($state);

        $maker
            ->design($design)
            ->build();

        // exec('echo "" > storage/logs/laravel.log');

        // nlog($maker->getCompiledHTML(true));

        $this->assertTrue(true);
    }
}

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

namespace App\Services\EDocument\Standards\Validation;

class XsltDocumentValidator
{
    private array $stylesheets = [
        '/Services/EDocument/Standards/Validation/Peppol/Stylesheets/CEN-EN16931-UBL.xslt',
        '/Services/EDocument/Standards/Validation/Peppol/Stylesheets/PEPPOL-EN16931-UBL.xslt',
    ];

    private string $ubl_xsd = 'Services/EDocument/Standards/Validation/Peppol/Stylesheets/UBL2.1/UBL-Invoice-2.1.xsd';

    private string $peppol_stylesheet = 'Services/EDocument/Standards/Validation/Peppol/Stylesheets/generic_stylesheet.xslt';
    // private string $peppol_stylesheet = 'Services/EDocument/Standards/Validation/Peppol/Stylesheets/xrechung.xslt';

    // private string $peppol_stylesheetx = 'Services/EDocument/Standards/Validation/Peppol/Stylesheets/ubl_stylesheet.xslt';
    // private string $peppol_stylesheet = 'Services/EDocument/Standards/Validation/Peppol/Stylesheets/ci_to_ubl_stylesheet.xslt';

    private array $errors = [];

    public function __construct(public string $xml_document)
    {
    }

    /**
     * Validate the XSLT document
     *
     * @return self
     */
    public function validate(): self
    {
        // nlog($this->xml_document);
        $this->validateXsd()
             ->validateSchema();

        return $this;
    }

    private function validateSchema(): self
    {

        try{
            $processor = new \Saxon\SaxonProcessor();

            $xslt = $processor->newXslt30Processor();

            foreach($this->stylesheets as $stylesheet)
            {
                $xdmNode = $processor->parseXmlFromString($this->xml_document);
                
                /** @var \Saxon\XsltExecutable $xsltExecutable */
                $xsltExecutable = $xslt->compileFromFile(app_path($stylesheet)); //@phpstan-ignore-line
                $result = $xsltExecutable->transformToValue($xdmNode);

                if($result->size() == 0)
                    continue;

                for ($x=0; $x<$result->size(); $x++) 
                {
                    $a = $result->itemAt($x);

                    if(strlen($a->getStringValue() ?? '') > 1)
                        $this->errors['stylesheet'][] = $a->getStringValue();
                }

            }
            
        }
        catch(\Throwable $th){

            $this->errors['general'][] = $th->getMessage();
        }   

        return $this;

    }

    private function validateXsd(): self
    {
                
        libxml_use_internal_errors(true);

        $xml = new \DOMDocument();
        $xml->loadXML($this->xml_document);

        if (!$xml->schemaValidate(app_path($this->ubl_xsd))) {
            $errors = libxml_get_errors();
            libxml_clear_errors();

            $errorMessages = [];
            foreach ($errors as $error) {
                $this->errors['xsd'][] = sprintf(
                    'Line %d: %s',
                    $error->line,
                    trim($error->message)
                );
            }

        }

        return $this;
    }

    public function setStyleSheets(array $stylesheets): self
    {
        $this->stylesheets = $stylesheets;
        
        return $this;
    }

    public function getStyleSheets(): array
    {
        return $this->stylesheets;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getHtml(): mixed
    {
        //@todo need to harvest the document type and apply the correct stylesheet
        try {
            // Create Saxon processor
            $processor = new \Saxon\SaxonProcessor();
            $xslt = $processor->newXslt30Processor();

            $xml = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $this->xml_document);

            // Load XML document
            $xml_doc = $processor->parseXmlFromString($xml);
            
            // Compile and apply stylesheet
            $stylesheet = $xslt->compileFromFile(app_path($this->peppol_stylesheet)); //@phpstan-ignore-line

            // Transform to HTML
            $result = $stylesheet->transformToString($xml_doc); //@phpstan-ignore-line

            return $result;

        } catch (\Throwable $th) {
            nlog("failed to convert xml to html ".$th->getMessage());
            return ['errors' => $th->getMessage()];
            // Handle any errors
            // throw new \Exception("XSLT transformation failed: " . $e->getMessage());
        }

    }

}

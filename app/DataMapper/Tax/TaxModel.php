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

namespace App\DataMapper\Tax;

class TaxModel
{
    /** @var string $seller_subregion */
    public string $seller_subregion = 'CA';

    /** @var string $version */
    public string $version = 'gamma';

    /** @var object $regions */
    public object $regions;
    
    /** @var bool $act_as_sender */
    public bool $act_as_sender = false;
    
    /** @var bool $act_as_receiver */
    public bool $act_as_receiver = false;
    
    /**
     * __construct
     *
     * @param  TaxModel $model
     * @return void
     */
    public function __construct(public mixed $model = null)
    {

        if(!$model) {
            $this->regions = $this->init();
        } else {
            
            $this->seller_subregion = $model->seller_subregion ?? '';
            $this->act_as_sender = $model->act_as_sender ?? false;
            $this->act_as_receiver = $model->act_as_receiver ?? false;
            
            $modelArray = get_object_vars($model);
            foreach ($modelArray as $key => $value) {
                $this->{$key} = $value;
            }

        }

        $this->migrate();
    }

    public function migrate(): self
    {

        if($this->version == 'alpha') {
            $this->regions->EU->subregions->PL = new \stdClass();
            $this->regions->EU->subregions->PL->tax_rate = 23;
            $this->regions->EU->subregions->PL->tax_name = 'VAT';
            $this->regions->EU->subregions->PL->reduced_tax_rate = 8;
            $this->regions->EU->subregions->PL->apply_tax = false;

            $this->version = 'beta';
        }

        if($this->version == 'beta') {

            //CEUTA
            $this->regions->EU->subregions->{'ES-CE'} = new \stdClass();
            $this->regions->EU->subregions->{'ES-CE'}->tax_rate = 4;
            $this->regions->EU->subregions->{'ES-CE'}->tax_name = 'IGIC';
            $this->regions->EU->subregions->{'ES-CE'}->reduced_tax_rate = 4;
            $this->regions->EU->subregions->{'ES-CE'}->apply_tax = false;

            //MELILLA ML 4
            $this->regions->EU->subregions->{'ES-ML'} = new \stdClass();
            $this->regions->EU->subregions->{'ES-ML'}->tax_rate = 4;
            $this->regions->EU->subregions->{'ES-ML'}->tax_name = 'IGIC';
            $this->regions->EU->subregions->{'ES-ML'}->reduced_tax_rate = 4;
            $this->regions->EU->subregions->{'ES-ML'}->apply_tax = false;

            //CANARIAS CN 7/3
            $this->regions->EU->subregions->{'ES-CN'} = new \stdClass();
            $this->regions->EU->subregions->{'ES-CN'}->tax_rate = 7;
            $this->regions->EU->subregions->{'ES-CN'}->tax_name = 'IGIC';
            $this->regions->EU->subregions->{'ES-CN'}->reduced_tax_rate = 4;
            $this->regions->EU->subregions->{'ES-CN'}->apply_tax = false;

            $this->version = 'gamma';
        }

        //@pending Flutter AP upgrade - deploying this breaks the AP.
        // if($this->version == 'gamma') {

        //     $this->regions->EU->subregions->IS = new \stdClass();
        //     $this->regions->EU->subregions->IS->tax_rate = 24;
        //     $this->regions->EU->subregions->IS->tax_name = 'VSK';
        //     $this->regions->EU->subregions->IS->reduced_tax_rate = 11;
        //     $this->regions->EU->subregions->IS->apply_tax = false;

        //     $this->regions->EU->subregions->LI = new \stdClass();
        //     $this->regions->EU->subregions->LI->tax_rate = 8.1;
        //     $this->regions->EU->subregions->LI->tax_name = 'MWST';
        //     $this->regions->EU->subregions->LI->reduced_tax_rate = 2.6;
        //     $this->regions->EU->subregions->LI->apply_tax = false;

        //     $this->regions->EU->subregions->NO = new \stdClass();
        //     $this->regions->EU->subregions->NO->tax_rate = 25;
        //     $this->regions->EU->subregions->NO->tax_name = 'MVA';
        //     $this->regions->EU->subregions->NO->reduced_tax_rate = 12;
        //     $this->regions->EU->subregions->NO->apply_tax = false;

        //     $this->ukRegion();

        //     $this->version = 'delta';

        // }

        return $this;
    }

    /**
     * Initializes the rules and builds any required data.
     *
     * @return object
     */
    public function init(): object
    {
        $this->regions = new \stdClass();
        $this->regions->US = new \stdClass();
        $this->regions->EU = new \stdClass();

        $this->usRegion()
             ->euRegion()
             ->auRegion();
            //  ->ukRegion();


        return $this->regions;
    }

    private function ukRegion(): self
    {

        // Add new UK region
        $this->regions->UK = new \stdClass();
        $this->regions->UK->has_sales_above_threshold = false;  
        $this->regions->UK->tax_threshold = 85000;             
        $this->regions->UK->tax_all_subregions = false;        
        $this->regions->UK->subregions = new \stdClass();

        // Great Britain (England, Scotland, Wales)
        $this->regions->UK->subregions->GB = new \stdClass();
        $this->regions->UK->subregions->GB->tax_rate = 20;
        $this->regions->UK->subregions->GB->tax_name = 'VAT';
        $this->regions->UK->subregions->GB->reduced_tax_rate = 5;
        $this->regions->UK->subregions->GB->apply_tax = false;

        // Northern Ireland (special case due to NI Protocol)
        $this->regions->UK->subregions->{'GB-NIR'} = new \stdClass();
        $this->regions->UK->subregions->{'GB-NIR'}->tax_rate = 20;
        $this->regions->UK->subregions->{'GB-NIR'}->tax_name = 'VAT';
        $this->regions->UK->subregions->{'GB-NIR'}->reduced_tax_rate = 5;
        $this->regions->UK->subregions->{'GB-NIR'}->apply_tax = false;

        // Isle of Man (follows UK VAT rules)
        $this->regions->UK->subregions->{'IM'} = new \stdClass();
        $this->regions->UK->subregions->{'IM'}->tax_rate = 20;
        $this->regions->UK->subregions->{'IM'}->tax_name = 'VAT';
        $this->regions->UK->subregions->{'IM'}->reduced_tax_rate = 5;
        $this->regions->UK->subregions->{'IM'}->apply_tax = false;

        return $this;

    }
    
    /**
     * Builds the model for Australian Taxes
     *
     * @return self
     */
    private function auRegion(): self
    {
        $this->regions->AU = new \stdClass();
        $this->regions->AU->has_sales_above_threshold = false;
        $this->regions->AU->tax_all_subregions = false;
        $this->regions->AU->tax_threshold = 75000;
        $this->auSubRegions();

        return $this;
    }

    /**
     * Builds the model for Australian Subregions
     *
     * @return self
     */
    private function auSubRegions(): self
    {

        $this->regions->AU->subregions = new \stdClass();
        $this->regions->AU->subregions->AU = new \stdClass();
        $this->regions->AU->subregions->AU->apply_tax = false;
        $this->regions->AU->subregions->AU->tax_rate = 10;
        $this->regions->AU->subregions->AU->tax_name = 'GST';

        return $this;
    }

    /**
     * Builds the model for US Taxes
     *
     * @return self
     */
    private function usRegion(): self
    {
        $this->regions->US->has_sales_above_threshold = false;
        $this->regions->US->tax_all_subregions = false;
        $this->usSubRegions();

        return $this;
    }

    /**
     * Builds the model for EU Taxes
     *
     * @return self
     */
    private function euRegion(): self
    {

        $this->regions->EU->has_sales_above_threshold = false;
        $this->regions->EU->tax_all_subregions = false;
        $this->regions->EU->tax_threshold = 10000;
        $this->euSubRegions();

        return $this;
    }

    /**
     * Builds the model for US States
     *
     * @return self
     */
    private function usSubRegions(): self
    {
        $this->regions->US->subregions = new \stdClass();
        $this->regions->US->subregions->AL = new \stdClass();
        $this->regions->US->subregions->AL->apply_tax = false;
        $this->regions->US->subregions->AL->tax_rate = 4;
        $this->regions->US->subregions->AL->tax_name = 'Sales Tax';
        $this->regions->US->subregions->AK = new \stdClass();
        $this->regions->US->subregions->AK->apply_tax = false;
        $this->regions->US->subregions->AK->tax_rate = 0;
        $this->regions->US->subregions->AK->tax_name = 'Sales Tax';
        $this->regions->US->subregions->AZ = new \stdClass();
        $this->regions->US->subregions->AZ->apply_tax = false;
        $this->regions->US->subregions->AZ->tax_rate = 5.6;
        $this->regions->US->subregions->AZ->tax_name = 'Sales Tax';
        $this->regions->US->subregions->AR = new \stdClass();
        $this->regions->US->subregions->AR->apply_tax = false;
        $this->regions->US->subregions->AR->tax_rate = 6.5;
        $this->regions->US->subregions->AR->tax_name = 'Sales Tax';
        $this->regions->US->subregions->CA = new \stdClass();
        $this->regions->US->subregions->CA->apply_tax = false;
        $this->regions->US->subregions->CA->tax_rate = 7.25;
        $this->regions->US->subregions->CA->tax_name = 'Sales Tax';
        $this->regions->US->subregions->CO = new \stdClass();
        $this->regions->US->subregions->CO->apply_tax = false;
        $this->regions->US->subregions->CO->tax_rate = 2.9;
        $this->regions->US->subregions->CO->tax_name = 'Sales Tax';
        $this->regions->US->subregions->CT = new \stdClass();
        $this->regions->US->subregions->CT->apply_tax = false;
        $this->regions->US->subregions->CT->tax_rate = 6.35;
        $this->regions->US->subregions->CT->tax_name = 'Sales Tax';
        $this->regions->US->subregions->DE = new \stdClass();
        $this->regions->US->subregions->DE->apply_tax = false;
        $this->regions->US->subregions->DE->tax_rate = 0;
        $this->regions->US->subregions->DE->tax_name = 'Sales Tax';
        $this->regions->US->subregions->FL = new \stdClass();
        $this->regions->US->subregions->FL->apply_tax = false;
        $this->regions->US->subregions->FL->tax_rate = 6;
        $this->regions->US->subregions->FL->tax_name = 'Sales Tax';
        $this->regions->US->subregions->GA = new \stdClass();
        $this->regions->US->subregions->GA->apply_tax = false;
        $this->regions->US->subregions->GA->tax_rate = 4;
        $this->regions->US->subregions->GA->tax_name = 'Sales Tax';
        $this->regions->US->subregions->HI = new \stdClass();
        $this->regions->US->subregions->HI->apply_tax = false;
        $this->regions->US->subregions->HI->tax_rate = 4;
        $this->regions->US->subregions->HI->tax_name = 'Sales Tax';
        $this->regions->US->subregions->ID = new \stdClass();
        $this->regions->US->subregions->ID->apply_tax = false;
        $this->regions->US->subregions->ID->tax_rate = 6;
        $this->regions->US->subregions->ID->tax_name = 'Sales Tax';
        $this->regions->US->subregions->IL = new \stdClass();
        $this->regions->US->subregions->IL->apply_tax = false;
        $this->regions->US->subregions->IL->tax_rate = 6.25;
        $this->regions->US->subregions->IL->tax_name = 'Sales Tax';
        $this->regions->US->subregions->IN = new \stdClass();
        $this->regions->US->subregions->IN->apply_tax = false;
        $this->regions->US->subregions->IN->tax_rate = 7;
        $this->regions->US->subregions->IN->tax_name = 'Sales Tax';
        $this->regions->US->subregions->IA = new \stdClass();
        $this->regions->US->subregions->IA->apply_tax = false;
        $this->regions->US->subregions->IA->tax_rate = 6;
        $this->regions->US->subregions->IA->tax_name = 'Sales Tax';
        $this->regions->US->subregions->KS = new \stdClass();
        $this->regions->US->subregions->KS->apply_tax = false;
        $this->regions->US->subregions->KS->tax_rate = 6.5;
        $this->regions->US->subregions->KS->tax_name = 'Sales Tax';
        $this->regions->US->subregions->KY = new \stdClass();
        $this->regions->US->subregions->KY->apply_tax = false;
        $this->regions->US->subregions->KY->tax_rate = 6;
        $this->regions->US->subregions->KY->tax_name = 'Sales Tax';
        $this->regions->US->subregions->LA = new \stdClass();
        $this->regions->US->subregions->LA->apply_tax = false;
        $this->regions->US->subregions->LA->tax_rate = 4.45;
        $this->regions->US->subregions->LA->tax_name = 'Sales Tax';
        $this->regions->US->subregions->ME = new \stdClass();
        $this->regions->US->subregions->ME->apply_tax = false;
        $this->regions->US->subregions->ME->tax_rate = 5.5;
        $this->regions->US->subregions->ME->tax_name = 'Sales Tax';
        $this->regions->US->subregions->MD = new \stdClass();
        $this->regions->US->subregions->MD->apply_tax = false;
        $this->regions->US->subregions->MD->tax_rate = 6;
        $this->regions->US->subregions->MD->tax_name = 'Sales Tax';
        $this->regions->US->subregions->MA = new \stdClass();
        $this->regions->US->subregions->MA->apply_tax = false;
        $this->regions->US->subregions->MA->tax_rate = 6.25;
        $this->regions->US->subregions->MA->tax_name = 'Sales Tax';
        $this->regions->US->subregions->MI = new \stdClass();
        $this->regions->US->subregions->MI->apply_tax = false;
        $this->regions->US->subregions->MI->tax_rate = 6;
        $this->regions->US->subregions->MI->tax_name = 'Sales Tax';
        $this->regions->US->subregions->MN = new \stdClass();
        $this->regions->US->subregions->MN->apply_tax = false;
        $this->regions->US->subregions->MN->tax_rate = 6.875;
        $this->regions->US->subregions->MN->tax_name = 'Sales Tax';
        $this->regions->US->subregions->MS = new \stdClass();
        $this->regions->US->subregions->MS->apply_tax = false;
        $this->regions->US->subregions->MS->tax_rate = 7;
        $this->regions->US->subregions->MS->tax_name = 'Sales Tax';
        $this->regions->US->subregions->MO = new \stdClass();
        $this->regions->US->subregions->MO->apply_tax = false;
        $this->regions->US->subregions->MO->tax_rate = 4.225;
        $this->regions->US->subregions->MO->tax_name = 'Sales Tax';
        $this->regions->US->subregions->MT = new \stdClass();
        $this->regions->US->subregions->MT->apply_tax = false;
        $this->regions->US->subregions->MT->tax_rate = 0;
        $this->regions->US->subregions->MT->tax_name = 'Sales Tax';
        $this->regions->US->subregions->NE = new \stdClass();
        $this->regions->US->subregions->NE->apply_tax = false;
        $this->regions->US->subregions->NE->tax_rate = 5.5;
        $this->regions->US->subregions->NE->tax_name = 'Sales Tax';
        $this->regions->US->subregions->NV = new \stdClass();
        $this->regions->US->subregions->NV->apply_tax = false;
        $this->regions->US->subregions->NV->tax_rate = 6.85;
        $this->regions->US->subregions->NV->tax_name = 'Sales Tax';
        $this->regions->US->subregions->NH = new \stdClass();
        $this->regions->US->subregions->NH->apply_tax = false;
        $this->regions->US->subregions->NH->tax_rate = 0;
        $this->regions->US->subregions->NH->tax_name = 'Sales Tax';
        $this->regions->US->subregions->NJ = new \stdClass();
        $this->regions->US->subregions->NJ->apply_tax = false;
        $this->regions->US->subregions->NJ->tax_rate = 6.625;
        $this->regions->US->subregions->NJ->tax_name = 'Sales Tax';
        $this->regions->US->subregions->NM = new \stdClass();
        $this->regions->US->subregions->NM->apply_tax = false;
        $this->regions->US->subregions->NM->tax_rate = 5.125;
        $this->regions->US->subregions->NM->tax_name = 'Sales Tax';
        $this->regions->US->subregions->NY = new \stdClass();
        $this->regions->US->subregions->NY->apply_tax = false;
        $this->regions->US->subregions->NY->tax_rate = 4;
        $this->regions->US->subregions->NY->tax_name = 'Sales Tax';
        $this->regions->US->subregions->NC = new \stdClass();
        $this->regions->US->subregions->NC->apply_tax = false;
        $this->regions->US->subregions->NC->tax_rate = 4.75;
        $this->regions->US->subregions->NC->tax_name = 'Sales Tax';
        $this->regions->US->subregions->ND = new \stdClass();
        $this->regions->US->subregions->ND->apply_tax = false;
        $this->regions->US->subregions->ND->tax_rate = 5;
        $this->regions->US->subregions->ND->tax_name = 'Sales Tax';
        $this->regions->US->subregions->OH = new \stdClass();
        $this->regions->US->subregions->OH->apply_tax = false;
        $this->regions->US->subregions->OH->tax_rate = 5.75;
        $this->regions->US->subregions->OH->tax_name = 'Sales Tax';
        $this->regions->US->subregions->OK = new \stdClass();
        $this->regions->US->subregions->OK->apply_tax = false;
        $this->regions->US->subregions->OK->tax_rate = 4.5;
        $this->regions->US->subregions->OK->tax_name = 'Sales Tax';
        $this->regions->US->subregions->OR = new \stdClass();
        $this->regions->US->subregions->OR->apply_tax = false;
        $this->regions->US->subregions->OR->tax_rate = 0;
        $this->regions->US->subregions->OR->tax_name = 'Sales Tax';
        $this->regions->US->subregions->PA = new \stdClass();
        $this->regions->US->subregions->PA->apply_tax = false;
        $this->regions->US->subregions->PA->tax_rate = 6;
        $this->regions->US->subregions->PA->tax_name = 'Sales Tax';
        $this->regions->US->subregions->RI = new \stdClass();
        $this->regions->US->subregions->RI->apply_tax = false;
        $this->regions->US->subregions->RI->tax_rate = 7;
        $this->regions->US->subregions->RI->tax_name = 'Sales Tax';
        $this->regions->US->subregions->SC = new \stdClass();
        $this->regions->US->subregions->SC->apply_tax = false;
        $this->regions->US->subregions->SC->tax_rate = 6;
        $this->regions->US->subregions->SC->tax_name = 'Sales Tax';
        $this->regions->US->subregions->SD = new \stdClass();
        $this->regions->US->subregions->SD->apply_tax = false;
        $this->regions->US->subregions->SD->tax_rate = 4.5;
        $this->regions->US->subregions->SD->tax_name = 'Sales Tax';
        $this->regions->US->subregions->TN = new \stdClass();
        $this->regions->US->subregions->TN->apply_tax = false;
        $this->regions->US->subregions->TN->tax_rate = 7;
        $this->regions->US->subregions->TN->tax_name = 'Sales Tax';
        $this->regions->US->subregions->TX = new \stdClass();
        $this->regions->US->subregions->TX->apply_tax = false;
        $this->regions->US->subregions->TX->tax_rate = 6.25;
        $this->regions->US->subregions->TX->tax_name = 'Sales Tax';
        $this->regions->US->subregions->UT = new \stdClass();
        $this->regions->US->subregions->UT->apply_tax = false;
        $this->regions->US->subregions->UT->tax_rate = 5.95;
        $this->regions->US->subregions->UT->tax_name = 'Sales Tax';
        $this->regions->US->subregions->VT = new \stdClass();
        $this->regions->US->subregions->VT->apply_tax = false;
        $this->regions->US->subregions->VT->tax_rate = 6;
        $this->regions->US->subregions->VT->tax_name = 'Sales Tax';
        $this->regions->US->subregions->VA = new \stdClass();
        $this->regions->US->subregions->VA->apply_tax = false;
        $this->regions->US->subregions->VA->tax_rate = 5.3;
        $this->regions->US->subregions->VA->tax_name = 'Sales Tax';
        $this->regions->US->subregions->WA = new \stdClass();
        $this->regions->US->subregions->WA->apply_tax = false;
        $this->regions->US->subregions->WA->tax_rate = 6.5;
        $this->regions->US->subregions->WA->tax_name = 'Sales Tax';
        $this->regions->US->subregions->WV = new \stdClass();
        $this->regions->US->subregions->WV->apply_tax = false;
        $this->regions->US->subregions->WV->tax_rate = 6;
        $this->regions->US->subregions->WV->tax_name = 'Sales Tax';
        $this->regions->US->subregions->WI = new \stdClass();
        $this->regions->US->subregions->WI->apply_tax = false;
        $this->regions->US->subregions->WI->tax_rate = 5;
        $this->regions->US->subregions->WI->tax_name = 'Sales Tax';
        $this->regions->US->subregions->WY = new \stdClass();
        $this->regions->US->subregions->WY->apply_tax = false;
        $this->regions->US->subregions->WY->tax_rate = 4;
        $this->regions->US->subregions->WY->tax_name = 'Sales Tax';

        return $this;
    }

    /**
     * Create the EU member countries
     *
     * @return self
     */
    private function euSubRegions(): self
    {

        $this->regions->EU->subregions = new \stdClass();

        $this->regions->EU->subregions->AT = new \stdClass();
        $this->regions->EU->subregions->AT->tax_rate = 20;
        $this->regions->EU->subregions->AT->tax_name = 'USt';
        $this->regions->EU->subregions->AT->reduced_tax_rate = 10;
        $this->regions->EU->subregions->AT->apply_tax = false;

        $this->regions->EU->subregions->BE = new \stdClass();
        $this->regions->EU->subregions->BE->tax_rate = 21;
        $this->regions->EU->subregions->BE->tax_name = 'BTW';
        $this->regions->EU->subregions->BE->reduced_tax_rate = 6;
        $this->regions->EU->subregions->BE->apply_tax = false;

        $this->regions->EU->subregions->BG = new \stdClass();
        $this->regions->EU->subregions->BG->tax_rate = 20;
        $this->regions->EU->subregions->BG->tax_name = 'НДС';
        $this->regions->EU->subregions->BG->reduced_tax_rate = 9;
        $this->regions->EU->subregions->BG->apply_tax = false;

        $this->regions->EU->subregions->CY = new \stdClass();
        $this->regions->EU->subregions->CY->tax_rate = 19;
        $this->regions->EU->subregions->CY->tax_name = 'ΦΠΑ';
        $this->regions->EU->subregions->CY->reduced_tax_rate = 9;
        $this->regions->EU->subregions->CY->apply_tax = false;

        $this->regions->EU->subregions->CZ = new \stdClass();
        $this->regions->EU->subregions->CZ->tax_rate = 21;
        $this->regions->EU->subregions->CZ->tax_name = 'DPH';
        $this->regions->EU->subregions->CZ->reduced_tax_rate = 15;
        $this->regions->EU->subregions->CZ->apply_tax = false;

        $this->regions->EU->subregions->DE = new \stdClass();
        $this->regions->EU->subregions->DE->tax_rate = 19;
        $this->regions->EU->subregions->DE->tax_name = 'MwSt';
        $this->regions->EU->subregions->DE->reduced_tax_rate = 7;
        $this->regions->EU->subregions->DE->apply_tax = false;

        $this->regions->EU->subregions->DK = new \stdClass();
        $this->regions->EU->subregions->DK->tax_rate = 25;
        $this->regions->EU->subregions->DK->tax_name = 'Moms';
        $this->regions->EU->subregions->DK->reduced_tax_rate = 0;
        $this->regions->EU->subregions->DK->apply_tax = false;

        $this->regions->EU->subregions->EE = new \stdClass();
        $this->regions->EU->subregions->EE->tax_rate = 20;
        $this->regions->EU->subregions->EE->tax_name = 'KM';
        $this->regions->EU->subregions->EE->reduced_tax_rate = 9;
        $this->regions->EU->subregions->EE->apply_tax = false;

        $this->regions->EU->subregions->ES = new \stdClass();
        $this->regions->EU->subregions->ES->tax_rate = 21;
        $this->regions->EU->subregions->ES->tax_name = 'IVA';
        $this->regions->EU->subregions->ES->reduced_tax_rate = 10;
        $this->regions->EU->subregions->ES->apply_tax = false;

        $this->regions->EU->subregions->{'ES-CE'} = new \stdClass();
        $this->regions->EU->subregions->{'ES-CE'}->tax_rate = 4;
        $this->regions->EU->subregions->{'ES-CE'}->tax_name = 'IGIC';
        $this->regions->EU->subregions->{'ES-CE'}->reduced_tax_rate = 4;
        $this->regions->EU->subregions->{'ES-CE'}->apply_tax = false;

        $this->regions->EU->subregions->{'ES-ML'} = new \stdClass();
        $this->regions->EU->subregions->{'ES-ML'}->tax_rate = 4;
        $this->regions->EU->subregions->{'ES-ML'}->tax_name = 'IGIC';
        $this->regions->EU->subregions->{'ES-ML'}->reduced_tax_rate = 4;
        $this->regions->EU->subregions->{'ES-ML'}->apply_tax = false;

        $this->regions->EU->subregions->{'ES-CN'} = new \stdClass();
        $this->regions->EU->subregions->{'ES-CN'}->tax_rate = 7;
        $this->regions->EU->subregions->{'ES-CN'}->tax_name = 'IGIC';
        $this->regions->EU->subregions->{'ES-CN'}->reduced_tax_rate = 3;
        $this->regions->EU->subregions->{'ES-CN'}->apply_tax = false;


        $this->regions->EU->subregions->FI = new \stdClass();
        $this->regions->EU->subregions->FI->tax_rate = 24;
        $this->regions->EU->subregions->FI->tax_name = 'ALV';
        $this->regions->EU->subregions->FI->reduced_tax_rate = 14;
        $this->regions->EU->subregions->FI->apply_tax = false;

        $this->regions->EU->subregions->FR = new \stdClass();
        $this->regions->EU->subregions->FR->tax_rate = 20;
        $this->regions->EU->subregions->FR->tax_name = 'TVA';
        $this->regions->EU->subregions->FR->reduced_tax_rate = 5.5;
        $this->regions->EU->subregions->FR->apply_tax = false;

        // $this->regions->EU->subregions->GB = new \stdClass();
        // $this->regions->EU->subregions->GB->tax_rate = 20;
        // $this->regions->EU->subregions->GB->reduced_tax_rate = 0;
        // $this->regions->EU->subregions->GB->apply_tax = false;

        $this->regions->EU->subregions->GR = new \stdClass();
        $this->regions->EU->subregions->GR->tax_rate = 24;
        $this->regions->EU->subregions->GR->tax_name = 'ΦΠΑ';
        $this->regions->EU->subregions->GR->reduced_tax_rate = 13;
        $this->regions->EU->subregions->GR->apply_tax = false;

        $this->regions->EU->subregions->HR = new \stdClass();
        $this->regions->EU->subregions->HR->tax_rate = 25;
        $this->regions->EU->subregions->HR->tax_name = 'PDV';
        $this->regions->EU->subregions->HR->reduced_tax_rate = 5;
        $this->regions->EU->subregions->HR->apply_tax = false;

        $this->regions->EU->subregions->HU = new \stdClass();
        $this->regions->EU->subregions->HU->tax_rate = 27;
        $this->regions->EU->subregions->HU->tax_name = 'ÁFA';
        $this->regions->EU->subregions->HU->reduced_tax_rate = 5;
        $this->regions->EU->subregions->HU->apply_tax = false;

        $this->regions->EU->subregions->IE = new \stdClass();
        $this->regions->EU->subregions->IE->tax_rate = 23;
        $this->regions->EU->subregions->IE->tax_name = 'VAT';
        $this->regions->EU->subregions->IE->reduced_tax_rate = 0;
        $this->regions->EU->subregions->IE->apply_tax = false;
        
        $this->regions->EU->subregions->IS = new \stdClass();
        $this->regions->EU->subregions->IS->tax_rate = 24;
        $this->regions->EU->subregions->IS->tax_name = 'VSK';
        $this->regions->EU->subregions->IS->reduced_tax_rate = 11;
        $this->regions->EU->subregions->IS->apply_tax = false;

        $this->regions->EU->subregions->IT = new \stdClass();
        $this->regions->EU->subregions->IT->tax_rate = 22;
        $this->regions->EU->subregions->IT->tax_name = 'IVA';
        $this->regions->EU->subregions->IT->reduced_tax_rate = 10;
        $this->regions->EU->subregions->IT->apply_tax = false;
        
        $this->regions->EU->subregions->LI = new \stdClass();
        $this->regions->EU->subregions->LI->tax_rate = 7.7;
        $this->regions->EU->subregions->LI->tax_name = 'MWST';
        $this->regions->EU->subregions->LI->reduced_tax_rate = 2.5;
        $this->regions->EU->subregions->LI->apply_tax = false;

        $this->regions->EU->subregions->LT = new \stdClass();
        $this->regions->EU->subregions->LT->tax_rate = 21;
        $this->regions->EU->subregions->LT->tax_name = 'PVM';
        $this->regions->EU->subregions->LT->reduced_tax_rate = 9;
        $this->regions->EU->subregions->LT->apply_tax = false;

        $this->regions->EU->subregions->LU = new \stdClass();
        $this->regions->EU->subregions->LU->tax_rate = 17;
        $this->regions->EU->subregions->LU->tax_name = 'TVA';
        $this->regions->EU->subregions->LU->reduced_tax_rate = 3;
        $this->regions->EU->subregions->LU->apply_tax = false;

        $this->regions->EU->subregions->LV = new \stdClass();
        $this->regions->EU->subregions->LV->tax_rate = 21;
        $this->regions->EU->subregions->LV->tax_name = 'PVN';
        $this->regions->EU->subregions->LV->reduced_tax_rate = 12;
        $this->regions->EU->subregions->LV->apply_tax = false;

        $this->regions->EU->subregions->MT = new \stdClass();
        $this->regions->EU->subregions->MT->tax_rate = 18;
        $this->regions->EU->subregions->MT->tax_name = 'VAT';
        $this->regions->EU->subregions->MT->reduced_tax_rate = 5;
        $this->regions->EU->subregions->MT->apply_tax = false;
        
        $this->regions->EU->subregions->NO = new \stdClass();
        $this->regions->EU->subregions->NO->tax_rate = 25;
        $this->regions->EU->subregions->NO->tax_name = 'MVA';
        $this->regions->EU->subregions->NO->reduced_tax_rate = 15;
        $this->regions->EU->subregions->NO->apply_tax = false;

        $this->regions->EU->subregions->NL = new \stdClass();
        $this->regions->EU->subregions->NL->tax_rate = 21;
        $this->regions->EU->subregions->NL->tax_name = 'BTW';
        $this->regions->EU->subregions->NL->reduced_tax_rate = 9;
        $this->regions->EU->subregions->NL->apply_tax = false;

        $this->regions->EU->subregions->PL = new \stdClass();
        $this->regions->EU->subregions->PL->tax_rate = 23;
        $this->regions->EU->subregions->PL->tax_name = 'VAT';
        $this->regions->EU->subregions->PL->reduced_tax_rate = 8;
        $this->regions->EU->subregions->PL->apply_tax = false;

        $this->regions->EU->subregions->PT = new \stdClass();
        $this->regions->EU->subregions->PT->tax_rate = 23;
        $this->regions->EU->subregions->PT->tax_name = 'IVA';
        $this->regions->EU->subregions->PT->reduced_tax_rate = 6;
        $this->regions->EU->subregions->PT->apply_tax = false;

        $this->regions->EU->subregions->RO = new \stdClass();
        $this->regions->EU->subregions->RO->tax_rate = 19;
        $this->regions->EU->subregions->RO->tax_name = 'TVA';
        $this->regions->EU->subregions->RO->reduced_tax_rate = 5;
        $this->regions->EU->subregions->RO->apply_tax = false;

        $this->regions->EU->subregions->SE = new \stdClass();
        $this->regions->EU->subregions->SE->tax_rate = 25;
        $this->regions->EU->subregions->SE->tax_name = 'Moms';
        $this->regions->EU->subregions->SE->reduced_tax_rate = 12;
        $this->regions->EU->subregions->SE->apply_tax = false;

        $this->regions->EU->subregions->SI = new \stdClass();
        $this->regions->EU->subregions->SI->tax_rate = 22;
        $this->regions->EU->subregions->SI->tax_name = 'DDV';
        $this->regions->EU->subregions->SI->reduced_tax_rate = 9.5;
        $this->regions->EU->subregions->SI->apply_tax = false;

        $this->regions->EU->subregions->SK = new \stdClass();
        $this->regions->EU->subregions->SK->tax_rate = 20;
        $this->regions->EU->subregions->SK->tax_name = 'DPH';
        $this->regions->EU->subregions->SK->reduced_tax_rate = 10;
        $this->regions->EU->subregions->SK->apply_tax = false;

        return $this;

    }

    public function getSubregions(): array
    {
        $subregions = [];

        foreach ($this->regions as $region_code => $region) {
            if (!isset($region->subregions)) {
                continue;
            }

            $subregions[$region_code] = [];
            
            foreach ($region->subregions as $subregion_code => $subregion) {
                $subregions[$region_code][] = $subregion_code;
            }
        }

        return $subregions;
    }

    public function getRegionBySubregion(string $subregion_code): ?string
    {
        foreach ($this->regions as $region_code => $region) {
            if (!isset($region->subregions)) {
                continue;
            }
            
            if (isset($region->subregions->{$subregion_code})) {
                return $region_code;
            }
        }

        return null;
    }

}

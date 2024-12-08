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

namespace App\DataMapper\Referral;

class ReferralEarning
{
    /** @var string $version */
    public string $version = 'alpha';

    public string $referral_start_date = ''; // The date this referral was registered.
    
    public string $qualifies_after = ''; // The date the payout qualifies after (5 months / 1 year)
     
    public string $period_ending = ''; // The Date this set relates to. ie 2024-07-31 = July 2024

    public string $account_key = '';

    public string $payout_status = 'pending'; //pending //qualified //paidout //invalid

    public float $gross_amount = 0;

    public float $commission_amount = 0;

    public string $notes = '';
    /**
     * __construct
     *
     * @param mixed $entity
     */
    public function __construct(mixed $entity = null)
    {
        if (!$entity) {
            $this->init();
            return;
        }

        $entityArray = is_object($entity) ? get_object_vars($entity) : $entity;

        foreach ($entityArray as $key => $value) {
            $this->{$key} = $value;
        }

        $this->migrate();
    }

    public function init(): self
    {
        return $this;
    }

    private function migrate(): self
    {
        return $this;
    }
}

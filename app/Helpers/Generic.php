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

use App\Utils\Ninja;

/**
 * Simple helper function that will log into "invoiceninja.log" file
 * only when extended logging is enabled.
 *
 * @param mixed $output
 * @param array $context
 *
 * @return void
 */
function nlog($output, $context = []): void
{

    //  if (getenv('GITHUB_ACTIONS')) {
    //     // Debug level
    //     echo "::debug::".print_r($output, true)."\n";
    // }

    if (! config('ninja.expanded_logging')) {
        return;
    }

    if (gettype($output) == 'object') {
        $output = print_r($output, true);
    }

    // $trace = debug_backtrace();

    if (Ninja::isHosted()) {
        try {
            info($output);
        } catch (\Exception $e) {
        }
    } else {
        \Illuminate\Support\Facades\Log::channel('invoiceninja')->info($output, $context);
    }

    $output = null;
    $context = null;
}


function nrlog($output, $context = []): void
{
    if (! config('ninja.expanded_logging')) {
        return;
    }

    if (gettype($output) == 'object') {
        $output = print_r($output, true);
    }

    // $trace = debug_backtrace();

    if (Ninja::isHosted()) {
        try {
            info($output);
        } catch (\Exception $e) {
        }
    } else {
        \Illuminate\Support\Facades\Log::channel('invoiceninja-reminders')->info($output, $context);
    }

    $output = null;
    $context = null;
}

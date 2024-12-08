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

namespace App\Console\Commands;

use App\Jobs\Mail\NinjaMailerObject;
use App\Mail\TestMailServer;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:send-test-emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends Test Emails to check templates';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $to_user = User::first();

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new TestMailServer('Email Server Works!', config('mail.from.address'));
        $nmo->company = $to_user->account->companies()->first();
        $nmo->settings = $to_user->account->companies()->first()->settings;
        $nmo->to_user = $to_user;

        try {

            Mail::raw("Test Message", function ($message) {
                $message->to(config('mail.from.address'))
                        ->from(config('mail.from.address'), config('mail.from.name'))
                        ->subject('Test Email');
            });


        } catch (\Exception $e) {
            $this->info("Error sending email: " . $e->getMessage());
        }
    }
}

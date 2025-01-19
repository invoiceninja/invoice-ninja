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

namespace App\Jobs\Mail;

use Illuminate\Mail\Mailable;

class NinjaMailer extends Mailable
{
    public $mail_obj;

    /**
     * Create a new message instance.
     *
     * @param $mail_obj
     */
    public function __construct($mail_obj)
    {
        $this->mail_obj = $mail_obj;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {

        $from_name = config('mail.from.name');

        if (property_exists($this->mail_obj, 'from_name')) {
            $from_name = $this->mail_obj->from_name;
        }

        $ninja_mailable = $this->from(config('mail.from.address'), $from_name)
                    ->subject($this->mail_obj->subject)
                    ->view($this->mail_obj->markdown, $this->mail_obj->data);

        if (property_exists($this->mail_obj, 'text_view')) {
            $ninja_mailable->text($this->mail_obj->text_view, $this->mail_obj->data);
        }

        if (property_exists($this->mail_obj, 'bcc')) {
            $ninja_mailable->bcc($this->mail_obj->bcc);
        }

        return $ninja_mailable;
    }
}

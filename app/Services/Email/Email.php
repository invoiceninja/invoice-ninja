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

namespace App\Services\Email;

use Log;
use App\Models\User;
use App\Utils\Ninja;
use App\Models\Client;
use App\Models\Vendor;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SystemLog;
use App\Utils\HtmlEngine;
use App\Libraries\MultiDB;
use App\Models\ClientContact;
use App\Models\VendorContact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use App\Utils\VendorHtmlEngine;
use App\Libraries\Google\Google;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Postmark\Models\PostmarkException;
use Turbo124\Beacon\Facades\LightLogs;
use App\Mail\Engine\PaymentEmailEngine;
use Illuminate\Queue\InteractsWithQueue;
use GuzzleHttp\Exception\ClientException;
use App\DataMapper\Analytics\EmailFailure;
use App\DataMapper\Analytics\EmailSuccess;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Events\Invoice\InvoiceWasEmailedAndFailed;
use App\Events\Payment\PaymentWasEmailedAndFailed;

class Email implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use MakesHash;

    /** number of retry attempts to send the emails */
    public $tries = 4;

    /** skip when we cannot resolve the model */
    public $deleteWhenMissingModels = true;

    /** force send the email */
    public bool $override;

    /** PostMark api key */
    protected ?string $client_postmark_secret = null;

    /** MailGun api key */
    protected ?string $client_mailgun_secret = null;

    /** MailGun domain */
    protected ?string $client_mailgun_domain = null;

    /** MailGun endpoint */
    protected ?string $client_mailgun_endpoint = null;

    /** Brevo endpoint */
    protected ?string $client_brevo_secret = null;

    /** Default mailer */
    private string $mailer = 'default';

    /** The mailable */
    public Mailable $mailable;

    public function __construct(public EmailObject $email_object, public Company $company)
    {
    }

    /**
     * The backoff time between retries.
     *
     * @return array
     */
    public function backoff()
    {
        return [rand(5, 29), rand(30, 59), rand(61, 100), rand(180, 500)];
    }

    /**
     * Send email job.
     */
    public function handle()
    {
        MultiDB::setDb($this->company->db);

        $this->setOverride()
            ->initModels()
            ->setDefaults()
            ->buildMailable();

        /** Ensure quota's on hosted platform are respected. :) */
        $this->setMailDriver();

        /** Fail early if pre flight checks fail. */
        if ($this->preFlightChecksFail()) {
            return;
        }

        /** Send the email */
        $this->email();
        /** Perform cleanups */
        $this->tearDown();
    }

    /**
     * Sets the override flag
     *
     * @return self
     */
    public function setOverride(): self
    {
        $this->override = $this->email_object->override;

        return $this;
    }

    /**
     * Initilializes the models
     *
     * @return self
     */
    public function initModels(): self
    {
        $this->email_object->entity_id ? $this->email_object->entity = $this->email_object->entity_class::withTrashed()->with('invitations')->find($this->email_object->entity_id) : $this->email_object->entity = null;

        $this->email_object->invitation_id ? $this->email_object->invitation = $this->email_object->entity->invitations()->where('id', $this->email_object->invitation_id)->first() : $this->email_object->invitation = null;

        $this->email_object->invitation_id ? $this->email_object->contact = $this->email_object->invitation->contact : $this->email_object->contact = null;

        $this->email_object->client_id ? $this->email_object->client = Client::withTrashed()->find($this->email_object->client_id) : $this->email_object->client = null;

        $this->email_object->vendor_id ? $this->email_object->vendor = Vendor::withTrashed()->find($this->email_object->vendor_id) : $this->email_object->vendor = null;

        if (!$this->email_object->contact) {
            $this->email_object->vendor_contact_id ? $this->email_object->contact = VendorContact::withTrashed()->find($this->email_object->vendor_contact_id) : null;

            $this->email_object->client_contact_id ? $this->email_object->contact = ClientContact::withTrashed()->find($this->email_object->client_contact_id) : null;
        }

        $this->email_object->user_id ? $this->email_object->user = User::withTrashed()->find($this->email_object->user_id) : $this->email_object->user = $this->company->owner();

        $this->email_object->company_key = $this->company->company_key;

        $this->email_object->company = $this->company;

        $this->email_object->client_id ? $this->email_object->settings = $this->email_object->client->getMergedSettings() : $this->email_object->settings = $this->company->settings;

        $this->email_object->whitelabel = $this->company->account->isPaid() ? true : false;

        $this->email_object->logo = $this->email_object->settings->company_logo;

        $this->email_object->signature = $this->email_object->settings->email_signature;

        $this->email_object->invitation_key = $this->email_object->invitation ? $this->email_object->invitation->key : null;

        $this->resolveVariables();

        return $this;
    }

    /**
     * Generates the correct set of variables
     *
     * @return self
     */
    private function resolveVariables(): self
    {
        $_variables = $this->email_object->variables;

        match (class_basename($this->email_object->entity)) {
            "Invoice" => $this->email_object->variables = (new HtmlEngine($this->email_object->invitation))->makeValues(),
            "Quote" => $this->email_object->variables = (new HtmlEngine($this->email_object->invitation))->makeValues(),
            "Credit" => $this->email_object->variables = (new HtmlEngine($this->email_object->invitation))->makeValues(),
            "PurchaseOrder" => $this->email_object->variables = (new VendorHtmlEngine($this->email_object->invitation))->makeValues(),
            "Payment" => $this->email_object->variables = (new PaymentEmailEngine($this->email_object->entity, $this->email_object->contact))->makePaymentVariables(),
            default => $this->email_object->variables = []
        };

        /** If we have passed some variable overrides we insert them here */
        foreach ($_variables as $key => $value) {
            $this->email_object->variables[$key] = $value;
        }

        return $this;
    }

    /**
     * Tear Down
     *
     * @return self
     */
    private function tearDown(): self
    {
        $this->email_object->entity = null;
        $this->email_object->invitation = null;
        $this->email_object->client = null;
        $this->email_object->vendor = null;
        $this->email_object->user = null;
        $this->email_object->contact = null;
        $this->email_object->settings = null;

        return $this;
    }

    /**
     * Builds the email defaults,
     * sets any missing props.
     *
     * @return self
     */
    public function setDefaults(): self
    {
        (new EmailDefaults($this))->run();

        return $this;
    }

    /**
     * Populates the mailable
     *
     * @return self
     */
    public function buildMailable(): self
    {
        $this->mailable = new EmailMailable($this->email_object);

        return $this;
    }

    private function incrementEmailCounter(): void
    {
        if (in_array($this->email_object->settings->email_sending_method, ['default','mailgun','postmark'])) {
            Cache::increment("email_quota".$this->company->account->key);
        }
    }

    /**
     * Attempts to send the email
     *
     * @return void
     */
    public function email()
    {

        /* Init the mailer*/
        $mailer = Mail::mailer($this->mailer);


        /* Additional configuration if using a client third party mailer */
        if ($this->client_postmark_secret) {
            $mailer->postmark_config($this->client_postmark_secret);
        }

        if ($this->client_mailgun_secret) {
            $mailer->mailgun_config($this->client_mailgun_secret, $this->client_mailgun_domain, $this->client_mailgun_endpoint);
        }

        if ($this->client_brevo_secret) {
            $mailer->brevo_config($this->client_brevo_secret);
        }

        /* Attempt the send! */
        try {
            nlog("Using mailer => " . $this->mailer . " " . now()->toDateTimeString());

            $mailer->send($this->mailable);

            $this->incrementEmailCounter();

            LightLogs::create(new EmailSuccess($this->company->company_key, $this->mailable->subject))
                ->send();

        } catch (\Symfony\Component\Mailer\Exception\TransportException $e) {
            nlog("Mailer failed with a Transport Exception {$e->getMessage()}");

            if (Ninja::isHosted() && $this->mailer == 'smtp') {
                $settings = $this->email_object->settings;
                $settings->email_sending_method = 'default';
                $this->company->settings = $settings;
                $this->company->save();
            }

            if (stripos($e->getMessage(), 'code 406') !== false) {

                $address_object = reset($this->email_object->to);

                $email = $address_object->address ?? '';

                $message = "Recipient {$email} has been suppressed and cannot receive emails from you.";

                $this->fail();
                $this->logMailError($message, $this->company->clients()->first());
                $this->cleanUpMailers();

                $this->entityEmailFailed($message);

                return;
            }

            $this->fail();
            $this->cleanUpMailers();
            $this->logMailError($e->getMessage(), $this->company->clients()->first());

        } catch (\Symfony\Component\Mime\Exception\RfcComplianceException $e) {
            nlog("Mailer failed with a Logic Exception {$e->getMessage()}");
            $this->fail();
            $this->cleanUpMailers();
            $this->logMailError($e->getMessage(), $this->company->clients()->first());
            return;
        } catch (\Symfony\Component\Mime\Exception\LogicException $e) {
            nlog("Mailer failed with a Logic Exception {$e->getMessage()}");
            $this->fail();
            $this->cleanUpMailers();
            $this->logMailError($e->getMessage(), $this->company->clients()->first());
            return;
        } catch (\Google\Service\Exception $e) {

            if ($e->getCode() == '429') {

                $message = "Google rate limiting triggered, we are queueing based on Gmail requirements.";
                $this->logMailError($message, $this->company->clients()->first());
                sleep(rand(1, 2));
                $this->release(900);
                $message = null;
            }

        } catch (\Exception | \RuntimeException $e) {
            nlog("Mailer failed with {$e->getMessage()}");
            $message = $e->getMessage();


            if (stripos($e->getMessage(), 'code 300') !== false || stripos($e->getMessage(), 'code 413') !== false) {
                $message = "Either Attachment too large, or recipient has been suppressed.";

                $this->fail();
                $this->logMailError($e->getMessage(), $this->company->clients()->first());
                $this->cleanUpMailers();

                $this->entityEmailFailed($message);

                return;
            }

            if (stripos($e->getMessage(), 'Dsn') !== false) {

                nlog("Incorrectly configured mail server - setting to default mail driver.");
                $this->email_object->settings->email_sending_method = 'default';
                return $this->setMailDriver();

            }

           

            /**
             * Post mark buries the proper message in a guzzle response
             * this merges a text string with a json object
             * need to harvest the ->Message property using the following
             */
            if ($e instanceof PostmarkException) { //postmark specific failure

                $this->fail();
                $this->entityEmailFailed($e->getMessage());
                $this->cleanUpMailers();

                return;
            }

            //only report once, not on all tries
            if ($this->attempts() == $this->tries) {
                /* If the is an entity attached to the message send a failure mailer */
                $this->entityEmailFailed($message);

                app('sentry')->captureException($e);

            }

            $this->tearDown();

            /* Releasing immediately does not add in the backoff */
            sleep(rand(0, 3));

            $this->release($this->backoff()[$this->attempts() - 1]);

            $message = null;
        }

        $this->cleanUpMailers();
    }

    /**
     * On the hosted platform we scan all outbound email for
     * spam. This sequence processes the filters we use on all
     * emails.
     *
     * @return bool
     */
    public function preFlightChecksFail(): bool
    {
        /* Always send if disabled */
        if ($this->override) {
            return false;
        }

        /* If we are migrating data we don't want to fire any emails */
        if ($this->company->is_disabled) {
            return true;
        }

        if (Ninja::isSelfHost()) {
            return false;
        }

        /* To handle spam users we drop all emails from flagged accounts */
        if ($this->company->account && $this->company->account->is_flagged) {
            return true;
        }

        /* On the hosted platform we set default contacts a @example.com email address - we shouldn't send emails to these types of addresses */
        if ($this->hasInValidEmails()) {
            return true;
        }

        /* GMail users are uncapped */
        if (in_array($this->email_object->settings->email_sending_method, ['gmail', 'office365', 'client_postmark', 'client_mailgun', 'client_brevo'])) {
            return false;
        }

        /* On the hosted platform, if the user is over the email quotas, we do not send the email. */
        if ($this->company->account && $this->company->account->emailQuotaExceeded()) {
            return true;
        }

        /* If the account is verified, we allow emails to flow */
        if ($this->company->account && $this->company->account->is_verified_account) {
            //11-01-2022

            /* Continue to analyse verified accounts in case they later start sending poor quality emails*/
            // if(class_exists(\Modules\Admin\Jobs\Account\EmailQuality::class))
            //     (new \Modules\Admin\Jobs\Account\EmailQuality($this->nmo, $this->company))->run();

            return false;
        }

        /* On the hosted platform if the user has not verified their account we fail here - but still check what they are trying to send! */
        if ($this->company->account && !$this->company->account->account_sms_verified) {
            if (class_exists(\Modules\Admin\Jobs\Account\EmailFilter::class)) {
                (new \Modules\Admin\Jobs\Account\EmailFilter($this->email_object, $this->company))->run();
            }

            return true;
        }

        /* On the hosted platform we actively scan all outbound emails to ensure outbound email quality remains high */
        if (class_exists(\Modules\Admin\Jobs\Account\EmailFilter::class)) {
            return (new \Modules\Admin\Jobs\Account\EmailFilter($this->email_object, $this->company))->run();
        }

        return false;
    }

    /**
     * hasInValidEmails
     *
     * @return bool
     */
    private function hasInValidEmails(): bool
    {
        foreach ($this->email_object->to as $address_object) {
            if (stripos($address_object->address, '@example.') !== false) {
                return true;
            }

            if (!str_contains($address_object->address, "@")) {
                return true;
            }

            if ($address_object->address == " ") {
                return true;
            }

            if ($address_object->address == "") {
                return true;
            }

            if ($address_object->name == " " || $address_object->name == "") {
                return true;
            }
        }

        return false;
    }

    private function setHostedMailgunMailer()
    {

        if (property_exists($this->email_object->settings, 'email_from_name') && strlen($this->email_object->settings->email_from_name) > 1) {
            $email_from_name = $this->email_object->settings->email_from_name;
        } else {
            $email_from_name = $this->company->present()->name();
        }

        $this->mailable
            ->from(config('services.mailgun.from.address'), $email_from_name);

    }


    /**
     * Sets the mail driver to use and applies any specific configuration
     * the the mailable
     */
    private function setMailDriver(): self
    {

        /** Force free/trials onto specific mail driver */
        if ($this->email_object->settings->email_sending_method == 'default' && $this->company->account->isNewHostedAccount()) {
            $this->mailer = 'mailgun';
            $this->setHostedMailgunMailer();
            return $this;
        }

        if (Ninja::isHosted() && $this->company->account->isPaid() && $this->email_object->settings->email_sending_method == 'default') {

            try {

                $address_object = reset($this->email_object->to);
                $email = $address_object->address ?? '';
                $domain = explode("@", $email)[1] ?? "";
                $dns = dns_get_record($domain, DNS_MX);
                $server = $dns[0]["target"];
                if (stripos($server, "outlook.com") !== false) {

                    if (property_exists($this->email_object->settings, 'email_from_name') && strlen($this->email_object->settings->email_from_name) > 1) {
                        $email_from_name = $this->email_object->settings->email_from_name;
                    } else {
                        $email_from_name = $this->company->present()->name();
                    }

                    $this->mailer = 'postmark';
                    $this->client_postmark_secret = config('services.postmark-outlook.token');
                    $this->mailable
                        ->from(config('services.postmark-outlook.from.address'), $email_from_name);

                    return $this;

                }
            } catch (\Exception $e) {
                nlog("problem switching outlook driver - hosted");
                nlog($e->getMessage());
            }
        }

        switch ($this->email_object->settings->email_sending_method) {
            case 'default':
                $this->mailer = config('mail.default');
                // $this->setHostedMailgunMailer(); //should only be activated if hosted platform needs to fall back to mailgun
                return $this;
            case 'mailgun':
                $this->mailer = 'mailgun';
                $this->setHostedMailgunMailer();
                return $this;
            case 'gmail':
                $this->mailer = 'gmail';
                $this->setGmailMailer();
                return $this;
            case 'office365':
            case 'microsoft':
                $this->mailer = 'office365';
                $this->setOfficeMailer();
                return $this;
            case 'client_postmark':
                $this->mailer = 'postmark';
                $this->setPostmarkMailer();
                return $this;
            case 'client_mailgun':
                $this->mailer = 'mailgun';
                $this->setMailgunMailer();
                return $this;
            case 'client_brevo':
                $this->mailer = 'brevo';
                $this->setBrevoMailer();
                return $this;
            case 'smtp':
                $this->mailer = 'smtp';
                $this->configureSmtpMailer();
                return $this;
            default:                
                $this->mailer = config('mail.default');
                break;

        }
        
        $this->mailer = config('mail.default');
        
        return $this;

    }

    private function configureSmtpMailer()
    {

        $company = $this->company;

        $smtp_host = $company->smtp_host ?? '';
        $smtp_port = (int)$company->smtp_port ?? 0; //@phpstan-ignore-line
        $smtp_username = $company->smtp_username ?? '';
        $smtp_password = $company->smtp_password ?? '';
        $smtp_encryption = $company->smtp_encryption ?? 'tls';
        $smtp_local_domain = strlen($company->smtp_local_domain ?? '') > 2 ? $company->smtp_local_domain : null;
        $smtp_verify_peer = $company->smtp_verify_peer ?? true;

        if (strlen($smtp_host) <= 1 ||
        strlen($smtp_username) <= 1 ||
        strlen($smtp_password) <= 1
        ) {
            $this->email_object->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        config([
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'host' => $smtp_host,
                'port' => (int)$smtp_port,
                'username' => $smtp_username,
                'password' => $smtp_password,
                'encryption' => $smtp_encryption,
                'local_domain' => $smtp_local_domain,
                'verify_peer' => $smtp_verify_peer,
                'timeout' => 30,
            ],
        ]);

        $user = $this->resolveSendingUser();

        $sending_email = (isset($this->email_object->settings->custom_sending_email) && (stripos($this->email_object->settings->custom_sending_email, "@")) !== false) ? $this->email_object->settings->custom_sending_email : $user->email;
        $sending_user = (isset($this->email_object->settings->email_from_name) && strlen($this->email_object->settings->email_from_name) > 2) ? $this->email_object->settings->email_from_name : $user->name();

        $this->mailable
            ->from($sending_email, $sending_user);

    }

    /**
     * Ensure we discard any data that is not required
     *
     * @return void
     */
    private function cleanUpMailers(): void
    {
        $this->client_postmark_secret = null;

        $this->client_mailgun_secret = null;

        $this->client_mailgun_domain = null;

        $this->client_mailgun_endpoint = null;

        $this->client_brevo_secret = null;

        //always dump the drivers to prevent reuse
        app('mail.manager')->forgetMailers();
    }


    /**
     * Check to ensure no cross account
     * emails can be sent.
     *
     * @param User $user
     */
    private function checkValidSendingUser($user)
    {
        /* Always ensure the user is set on the correct account */
        if (!$user || ($user->account_id != $this->company->account_id)) {
            $this->email_object->settings->email_sending_method = 'default';

            return $this->setMailDriver();
        }
    }

    /**
     * Resolves the sending user
     * when configuring the Mailer
     * on behalf of the client
     *
     * @return User $user
     */
    private function resolveSendingUser(): ?User
    {
        $sending_user = $this->email_object->settings->gmail_sending_user_id;

        if ($sending_user == "0") {
            $user = $this->company->owner();
        } else {
            $user = User::find($this->decodePrimaryKey($sending_user));
        }

        return $user;
    }
    /**
     * Configures Mailgun using client supplied secret
     * as the Mailer
     */
    private function setMailgunMailer()
    {
        if (strlen($this->email_object->settings->mailgun_secret) > 2 && strlen($this->email_object->settings->mailgun_domain) > 2) {
            $this->client_mailgun_secret = $this->email_object->settings->mailgun_secret;
            $this->client_mailgun_domain = $this->email_object->settings->mailgun_domain;
            $this->client_mailgun_endpoint = $this->email_object->settings->mailgun_endpoint;

        } else {
            $this->email_object->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        $user = $this->resolveSendingUser();

        $sending_email = (isset($this->email_object->settings->custom_sending_email) && stripos($this->email_object->settings->custom_sending_email, "@")) ? $this->email_object->settings->custom_sending_email : $user->email;
        $sending_user = (isset($this->email_object->settings->email_from_name) && strlen($this->email_object->settings->email_from_name) > 2) ? $this->email_object->settings->email_from_name : $user->name();

        $this->mailable
            ->from($sending_email, $sending_user);
    }
    /**
     * Configures Brevo using client supplied secret
     * as the Mailer
     */
    private function setBrevoMailer()
    {
        if (strlen($this->email_object->settings->brevo_secret) > 2) {
            $this->client_brevo_secret = $this->email_object->settings->brevo_secret;

        } else {
            $this->email_object->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        $user = $this->resolveSendingUser();

        $sending_email = (isset($this->email_object->settings->custom_sending_email) && stripos($this->email_object->settings->custom_sending_email, "@")) ? $this->email_object->settings->custom_sending_email : $user->email;
        $sending_user = (isset($this->email_object->settings->email_from_name) && strlen($this->email_object->settings->email_from_name) > 2) ? $this->email_object->settings->email_from_name : $user->name();

        $this->mailable
            ->from($sending_email, $sending_user);
    }

    /**
     * Configures Postmark using client supplied secret
     * as the Mailer
     */
    private function setPostmarkMailer()
    {
        if (strlen($this->email_object->settings->postmark_secret) > 2) {
            $this->client_postmark_secret = $this->email_object->settings->postmark_secret;
        } else {
            $this->email_object->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        $user = $this->resolveSendingUser();

        $sending_email = (isset($this->email_object->settings->custom_sending_email) && stripos($this->email_object->settings->custom_sending_email, "@")) ? $this->email_object->settings->custom_sending_email : $user->email;
        $sending_user = (isset($this->email_object->settings->email_from_name) && strlen($this->email_object->settings->email_from_name) > 2) ? $this->email_object->settings->email_from_name : $user->name();

        $this->mailable
            ->from($sending_email, $sending_user);
    }

    /**
     * Configures Microsoft via Oauth
     * as the Mailer
     */
    private function setOfficeMailer()
    {
        $user = $this->resolveSendingUser();

        $this->checkValidSendingUser($user);

        nlog("Sending via {$user->name()}");

        $token = $this->refreshOfficeToken($user);

        if ($token) {
            $user->oauth_user_token = $token;
            $user->save();
        } else {
            $this->email_object->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        $this->mailable
            ->from($user->email, $user->name())
            ->withSymfonyMessage(function ($message) use ($token) {
                $message->getHeaders()->addTextHeader('gmailtoken', $token);
            });
    }

    /**
     * Configures GMail via Oauth
     * as the Mailer
     */
    private function setGmailMailer()
    {
        $user = $this->resolveSendingUser();

        $this->checkValidSendingUser($user);

        nlog("Sending via {$user->name()}");

        $google = (new Google())->init();

        try {
            if ($google->getClient()->isAccessTokenExpired()) {
                $google->refreshToken($user);
                $user = $user->fresh();
            }

            $google->getClient()->setAccessToken(json_encode($user->oauth_user_token));
        } catch (\Exception $e) {
            $this->logMailError('Gmail Token Invalid', $this->company->clients()->first());
            $this->email_object->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        /**
         * If the user doesn't have a valid token, notify them
         */

        if (!$user->oauth_user_token) {
            $this->company->account->gmailCredentialNotification();
            $this->email_object->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        /*
         *  Now that our token is refreshed and valid we can boot the
         *  mail driver at runtime and also set the token which will persist
         *  just for this request.
         */

        $token = $user->oauth_user_token->access_token;

        if (!$token) {
            $this->company->account->gmailCredentialNotification();
            $this->email_object->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        $this->mailable
            ->from($user->email, $user->name())
            ->withSymfonyMessage(function ($message) use ($token) {
                $message->getHeaders()->addTextHeader('gmailtoken', $token);
            });
    }

    /**
     * Logs any errors to the SystemLog
     *
     * @param  string $errors
     * @param  null | \App\Models\Client $recipient_object
     * @return void
     */
    private function logMailError($errors, $recipient_object): void
    {
        (
            new SystemLogger(
                $errors,
                SystemLog::CATEGORY_MAIL,
                SystemLog::EVENT_MAIL_SEND,
                SystemLog::TYPE_FAILURE,
                $recipient_object,
                $this->company
            )
        )->handle();

        $job_failure = new EmailFailure($this->company->company_key);
        $job_failure->string_metric5 = 'failed_email';
        $job_failure->string_metric6 = substr($errors, 0, 150);

        LightLogs::create($job_failure)
            ->send();

        $job_failure = null;
    }

    /**
     * Attempts to refresh the Microsoft refreshToken
     *
     * @param \App\Models\User $user
     * @return mixed
     */
    private function refreshOfficeToken(User $user): mixed
    {
        $expiry = $user->oauth_user_token_expiry ?: now()->subDay();
        $token = false;

        if ($expiry->lt(now())) {
            $guzzle = new \GuzzleHttp\Client();
            $url = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';

            try {
                $token = json_decode($guzzle->post($url, [
                    'form_params' => [
                        'client_id' => config('ninja.o365.client_id'),
                        'client_secret' => config('ninja.o365.client_secret'),
                        'scope' => 'email Mail.Send offline_access profile User.Read openid',
                        'grant_type' => 'refresh_token',
                        'refresh_token' => $user->oauth_user_refresh_token
                    ],
                ])->getBody()->getContents());
            } catch (\Exception $e) {
                nlog("Problem getting new Microsoft token for User: {$user->email}");
            }

            if ($token) {
                $user->oauth_user_refresh_token = property_exists($token, 'refresh_token') ? $token->refresh_token : $user->oauth_user_refresh_token;
                $user->oauth_user_token = $token->access_token;
                $user->oauth_user_token_expiry = now()->addSeconds($token->expires_in);
                $user->save();

                return $token->access_token;
            }

            return false;
        }

        return $user->oauth_user_token;
    }

    /**
     * Entity notification when an email fails to send
     *
     * @param  string $message
     * @return void
     */
    private function entityEmailFailed(string $message = ''): void
    {
        $class = get_class($this->email_object->entity);

        switch ($class) {
            case Invoice::class:
                event(new InvoiceWasEmailedAndFailed($this->email_object->invitation, $this->company, $message, $this->email_object->html_template, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
                break;
            case Payment::class:
                event(new PaymentWasEmailedAndFailed($this->email_object->entity, $this->company, $message, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
                break;
            default:
                # code...
                break;
        }

        if ($this->email_object->client) {
            $this->logMailError($message, $this->email_object->client);
        }
    }


    public function failed($exception = null)
    {
        if ($exception) {
            nlog($exception->getMessage());
        }

        config(['queue.failed.driver' => null]);
    }
}

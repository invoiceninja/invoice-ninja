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

use App\Models\User;
use App\Utils\Ninja;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SystemLog;
use App\Libraries\MultiDB;
use App\Models\ClientContact;
use Illuminate\Bus\Queueable;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use App\Libraries\Google\Google;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Postmark\Models\PostmarkException;
use Turbo124\Beacon\Facades\LightLogs;
use Illuminate\Queue\InteractsWithQueue;
use GuzzleHttp\Exception\ClientException;
use App\DataMapper\Analytics\EmailFailure;
use App\DataMapper\Analytics\EmailSuccess;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Events\Invoice\InvoiceWasEmailedAndFailed;
use App\Events\Payment\PaymentWasEmailedAndFailed;

/*Multi Mailer implemented*/

class NinjaMailerJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use MakesHash;

    public $tries = 4; //number of retries
    
    public $deleteWhenMissingModels = true;

    /** @var null|\App\Models\Company $company  **/
    public ?Company $company;

    private $mailer;

    protected $client_postmark_secret = false;

    protected $client_mailgun_secret = false;

    protected $client_mailgun_domain = false;

    protected $client_brevo_secret = false;

    public function __construct(public ?NinjaMailerObject $nmo, public bool $override = false)
    {
    }

    public function backoff()
    {
        return [rand(5, 29), rand(30, 59), rand(61, 100), rand(180, 500)];
    }

    public function handle()
    {
        /*Set the correct database*/
        MultiDB::setDb($this->nmo->company->db);

        /* Serializing models from other jobs wipes the primary key */
        $this->company = Company::query()->where('company_key', $this->nmo->company->company_key)->first();

        /* Set the email driver */
        $this->setMailDriver();

        /* If any pre conditions fail, we return early here */
        if (!$this->company || $this->preFlightChecksFail()) {
            return;
        }
        /* Run time we set Reply To Email*/
        if (strlen($this->nmo->settings->reply_to_email) > 1) {
            if (property_exists($this->nmo->settings, 'reply_to_name')) {
                $reply_to_name = strlen($this->nmo->settings->reply_to_name) > 3 ? $this->nmo->settings->reply_to_name : $this->nmo->settings->reply_to_email;
            } else {
                $reply_to_name = $this->nmo->settings->reply_to_email;
            }

            $this->nmo->mailable->replyTo($this->nmo->settings->reply_to_email, $reply_to_name);
        } elseif (isset($this->nmo->invitation->user)) {
            $this->nmo->mailable->replyTo($this->nmo->invitation->user->email, $this->nmo->invitation->user->present()->name());
        } else {
            $this->nmo->mailable->replyTo($this->company->owner()->email, $this->company->owner()->present()->name());
        }


        /* Run time we set the email tag */
        $this->nmo->mailable->tag($this->company->company_key);

        /* If we have an invitation present, we pass the invitation key into the email headers*/
        if ($this->nmo->invitation) {
            $this->nmo
                ->mailable
                ->withSymfonyMessage(function ($message) {
                    $message->getHeaders()->addTextHeader('x-invitation', $this->nmo->invitation->key);
                    // $message->getHeaders()->addTextHeader('List-Unsubscribe', $this->nmo->mailable->viewData->email_preferences);
                });
        }

        //send email
        try {
            nlog("Trying to send to {$this->nmo->to_user->email} " . now()->toDateTimeString());
            nlog("Using mailer => " . $this->mailer);

            $mailer = Mail::mailer($this->mailer);

            if ($this->client_postmark_secret) {
                $mailer->postmark_config($this->client_postmark_secret);
            }

            if ($this->client_mailgun_secret) {
                $mailer->mailgun_config($this->client_mailgun_secret, $this->client_mailgun_domain, $this->nmo->settings->mailgun_endpoint);
            }

            if ($this->client_brevo_secret) {
                $mailer->brevo_config($this->client_brevo_secret);
            }

            $mailable = $this->nmo->mailable;

            /** May need to re-build it here @todo explain why we need this? */
            if (Ninja::isHosted() && method_exists($mailable, 'build') && $this->nmo->settings->email_style != "custom") {
                $mailable->build();
            }

            $mailer
                ->to($this->nmo->to_user->email)
                ->send($mailable);

            /* Count the amount of emails sent across all the users accounts */

            $this->incrementEmailCounter();

            LightLogs::create(new EmailSuccess($this->nmo->company->company_key, $this->nmo->mailable->subject))
                ->send();

        } catch(\Symfony\Component\Mailer\Exception\TransportException $e){
            nlog("Mailer failed with a Transport Exception {$e->getMessage()}");
            
            if(Ninja::isHosted() && $this->mailer == 'smtp'){
                $settings = $this->nmo->settings;
                $settings->email_sending_method = 'default';
                $this->company->settings = $settings;
                $this->company->save();
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
        } catch(\Google\Service\Exception $e) {

            if ($e->getCode() == '429') {

                $message = "Google rate limiting triggered, we are queueing based on Gmail requirements.";
                $this->logMailError($message, $this->company->clients()->first());
                sleep(rand(1, 2));
                $this->release(900);

            }

        } catch (\Exception $e) {
            nlog("Mailer failed with {$e->getMessage()}");
            $message = $e->getMessage();

            /**
             * Post mark buries the proper message in a a guzzle response
             * this merges a text string with a json object
             * need to harvest the ->Message property using the following
             */
            if (stripos($e->getMessage(), 'code 300') !== false || stripos($e->getMessage(), 'code 413') !== false) {
                $message = "Either Attachment too large, or recipient has been suppressed.";

                $this->fail();
                $this->logMailError($e->getMessage(), $this->company->clients()->first());

                if ($this->nmo->entity) {
                    $this->entityEmailFailed($message);
                }

                $this->cleanUpMailers();

                return;
            }

            if(stripos($e->getMessage(), 'Dsn') !== false) {

                nlog("Incorrectly configured mail server - setting to default mail driver.");
                $this->nmo->settings->email_sending_method = 'default';
                return $this->setMailDriver();

            }

            if (stripos($e->getMessage(), 'code 406') !== false) {

                $email = $this->nmo->to_user->email ?? '';

                $message = "Recipient {$email} has been suppressed and cannot receive emails from you.";

                $this->fail();
                $this->logMailError($message, $this->company->clients()->first());

                if ($this->nmo->entity) {
                    $this->entityEmailFailed($message);
                }

                $this->cleanUpMailers();

                return;
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
                /* If there is an entity attached to the message send a failure mailer */
                if ($this->nmo->entity) {
                    $this->entityEmailFailed($message);
                }

                app('sentry')->captureException($e);

            }

            /* Releasing immediately does not add in the backoff */
            sleep(rand(2, 3));
            $this->release($this->backoff()[$this->attempts() - 1]);
        }

        $this->nmo = null;
        $this->company = null;

        /*Clean up mailers*/
        $this->cleanUpMailers();
    }

    private function incrementEmailCounter(): void
    {
        if(in_array($this->nmo->settings->email_sending_method, ['default','mailgun','postmark'])) {
            Cache::increment("email_quota".$this->company->account->key);
        }

    }
    /**
     * Entity notification when an email fails to send
     *
     * @param  string $message
     * @return void
     */
    private function entityEmailFailed($message): void
    {
        $class = get_class($this->nmo->entity);

        switch ($class) {
            case Invoice::class:
                event(new InvoiceWasEmailedAndFailed($this->nmo->invitation, $this->nmo->company, $message, $this->nmo->template, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
                break;
            case Payment::class:
                event(new PaymentWasEmailedAndFailed($this->nmo->entity, $this->nmo->company, $message, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
                break;
            default:
                # code...
                break;
        }

        if ($this->nmo->to_user instanceof ClientContact) {
            $this->logMailError($message, $this->nmo->to_user->client);
        }
    }

    /**
     * Initializes the configured Mailer
     */
    private function setMailDriver()
    {
        /* Singletons need to be rebooted each time just in case our Locale is changing*/
        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->nmo->settings));

        /** Force free/trials onto specific mail driver */

        if($this->nmo->settings->email_sending_method == 'default' && $this->company->account->isNewHostedAccount()) {
            $this->mailer = 'mailgun';
            $this->setHostedMailgunMailer();
            return $this;
        }


        if (Ninja::isHosted() && $this->company->account->isPaid() && $this->nmo->settings->email_sending_method == 'default') {
            //check if outlook.

            try {
                $email = $this->nmo->to_user->email;
                $domain = explode("@", $email)[1] ?? "";
                $dns = dns_get_record($domain, DNS_MX);
                $server = $dns[0]["target"];
                if (stripos($server, "outlook.com") !== false) {

                    $this->mailer = 'postmark';
                    $this->client_postmark_secret = config('services.postmark-outlook.token');

                    if (property_exists($this->nmo->settings, 'email_from_name') && strlen($this->nmo->settings->email_from_name) > 1) {
                        $email_from_name = $this->nmo->settings->email_from_name;
                    } else {
                        $email_from_name = $this->company->present()->name();
                    }

                    $this->nmo
                        ->mailable
                        ->from(config('services.postmark-outlook.from.address'), $email_from_name);

                    return $this;
                }
            } catch (\Exception $e) {

                nlog("problem switching outlook driver - hosted");
                nlog($e->getMessage());
            }
        }

        switch ($this->nmo->settings->email_sending_method) {
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
                break;
        }

        if (Ninja::isSelfHost()) {
            $this->setSelfHostMultiMailer();
        }

        return $this;
    }

    private function configureSmtpMailer()
    {

        $company = $this->company;

        $smtp_host = $company->smtp_host ?? '';
        $smtp_port = $company->smtp_port ?? 0;
        $smtp_username = $company->smtp_username ?? '';
        $smtp_password = $company->smtp_password ?? '';
        $smtp_encryption = $company->smtp_encryption ?? 'tls';
        $smtp_local_domain = strlen($company->smtp_local_domain ?? '') > 2 ? $company->smtp_local_domain : null;
        $smtp_verify_peer = $company->smtp_verify_peer ?? true;

        if(strlen($smtp_host) <= 1 ||
        strlen($smtp_username) <= 1 ||
        strlen($smtp_password) <= 1
        ) {
            $this->nmo->settings->email_sending_method = 'default';
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

        if (property_exists($this->nmo->settings, 'email_from_name') && strlen($this->nmo->settings->email_from_name) > 1) {
            $email_from_name = $this->nmo->settings->email_from_name;
        } else {
            $email_from_name = $this->company->present()->name();
        }

        $user = $this->resolveSendingUser();
        $sending_email = (isset($this->nmo->settings->custom_sending_email) && stripos($this->nmo->settings->custom_sending_email, "@")) ? $this->nmo->settings->custom_sending_email : $user->email;

        $this->nmo
            ->mailable
            ->from($sending_email, $email_from_name);

    }

    /**
     * Allows configuration of multiple mailers
     * per company for use by self hosted users
     */
    private function setSelfHostMultiMailer(): void
    {
        if (env($this->company->id . '_MAIL_HOST')) {
            config([
                'mail.mailers.smtp' => [
                    'transport' => 'smtp',
                    'host' => env($this->company->id . '_MAIL_HOST'),
                    'port' => env($this->company->id . '_MAIL_PORT'),
                    'username' => env($this->company->id . '_MAIL_USERNAME'),
                    'password' => env($this->company->id . '_MAIL_PASSWORD'),
                ],
            ]);

            if (env($this->company->id . '_MAIL_FROM_ADDRESS')) {
                $this->nmo
                    ->mailable
                    ->from(env($this->company->id . '_MAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')), env($this->company->id . '_MAIL_FROM_NAME', env('MAIL_FROM_NAME')));
            }
        }
    }

    /**
     * Ensure we discard any data that is not required
     *
     * @return void
     */
    private function cleanUpMailers(): void
    {
        $this->client_postmark_secret = false;

        $this->client_mailgun_secret = false;

        $this->client_mailgun_domain = false;

        $this->client_brevo_secret = false;

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
        if ($user->account_id != $this->company->account_id) {
            $this->nmo->settings->email_sending_method = 'default';
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
        $sending_user = $this->nmo->settings->gmail_sending_user_id;

        if ($sending_user == "0") {
            $user = $this->company->owner();
        } else {
            $user = User::find($this->decodePrimaryKey($sending_user));
        }

        return $user;
    }

    private function setHostedMailgunMailer()
    {

        if (property_exists($this->nmo->settings, 'email_from_name') && strlen($this->nmo->settings->email_from_name) > 1) {
            $email_from_name = $this->nmo->settings->email_from_name;
        } else {
            $email_from_name = $this->company->present()->name();
        }

        $this->nmo
            ->mailable
            ->from(config('services.mailgun.from.address'), $email_from_name);

    }

    /**
     * Configures Mailgun using client supplied secret
     * as the Mailer
     */
    private function setMailgunMailer()
    {
        if (strlen($this->nmo->settings->mailgun_secret) > 2 && strlen($this->nmo->settings->mailgun_domain) > 2) {
            $this->client_mailgun_secret = $this->nmo->settings->mailgun_secret;
            $this->client_mailgun_domain = $this->nmo->settings->mailgun_domain;
        } else {
            $this->nmo->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        $user = $this->resolveSendingUser();

        $sending_email = (isset($this->nmo->settings->custom_sending_email) && stripos($this->nmo->settings->custom_sending_email, "@")) ? $this->nmo->settings->custom_sending_email : $user->email;
        $sending_user = (isset($this->nmo->settings->email_from_name) && strlen($this->nmo->settings->email_from_name) > 2) ? $this->nmo->settings->email_from_name : $user->name();

        $this->nmo
            ->mailable
            ->from($sending_email, $sending_user);
    }

    /**
     * Configures Brevo using client supplied secret
     * as the Mailer
     */
    private function setBrevoMailer()
    {
        if (strlen($this->nmo->settings->brevo_secret) > 2) {
            $this->client_brevo_secret = $this->nmo->settings->brevo_secret;
        } else {
            $this->nmo->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        $user = $this->resolveSendingUser();

        $sending_email = (isset($this->nmo->settings->custom_sending_email) && stripos($this->nmo->settings->custom_sending_email, "@")) ? $this->nmo->settings->custom_sending_email : $user->email;
        $sending_user = (isset($this->nmo->settings->email_from_name) && strlen($this->nmo->settings->email_from_name) > 2) ? $this->nmo->settings->email_from_name : $user->name();

        $this->nmo
            ->mailable
            ->from($sending_email, $sending_user);
    }

    /**
     * Configures Postmark using client supplied secret
     * as the Mailer
     */
    private function setPostmarkMailer()
    {
        if (strlen($this->nmo->settings->postmark_secret) > 2) {
            $this->client_postmark_secret = $this->nmo->settings->postmark_secret;
        } else {
            $this->nmo->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        $user = $this->resolveSendingUser();

        $sending_email = (isset($this->nmo->settings->custom_sending_email) && stripos($this->nmo->settings->custom_sending_email, "@")) ? $this->nmo->settings->custom_sending_email : $user->email;
        $sending_user = (isset($this->nmo->settings->email_from_name) && strlen($this->nmo->settings->email_from_name) > 2) ? $this->nmo->settings->email_from_name : $user->name();

        $this->nmo
            ->mailable
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
            $this->nmo->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        $this->nmo
            ->mailable
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
            $this->nmo->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        /**
         * If the user doesn't have a valid token, notify them
         */

        if (!$user->oauth_user_token) {
            $this->company->account->gmailCredentialNotification();
            $this->nmo->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        /*
         *  Now that our token is refreshed and valid we can boot the
         *  mail driver at runtime and also set the token which will persist
         *  just for this request.
         */

        $token = $user->oauth_user_token->access_token; /** @phpstan-ignore-line */

        if (!$token) {
            $this->company->account->gmailCredentialNotification();
            $this->nmo->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        $this->nmo
            ->mailable
            ->from($user->email, $user->name())
            ->withSymfonyMessage(function ($message) use ($token) {
                $message->getHeaders()->addTextHeader('gmailtoken', $token);
            });
    }

    /**
     * On the hosted platform we scan all outbound email for
     * spam. This sequence processes the filters we use on all
     * emails.
     *
     * @return bool
     */
    private function preFlightChecksFail(): bool
    {
        /* Always send regardless */
        if ($this->override) {
            return false;
        }

        /* If we are migrating data we don't want to fire any emails */
        if ($this->company->is_disabled) {
            return true;
        }

        /* To handle spam users we drop all emails from flagged accounts */
        if (Ninja::isHosted() && $this->company->account && $this->company->account->is_flagged) {
            return true;
        }

        /* On the hosted platform we set default contacts a @example.com email address - we shouldn't send emails to these types of addresses */
        if (Ninja::isHosted() && $this->nmo->to_user && strpos($this->nmo->to_user->email, '@example.com') !== false) {
            return true;
        }

        /* GMail users are uncapped */
        if (Ninja::isHosted() && (in_array($this->nmo->settings->email_sending_method, ['gmail', 'office365', 'client_postmark', 'client_mailgun', 'client_brevo']))) {
            return false;
        }

        /* On the hosted platform, if the user is over the email quotas, we do not send the email. */
        if (Ninja::isHosted() && $this->company->account && $this->company->account->emailQuotaExceeded()) {
            return true;
        }

        /* If the account is verified, we allow emails to flow */
        if (Ninja::isHosted() && $this->company->account && $this->company->account->is_verified_account) {
            return false;
        }

        /* Ensure the user has a valid email address */
        if (!str_contains($this->nmo->to_user->email, "@")) {
            return true;
        }

        /* On the hosted platform if the user has not verified their account we fail here - but still check what they are trying to send! */
        if (Ninja::isHosted() && $this->company->account && !$this->company->account->account_sms_verified) {
            if (class_exists(\Modules\Admin\Jobs\Account\EmailQuality::class)) {
                (new \Modules\Admin\Jobs\Account\EmailQuality($this->nmo, $this->company))->run();
            }
            return true;
        }

        /* On the hosted platform we actively scan all outbound emails to ensure outbound email quality remains high */
        if (class_exists(\Modules\Admin\Jobs\Account\EmailQuality::class)) {
            return (new \Modules\Admin\Jobs\Account\EmailQuality($this->nmo, $this->company))->run();
        }

        return false;
    }

    /**
     * Logs any errors to the SystemLog
     *
     * @param  string $errors
     * @param  \App\Models\User | \App\Models\Client | null $recipient_object
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
                $this->nmo->company
            )
        )->handle();

        $job_failure = new EmailFailure($this->nmo->company->company_key);
        $job_failure->string_metric5 = 'failed_email';
        $job_failure->string_metric6 = substr($errors, 0, 150);

        LightLogs::create($job_failure)
            ->send();

        $job_failure = null;
    }

    /**
     * Attempts to refresh the Microsoft refreshToken
     *
     * @param  \App\Models\User $user
     * @return mixed
     */
    private function refreshOfficeToken(User $user)
    {
        $expiry = $user->oauth_user_token_expiry ?: now()->subDay();
        $token = false;

        if ($expiry->lt(now())) {
            $guzzle = new \GuzzleHttp\Client();
            $url = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';

            if (!$user->oauth_user_refresh_token || $user->oauth_user_refresh_token == '') {
                return false;
            }

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
            } catch(\Exception $e) {
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

    public function failed($exception = null)
    {
        if ($exception) {
            nlog($exception->getMessage());
        }

        config(['queue.failed.driver' => null]);
    }
}

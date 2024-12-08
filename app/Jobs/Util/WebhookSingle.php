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

namespace App\Jobs\Util;

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\SystemLog;
use App\Models\Webhook;
use App\Transformers\ArraySerializer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\RequestOptions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;

class WebhookSingle implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private $entity;

    private string $db;

    private int $subscription_id;

    public $tries = 5; //number of retries

    public $deleteWhenMissingModels = true;

    private string $includes;

    private Company $company;

    /**
     * Create a new job instance.
     *
     * @param $event_id
     * @param $entity
     */
    public function __construct($subscription_id, $entity, $db, $includes = '')
    {
        $this->entity = $entity;
        $this->db = $db;
        $this->includes = $includes;
        $this->subscription_id = $subscription_id;
    }

    public function backoff()
    {
        return [rand(10, 15), rand(30, 40), rand(60, 79), rand(160, 200), rand(3000, 5000)];
    }

    /**
     * Execute the job.
     *
     */
    public function handle()
    {
        MultiDB::setDb($this->db);

        $subscription = Webhook::query()->with('company')->find($this->subscription_id);

        if (!$subscription) {
            $this->fail();
            nlog("failed to fire event, could not find webhook ID {$this->subscription_id}");
            return;
        }

        $this->company = $subscription->company;

        $this->entity->refresh();

        // generate JSON data
        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $manager->parseIncludes($this->includes);

        $class = sprintf('App\\Transformers\\%sTransformer', class_basename($this->entity));

        $transformer = new $class();

        $resource = new Item($this->entity, $transformer, $this->entity->getEntityType());
        $data = $manager->createData($resource)->toArray();

        $headers = is_array($subscription->headers) ? $subscription->headers : [];

        $this->postData($subscription, $data, $headers);
    }

    private function postData($subscription, $data, $headers = [])
    {
        $base_headers = [
            'Content-Length' => strlen(json_encode($data)),
            'Accept'         => 'application/json',
        ];

        $client = new Client(['headers' => array_merge($base_headers, $headers)]);

        try {
            $verb = $subscription->rest_method ?? 'post';

            $response = $client->{$verb}($subscription->target_url, [
                RequestOptions::JSON => $data, // or 'json' => [...]
            ]);

            (new SystemLogger(
                ['message' => $response->getHeaders(), 'body' => $data],
                SystemLog::CATEGORY_WEBHOOK,
                SystemLog::EVENT_WEBHOOK_SUCCESS,
                SystemLog::TYPE_WEBHOOK_RESPONSE,
                $this->resolveClient(),
                $this->company
            ))->handle();
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            nlog("connection problem");
            nlog($e->getCode());
            nlog($e->getMessage());

            (new SystemLogger(
                ['message' => "Error connecting to ". $subscription->target_url, 'body' => $data],
                SystemLog::CATEGORY_WEBHOOK,
                SystemLog::EVENT_WEBHOOK_FAILURE,
                SystemLog::TYPE_WEBHOOK_RESPONSE,
                $this->resolveClient(),
                $this->company
            ))->handle();
        } catch (BadResponseException $e) {
            if ($e->getResponse()->getStatusCode() >= 400 && $e->getResponse()->getStatusCode() < 500) {

                /* Some 400's should never be repeated */
                if (in_array($e->getResponse()->getStatusCode(), [404, 410, 405])) {

                    $message = "There was a problem when connecting to {$subscription->target_url} => status code ". $e->getResponse()->getStatusCode(). " This webhook call will be suspended until further action is taken.";

                    (new SystemLogger(
                        ['message' => $message, 'body' => $data],
                        SystemLog::CATEGORY_WEBHOOK,
                        SystemLog::EVENT_WEBHOOK_FAILURE,
                        SystemLog::TYPE_WEBHOOK_RESPONSE,
                        $this->resolveClient(),
                        $this->company
                    ))->handle();

                    $subscription->delete();
                    $this->fail();
                    return;
                }

                $message = "There was a problem when connecting to {$subscription->target_url} => status code ". $e->getResponse()->getStatusCode();

                nlog($message);

                (new SystemLogger(
                    ['message' => $message, 'body' => $data],
                    SystemLog::CATEGORY_WEBHOOK,
                    SystemLog::EVENT_WEBHOOK_FAILURE,
                    SystemLog::TYPE_WEBHOOK_RESPONSE,
                    $this->resolveClient(),
                    $this->company
                ))->handle();

                if (in_array($e->getResponse()->getStatusCode(), [400])) {
                    $this->fail();
                    return;
                }

                $this->release($this->backoff()[$this->attempts() - 1]);
            }

            if ($e->getResponse()->getStatusCode() >= 500) {
                nlog("{$subscription->target_url} returned a 500, failing");

                $message = "There was a problem when connecting to {$subscription->target_url} => status code ". $e->getResponse()->getStatusCode(). " no retry attempted.";

                (new SystemLogger(
                    ['message' => $message, 'body' => $data],
                    SystemLog::CATEGORY_WEBHOOK,
                    SystemLog::EVENT_WEBHOOK_FAILURE,
                    SystemLog::TYPE_WEBHOOK_RESPONSE,
                    $this->resolveClient(),
                    $this->company
                ))->handle();

                $this->fail();
                return;
            }
        } catch (ServerException $e) {
            nlog("Server exception");
            $error = json_decode($e->getResponse()->getBody()->getContents());

            (new SystemLogger(
                ['message' => $error, 'body' => $data],
                SystemLog::CATEGORY_WEBHOOK,
                SystemLog::EVENT_WEBHOOK_FAILURE,
                SystemLog::TYPE_WEBHOOK_RESPONSE,
                $this->resolveClient(),
                $this->company
            ))->handle();
        } catch (ClientException $e) {
            nlog("Client exception");
            $error = json_decode($e->getResponse()->getBody()->getContents());

            (new SystemLogger(
                ['message' => $error, 'body' => $data],
                SystemLog::CATEGORY_WEBHOOK,
                SystemLog::EVENT_WEBHOOK_FAILURE,
                SystemLog::TYPE_WEBHOOK_RESPONSE,
                $this->resolveClient(),
                $this->company
            ))->handle();
        } catch (\Exception $e) {
            nlog("Exception handler => " . $e->getMessage());
            nlog($e->getCode());

            (new SystemLogger(
                ['message' => $e->getMessage(), 'body' => $data],
                SystemLog::CATEGORY_WEBHOOK,
                SystemLog::EVENT_WEBHOOK_FAILURE,
                SystemLog::TYPE_WEBHOOK_RESPONSE,
                $this->resolveClient(),
                $this->company,
            ))->handle();

            //add some entropy to the retry
            sleep(rand(0, 3));

            $this->release($this->backoff()[$this->attempts() - 1]);
        }
    }

    private function resolveClient()
    {
        //make sure it isn't an instance of the Client Model
        if (!$this->entity instanceof \App\Models\Client &&
            !$this->entity instanceof \App\Models\Vendor &&
            !$this->entity instanceof \App\Models\Product &&
            !$this->entity instanceof \App\Models\PurchaseOrder &&
            $this->entity->client()->exists()) {
            return $this->entity->client;
        }

        return null;
    }

    public function failed($exception = null)
    {
        config(['queue.failed.driver' => null]);
    }
}

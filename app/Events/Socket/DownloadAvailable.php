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

namespace App\Events\Socket;

use App\Models\User;
use League\Fractal\Manager;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use App\Utils\Traits\Invoice\Broadcasting\DefaultResourceBroadcast;

/**
 * Class DownloadAvailable.
 */
class DownloadAvailable implements ShouldBroadcast
{
    use SerializesModels;
    use InteractsWithSockets;

    public function __construct(public string $url, public string $message, public User $user)
    {
    }

    public function broadcastOn()
    {
        return [
            new PrivateChannel("user-{$this->user->account->key}-{$this->user->id}"),
        ];
    }

    public function broadcastWith(): array
    {

        ctrans('texts.document_download_subject');

        return [
            'message' => $this->message,
            'url' => $this->url,
        ];
    }

}

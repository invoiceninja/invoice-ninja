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

namespace App\Events\General;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class GenericMessage implements ShouldBroadcast
{
    use SerializesModels;

    public const CHANNEL_HOSTED = 'general_hosted';

    public const CHANNEL_SELFHOSTED = 'general_selfhosted';

    public function __construct(
        public string $message,
        public ?string $link = null,
        public ?array $channels = [self::CHANNEL_HOSTED, self::CHANNEL_SELFHOSTED],
    ) {
        //
    }

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];

        if (\in_array(self::CHANNEL_HOSTED, $this->channels)) {
            $channels[] = new Channel(self::CHANNEL_HOSTED);
        }

        if (\in_array(self::CHANNEL_SELFHOSTED, $this->channels)) {
            $channels[] = new Channel(self::CHANNEL_SELFHOSTED);
        }

        return $channels;
    }
}

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

namespace App\DataMapper;

use Illuminate\Contracts\Database\Eloquent\Castable;
use App\Casts\QuickbooksSettingsCast;

/**
 * QuickbooksSettings.
 */
class QuickbooksSettings implements Castable
{
    public string $accessTokenKey;

    public string $refresh_token;

    public string $realmID;

    public int $accessTokenExpiresAt;

    public int $refreshTokenExpiresAt;

    public string $baseURL;

    public QuickbooksSync $settings;

    public function __construct(array $attributes = [])
    {
        $this->accessTokenKey = $attributes['accessTokenKey'] ?? '';
        $this->refresh_token = $attributes['refresh_token'] ?? '';
        $this->realmID = $attributes['realmID'] ?? '';
        $this->accessTokenExpiresAt = $attributes['accessTokenExpiresAt'] ?? 0;
        $this->refreshTokenExpiresAt = $attributes['refreshTokenExpiresAt'] ?? 0;
        $this->baseURL = $attributes['baseURL'] ?? '';
        $this->settings = new QuickbooksSync($attributes['settings'] ?? []);
    }

    public static function castUsing(array $arguments): string
    {
        return QuickbooksSettingsCast::class;
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

}

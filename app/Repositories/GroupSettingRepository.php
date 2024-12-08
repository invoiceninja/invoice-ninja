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

namespace App\Repositories;

use App\Models\Client;
use App\Models\GroupSetting;

class GroupSettingRepository extends BaseRepository
{
    public function save($data, GroupSetting $group_setting): ?GroupSetting
    {

        if (isset($data['settings']['translations'])) {
            unset($data['settings']['translations']);
        }

        if (isset($data['settings']['pdf_variables'])) {
            unset($data['settings']['pdf_variables']);
        }

        $group_setting->fill($data);
        $group_setting->save();

        if (array_key_exists('company_logo', $data) && $data['company_logo'] == '') {
            $settings = $group_setting->settings;
            unset($settings->company_logo);
            $group_setting->settings = $settings;
        }

        if (! array_key_exists('settings', $data) || count((array) $data['settings']) == 0) {
            $settings = new \stdClass();
            $settings->entity = Client::class;
            $group_setting->settings = $settings;
        }

        $group_setting->save();

        return $group_setting;
    }
}

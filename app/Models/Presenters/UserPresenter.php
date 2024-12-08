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

namespace App\Models\Presenters;

/**
 * Class UserPresenter.
 */
class UserPresenter extends EntityPresenter
{
    /**
     * Returns the first and last names concatenated.
     *
     * @return string
     */
    public function name(): string
    {
        if (! $this->entity) {
            return 'No User Object Available';
        }

        $first_name = isset($this->entity->first_name) ? $this->entity->first_name : '';
        $last_name = isset($this->entity->last_name) ? $this->entity->last_name : '';

        return $first_name.' '.$last_name;
    }

    /**
     * Returns a full name (with fallback) of the user
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        if ($this->getFullName()) {
            return $this->getFullName();
        } elseif ($this->entity->email) {
            return $this->entity->email;
        } else {
            return ctrans('texts.guest');
        }
    }

    /**
     * Returns the full name of the user
     * @return string
     */
    public function getFullName()
    {
        if ($this->entity->first_name || $this->entity->last_name) {
            return $this->entity->first_name.' '.$this->entity->last_name;
        } else {
            return '';
        }
    }

    /**
     * Returns the first name of the user
     *
     * @return string
     */
    public function firstName(): string
    {
        if (! $this->entity) {
            return 'No First Name Available';
        }

        return $this->entity->first_name ?? ' ';

    }

    /**
     * Returns the last name of the user
     *
     * @return string
     */
    public function lastName(): string
    {
        if (! $this->entity) {
            return 'No Last Name Available';
        }

        return $this->entity->last_name ?? 'Last Name';
    }

    public function phone(): string
    {
        return $this->entity->phone ?? ' ';
    }

    public function email(): string
    {
        return $this->entity->email ?? ' ';
    }
}

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

namespace App\Http\Requests\Preview;

use App\Http\Requests\Request;
use App\Models\Client;
use App\Models\Credit;
use App\Models\CreditInvitation;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\Quote;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceInvitation;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class PreviewInvoiceRequest extends Request
{
    use MakesHash;
    use CleanLineItems;

    private string $entity_plural = '';

    private ?Client $client = null;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->hasIntersectPermissionsOrAdmin(['view_invoice', 'view_quote', 'view_recurring_invoice', 'view_credit', 'create_invoice', 'create_quote', 'create_recurring_invoice', 'create_credit','edit_invoice', 'edit_quote', 'edit_recurring_invoice', 'edit_credit']);
    }

    public function rules()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return [
            'number' => 'nullable',
            'entity' => 'bail|sometimes|in:invoice,quote,credit,recurring_invoice',
            'entity_id' => ['bail','sometimes','integer',Rule::exists($this->entity_plural, 'id')->where('is_deleted', 0)->where('company_id', $user->company()->id)],
            'client_id' => ['required', Rule::exists(Client::class, 'id')->where('is_deleted', 0)->where('company_id', $user->company()->id)],
        ];

    }

    public function prepareForValidation()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];
        $input['amount'] = 0;
        $input['balance'] = 0;
        $input['number'] = isset($input['number']) ? $input['number'] : ctrans('texts.live_preview').' #'.rand(0, 1000);

        if ($input['entity_id'] ?? false) {
            $input['entity_id'] = $this->decodePrimaryKey($input['entity_id'], true);
        }

        $this->convertEntityPlural($input['entity'] ?? 'invoice');

        $this->replace($input);
    }

    public function resolveInvitation()
    {
        $invitation = false;

        /** @phpstan-ignore-next-line */
        if (! $this->entity_id ?? false) {
            return $this->stubInvitation();
        }

        match($this->entity) {
            'invoice' => $invitation = InvoiceInvitation::withTrashed()->where('invoice_id', $this->entity_id)->first(),
            'quote' => $invitation = QuoteInvitation::withTrashed()->where('quote_id', $this->entity_id)->first(),
            'credit' => $invitation = CreditInvitation::withTrashed()->where('credit_id', $this->entity_id)->first(),
            'recurring_invoice' => $invitation = RecurringInvoiceInvitation::withTrashed()->where('recurring_invoice_id', $this->entity_id)->first(),
            default => $invitation = false,
        };

        if ($invitation) {
            return $invitation;
        }

        return $this->stubInvitation();
    }

    public function getClient(): ?Client
    {
        if (!$this->client) {
            $this->client = Client::query()->with('contacts', 'company', 'user')->withTrashed()->find($this->client_id);
        }

        return $this->client;
    }

    public function setClient(Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function stubInvitation()
    {
        $client = Client::query()->with('contacts', 'company', 'user')->withTrashed()->find($this->client_id);
        $this->setClient($client);
        $invitation = false;

        match($this->entity) {
            'invoice' => $invitation = InvoiceInvitation::factory()->make(),
            'quote' => $invitation = QuoteInvitation::factory()->make(),
            'credit' => $invitation = CreditInvitation::factory()->make(),
            'recurring_invoice' => $invitation = RecurringInvoiceInvitation::factory()->make(),
            default => $invitation = InvoiceInvitation::factory()->make(),
        };

        $entity = $this->stubEntity($client);

        $invitation->make();
        $invitation->setRelation($this->entity, $entity);
        $invitation->setRelation('contact', $client->contacts->first()->load('client.company'));
        $invitation->setRelation('company', $client->company);

        return $invitation;
    }

    private function stubEntity(Client $client)
    {
        $entity = false;

        match($this->entity) {
            'invoice' => $entity = Invoice::factory()->make(['client_id' => $client->id,'user_id' => $client->user_id, 'company_id' => $client->company_id]),
            'quote' => $entity = Quote::factory()->make(['client_id' => $client->id,'user_id' => $client->user_id, 'company_id' => $client->company_id]),
            'credit' => $entity = Credit::factory()->make(['client_id' => $client->id,'user_id' => $client->user_id, 'company_id' => $client->company_id]),
            'recurring_invoice' => $entity = RecurringInvoice::factory()->make(['client_id' => $client->id,'user_id' => $client->user_id, 'company_id' => $client->company_id]),
            default => $entity = Invoice::factory()->make(['client_id' => $client->id,'user_id' => $client->user_id, 'company_id' => $client->company_id]),
        };

        $entity->setRelation('client', $client);
        $entity->setRelation('company', $client->company);
        $entity->setRelation('user', $client->user);
        $entity->fill($this->all());

        return $entity;
    }

    private function convertEntityPlural(string $entity): self
    {
        switch ($entity) {
            case 'invoice':
                $this->entity_plural = 'invoices';
                return $this;
            case 'quote':
                $this->entity_plural = 'quotes';
                return $this;
            case 'credit':
                $this->entity_plural = 'credits';
                return $this;
            case 'recurring_invoice':
                $this->entity_plural = 'recurring_invoices';
                return $this;
            default:
                $this->entity_plural = 'invoices';
                return $this;
        }
    }

}

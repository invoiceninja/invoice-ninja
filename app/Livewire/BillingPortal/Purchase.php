<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Livewire\BillingPortal;

use App\Utils\Ninja;
use Livewire\Component;
use App\Libraries\MultiDB;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use App\Models\Subscription;
use App\Utils\Traits\MakesHash;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\App;
use App\Livewire\BillingPortal\Cart\Cart;
use App\Livewire\BillingPortal\Payments\Methods;
use App\Livewire\BillingPortal\Authentication\Login;
use App\Livewire\BillingPortal\Authentication\Register;
use App\Livewire\BillingPortal\Authentication\RegisterOrLogin;

class Purchase extends Component
{
    use MakesHash;

    public string $subscription_id;

    public string $db;

    public array $request_data;

    public string $hash;

    public ?string $campaign;

    public int $step = 0;

    public string $id;

    public static array $dependencies = [
        Login::class => [
            'id' => 'auth.login',
            'dependencies' => [],
        ],
        RegisterOrLogin::class => [
            'id' => 'auth.login-or-register',
            'dependencies' => [],
        ],
        Register::class => [
            'id' => 'auth.register',
            'dependencies' => [],
        ],
        Cart::class => [
            'id' => 'cart',
            'dependencies' => [],
        ],
    ];

    public array $steps = [];

    public array $context = [];

    #[On('purchase.context')]
    public function handleContext(string $property, $value): self
    {
        $clone = $this->context;

        data_set($this->context, $property, $value);

        if ($clone !== $this->context) {
            $this->id = Str::uuid();
        }

        return $this;
    }

    #[On('purchase.next')]
    public function handleNext(): void
    {
        if (count($this->steps) >= 1 && $this->step < count($this->steps) - 1) {
            $this->step++;
            $this->id = Str::uuid();
        }
    }

    #[On('purchase.forward')]
    public function handleForward(string $component): void
    {
        $this->step = array_search($component, $this->steps);

        $this->id = Str::uuid();
    }

    #[Computed()]
    public function component(): string
    {
        return $this->steps[$this->step];
    }

    #[Computed()]
    public function componentUniqueId(): string
    {
        return "purchase-{$this->id}";
    }

    #[Computed()]
    public function summaryUniqueId(): string
    {
        return "summary-{$this->id}";
    }

    #[Computed()]
    public function subscription()
    {
        return Subscription::find($this->decodePrimaryKey($this->subscription_id))?->withoutRelations()?->makeHidden(['webhook_configuration','steps']);
    }

    public static function defaultSteps()
    {
        return [
            Cart::class,
            RegisterOrLogin::class,
        ];
    }

    public function mount()
    {
        $classes = collect(self::$dependencies)->mapWithKeys(fn ($dependency, $class) => [$dependency['id'] => $class])->toArray();

        MultiDB::setDb($this->db);

        $sub = Subscription::find($this->decodePrimaryKey($this->subscription_id));


        if(!$sub){


            session()->flash('title', __('texts.subscription_unavailable'));
            session()->flash('notification', '');

            return redirect()->route('client.error');

        }
        
        if ($sub->steps) {
            $steps = collect(explode(',', $sub->steps))
                ->map(fn ($step) => $classes[$step])
                ->toArray();
            $this->steps = [
                Setup::class,
                ...$steps,
                Methods::class,
                RFF::class,
                Submit::class,
            ];
        } else {
            $this->steps = [
                Setup::class,
                ...self::defaultSteps(),
                Methods::class,
                RFF::class,
                Submit::class,
            ];
        }

        $this->id = Str::uuid();

        $this
            ->handleContext('hash', $this->hash)
            ->handleContext('quantity', 1)
            ->handleContext('request_data', $this->request_data)
            ->handleContext('campaign', $this->campaign)
            ->handleContext('subcription_id', $this->subscription_id);
    }

    public function render()
    {
        return view('billing-portal.v3.purchase');
    }
}

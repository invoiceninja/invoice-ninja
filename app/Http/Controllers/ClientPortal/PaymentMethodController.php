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

namespace App\Http\Controllers\ClientPortal;

use App\Events\Payment\Methods\MethodDeleted;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\PaymentMethod\CreatePaymentMethodRequest;
use App\Http\Requests\Request;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Utils\Ninja;
use App\Utils\Traits\MakesDates;
use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentMethodController extends Controller
{
    use MakesDates;

    public function __construct()
    {
        $this->middleware('throttle:10,1')->only('store');
    }

    /**
     * Display a listing of the resource.
     *
     * @return Factory|View
     */
    public function index()
    {
        return $this->render('payment_methods.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreatePaymentMethodRequest $request
     * @return \Illuminate\View\View
     */
    public function create(CreatePaymentMethodRequest $request)
    {
        $gateway = $this->getClientGateway();

        $data['gateway'] = $gateway;

        /** @var \App\Models\ClientContact auth()->guard('contact')->user() **/
        $client_contact = auth()->guard('contact')->user();
        $data['client'] = $client_contact->client;

        return $gateway
            ->driver($client_contact->client)
            ->setPaymentMethod($request->query('method'))
            ->checkRequirements()
            ->authorizeView($data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function store(Request $request)
    {
        $gateway = $this->getClientGateway();

        /** @var \App\Models\ClientContact auth()->guard('contact')->user() **/
        $client_contact = auth()->guard('contact')->user();

        return $gateway
            ->driver($client_contact->client)
            ->setPaymentMethod($request->query('method'))
            ->checkRequirements()
            ->authorizeResponse($request);
    }

    /**
     * Display the specified resource.
     *
     * @param ClientGatewayToken $payment_method
     * @return Factory|View
     */
    public function show(ClientGatewayToken $payment_method)
    {
        return $this->render('payment_methods.show', [
            'payment_method' => $payment_method,
        ]);
    }

    public function verify(ClientGatewayToken $payment_method)
    {

        /** @var \App\Models\ClientContact auth()->guard('contact')->user() **/
        $client_contact = auth()->guard('contact')->user();

        return $payment_method->gateway
            ->driver($client_contact->client)
            ->setPaymentMethod(request()->query('method'))
            ->verificationView($payment_method);
    }

    public function processVerification(Request $request, ClientGatewaytoken $payment_method)
    {
        /** @var \App\Models\ClientContact auth()->guard('contact')->user() **/
        $client_contact = auth()->guard('contact')->user();

        return $payment_method->gateway
            ->driver($client_contact->client)
            ->setPaymentMethod(request()->query('method'))
            ->processVerification($request, $payment_method);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param ClientGatewayToken $payment_method
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(ClientGatewayToken $payment_method)
    {
        /** @var \App\Models\ClientContact auth()->guard('contact')->user() **/
        $client_contact = auth()->guard('contact')->user();

        if ($payment_method->gateway()->exists()) {
            $payment_method->gateway
                ->driver($client_contact->client)
                ->setPaymentMethod(request()->query('method'))
                ->detach($payment_method);
        }

        try {
            event(new MethodDeleted($payment_method, auth()->guard('contact')->user()->company, Ninja::eventVars(auth()->guard('contact')->user()->id)));

            $payment_method->is_deleted = true;
            $payment_method->is_default = false;
            $payment_method->delete();
            $payment_method->save();


            $def_cgt = auth()->guard('contact')->user()->client->gateway_tokens()->orderBy('id', 'desc')->first();

            if ($def_cgt) {
                $def_cgt->is_default = true;
                $def_cgt->save();
            }

        } catch (Exception $e) {
            nlog($e->getMessage());

            return back();
        }

        return redirect()
            ->route('client.payment_methods.index')
            ->withSuccess(ctrans('texts.payment_method_removed'));
    }

    private function getClientGateway()
    {
        /** @var \App\Models\ClientContact auth()->guard('contact')->user() **/
        $client_contact = auth()->guard('contact')->user();

        if (request()->query('method') == GatewayType::CREDIT_CARD) {
            return $client_contact->client->getCreditCardGateway();
        }
        if (request()->query('method') == GatewayType::BACS) {
            return $client_contact->client->getBACSGateway();
        }

        if (in_array(request()->query('method'), [GatewayType::BANK_TRANSFER, GatewayType::DIRECT_DEBIT, GatewayType::SEPA, GatewayType::ACSS])) {
            return $client_contact->client->getBankTransferGateway();
        }

        abort(404, 'Gateway not found.');
    }
}

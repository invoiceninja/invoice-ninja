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

namespace App\Http\Controllers\VendorPortal;

use App\Utils\Ninja;
use App\Models\Webhook;
use Illuminate\View\View;
use App\Models\PurchaseOrder;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesDates;
use App\Jobs\Entity\CreateRawPdf;
use App\Jobs\Util\WebhookHandler;
use App\Http\Controllers\Controller;
use App\Jobs\Invoice\InjectSignature;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\View\Factory;
use App\Models\PurchaseOrderInvitation;
use App\Events\Misc\InvitationWasViewed;
use App\Events\PurchaseOrder\PurchaseOrderWasViewed;
use App\Events\PurchaseOrder\PurchaseOrderWasAccepted;
use App\Http\Requests\VendorPortal\PurchaseOrders\ShowPurchaseOrderRequest;
use App\Http\Requests\VendorPortal\PurchaseOrders\ShowPurchaseOrdersRequest;
use App\Http\Requests\VendorPortal\PurchaseOrders\ProcessPurchaseOrdersInBulkRequest;

class PurchaseOrderController extends Controller
{
    use MakesHash;
    use MakesDates;

    public const MODULE_RECURRING_INVOICES = 1;

    public const MODULE_CREDITS = 2;

    public const MODULE_QUOTES = 4;

    public const MODULE_TASKS = 8;

    public const MODULE_EXPENSES = 16;

    public const MODULE_PROJECTS = 32;

    public const MODULE_VENDORS = 64;

    public const MODULE_TICKETS = 128;

    public const MODULE_PROPOSALS = 256;

    public const MODULE_RECURRING_EXPENSES = 512;

    public const MODULE_RECURRING_TASKS = 1024;

    public const MODULE_RECURRING_QUOTES = 2048;

    public const MODULE_INVOICES = 4096;

    public const MODULE_PROFORMAL_INVOICES = 8192;

    public const MODULE_PURCHASE_ORDERS = 16384;

    /**
     * Display list of invoices.
     *
     * @return Factory|View
     */
    public function index(ShowPurchaseOrdersRequest $request)
    {
        return $this->render('purchase_orders.index', ['company' => auth()->user()->company, 'settings' => auth()->user()->company->settings, 'sidebar' => $this->sidebarMenu()]);
    }

    /**
     * Show specific PurchaseOrder.
     *
     * @param ShowPurchaseOrderRequest $request
     * @param PurchaseOrder $purchase_order
     *
     * @return Factory|View
     */
    public function show(ShowPurchaseOrderRequest $request, PurchaseOrder $purchase_order)
    {
        set_time_limit(0);

        $invitation = $purchase_order->invitations()->where('vendor_contact_id', auth()->guard('vendor')->user()->id)->first();

        if ($invitation && auth()->guard('vendor') && ! session()->get('is_silent') && ! $invitation->viewed_date) {
            $invitation->markViewed();

            event(new InvitationWasViewed($purchase_order, $invitation, $purchase_order->company, Ninja::eventVars()));
            event(new PurchaseOrderWasViewed($invitation, $invitation->company, Ninja::eventVars()));
        }

        $data = [
            'purchase_order' => $purchase_order,
            'key' => $invitation ? $invitation->key : false,
            'settings' => $purchase_order->company->settings,
            'sidebar' => $this->sidebarMenu(),
            'company' => $purchase_order->company,
            'invitation' => $invitation,
            'variables' => false,

        ];

        if ($request->query('mode') === 'fullscreen') {
            return render('purchase_orders.show-fullscreen', $data);
        }

        return $this->render('purchase_orders.show', $data);
    }

    public function showBlob($hash)
    {
        $data = Cache::pull($hash);

        $invitation = PurchaseOrderInvitation::withTrashed()->find($data['invitation_id']);

        $file = $invitation->purchase_order->service()->getPurchaseOrderPdf();

        $headers = ['Content-Type' => 'application/pdf'];

        return response()->make($file, 200, $headers);

    }



    private function sidebarMenu(): array
    {
        $enabled_modules = auth()->guard('vendor')->user()->company->enabled_modules;
        $data = [];

        // TODO: Enable dashboard once it's completed.
        // $this->settings->enable_client_portal_dashboard
        // $data[] = [ 'title' => ctrans('texts.dashboard'), 'url' => 'client.dashboard', 'icon' => 'activity'];

        if (self::MODULE_PURCHASE_ORDERS & $enabled_modules) {
            $data[] = ['title' => ctrans('texts.purchase_orders'), 'url' => 'vendor.purchase_orders.index', 'icon' => 'file-text', 'id' => 'purchase_orders'];
        }

        // $data[] = ['title' => ctrans('texts.documents'), 'url' => 'client.documents.index', 'icon' => 'download'];

        return $data;
    }

    public function bulk(ProcessPurchaseOrdersInBulkRequest $request)
    {
        $transformed_ids = $this->transformKeys($request->purchase_orders);

        if ($request->input('action') == 'download') {
            return $this->downloadPurchaseOrders((array) $transformed_ids);
        } elseif ($request->input('action') == 'accept') {
            return $this->acceptPurchaseOrder($request->all());
        }

        return redirect()
            ->back()
            ->with('message', ctrans('texts.no_action_provided'));
    }

    public function acceptPurchaseOrder($data)
    {
        $purchase_orders = PurchaseOrder::query()
                                        ->whereIn('id', $this->transformKeys($data['purchase_orders']))
                                        ->where('company_id', auth()->guard('vendor')->user()->vendor->company_id)
                                        ->where('is_deleted', 0)
                                        ->withTrashed();

        $purchase_count_query = clone $purchase_orders;

        $purchase_orders->whereIn('status_id', [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_SENT])
                        ->cursor()
                        ->each(function ($purchase_order) {

                            $purchase_order->service()
                                        ->markSent()
                                        ->applyNumber()
                                        ->setStatus(PurchaseOrder::STATUS_ACCEPTED)
                                        ->save();

                            if (request()->has('signature') && ! is_null(request()->signature) && ! empty(request()->signature)) {
                                (new InjectSignature($purchase_order, auth()->guard('vendor')->user()->id, request()->signature, request()->getClientIp()))->handle();
                            }

                            event(new PurchaseOrderWasAccepted($purchase_order, auth()->guard('vendor')->user(), $purchase_order->company, Ninja::eventVars()));

                            WebhookHandler::dispatch(Webhook::EVENT_ACCEPTED_PURCHASE_ORDER, $purchase_order, $purchase_order->company, 'vendor')->delay(0);

                        });

        if ($purchase_count_query->count() == 1) {
            $purchase_order = $purchase_count_query->first();

            return redirect()->route('vendor.purchase_order.show', ['purchase_order' => $purchase_order->hashed_id]);
        } else {
            return redirect()->route('vendor.purchase_orders.index');
        }
    }

    public function downloadPurchaseOrders($ids)
    {
        $purchase_order_invitations = PurchaseOrderInvitation::query()
                            ->with('purchase_order', 'company')
                            ->whereIn('purchase_order_id', $ids)
                            ->where('vendor_contact_id', auth()->guard('vendor')->user()->id)
                            ->withTrashed()
                            ->get();

        if (count($purchase_order_invitations) == 0) {
            return back()->with(['message' => ctrans('texts.no_items_selected')]);
        }

        if (count($purchase_order_invitations) == 1) {

            $invitation = $purchase_order_invitations->first();
            $file = (new CreateRawPdf($invitation))->handle();

            return response()->streamDownload(function () use ($file) {
                echo $file;
            }, $invitation->purchase_order->numberFormatter().".pdf", ['Content-Type' => 'application/pdf']);
        }

        return $this->buildZip($purchase_order_invitations);
    }

    private function buildZip($invitations)
    {
        // create new archive
        $zipFile = new \PhpZip\ZipFile();
        try {
            foreach ($invitations as $invitation) {

                $file = (new CreateRawPdf($invitation))->handle();
                $zipFile->addFromString($invitation->purchase_order->numberFormatter().".pdf", $file);
            }

            $filename = date('Y-m-d').'_'.str_replace(' ', '_', trans('texts.purchase_orders')).'.zip';
            $filepath = sys_get_temp_dir().'/'.$filename;

            $zipFile->saveAsFile($filepath) // save the archive to a file
                   ->close(); // close archive

            return response()->download($filepath, $filename)->deleteFileAfterSend(true);
        } catch (\PhpZip\Exception\ZipException $e) {
            // handle exception
        } finally {
            $zipFile->close();
        }
    }
}

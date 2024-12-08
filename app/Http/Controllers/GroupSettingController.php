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

namespace App\Http\Controllers;

use App\Factory\GroupSettingFactory;
use App\Filters\GroupSettingFilters;
use App\Http\Requests\GroupSetting\CreateGroupSettingRequest;
use App\Http\Requests\GroupSetting\DestroyGroupSettingRequest;
use App\Http\Requests\GroupSetting\EditGroupSettingRequest;
use App\Http\Requests\GroupSetting\ShowGroupSettingRequest;
use App\Http\Requests\GroupSetting\StoreGroupSettingRequest;
use App\Http\Requests\GroupSetting\UpdateGroupSettingRequest;
use App\Http\Requests\GroupSetting\UploadGroupSettingRequest;
use App\Models\Account;
use App\Models\GroupSetting;
use App\Repositories\GroupSettingRepository;
use App\Transformers\GroupSettingTransformer;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SavesDocuments;
use App\Utils\Traits\Uploadable;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Response;

class GroupSettingController extends BaseController
{
    use DispatchesJobs;
    use Uploadable;
    use MakesHash;
    use SavesDocuments;

    protected $entity_type = GroupSetting::class;

    protected $entity_transformer = GroupSettingTransformer::class;

    protected $group_setting_repo;

    public function __construct(GroupSettingRepository $group_setting_repo)
    {
        parent::__construct();

        $this->group_setting_repo = $group_setting_repo;
    }


    /**
     * Show the form for creating a new resource.
     *
     * @param GroupSettingFilters $filters
     * @return Response| \Illuminate\Http\JsonResponse
     *
    */
    public function index(GroupSettingFilters $filters)
    {
        $group_settings = GroupSetting::filter($filters);

        return $this->listResponse($group_settings);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateGroupSettingRequest $request
     * @return Response| \Illuminate\Http\JsonResponse
     *
    */
    public function create(CreateGroupSettingRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $group_setting = GroupSettingFactory::create($user->company()->id, $user->id);

        return $this->itemResponse($group_setting);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreGroupSettingRequest $request
     * @return Response| \Illuminate\Http\JsonResponse
     *
     */
    public function store(StoreGroupSettingRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $group_setting = GroupSettingFactory::create($user->company()->id, $user->id);

        $group_setting = $this->group_setting_repo->save($request->all(), $group_setting);

        $this->uploadLogo($request->file('company_logo'), $group_setting->company, $group_setting);

        return $this->itemResponse($group_setting);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowGroupSettingRequest $request
     * @param GroupSetting $group_setting
     * @return Response| \Illuminate\Http\JsonResponse
     *
     */
    public function show(ShowGroupSettingRequest $request, GroupSetting $group_setting)
    {
        return $this->itemResponse($group_setting);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditGroupSettingRequest $request
     * @param GroupSetting $group_setting
     * @return Response| \Illuminate\Http\JsonResponse
     *
     */
    public function edit(EditGroupSettingRequest $request, GroupSetting $group_setting)
    {
        return $this->itemResponse($group_setting);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateGroupSettingRequest $request
     * @param GroupSetting $group_setting
     * @return Response| \Illuminate\Http\JsonResponse
     *
     */
    public function update(UpdateGroupSettingRequest $request, GroupSetting $group_setting)
    {
        /** Need this to prevent settings from being overwritten */
        if (!$request->file('company_logo')) {
            $group_setting = $this->group_setting_repo->save($request->all(), $group_setting);
        }

        $this->uploadLogo($request->file('company_logo'), $group_setting->company, $group_setting);

        if ($request->has('documents')) {
            $this->saveDocuments($request->input('documents'), $group_setting, $request->input('is_public', true));
        }

        return $this->itemResponse($group_setting);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyGroupSettingRequest $request
     * @param GroupSetting $group_setting
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @throws \Exception
     */
    public function destroy(DestroyGroupSettingRequest $request, GroupSetting $group_setting)
    {
        $group_setting->delete();

        return $this->itemResponse($group_setting->fresh());
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     */
    public function bulk()
    {
        $action = request()->input('action');

        $ids = request()->input('ids');

        $group_settings = GroupSetting::withTrashed()->whereIn('id', $this->transformKeys($ids))->company();

        if ($group_settings->count() == 0) {
            return response()->json(['message' => ctrans('texts.no_group_settings_found')]);
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();

        /*
         * Send the other actions to the switch
         */
        $group_settings->cursor()->each(function ($group, $key) use ($action, $user) {
            if ($user->can('edit', $group)) {
                $this->group_setting_repo->{$action}($group);
            }
        });

        /* Need to understand which permission are required for the given bulk action ie. view / edit */

        return $this->listResponse(GroupSetting::withTrashed()->whereIn('id', $this->transformKeys($ids))->company());
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UploadGroupSettingRequest $request
     * @param GroupSetting $group_setting
     * @return Response| \Illuminate\Http\JsonResponse
     *
     */
    public function upload(UploadGroupSettingRequest $request, GroupSetting $group_setting)
    {
        if (! $this->checkFeature(Account::FEATURE_DOCUMENTS)) {
            return $this->featureFailure();
        }

        if ($request->has('documents')) {
            $this->saveDocuments($request->file('documents'), $group_setting, $request->input('is_public', true));
        }

        return $this->itemResponse($group_setting->fresh());
    }
}

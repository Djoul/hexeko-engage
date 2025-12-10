<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\MobileVersionRequest;
use App\Http\Resources\MobileVersionResource;
use App\Services\MobileVersionService;
use Exception;
use Illuminate\Support\Facades\Log;

class MobileVersionController extends Controller
{
    public function __construct(protected MobileVersionService $mobileVersionService) {}

    /**
     * Check update status
     *
     * @return MobileVersionResource
     */
    public function checkUpdateStatus(MobileVersionRequest $request)
    {
        $check = $this->mobileVersionService->check($request->validated());

        try {
            $data = $request->validated();
            $data['ip_address'] = request()->ip();
            $data['user_agent'] = request()->userAgent();
            $data['metadata'] = [
                'device' => request()->device,
            ];
            $data['should_update'] = $check->should_update;
            $data['update_type'] = $check->update_type;
            $data['version'] = $check->version;
            $data['minimum_required_version'] = $check->minimum_required_version;
            $this->mobileVersionService->log($data);
        } catch (Exception $e) {
            Log::error('Failed to log mobile version request', ['error' => $e->getMessage()]);
        }

        return MobileVersionResource::make($check);
    }
}

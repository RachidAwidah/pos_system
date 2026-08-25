<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingRequest;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use App\Services\AuditLogService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    public function publicIndex(): AnonymousResourceCollection
    {
        return SettingResource::collection(Setting::query()->where('is_public', true)->orderBy('group')->orderBy('key')->get());
    }

    public function index(): AnonymousResourceCollection
    {
        return SettingResource::collection(Setting::query()->orderBy('group')->orderBy('key')->get());
    }

    public function update(UpdateSettingRequest $request, Setting $setting): SettingResource
    {
        return DB::transaction(function () use ($request, $setting): SettingResource {
            $oldValues = [$setting->key => $setting->value];
            $value = $request->validated('value');

            if ($setting->type === 'json') {
                $value = json_encode($value, JSON_THROW_ON_ERROR);
            }

            $setting->update(['value' => $value]);
            AuditLogService::updated(Setting::class, $setting->id, $oldValues, [
                $setting->key => $setting->value,
            ]);

            return new SettingResource($setting);
        });
    }
}

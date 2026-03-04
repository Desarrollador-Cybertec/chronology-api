<?php

namespace App\Http\Controllers;

use App\Http\Requests\SystemSetting\UpdateSystemSettingRequest;
use App\Http\Resources\SystemSettingResource;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SystemSettingController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $settings = SystemSetting::query()->orderBy('group')->orderBy('key')->get();

        return SystemSettingResource::collection($settings);
    }

    public function update(UpdateSystemSettingRequest $request): JsonResponse
    {
        $updated = [];

        foreach ($request->validated('settings') as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value'] ?? null]
            );
            $updated[] = $setting['key'];
        }

        return response()->json([
            'message' => 'Configuración actualizada correctamente.',
            'updated_keys' => $updated,
        ]);
    }
}

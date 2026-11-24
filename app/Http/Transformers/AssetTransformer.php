<?php

namespace App\Http\Transformers;

use App\Models\Asset;
use App\Models\Location;
use App\Models\User;
use App\Services\CustomFieldService;
use Illuminate\Support\Facades\Auth;

class AssetTransformer
{
    public const WITH = [
        'model.manufacturer', 'model.category', 'model.fieldset.fields', 'status', 'supplier', 'company',
        'location', 'rtdLocation', 'assigned', 'createdBy',
    ];

    public static function one(Asset $asset, bool $reveal = false): array
    {
        $viewer = Auth::user();

        return [
            'id' => $asset->id,
            'name' => Fmt::text($asset->name),
            'asset_tag' => Fmt::text($asset->asset_tag),
            'serial' => Fmt::text($asset->serial),
            'missing_fields' => $asset->missingFields(),
            'model' => Fmt::rel($asset->model),
            'byod' => false,
            'requestable' => false,
            'model_number' => Fmt::text($asset->model?->model_number),
            'eol' => null,
            'asset_eol_date' => null,
            'status_label' => $asset->status ? [
                'id' => $asset->status->id,
                'name' => e($asset->status->name),
                'status_type' => $asset->status->statusType(),
                'status_meta' => $asset->statusMeta(),
            ] : null,
            'category' => Fmt::rel($asset->model?->category),
            'manufacturer' => Fmt::rel($asset->model?->manufacturer),
            'supplier' => Fmt::rel($asset->supplier),
            'notes' => Fmt::text($asset->notes),
            'order_number' => Fmt::text($asset->order_number),
            'company' => Fmt::rel($asset->company),
            'location' => Fmt::rel($asset->location),
            'rtd_location' => Fmt::rel($asset->rtdLocation),
            'image' => $asset->image ? asset('storage/'.$asset->image) : null,
            'qr' => null,
            'alt_barcode' => null,
            'assigned_to' => self::assignedTo($asset),
            'warranty_months' => $asset->warranty_months ? $asset->warranty_months.' meses' : null,
            'warranty_expires' => Fmt::date($asset->warranty_expires),
            'created_by' => Fmt::rel($asset->createdBy),
            'created_at' => Fmt::datetime($asset->created_at),
            'updated_at' => Fmt::datetime($asset->updated_at),
            'last_audit_date' => null,
            'next_audit_date' => null,
            'deleted_at' => Fmt::datetime($asset->deleted_at),
            'purchase_date' => Fmt::date($asset->purchase_date),
            'age' => $asset->purchase_date?->diffForHumans(null, true),
            'last_checkout' => Fmt::datetime($asset->last_checkout),
            'last_checkin' => Fmt::datetime($asset->last_checkin),
            'expected_checkin' => Fmt::date($asset->expected_checkin),
            'purchase_cost' => Fmt::money($asset->purchase_cost),
            'checkin_counter' => 0,
            'checkout_counter' => 0,
            'requests_counter' => 0,
            'user_can_checkout' => ! $asset->isAssigned() && $asset->isDeployable(),
            'custom_fields' => self::fields()->present($asset, $viewer, $reveal),
            'available_actions' => [
                'checkout' => (bool) $viewer?->canWrite() && ! $asset->isAssigned(),
                'checkin' => (bool) $viewer?->canWrite() && $asset->isAssigned(),
                'clone' => (bool) $viewer?->canWrite(),
                'restore' => (bool) $viewer?->canWrite() && $asset->trashed(),
                'update' => (bool) $viewer?->canWrite(),
                'delete' => (bool) $viewer?->isAdmin() && ! $asset->isAssigned(),
            ],
        ];
    }

    private static function fields(): CustomFieldService
    {
        static $service;

        return $service ??= app(CustomFieldService::class);
    }

    public static function assignedTo(Asset $asset): ?array
    {
        $target = $asset->assigned;

        return match (true) {
            $target instanceof User => [
                'id' => $target->id,
                'username' => Fmt::text($target->username),
                'name' => Fmt::text($target->name),
                'first_name' => Fmt::text($target->first_name),
                'last_name' => Fmt::text($target->last_name),
                'email' => Fmt::text($target->email),
                'employee_number' => Fmt::text($target->employee_num),
                'type' => 'user',
            ],
            $target instanceof Location => ['id' => $target->id, 'name' => Fmt::text($target->name), 'type' => 'location'],
            $target instanceof Asset => [
                'id' => $target->id,
                'name' => Fmt::text($target->name ?: $target->asset_tag),
                'asset_tag' => Fmt::text($target->asset_tag),
                'type' => 'asset',
            ],
            default => null,
        };
    }
}

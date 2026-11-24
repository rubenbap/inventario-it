<?php

namespace App\Http\Transformers;

use App\Models\Accessory;
use App\Models\AccessoryCheckout;
use App\Models\ActionLog;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Company;
use App\Models\CustomField;
use App\Models\CustomFieldset;
use App\Models\Department;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\StatusLabel;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Morph;
use Illuminate\Support\Facades\Auth;

class Transformers
{
    private static function stamps($m): array
    {
        return ['created_at' => Fmt::datetime($m->created_at), 'updated_at' => Fmt::datetime($m->updated_at)];
    }

    private static function actions(bool $deletable = true): array
    {
        $u = Auth::user();

        return ['update' => (bool) $u?->canWrite(), 'delete' => (bool) $u?->isAdmin() && $deletable];
    }

    public static function user(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => Fmt::text($u->name),
            'first_name' => Fmt::text($u->first_name),
            'last_name' => Fmt::text($u->last_name),
            'username' => Fmt::text($u->username),
            'email' => Fmt::text($u->email),
            'employee_num' => Fmt::text($u->employee_num),
            'jobtitle' => Fmt::text($u->jobtitle),
            'phone' => Fmt::text($u->phone),
            'company' => Fmt::rel($u->company),
            'department' => Fmt::rel($u->department),
            'location' => Fmt::rel($u->location),
            'manager' => $u->manager ? ['id' => $u->manager->id, 'name' => Fmt::text($u->manager->name)] : null,
            'activated' => $u->activated,
            'can_login' => $u->can_login,
            'role' => $u->role,
            'notes' => Fmt::text($u->notes),
            'assets_count' => (int) ($u->assets_count ?? 0),
            'accessories_count' => (int) ($u->accessory_checkouts_count ?? 0),
            ...self::stamps($u),
            'available_actions' => self::actions(),
        ];
    }

    public static function company(Company $c): array
    {
        return [
            'id' => $c->id,
            'name' => Fmt::text($c->name),
            'code' => $c->code,
            'tax_id' => Fmt::text($c->tax_id),
            'active' => $c->active,
            'assets_count' => (int) ($c->assets_count ?? 0),
            'notes' => Fmt::text($c->notes),
            ...self::stamps($c),
            'available_actions' => self::actions(),
        ];
    }

    public static function manufacturer(Manufacturer $m): array
    {
        return [
            'id' => $m->id,
            'name' => Fmt::text($m->name),
            'url' => Fmt::text($m->url),
            'support_email' => Fmt::text($m->support_email),
            'support_phone' => Fmt::text($m->support_phone),
            'models_count' => (int) ($m->models_count ?? 0),
            ...self::stamps($m),
            'available_actions' => self::actions(),
        ];
    }

    public static function category(Category $c): array
    {
        return [
            'id' => $c->id,
            'name' => Fmt::text($c->name),
            'category_type' => $c->category_type,
            'kind' => $c->kind,
            'notes' => Fmt::text($c->notes),
            ...self::stamps($c),
            'available_actions' => self::actions(),
        ];
    }

    public static function model(AssetModel $m): array
    {
        return [
            'id' => $m->id,
            'name' => Fmt::text($m->name),
            'model_number' => Fmt::text($m->model_number),
            'manufacturer' => Fmt::rel($m->manufacturer),
            'category' => Fmt::rel($m->category),
            'fieldset' => Fmt::rel($m->fieldset),
            'image' => $m->image ? asset('storage/'.$m->image) : null,
            'assets_count' => (int) ($m->assets_count ?? 0),
            'notes' => Fmt::text($m->notes),
            ...self::stamps($m),
            'available_actions' => self::actions(),
        ];
    }

    public static function statuslabel(StatusLabel $s): array
    {
        return [
            'id' => $s->id,
            'name' => Fmt::text($s->name),
            'type' => $s->statusType(),
            'color' => $s->color,
            'assets_count' => (int) ($s->assets_count ?? 0),
            'notes' => Fmt::text($s->notes),
            ...self::stamps($s),
            'available_actions' => self::actions(),
        ];
    }

    public static function location(Location $l): array
    {
        return [
            'id' => $l->id,
            'name' => Fmt::text($l->name),
            'company' => Fmt::rel($l->company),
            'parent' => Fmt::rel($l->parent),
            'address' => Fmt::text($l->address),
            'city' => Fmt::text($l->city),
            'zip' => Fmt::text($l->zip),
            'country' => $l->country,
            'notes' => Fmt::text($l->notes),
            ...self::stamps($l),
            'available_actions' => self::actions(),
        ];
    }

    public static function supplier(Supplier $s): array
    {
        return [
            'id' => $s->id,
            'name' => Fmt::text($s->name),
            'contact' => Fmt::text($s->contact),
            'email' => Fmt::text($s->email),
            'phone' => Fmt::text($s->phone),
            'url' => Fmt::text($s->url),
            'notes' => Fmt::text($s->notes),
            ...self::stamps($s),
            'available_actions' => self::actions(),
        ];
    }

    public static function department(Department $d): array
    {
        return [
            'id' => $d->id,
            'name' => Fmt::text($d->name),
            'company' => Fmt::rel($d->company),
            'notes' => Fmt::text($d->notes),
            ...self::stamps($d),
            'available_actions' => self::actions(),
        ];
    }

    public static function field(CustomField $f): array
    {
        return [
            'id' => $f->id,
            'name' => Fmt::text($f->name),
            'db_column_name' => $f->db_column,
            'format' => $f->format,
            'field_values' => $f->field_values,
            'field_values_array' => $f->options(),
            'type' => $f->element,
            'field_encrypted' => $f->field_encrypted,
            'show_in_listview' => $f->show_in_listview,
            'help_text' => Fmt::text($f->help_text),
            'required' => isset($f->pivot) ? (bool) $f->pivot->required : null,
            ...self::stamps($f),
        ];
    }

    public static function fieldset(CustomFieldset $s): array
    {
        return [
            'id' => $s->id,
            'name' => Fmt::text($s->name),
            'fields' => [
                'total' => $s->fields->count(),
                'rows' => $s->fields->map(fn ($f) => self::field($f))->all(),
            ],
            'models' => [
                'total' => $s->models->count(),
                'rows' => $s->models->map(fn ($m) => Fmt::rel($m))->all(),
            ],
            ...self::stamps($s),
        ];
    }

    public static function accessory(Accessory $a): array
    {
        $checkedOut = (int) ($a->open_checkouts_count ?? $a->openCheckouts()->count());

        return [
            'id' => $a->id,
            'name' => Fmt::text($a->name),
            'image' => $a->image ? asset('storage/'.$a->image) : null,
            'company' => Fmt::rel($a->company),
            'manufacturer' => Fmt::rel($a->manufacturer),
            'supplier' => Fmt::rel($a->supplier),
            'model_number' => Fmt::text($a->model_number),
            'category' => Fmt::rel($a->category),
            'location' => Fmt::rel($a->location),
            'notes' => Fmt::text($a->notes),
            'qty' => $a->qty,
            'purchase_date' => Fmt::date($a->purchase_date),
            'purchase_cost' => Fmt::money($a->purchase_cost),
            'order_number' => Fmt::text($a->order_number),
            'min_qty' => $a->min_amt,
            'remaining_qty' => max(0, $a->qty - $checkedOut),
            'users_count' => $checkedOut,
            ...self::stamps($a),
            'available_actions' => [
                'checkout' => (bool) Auth::user()?->canWrite() && $a->qty > $checkedOut,
                'checkin' => false,
                'update' => (bool) Auth::user()?->canWrite(),
                'delete' => (bool) Auth::user()?->isAdmin() && $checkedOut === 0,
            ],
        ];
    }

    public static function accessoryCheckout(AccessoryCheckout $c): array
    {
        return [
            'id' => $c->id,
            'assigned_to' => $c->user ? [
                'id' => $c->user->id,
                'name' => Fmt::text($c->user->name),
                'type' => 'user',
            ] : null,
            'note' => Fmt::text($c->note),
            'created_by' => $c->created_by,
            'created_at' => Fmt::datetime($c->created_at),
            'available_actions' => ['checkin' => (bool) Auth::user()?->canWrite()],
        ];
    }

    public static function activity(ActionLog $log): array
    {
        $item = $log->item;
        $target = $log->target;

        $describe = function ($model, ?string $class) {
            if (! $model) {
                return null;
            }

            return [
                'id' => $model->getKey(),
                'name' => Fmt::text(match (true) {
                    $model instanceof Asset => trim(($model->asset_tag ?? '').' '.($model->name ?? '')),
                    $model instanceof User => $model->name,
                    default => $model->getAttribute('name'),
                }),
                'type' => Morph::short($class),
            ];
        };

        return [
            'id' => $log->id,
            'created_at' => Fmt::datetime($log->created_at),
            'item' => $describe($item, $log->item_type),
            'target' => $describe($target, $log->target_type),
            'company' => $log->company_id,
            'action_type' => $log->action_type,
            'admin' => $log->admin ? ['id' => $log->admin->id, 'name' => Fmt::text($log->admin->name)] : null,
            'note' => Fmt::text($log->note),
            'log_meta' => $log->log_meta,
        ];
    }
}

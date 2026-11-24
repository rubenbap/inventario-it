<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function checkout(Asset $asset, array $data, User $actor): Asset
    {
        $type = $data['checkout_to_type'] ?? $this->guessType($data);
        $target = $this->resolveTarget($type, $data);

        return DB::transaction(function () use ($asset, $target, $data, $actor) {
            $asset = $this->lock($asset);

            if ($asset->isAssigned()) {
                $this->fail('assigned_to', 'Este asset já está atribuído. Faz check-in primeiro.');
            }
            if (! empty($data['status_id'])) {
                $asset->status_id = (int) $data['status_id'];
                $asset->load('status');
            }
            if (! $asset->isDeployable()) {
                $this->fail('status_id', 'O estado deste asset não permite check-out.');
            }

            $this->checkTarget($asset, $target, $data, $actor);

            $asset->assigned()->associate($target);
            $asset->last_checkout = $data['checkout_at'] ?? now();
            $asset->expected_checkin = $data['expected_checkin'] ?? null;
            if (! empty($data['name'])) {
                $asset->name = $data['name'];
            }
            $asset->location_id = match (true) {
                $target instanceof Location => $target->id,
                $target instanceof User, $target instanceof Asset => $target->location_id ?? $asset->location_id,
                default => $asset->location_id,
            };
            $asset->save();

            ActivityLogger::log('checkout', $asset, $target, $data['note'] ?? null);

            return $asset->refresh();
        });
    }

    public function checkin(Asset $asset, array $data): Asset
    {
        return DB::transaction(function () use ($asset, $data) {
            $asset = $this->lock($asset);

            if (! $asset->isAssigned()) {
                $this->fail('assigned_to', 'Este asset não está atribuído.');
            }

            $from = $asset->assigned;
            $asset->assigned()->dissociate();
            $asset->expected_checkin = null;
            $asset->last_checkin = now();
            $asset->location_id = $data['location_id'] ?? $asset->rtd_location_id ?? $asset->location_id;
            if (! empty($data['status_id'])) {
                $asset->status_id = (int) $data['status_id'];
            }
            if (array_key_exists('name', $data)) {
                $asset->name = $data['name'];
            }
            $asset->save();

            ActivityLogger::log('checkin from', $asset, $from, $data['note'] ?? null);

            return $asset->refresh();
        });
    }

    private function lock(Asset $asset): Asset
    {
        return Asset::query()->with('status')->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();
    }

    private function guessType(array $data): string
    {
        return match (true) {
            ! empty($data['assigned_location']) => 'location',
            ! empty($data['assigned_asset']) => 'asset',
            default => 'user',
        };
    }

    private function resolveTarget(string $type, array $data): Model
    {
        $id = $data['assigned_'.$type] ?? null;

        if (! in_array($type, ['user', 'location', 'asset'], true)) {
            $this->fail('checkout_to_type', 'checkout_to_type tem de ser user, location ou asset.');
        }
        if (! $id) {
            $this->fail('assigned_'.$type, 'Falta o campo assigned_'.$type.'.');
        }

        $target = match ($type) {
            'user' => User::query()->find($id),
            'location' => Location::query()->find($id),
            'asset' => Asset::query()->find($id),
        };

        if (! $target) {
            $this->fail('assigned_'.$type, 'Destino não encontrado.');
        }

        return $target;
    }

    private function checkTarget(Asset $asset, Model $target, array $data, User $actor): void
    {
        if ($target instanceof User) {
            if (! $target->activated) {
                $this->fail('assigned_user', 'Este utilizador está inativo e não pode receber equipamentos.');
            }

            $differentCompany = $target->company_id !== null && (int) $target->company_id !== (int) $asset->company_id;
            if ($differentCompany && config('inventario.enforce_same_company_checkout')) {
                if (! $actor->isAdmin()) {
                    $this->fail('assigned_user', 'O utilizador é de outra empresa.');
                }
                if (empty($data['note'])) {
                    $this->fail('note', 'Check-out para outra empresa: tens de deixar uma nota.');
                }
            }
        }

        if ($target instanceof Asset) {
            $cursor = $target;
            for ($i = 0; $cursor && $i < 10; $i++) {
                if ($cursor->is($asset)) {
                    $this->fail('assigned_asset', 'Não se pode atribuir um asset a si próprio.');
                }
                $cursor = $cursor->assigned_type === Asset::class ? $cursor->assigned : null;
            }
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}

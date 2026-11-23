<?php

namespace App\Filament\Admin\Resources\ProtocolResource\Pages;

use App\Filament\Admin\Resources\ProtocolResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateProtocol extends CreateRecord
{
    protected static string $resource = ProtocolResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var User|null $actor */
        $actor = auth()->user();

        if ($actor instanceof User) {
            $data['created_by'] = $actor->id;
        }

        if (($data['status'] ?? null) === 'active' && empty($data['approved_at'])) {
            $data['approved_at'] = now();
        }

        return $data;
    }
}
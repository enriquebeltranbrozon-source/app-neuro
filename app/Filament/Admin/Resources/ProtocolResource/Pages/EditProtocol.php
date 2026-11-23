<?php

namespace App\Filament\Admin\Resources\ProtocolResource\Pages;

use App\Filament\Admin\Resources\ProtocolResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProtocol extends EditRecord
{
    protected static string $resource = ProtocolResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
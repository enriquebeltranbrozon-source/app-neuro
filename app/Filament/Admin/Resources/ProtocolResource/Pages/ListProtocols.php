<?php

namespace App\Filament\Admin\Resources\ProtocolResource\Pages;

use App\Filament\Admin\Resources\ProtocolResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProtocols extends ListRecords
{
    protected static string $resource = ProtocolResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Nuevo Protocolo'),
        ];
    }
}
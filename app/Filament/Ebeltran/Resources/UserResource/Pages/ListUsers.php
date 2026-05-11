<?php

namespace App\Filament\Ebeltran\Resources\UserResource\Pages;

use App\Filament\Ebeltran\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
{
    return [
        \Filament\Actions\CreateAction::make()
            ->label('Nuevo Terapeuta'), // <-- Aquí le cambias el nombre al botón
    ];
}
}

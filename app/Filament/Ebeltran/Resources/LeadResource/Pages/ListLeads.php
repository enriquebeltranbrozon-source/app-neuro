<?php

namespace App\Filament\Ebeltran\Resources\LeadResource\Pages;

use App\Filament\Ebeltran\Resources\LeadResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

   protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make()
                ->label('Nuevo Prospecto'), // <-- Aquí le cambias el nombre al botón
        ];
    }
    
}

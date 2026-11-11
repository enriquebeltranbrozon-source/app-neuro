<?php

namespace App\Filament\Admin\Resources\PatientSessionResource\Pages;

use App\Filament\Admin\Resources\PatientSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPatientSessions extends ListRecords
{
    protected static string $resource = PatientSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

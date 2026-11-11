<?php

namespace App\Filament\Admin\Resources\ClinicResource\Pages;

use App\Filament\Admin\Resources\ClinicResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClinics extends ListRecords
{
    protected static string $resource = ClinicResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

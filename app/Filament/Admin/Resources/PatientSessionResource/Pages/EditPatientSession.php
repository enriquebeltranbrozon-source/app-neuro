<?php

namespace App\Filament\Admin\Resources\PatientSessionResource\Pages;

use App\Filament\Admin\Resources\PatientSessionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPatientSession extends EditRecord
{
    protected static string $resource = PatientSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Admin\Resources\EegStudyResource\Pages;

use App\Filament\Admin\Resources\EegStudyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEegStudies extends ListRecords
{
    protected static string $resource = EegStudyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Subir Estudio EEG'),
        ];
    }
}
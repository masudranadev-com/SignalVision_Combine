<?php

namespace App\Filament\Resources\SignalFormatResource\Pages;

use App\Filament\Resources\SignalFormatResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSignalFormats extends ListRecords
{
    protected static string $resource = SignalFormatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

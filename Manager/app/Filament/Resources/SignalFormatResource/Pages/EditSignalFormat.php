<?php

namespace App\Filament\Resources\SignalFormatResource\Pages;

use App\Filament\Resources\SignalFormatResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSignalFormat extends EditRecord
{
    protected static string $resource = SignalFormatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

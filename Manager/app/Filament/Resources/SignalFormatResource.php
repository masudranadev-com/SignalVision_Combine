<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SignalFormatResource\Pages;
use App\Filament\Resources\SignalFormatResource\RelationManagers;
use App\Models\SignalFormat;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class SignalFormatResource extends Resource
{
    protected static ?string $model = SignalFormat::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('format_name')
                ->label('Format Name')
                ->placeholder('Eg: SignalVision'),

                FileUpload::make('logo')
                ->label('Channel Logo')
                ->image()  // Ensures it's an image file
                ->disk('public')
                ->directory('images/providers')
                ->visibility('public')
                ->preserveFilenames()
                ->helperText(function ($state) {
                    if (!$state) {
                        return 'No logo uploaded, default image will be used.';
                    }
                }),

                Forms\Components\TextInput::make('type')
                ->label('Channel Type')
                ->placeholder('Eg: Future / Future , Spot'),

                Forms\Components\TextInput::make('group_link')
                ->label('Channel Link'),

                Textarea::make('format_demo')
                ->label('Channel Demo')
                ->rows(3),

                Textarea::make('features')
                ->label('Channel Features')
                ->default('[{"name": "Instant", "icon": "💡"}, {"name": "TP", "icon": "🎯"}]')
                ->rows(3),

                 // Additional fields for editing 
                Forms\Components\TextInput::make('short')
                ->label('Order')
                ->placeholder('Eg: 1-100')
                ->visible(fn ($record) => $record !== null),

                Select::make('status')
                ->label('Order / Status')
                ->options([
                    'active' => 'Active',
                    'inactive' => 'Inactive',
                ])
                ->placeholder('Select status')
                ->visible(fn ($record) => $record !== null),

                Forms\Components\TextInput::make('group_id')
                ->label('Channel ID')
                ->placeholder('Eg: Future / Future , Spot'),

                Textarea::make('format_formula')
                ->label('Format Formula')
                ->rows(8)
                ->visible(fn ($record) => $record !== null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo')
                ->label('Chanel Logo')
                ->searchable()
                ->width(100)
                ->height(100)
                ->square()
                ->defaultImageUrl(url('images/logo/default.png')),

                Tables\Columns\TextColumn::make('format_name')
                ->label('Format Name')
                ->searchable(),

                Tables\Columns\TextColumn::make('group_id')
                ->label('Group ID')
                ->searchable(),

                Tables\Columns\TextColumn::make('status')
                ->label('Status')
                ->sortable(),

            ])
            ->filters([
                SelectFilter::make('status')
                ->label('Status')
                ->options([
                    'active' => 'Active',
                    'inactive' => 'Inactive',
                    'assign' => 'Assign',
                    'user' => 'Users Submited',
                ])
                ->attribute('status'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSignalFormats::route('/'),
            'create' => Pages\CreateSignalFormat::route('/create'),
            'edit' => Pages\EditSignalFormat::route('/{record}/edit'),
        ];
    }
}

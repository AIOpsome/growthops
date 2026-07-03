<?php

namespace App\Filament\Resources\RecommendedActions\Schemas;

use App\Models\RecommendedAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;

class RecommendedActionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Recommendation')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('campaign.name')->label('Campaign'),
                        TextEntry::make('campaign.platform')->label('Platform')->badge(),
                        TextEntry::make('type')->badge(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('confidence')->numeric(decimalPlaces: 2),
                        TextEntry::make('risk')->badge(),
                        TextEntry::make('expected_upside')->label('Expected upside')->money('USD'),
                        TextEntry::make('run_date')->date(),
                    ]),
                Section::make('Meta 72h caveat')
                    ->visible(fn (RecommendedAction $record): bool => isset($record->evidence['caveat']))
                    ->schema([
                        TextEntry::make('evidence.caveat')
                            ->label('Caveat')
                            ->badge()
                            ->color('warning'),
                        TextEntry::make('evidence.provisional_days')
                            ->label('Provisional days (excluded from conversion signal)')
                            ->listWithLineBreaks()
                            ->badge(),
                    ]),
                Section::make('Evidence')
                    ->schema([
                        TextEntry::make('evidence_payload')
                            ->hiddenLabel()
                            ->fontFamily(FontFamily::Mono)
                            ->state(fn (RecommendedAction $record): string => "```json\n".json_encode($record->evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n```")
                            ->markdown()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}

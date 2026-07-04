<?php

namespace App\Filament\Resources\RecommendedActions\Schemas;

use App\Models\RecommendedAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;

class RecommendedActionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make([
                    Section::make('Recommendation')
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 3])
                                ->schema([
                                    ViewEntry::make('confidence')
                                        ->view('filament.components.confidence-donut')
                                        ->viewData(fn (RecommendedAction $record): array => ['confidence' => $record->confidence]),
                                    Grid::make(1)
                                        ->columnSpan(2)
                                        ->schema([
                                            TextEntry::make('campaign.name')->label('Campaign'),
                                            TextEntry::make('campaign.platform')->label('Platform')->badge(),
                                            TextEntry::make('type')->badge(),
                                            TextEntry::make('status')->badge(),
                                            TextEntry::make('risk')->badge(),
                                            TextEntry::make('expected_upside')->label('Expected upside')->money('USD'),
                                            TextEntry::make('created_at')->label('Run date')->dateTime(),
                                        ])
                                        ->columns(2),
                                ]),
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
                    Section::make('Narrative')
                        ->schema([
                            TextEntry::make('narrative')
                                ->hiddenLabel()
                                ->state(fn (RecommendedAction $record): string => $record->ensureNarrative())
                                ->columnSpanFull(),
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
                    Section::make('Simulated execution')
                        ->description('SIMULATED — no live ad-account writes.')
                        ->visible(fn (RecommendedAction $record): bool => $record->executionLog !== null)
                        ->schema([
                            TextEntry::make('executionLog.platform')->label('Platform')->badge(),
                            TextEntry::make('executionLog.simulated_endpoint')
                                ->label('Endpoint')
                                ->fontFamily(FontFamily::Mono),
                            TextEntry::make('execution_payload')
                                ->hiddenLabel()
                                ->fontFamily(FontFamily::Mono)
                                ->state(fn (RecommendedAction $record): string => "```json\n".json_encode($record->executionLog?->simulated_payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n```")
                                ->markdown()
                                ->columnSpanFull(),
                        ]),
                    Section::make('Audit trail')
                        ->description('Immutable — one row per decision.')
                        ->visible(fn (RecommendedAction $record): bool => $record->audits()->exists())
                        ->schema([
                            RepeatableEntry::make('audits')
                                ->hiddenLabel()
                                ->schema([
                                    TextEntry::make('from_status')->badge(),
                                    TextEntry::make('to_status')->badge(),
                                    TextEntry::make('actor'),
                                    TextEntry::make('created_at')->dateTime(),
                                    TextEntry::make('reason')->placeholder('—')->columnSpanFull(),
                                    TextEntry::make('edited_value')
                                        ->label('Edited value')
                                        ->placeholder('—')
                                        ->formatStateUsing(fn (mixed $state): ?string => $state === null ? null : json_encode($state, JSON_UNESCAPED_SLASHES))
                                        ->columnSpanFull(),
                                ])
                                ->columns(4),
                        ]),
                ])->columnSpan(2),
                Group::make([
                    Section::make('AI reasoning')
                        ->description('The model\'s own chain-of-thought before it wrote the narrative. Only present for live LLM calls — the deterministic fallback has none.')
                        ->visible(function (RecommendedAction $record): bool {
                            $record->ensureNarrative();

                            return filled($record->reasoning);
                        })
                        ->schema([
                            ViewEntry::make('reasoning')
                                ->hiddenLabel()
                                ->view('filament.components.reasoning-text')
                                ->viewData(fn (RecommendedAction $record): array => ['reasoning' => $record->reasoning])
                                ->columnSpanFull(),
                        ]),
                ])->columnSpan(1),
            ]);
    }
}

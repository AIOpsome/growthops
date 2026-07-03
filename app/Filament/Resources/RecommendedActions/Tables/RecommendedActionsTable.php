<?php

namespace App\Filament\Resources\RecommendedActions\Tables;

use App\Filament\Resources\RecommendedActions\Actions\DecisionActions;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RecommendedActionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('expected_upside', 'desc')
            ->columns([
                TextColumn::make('campaign.name')
                    ->label('Campaign')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('campaign.platform')
                    ->label('Platform')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'meta' => 'info',
                        'google' => 'danger',
                        'tiktok' => 'gray',
                        'taboola' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pause' => 'danger',
                        'scale' => 'success',
                        'investigate' => 'warning',
                        'fix' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('confidence')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('risk')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'low' => 'success',
                        'medium' => 'warning',
                        'high' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('expected_upside')
                    ->label('Expected upside')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'edited' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('run_date')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'edited' => 'Edited',
                    ]),
                SelectFilter::make('type')
                    ->options([
                        'pause' => 'Pause',
                        'scale' => 'Scale',
                        'investigate' => 'Investigate',
                        'fix' => 'Fix',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                DecisionActions::approve(),
                DecisionActions::editThenApprove(),
                DecisionActions::reject(),
            ]);
    }
}

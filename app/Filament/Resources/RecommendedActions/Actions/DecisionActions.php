<?php

namespace App\Filament\Resources\RecommendedActions\Actions;

use App\Models\RecommendedAction;
use App\Services\ActionDecisionService;
use App\Services\SimulatedExecutionBuilder;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

final class DecisionActions
{
    public static function approve(): Action
    {
        return Action::make('approve')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (RecommendedAction $record): bool => $record->status === 'pending')
            ->requiresConfirmation()
            ->modalDescription('Records a SIMULATED execution — no live ad-account writes.')
            ->action(function (RecommendedAction $record): void {
                app(ActionDecisionService::class)->approve($record);

                Notification::make()->title('Approved — simulated execution logged.')->success()->send();
            });
    }

    public static function editThenApprove(): Action
    {
        return Action::make('editThenApprove')
            ->label('Edit & approve')
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->visible(fn (RecommendedAction $record): bool => $record->status === 'pending')
            ->schema(fn (RecommendedAction $record): array => [
                TextInput::make('parameter')
                    ->label(config("growthops.approval.parameters.{$record->type}.label"))
                    ->numeric()
                    ->required()
                    ->default(fn (): float => app(SimulatedExecutionBuilder::class)->defaultParameter($record)),
            ])
            ->action(function (RecommendedAction $record, array $data): void {
                app(ActionDecisionService::class)->editThenApprove($record, (float) $data['parameter']);

                Notification::make()->title('Edited & approved — simulated execution logged.')->success()->send();
            });
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (RecommendedAction $record): bool => $record->status === 'pending')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->minLength(3),
            ])
            ->action(function (RecommendedAction $record, array $data): void {
                app(ActionDecisionService::class)->reject($record, $data['reason']);

                Notification::make()->title('Rejected.')->success()->send();
            });
    }
}

<?php

namespace App\Filament\Resources\RecommendedActions\Pages;

use App\Filament\Resources\RecommendedActions\RecommendedActionResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListRecommendedActions extends ListRecords
{
    protected static string $resource = RecommendedActionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('runAnalysis')
                ->label('Run daily analysis')
                ->icon('heroicon-o-play')
                ->requiresConfirmation()
                ->modalDescription('Runs the detector engine against every campaign\'s current data and rebuilds today\'s pending action queue.')
                ->action(function (): void {
                    Artisan::call('growthops:analyze');

                    Notification::make()
                        ->title(trim(Artisan::output()) ?: 'Analysis complete.')
                        ->success()
                        ->send();
                }),
        ];
    }
}

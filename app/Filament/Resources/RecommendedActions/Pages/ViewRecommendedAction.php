<?php

namespace App\Filament\Resources\RecommendedActions\Pages;

use App\Filament\Resources\RecommendedActions\Actions\DecisionActions;
use App\Filament\Resources\RecommendedActions\RecommendedActionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewRecommendedAction extends ViewRecord
{
    protected static string $resource = RecommendedActionResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            DecisionActions::approve(),
            DecisionActions::editThenApprove(),
            DecisionActions::reject(),
        ];
    }
}

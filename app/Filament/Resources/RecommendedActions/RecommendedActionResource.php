<?php

namespace App\Filament\Resources\RecommendedActions;

use App\Filament\Resources\RecommendedActions\Pages\ListRecommendedActions;
use App\Filament\Resources\RecommendedActions\Pages\ViewRecommendedAction;
use App\Filament\Resources\RecommendedActions\Schemas\RecommendedActionInfolist;
use App\Filament\Resources\RecommendedActions\Tables\RecommendedActionsTable;
use App\Models\RecommendedAction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RecommendedActionResource extends Resource
{
    protected static ?string $model = RecommendedAction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Action Queue';

    protected static ?string $modelLabel = 'recommended action';

    public static function infolist(Schema $schema): Schema
    {
        return RecommendedActionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecommendedActionsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecommendedActions::route('/'),
            'view' => ViewRecommendedAction::route('/{record}'),
        ];
    }
}

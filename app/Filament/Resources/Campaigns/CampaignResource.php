<?php

namespace App\Filament\Resources\Campaigns;

use App\Filament\Resources\Campaigns\Pages\ListCampaigns;
use App\Models\Campaign;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('platform')
                    ->badge()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('spend_total')
                    ->label('Spend')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('impressions_total')
                    ->label('Impressions')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('clicks_total')
                    ->label('Clicks')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('conversions_total')
                    ->label('Conversions')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('revenue_total')
                    ->label('Revenue')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('leads_total')
                    ->label('Leads')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('accepted_leads_total')
                    ->label('Accepted')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('lead_acceptance_rate')
                    ->label('Lead accept')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%'),
                TextColumn::make('cpc')
                    ->label('CPC')
                    ->money('USD'),
                TextColumn::make('cpm')
                    ->label('CPM')
                    ->money('USD'),
                TextColumn::make('cpa')
                    ->label('CPA')
                    ->money('USD'),
                TextColumn::make('cpl')
                    ->label('CPL')
                    ->money('USD'),
                TextColumn::make('roas')
                    ->label('ROAS')
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('epc')
                    ->label('EPC')
                    ->money('USD'),
            ])
            ->defaultSort('name');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withMetricTotals()->withLeadTotals();
    }

    /**
     * @return array<string, \Filament\Resources\Pages\PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListCampaigns::route('/'),
        ];
    }
}

<?php

use App\Filament\Resources\Campaigns\Pages\ListCampaigns;
use App\Models\Campaign;
use App\Models\DailyMetric;
use App\Models\User;
use App\Services\CsvImports\CampaignCsvImporter;
use App\Services\CsvImports\CsvImportException;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

function fixture_path(string $file): string
{
    return base_path("tests/fixtures/{$file}");
}

it('imports Meta Ads Manager campaign exports into normalized rows', function () {
    app(CampaignCsvImporter::class)->import(fixture_path('meta_campaign_export.csv'));

    expect(Campaign::query()->count())->toBe(2)
        ->and(DailyMetric::query()->count())->toBe(2);

    $campaign = Campaign::query()->where('name', 'Prospecting - Summer Trial')->firstOrFail();
    $metric = $campaign->dailyMetrics()->firstOrFail();

    expect($campaign->platform)->toBe('meta')
        ->and($metric->date->toDateString())->toBe('2026-06-28')
        ->and($metric->spend)->toBe('143.25')
        ->and($metric->impressions)->toBe(35812)
        ->and($metric->clicks)->toBe(684)
        ->and($metric->conversions)->toBe('19.00');

    $campaignWithTotals = Campaign::query()
        ->withMetricTotals()
        ->whereKey($campaign->id)
        ->firstOrFail();

    expect(round($campaignWithTotals->cpc, 2))->toBe(0.21)
        ->and(round($campaignWithTotals->cpm, 2))->toBe(4.00)
        ->and(round($campaignWithTotals->cpa, 2))->toBe(7.54);
});

it('imports Google Ads campaign reports with preamble rows', function () {
    app(CampaignCsvImporter::class)->import(fixture_path('google_campaign_report.csv'));

    expect(Campaign::query()->count())->toBe(2)
        ->and(DailyMetric::query()->count())->toBe(2);

    $campaign = Campaign::query()->where('name', 'Search - Nonbrand - US')->firstOrFail();
    $metric = $campaign->dailyMetrics()->firstOrFail();

    expect($campaign->platform)->toBe('google')
        ->and($metric->date->toDateString())->toBe('2026-06-28')
        ->and($metric->spend)->toBe('212.70')
        ->and($metric->impressions)->toBe(18420)
        ->and($metric->clicks)->toBe(482)
        ->and($metric->conversions)->toBe('31.00');
});

it('rejects malformed CSV files with a clear error', function () {
    expect(fn () => app(CampaignCsvImporter::class)->import(fixture_path('malformed_campaign_export.csv')))
        ->toThrow(CsvImportException::class, 'Unrecognized Meta Ads or Google Ads CSV headers.');
});

it('upserts repeated imports without duplicate campaigns or daily rows', function () {
    $importer = app(CampaignCsvImporter::class);

    $importer->import(fixture_path('meta_campaign_export.csv'));
    $importer->import(fixture_path('meta_campaign_export.csv'));

    expect(Campaign::query()->count())->toBe(2)
        ->and(DailyMetric::query()->count())->toBe(2);
});

it('imports a CSV from the Filament campaign table action', function () {
    config(['app.key' => 'base64:YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE=']);

    actingAs(User::factory()->create());

    $upload = UploadedFile::fake()->createWithContent(
        'google_campaign_report.csv',
        file_get_contents(fixture_path('google_campaign_report.csv')),
    );

    Livewire::test(ListCampaigns::class)
        ->callAction(TestAction::make('importCsv'), data: [
            'csv' => $upload,
            'platform' => null,
        ])
        ->assertHasNoFormErrors()
        ->assertCanSeeTableRecords(Campaign::query()->get());

    expect(Campaign::query()->where('platform', 'google')->count())->toBe(2)
        ->and(DailyMetric::query()->count())->toBe(2);
});

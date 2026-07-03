<?php

use App\Filament\Resources\Campaigns\Pages\ListCampaigns;
use App\Models\Campaign;
use App\Models\DailyMetric;
use App\Models\Lead;
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

it('imports Taboola campaign exports into normalized rows', function () {
    app(CampaignCsvImporter::class)->import(fixture_path('taboola_campaign_export.csv'));

    expect(Campaign::query()->count())->toBe(2)
        ->and(DailyMetric::query()->count())->toBe(2);

    $campaign = Campaign::query()->where('name', 'Native - Trial')->firstOrFail();
    $metric = $campaign->dailyMetrics()->firstOrFail();

    expect($campaign->platform)->toBe('taboola')
        ->and($metric->date->toDateString())->toBe('2026-06-28')
        ->and($metric->spend)->toBe('82.50')
        ->and($metric->impressions)->toBe(42000)
        ->and($metric->clicks)->toBe(330)
        ->and($metric->conversions)->toBe('11.00');
});

it('imports TikTok campaign exports into normalized rows', function () {
    app(CampaignCsvImporter::class)->import(fixture_path('tiktok_campaign_export.csv'));

    expect(Campaign::query()->count())->toBe(3)
        ->and(DailyMetric::query()->count())->toBe(3);

    $campaign = Campaign::query()->where('name', 'TikTok - Trial')->firstOrFail();
    $metric = $campaign->dailyMetrics()->firstOrFail();

    expect($campaign->platform)->toBe('tiktok')
        ->and($metric->date->toDateString())->toBe('2026-06-28')
        ->and($metric->spend)->toBe('126.00')
        ->and($metric->impressions)->toBe(25000)
        ->and($metric->clicks)->toBe(700)
        ->and($metric->conversions)->toBe('14.00');
});

it('imports leads and calculates revenue and lead-quality metrics', function () {
    $importer = app(CampaignCsvImporter::class);

    $importer->import(fixture_path('tiktok_campaign_export.csv'));
    $importer->import(fixture_path('leads_export.csv'));
    $importer->import(fixture_path('leads_export.csv'));

    expect(Lead::query()->count())->toBe(4);

    $campaign = Campaign::query()
        ->withMetricTotals()
        ->withLeadTotals()
        ->where('name', 'TikTok - Trial')
        ->firstOrFail();

    $metric = $campaign->dailyMetrics()->firstOrFail();

    expect($metric->revenue)->toBe('240.00')
        ->and($campaign->revenue_total)->toBe(240.0)
        ->and($campaign->leads_total)->toBe(3)
        ->and($campaign->accepted_leads_total)->toBe(1)
        ->and($campaign->rejected_leads_total)->toBe(1)
        ->and($campaign->pending_leads_total)->toBe(1)
        ->and(round($campaign->cpl, 2))->toBe(42.00)
        ->and(round($campaign->roas, 2))->toBe(1.90)
        ->and(round($campaign->epc, 2))->toBe(0.34)
        ->and(round($campaign->lead_acceptance_rate, 2))->toBe(50.00);

    $zeroCampaign = Campaign::query()
        ->withMetricTotals()
        ->withLeadTotals()
        ->where('name', 'TikTok - Zero Clicks')
        ->firstOrFail();

    expect($zeroCampaign->roas)->toBeNull()
        ->and($zeroCampaign->epc)->toBeNull()
        ->and($zeroCampaign->lead_acceptance_rate)->toBe(100.0);

    $noLeadsCampaign = Campaign::query()
        ->withMetricTotals()
        ->withLeadTotals()
        ->where('name', 'TikTok - No Leads')
        ->firstOrFail();

    expect($noLeadsCampaign->cpl)->toBeNull()
        ->and($noLeadsCampaign->epc)->toBeNull()
        ->and($noLeadsCampaign->lead_acceptance_rate)->toBeNull();
});

it('rejects malformed CSV files with a clear error', function () {
    expect(fn () => app(CampaignCsvImporter::class)->import(fixture_path('malformed_campaign_export.csv')))
        ->toThrow(CsvImportException::class, 'Unrecognized campaign or leads CSV headers.');
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

it('imports leads from the Filament campaign table action', function () {
    config(['app.key' => 'base64:YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE=']);

    app(CampaignCsvImporter::class)->import(fixture_path('tiktok_campaign_export.csv'));

    actingAs(User::factory()->create());

    $upload = UploadedFile::fake()->createWithContent(
        'leads_export.csv',
        file_get_contents(fixture_path('leads_export.csv')),
    );

    Livewire::test(ListCampaigns::class)
        ->callAction(TestAction::make('importCsv'), data: [
            'csv' => $upload,
            'platform' => null,
        ])
        ->assertHasNoFormErrors();

    $campaign = Campaign::query()->where('name', 'TikTok - Trial')->firstOrFail();

    expect(Lead::query()->count())->toBe(4)
        ->and($campaign->dailyMetrics()->firstOrFail()->revenue)->toBe('240.00');
});

<?php

namespace App\Filament\Resources\Campaigns\Pages;

use App\Filament\Resources\Campaigns\CampaignResource;
use App\Services\CsvImports\CampaignCsvImporter;
use App\Services\CsvImports\CsvImportException;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListCampaigns extends ListRecords
{
    protected static string $resource = CampaignResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('importCsv')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->schema([
                    FileUpload::make('csv')
                        ->label('CSV file')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->storeFiles(false)
                        ->required(),
                    Select::make('platform')
                        ->label('Platform override')
                        ->options([
                            'meta' => 'Meta Ads',
                            'google' => 'Google Ads',
                            'taboola' => 'Taboola',
                            'tiktok' => 'TikTok',
                            'leads' => 'Leads',
                        ])
                        ->placeholder('Auto-detect')
                        ->native(false),
                ])
                ->action(function (array $data): void {
                    try {
                        $result = app(CampaignCsvImporter::class)->import(
                            $this->uploadedCsvPath($data['csv'] ?? null),
                            $data['platform'] ?? null,
                        );
                    } catch (CsvImportException $exception) {
                        throw ValidationException::withMessages([
                            'csv' => $exception->getMessage(),
                        ]);
                    }

                    Notification::make()
                        ->title("Imported {$result['campaigns']} campaigns and {$result['metrics']} daily rows.")
                        ->success()
                        ->send();
                }),
        ];
    }

    private function uploadedCsvPath(mixed $upload): string
    {
        if (is_array($upload)) {
            $upload = Arr::first($upload);
        }

        if ($upload instanceof TemporaryUploadedFile || $upload instanceof UploadedFile) {
            return $upload->getRealPath();
        }

        if (is_string($upload) && $upload !== '') {
            return Storage::disk('local')->path($upload);
        }

        throw ValidationException::withMessages([
            'csv' => 'Upload a CSV file.',
        ]);
    }
}

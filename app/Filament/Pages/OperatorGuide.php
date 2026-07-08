<?php

namespace App\Filament\Pages;

use App\Services\OperatorGuideService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class OperatorGuide extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'Operator Guide';

    protected static ?string $title = 'Operator Guide';

    protected string $view = 'filament.pages.operator-guide';

    /**
     * Last workflow result rendered on the page.
     *
     * @var array{workflow: string, summary: string, data: array<string, mixed>}|null
     */
    public ?array $result = null;

    /**
     * @return array<string, array{label: string, mode: string, keywords: list<string>}>
     */
    public function getAllowedWorkflowsProperty(): array
    {
        return app(OperatorGuideService::class)->allowedWorkflows();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->askAction(),
            $this->fillCampaignBriefAction(),
        ];
    }

    private function askAction(): Action
    {
        return Action::make('ask')
            ->label('Ask the guide')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->schema([
                Textarea::make('question')
                    ->label('What do you need?')
                    ->placeholder('e.g. "find stuck leads", "show risky campaigns", "prepare weekly report"')
                    ->required()
                    ->minLength(3),
            ])
            ->action(function (array $data): void {
                $service = app(OperatorGuideService::class);
                $question = (string) $data['question'];
                $workflow = $service->resolveIntent($question);

                if ($workflow === null) {
                    Notification::make()
                        ->title('That request is not on the guide allowlist.')
                        ->body('Try: find stuck leads, show risky campaigns, prepare weekly report, or fill campaign brief.')
                        ->warning()
                        ->send();

                    return;
                }

                if ($service->mode($workflow) === 'form_assist') {
                    Notification::make()
                        ->title('Use the "Fill campaign brief" action.')
                        ->body('Drafting a brief needs explicit confirmation before it is saved.')
                        ->info()
                        ->send();

                    return;
                }

                $this->result = $service->runReadWorkflow($workflow);

                $service->logInvocation(
                    actor: $this->actor(),
                    workflow: $workflow,
                    rawIntent: $question,
                    confirmed: false,
                );

                Notification::make()->title($this->result['summary'])->success()->send();
            });
    }

    private function fillCampaignBriefAction(): Action
    {
        return Action::make('fillCampaignBrief')
            ->label('Fill campaign brief')
            ->icon('heroicon-o-pencil-square')
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription('Drafts a campaign brief only — no live campaign is created and no ad-account writes happen. Review and confirm to save the draft.')
            ->modalSubmitActionLabel('Confirm & save draft')
            ->schema([
                TextInput::make('name')
                    ->label('Campaign name')
                    ->required(),
                Select::make('platform')
                    ->label('Platform')
                    ->options(['google' => 'Google', 'meta' => 'Meta', 'tiktok' => 'TikTok'])
                    ->required()
                    ->default('google'),
                TextInput::make('objective')
                    ->label('Objective')
                    ->required(),
                TextInput::make('daily_budget')
                    ->label('Daily budget (USD)')
                    ->numeric()
                    ->required()
                    ->default(config('growthops.approval.default_daily_budget')),
                TextInput::make('target_cpa')
                    ->label('Target CPA (USD)')
                    ->numeric(),
                Textarea::make('notes')
                    ->label('Notes'),
            ])
            ->action(function (array $data): void {
                $service = app(OperatorGuideService::class);

                $service->logInvocation(
                    actor: $this->actor(),
                    workflow: 'fill_campaign_brief',
                    rawIntent: 'fill campaign brief',
                    confirmed: true,
                    // Only structured fields enter the audit trail; free-text
                    // name/objective/notes are deliberately excluded so operator-
                    // authored prose never lands in the log verbatim.
                    details: [
                        'platform' => $data['platform'],
                        'daily_budget' => $data['daily_budget'],
                        'target_cpa' => $data['target_cpa'] ?? null,
                    ],
                );

                $this->result = [
                    'workflow' => 'fill_campaign_brief',
                    'summary' => "Draft brief saved for \"{$data['name']}\" ({$data['platform']}). Nothing was submitted to any ad account.",
                    'data' => $data,
                ];

                Notification::make()->title('Draft brief saved — no live campaign created.')->success()->send();
            });
    }

    private function actor(): string
    {
        return auth()->user()?->email ?? config('growthops.approval.actor');
    }
}

<?php

namespace App\Services;

use App\Models\ActionAudit;
use App\Models\ExecutionLog;
use App\Models\RecommendedAction;
use Illuminate\Support\Facades\DB;

final class ActionDecisionService
{
    public function __construct(private SimulatedExecutionBuilder $builder) {}

    public function approve(RecommendedAction $action): ExecutionLog
    {
        return $this->execute($action, 'approved', $this->builder->defaultParameter($action), null);
    }

    public function editThenApprove(RecommendedAction $action, float $parameter): ExecutionLog
    {
        $original = $this->builder->defaultParameter($action);

        $editedValue = [
            'key' => $this->builder->parameterKey($action->type),
            'original' => $original,
            'value' => $parameter,
        ];

        return $this->execute($action, 'edited', $parameter, $editedValue);
    }

    public function reject(RecommendedAction $action, string $reason): void
    {
        DB::transaction(function () use ($action, $reason): void {
            ActionAudit::create([
                'recommended_action_id' => $action->id,
                'actor' => config('growthops.approval.actor'),
                'from_status' => $action->status,
                'to_status' => 'rejected',
                'reason' => $reason,
                'edited_value' => null,
            ]);

            $action->update(['status' => 'rejected']);
        });
    }

    /**
     * @param  array<string, mixed>|null  $editedValue
     */
    private function execute(RecommendedAction $action, string $toStatus, float $parameter, ?array $editedValue): ExecutionLog
    {
        return DB::transaction(function () use ($action, $toStatus, $parameter, $editedValue): ExecutionLog {
            $simulated = $this->builder->build($action, $parameter);

            $log = ExecutionLog::create([
                'recommended_action_id' => $action->id,
                'status' => 'simulated',
                ...$simulated,
            ]);

            ActionAudit::create([
                'recommended_action_id' => $action->id,
                'actor' => config('growthops.approval.actor'),
                'from_status' => $action->status,
                'to_status' => $toStatus,
                'reason' => null,
                'edited_value' => $editedValue,
            ]);

            $action->update([
                'status' => $toStatus,
                'applied_parameter' => $parameter,
            ]);

            return $log;
        });
    }
}

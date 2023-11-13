<?php

namespace RingleSoft\LaravelProcessApproval;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RingleSoft\LaravelProcessApproval\Contracts\ProcessApprovalContract;
use RingleSoft\LaravelProcessApproval\Exceptions\ApprovalFlowDoesNotExistsException;
use RingleSoft\LaravelProcessApproval\Exceptions\ApprovalFlowExistsException;
use RingleSoft\LaravelProcessApproval\Exceptions\ApprovalFlowModelDoesNotExistsException;
use RingleSoft\LaravelProcessApproval\Exceptions\ApprovalFlowStepDoesNotExistsException;
use RingleSoft\LaravelProcessApproval\Models\ProcessApprovalFlow;
use RingleSoft\LaravelProcessApproval\Models\ProcessApprovalFlowStep;

class ProcessApproval implements ProcessApprovalContract
{
    public function __construct()
    {
    }

    public function flows(): \Illuminate\Database\Eloquent\Collection|array
    {
        return ProcessApprovalFlow::query()->with('steps.role')->get();
    }

    public function flowsWithSteps(): \Illuminate\Database\Eloquent\Collection|array
    {
        return  ProcessApprovalFlow::query()->with(['steps', 'steps.role'])->whereHas('steps')->get();
    }

    public function steps(): \Illuminate\Database\Eloquent\Collection|array
    {
        return ProcessApprovalFlowStep::query()->with('role')->get();
    }

    public function createFlow(string $name, string $modelClass): ProcessApprovalFlow
    {
        if (!Str::contains($modelClass, '\\')) {
            $modelClass = config('process_approval.models_path') . "\\{$modelClass}";
        }
        if (!class_exists($modelClass)) {
            throw ApprovalFlowModelDoesNotExistsException::create($modelClass);
        }
        if(ProcessApprovalFlow::query()->where('approvable_type', trim($modelClass, '\\'))->exists()) {
            throw ApprovalFlowExistsException::create($name, $modelClass);
        }
        return ProcessApprovalFlow::query()->create([
            'name' => $name,
            'approvable_type' => get_class(new $modelClass()),
        ]);
    }

    public function deleteFlow(int $flowId): bool|null
    {
        $approvalFlow = ProcessApprovalFlow::find($flowId);
        if(!$approvalFlow){
            throw ApprovalFlowDoesNotExistsException::create();
        }
        DB::beginTransaction();
        try {
            $approvalFlow->steps()->delete();
            $approvalFlow->delete();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
        DB::commit();
        return true;
    }

    public function deleteStep(int $stepId): ?bool
    {
        $step = ProcessApprovalFlowStep::find($stepId);
        if(!$step){
            throw ApprovalFlowStepDoesNotExistsException::create();
        }
        return $step->delete();
    }


    public function createStep(int $flowId, int $roleId, string|null $action = 'APPROVE'): ProcessApprovalFlowStep
    {
        $flow = ProcessApprovalFlow::find($flowId);
        if(!$flow){
            throw ApprovalFlowDoesNotExistsException::create();
        }
        $step = $flow->steps()->create([
            'role_id' => $roleId,
            'action' => $action,
            'active' => 1
        ]);
        return $step;
    }
}
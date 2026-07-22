<?php

namespace App\Http\Controllers\Api\V1\Communication;

use App\Enums\Communication\GatewayCommandType;
use App\Enums\Communication\InboxStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\StoreInboxRequest;
use App\Http\Requests\Communication\UpdateInboxRequest;
use App\Http\Resources\Communication\CommunicationInboxResource;
use App\Models\CommunicationInbox;
use App\Models\CommunicationInboxMember;
use App\Models\OfficeMembership;
use App\Models\User;
use App\Models\WorkDepartment;
use App\Services\Communication\Authorization\CommunicationAccess;
use App\Services\Communication\Events\CommunicationEventRecorder;
use App\Services\Communication\Outbox\CommunicationOutboxService;
use App\Services\Communication\Pairing\CommunicationPairingStateStore;
use App\Support\CurrentOffice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CommunicationInboxController extends Controller
{
    public function __construct(
        private readonly CurrentOffice $currentOffice,
        private readonly CommunicationAccess $access,
        private readonly CommunicationOutboxService $outbox,
        private readonly CommunicationPairingStateStore $pairing,
        private readonly CommunicationEventRecorder $events,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $this->access->assertView($actor);
        $ids = $this->access->visibleInboxIds($actor);
        $inboxes = CommunicationInbox::query()
            ->whereIn('id', $ids)
            ->with(['members' => fn ($query) => $query
                ->where('is_active', true)->with('membership.user')])
            ->withCount(['members' => fn ($query) => $query->where('is_active', true)])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => CommunicationInboxResource::collection($inboxes),
            'meta' => [
                'global_enabled' => (bool) config('communication.enabled'),
                'gateway_enabled' => (bool) config('communication.gateway.enabled'),
                'office_enabled' => (bool) $this->currentOffice->office()->communication_enabled,
                'departments' => WorkDepartment::query()->where('is_active', true)
                    ->orderBy('name')->get(['id', 'name', 'code', 'color'])
                    ->map(fn (WorkDepartment $department) => [
                        'id' => (int) $department->id,
                        'name' => $department->name,
                        'code' => $department->code,
                        'color' => $department->color,
                        'is_active' => true,
                    ])->values(),
            ],
        ]);
    }

    public function store(StoreInboxRequest $request): JsonResponse
    {
        $actor = $this->actor($request);
        $this->access->assertManage($actor);
        $office = $this->currentOffice->office();
        $data = $request->validated();
        $departmentId = $this->departmentId($data['work_department_id'] ?? null, (int) $office->id);

        $inbox = DB::transaction(function () use ($office, $data, $departmentId): CommunicationInbox {
            if (($data['is_default'] ?? false) === true) {
                CommunicationInbox::query()->where('office_id', $office->id)->update(['is_default' => false]);
            }

            return CommunicationInbox::query()->create([
                'office_id' => $office->id,
                'name' => trim((string) $data['name']),
                'session_id' => 'session-'.strtolower((string) Str::ulid()),
                'status' => InboxStatus::Disabled,
                'is_enabled' => (bool) ($data['is_enabled'] ?? false),
                'is_default' => (bool) ($data['is_default'] ?? false),
                'work_department_id' => $departmentId,
            ]);
        });
        $this->events->record((int) $office->id, 'INBOX_CREATED', [
            'inbox_id' => (int) $inbox->id,
            'name' => $inbox->name,
        ], inboxId: (int) $inbox->id, actorMembershipId: $this->currentOffice->realMembership()?->id);

        return (new CommunicationInboxResource($inbox))->response()->setStatusCode(201);
    }

    public function update(UpdateInboxRequest $request, int $inbox): JsonResponse
    {
        $actor = $this->actor($request);
        $model = $this->inbox($inbox);
        $this->access->assertManage($actor, $model);
        $data = $request->validated();
        $departmentId = array_key_exists('work_department_id', $data)
            ? $this->departmentId($data['work_department_id'], (int) $model->office_id)
            : $model->work_department_id;

        $updated = DB::transaction(function () use ($model, $data, $departmentId): ?CommunicationInbox {
            if (($data['is_default'] ?? false) === true) {
                CommunicationInbox::query()->where('office_id', $model->office_id)
                    ->where('id', '<>', $model->id)->update(['is_default' => false]);
            }
            $attributes = array_intersect_key($data, array_flip(['name', 'is_enabled', 'is_default']));
            $attributes['work_department_id'] = $departmentId;
            $attributes['lock_version'] = (int) $data['lock_version'] + 1;
            $changed = CommunicationInbox::query()
                ->whereKey($model->id)
                ->where('lock_version', $data['lock_version'])
                ->update($attributes);

            return $changed === 1 ? $model->fresh() : null;
        });
        if ($updated === null) {
            return response()->json(['message' => 'Inbox foi alterada por outro usuário.', 'code' => 'version_conflict'], 409);
        }
        $this->events->record((int) $updated->office_id, 'INBOX_UPDATED', [
            'inbox_id' => (int) $updated->id,
            'lock_version' => (int) $updated->lock_version,
        ], inboxId: (int) $updated->id, actorMembershipId: $this->currentOffice->realMembership()?->id);

        return (new CommunicationInboxResource($updated))->response();
    }

    public function updateOfficeSettings(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $this->access->assertManage($actor);
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        $office = $this->currentOffice->office();

        if ($office->communication_enabled && ! $data['enabled']
            && config('communication.enabled') && config('communication.gateway.enabled')) {
            CommunicationInbox::query()->where('is_enabled', true)->each(function (CommunicationInbox $inbox): void {
                $this->outbox->enqueue($inbox, GatewayCommandType::LogoutSession, []);
            });
        }
        $office->forceFill(['communication_enabled' => (bool) $data['enabled']])->save();
        $this->events->record((int) $office->id, 'OFFICE_COMMUNICATION_SWITCH_CHANGED', [
            'enabled' => (bool) $office->communication_enabled,
        ], actorMembershipId: $this->currentOffice->realMembership()?->id);

        return response()->json(['data' => ['enabled' => (bool) $office->communication_enabled]]);
    }

    public function startPairing(Request $request, int $inbox): JsonResponse
    {
        $model = $this->inbox($inbox);
        $this->access->assertManage($this->actor($request), $model);
        $provision = $this->outbox->enqueue(
            $model,
            GatewayCommandType::ProvisionSession,
            ['desired_connected' => true],
        );
        $pair = $this->outbox->enqueue($model, GatewayCommandType::PairSession, []);
        $model->forceFill([
            'status' => InboxStatus::Provisioned,
            'lock_version' => (int) $model->lock_version + 1,
        ])->save();

        return response()->json(['data' => [
            'status' => 'PROVISIONING',
            'commands' => [$provision->command_id, $pair->command_id],
        ]], 202);
    }

    public function pairing(Request $request, int $inbox): JsonResponse
    {
        $model = $this->inbox($inbox);
        $this->access->assertManage($this->actor($request), $model);
        $state = $this->pairing->get((int) $model->id);

        return response()->json(['data' => $state ?? [
            'event' => null,
            'status' => $model->status?->value ?? $model->status,
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function revoke(Request $request, int $inbox): JsonResponse
    {
        $model = $this->inbox($inbox);
        $this->access->assertManage($this->actor($request), $model);
        $entry = $this->outbox->enqueue($model, GatewayCommandType::LogoutSession, []);
        $model->forceFill([
            'is_enabled' => false,
            'status' => InboxStatus::Revoked,
            'revoked_at' => now(),
            'lock_version' => (int) $model->lock_version + 1,
        ])->save();
        $this->pairing->forget((int) $model->id);

        return response()->json(['data' => ['command_id' => $entry->command_id]], 202);
    }

    public function replaceMembers(Request $request, int $inbox): JsonResponse
    {
        $model = $this->inbox($inbox);
        $this->access->assertManage($this->actor($request), $model);
        $data = $request->validate(['membership_ids' => ['present', 'array', 'max:500'], 'membership_ids.*' => ['integer', 'min:1']]);
        $ids = array_values(array_unique(array_map('intval', $data['membership_ids'])));
        $valid = OfficeMembership::query()->where('office_id', $model->office_id)
            ->where('is_active', true)->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (count($valid) !== count($ids)) {
            return response()->json(['message' => 'Membership inválida para este escritório.'], 422);
        }

        DB::transaction(function () use ($model, $ids): void {
            CommunicationInboxMember::query()->withoutGlobalScopes()->where('inbox_id', $model->id)->delete();
            foreach ($ids as $membershipId) {
                CommunicationInboxMember::query()->withoutGlobalScopes()->create([
                    'office_id' => $model->office_id,
                    'inbox_id' => $model->id,
                    'office_membership_id' => $membershipId,
                    'is_active' => true,
                ]);
            }
        });

        return response()->json(['data' => ['membership_ids' => $ids]]);
    }

    private function inbox(int $id): CommunicationInbox
    {
        return CommunicationInbox::query()->findOrFail($id);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function departmentId(mixed $id, int $officeId): ?int
    {
        if ($id === null) {
            return null;
        }
        $exists = WorkDepartment::query()->withoutGlobalScopes()
            ->where('office_id', $officeId)->whereKey((int) $id)->exists();
        abort_unless($exists, 422, 'Departamento inválido para este escritório.');

        return (int) $id;
    }
}

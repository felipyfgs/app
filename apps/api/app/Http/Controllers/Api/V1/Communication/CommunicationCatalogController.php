<?php

namespace App\Http\Controllers\Api\V1\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\StoreCannedResponseRequest;
use App\Models\CommunicationCannedResponse;
use App\Models\CommunicationLabel;
use App\Models\User;
use App\Services\Communication\Authorization\CommunicationAccess;
use App\Support\CurrentOffice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CommunicationCatalogController extends Controller
{
    public function __construct(
        private readonly CommunicationAccess $access,
        private readonly CurrentOffice $currentOffice,
    ) {}

    public function labels(Request $request): JsonResponse
    {
        $this->access->assertView($this->actor($request));

        return response()->json(['data' => CommunicationLabel::query()->orderBy('name')->get()->map(fn ($label) => [
            'id' => $label->id,
            'name' => $label->name,
            'color' => $label->color,
        ])]);
    }

    public function storeLabel(Request $request): JsonResponse
    {
        $this->access->assertManage($this->actor($request));
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'regex:/^(neutral|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)$/'],
        ]);
        $label = CommunicationLabel::query()->create([
            'office_id' => $this->currentOffice->office()->id,
            'name' => trim($data['name']),
            'color' => $data['color'] ?? 'neutral',
        ]);

        return response()->json(['data' => ['id' => $label->id, 'name' => $label->name, 'color' => $label->color]], 201);
    }

    public function deleteLabel(Request $request, int $label): JsonResponse
    {
        $model = CommunicationLabel::query()->findOrFail($label);
        $this->access->assertManage($this->actor($request), $model);
        $model->delete();

        return response()->json(status: 204);
    }

    public function cannedResponses(Request $request): JsonResponse
    {
        $this->access->assertView($this->actor($request));
        $query = CommunicationCannedResponse::query()->where('is_active', true);
        if ($search = trim($request->string('q')->toString())) {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(fn ($builder) => $builder
                ->whereRaw('LOWER(title) LIKE ?', [$needle])
                ->orWhereRaw('LOWER(shortcut) LIKE ?', [$needle]));
        }

        return response()->json(['data' => $query->orderBy('shortcut')->get()->map(fn ($item) => [
            'id' => $item->id,
            'title' => $item->title,
            'shortcut' => $item->shortcut,
            'body' => $item->body_encrypted,
            'is_active' => (bool) $item->is_active,
        ])]);
    }

    public function storeCannedResponse(StoreCannedResponseRequest $request): JsonResponse
    {
        $this->access->assertManage($this->actor($request));
        $data = $request->validated();
        $item = CommunicationCannedResponse::query()->create([
            'office_id' => $this->currentOffice->office()->id,
            'title' => trim($data['title']),
            'shortcut' => strtolower(trim($data['shortcut'])),
            'body_encrypted' => $data['body'],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'created_by_membership_id' => $this->currentOffice->realMembership()?->id,
        ]);

        return response()->json(['data' => [
            'id' => $item->id,
            'title' => $item->title,
            'shortcut' => $item->shortcut,
            'body' => $item->body_encrypted,
            'is_active' => (bool) $item->is_active,
        ]], 201);
    }

    public function updateCannedResponse(StoreCannedResponseRequest $request, int $canned): JsonResponse
    {
        $model = CommunicationCannedResponse::query()->findOrFail($canned);
        $this->access->assertManage($this->actor($request), $model);
        $data = $request->validated();
        $model->fill([
            'title' => trim($data['title']),
            'shortcut' => strtolower(trim($data['shortcut'])),
            'body_encrypted' => $data['body'],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ])->save();

        return response()->json(['data' => [
            'id' => $model->id,
            'title' => $model->title,
            'shortcut' => $model->shortcut,
            'body' => $model->body_encrypted,
            'is_active' => (bool) $model->is_active,
        ]]);
    }

    public function deleteCannedResponse(Request $request, int $canned): JsonResponse
    {
        $model = CommunicationCannedResponse::query()->findOrFail($canned);
        $this->access->assertManage($this->actor($request), $model);
        $model->delete();

        return response()->json(status: 204);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}

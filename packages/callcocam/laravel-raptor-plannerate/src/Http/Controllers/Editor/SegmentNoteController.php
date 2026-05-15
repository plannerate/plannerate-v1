<?php

namespace Callcocam\LaravelRaptorPlannerate\Http\Controllers\Editor;

use App\Models\User;
use App\Models\WorkflowGondolaExecution;
use App\Models\WorkflowPlanogramStep;
use App\Notifications\AppNotification;
use Callcocam\LaravelRaptorPlannerate\Http\Controllers\Controller;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Gondola;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\Segment;
use Callcocam\LaravelRaptorPlannerate\Models\Editor\SegmentNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SegmentNoteController extends Controller
{
    public function index(string $subdomain, string $segment): JsonResponse
    {
        unset($subdomain);

        $notes = SegmentNote::where('segment_id', $segment)
            ->with('user:id,name')
            ->latest()
            ->get()
            ->map(fn (SegmentNote $note) => [
                'id' => $note->id,
                'content' => $note->content,
                'author' => $note->user?->name ?? 'Usuário',
                'created_at' => $note->created_at?->format('d/m/Y H:i'),
            ]);

        return response()->json(['data' => $notes]);
    }

    public function store(Request $request, string $subdomain, string $segment): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $segmentModel = Segment::with(['shelf.section'])->findOrFail($segment);
        $gondolaId = $segmentModel->shelf?->section?->gondola_id;
        $tenantId = $request->user()?->tenant_id ?? $segmentModel->tenant_id;

        $note = SegmentNote::create([
            'tenant_id' => $tenantId,
            'gondola_id' => $gondolaId,
            'segment_id' => $segment,
            'user_id' => $request->user()?->id,
            'content' => $validated['content'],
        ]);

        $this->notifyResponsibleUsers($gondolaId, $tenantId, $subdomain, $note, $request->user()?->name ?? 'Alguém');

        return response()->json([
            'data' => [
                'id' => $note->id,
                'content' => $note->content,
                'author' => $request->user()?->name ?? 'Usuário',
                'created_at' => $note->created_at?->format('d/m/Y H:i'),
            ],
        ], 201);
    }

    private function notifyResponsibleUsers(?string $gondolaId, ?string $tenantId, string $subdomain, SegmentNote $note, string $authorName): void
    {
        if (! $gondolaId) {
            return;
        }

        $planogramId = Gondola::where('id', $gondolaId)->value('planogram_id');
        $recipientIds = $this->resolveRecipientIds($gondolaId, $planogramId, $note->user_id);

        if ($recipientIds->isEmpty()) {
            return;
        }

        $routeParameters = ['subdomain' => $subdomain];
        if ($planogramId !== null) {
            $routeParameters['planogram_id'] = $planogramId;
        }

        $notification = new AppNotification(
            title: 'Nova nota em segmento',
            message: sprintf('%s adicionou: "%s"', $authorName, mb_strimwidth($note->content, 0, 80, '…')),
            type: 'segment_note',
            actionUrl: route('tenant.kanban.index', $routeParameters),
            tenantId: $tenantId,
        );

        User::whereIn('id', $recipientIds)
            ->get()
            ->each(fn ($user) => $user->notifyNow($notification));
    }

    private function resolveRecipientIds(string $gondolaId, ?string $planogramId, ?string $excludeUserId): Collection
    {
        $responsibleIds = WorkflowGondolaExecution::where('gondola_id', $gondolaId)
            ->whereNotNull('current_responsible_id')
            ->pluck('current_responsible_id')
            ->unique()
            ->filter(fn ($id) => $id !== $excludeUserId)
            ->values();

        if ($responsibleIds->isNotEmpty()) {
            return $responsibleIds;
        }

        if (! $planogramId) {
            return collect();
        }

        return WorkflowPlanogramStep::where('planogram_id', $planogramId)
            ->with('availableUsers:id')
            ->get()
            ->flatMap(fn ($step) => $step->availableUsers->pluck('id'))
            ->unique()
            ->filter(fn ($id) => $id !== $excludeUserId)
            ->values();
    }
}

<?php

namespace App\Http\Controllers\Tenant;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class NotificationController extends Controller
{
    public function markRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        $notification->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        $request->user()->notifications()->delete();

        return back();
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $request->user()
            ->notifications()
            ->findOrFail($id)
            ->delete();

        return back();
    }

    public function download(Request $request, string $id): BinaryFileResponse|Response
    {
        $notification = $request->user()
            ->notifications()
            ->findOrFail($id);

        $filePath = $notification->data['download_url'] ?? null;

        if (! $filePath || ! Storage::disk('local')->exists($filePath)) {
            abort(404);
        }

        $downloadName = $notification->data['download_name'] ?? basename($filePath);

        return response()->download(
            Storage::disk('local')->path($filePath),
            $downloadName,
        );
    }
}

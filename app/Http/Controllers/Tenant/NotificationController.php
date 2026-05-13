<?php

namespace App\Http\Controllers\Tenant;

use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class NotificationController extends Controller
{
    public function markRead(Request $request, string $subdomain, string $id): RedirectResponse
    {
        unset($subdomain); // não é necessário, mas evita warnings de variável não usada
        $tenantId = (string) (Tenant::current()?->getKey() ?? '');
        $notification = $request->user()
            ->notifications()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        $notification->markAsRead();

        return back();
    }

    public function markAllRead(Request $request, string $subdomain): RedirectResponse
    {
        unset($subdomain); // não é necessário, mas evita warnings de variável não usada
        $tenantId = (string) (Tenant::current()?->getKey() ?? '');
        $request->user()->unreadNotifications()
            ->where('tenant_id', $tenantId)
            ->update(['read_at' => now()]);

        return back();
    }

    public function destroyAll(Request $request, string $subdomain): RedirectResponse
    {
        unset($subdomain); // não é necessário, mas evita warnings de variável não usada
        $tenantId = (string) (Tenant::current()?->getKey() ?? '');
        $request->user()->notifications()
            ->where('tenant_id', $tenantId)
            ->delete();

        return back();
    }

    public function destroy(Request $request, string $subdomain, string $id): RedirectResponse
    {
        unset($subdomain); // não é necessário, mas evita warnings de variável não usada
        $tenantId = (string) (Tenant::current()?->getKey() ?? '');
        $request->user()
            ->notifications()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id)
            ->delete();

        return back();
    }

    public function download(Request $request, string $subdomain, string $id): BinaryFileResponse|Response
    {
        unset($subdomain); // não é necessário, mas evita warnings de variável não usada
        $tenantId = (string) (Tenant::current()?->getKey() ?? '');
        $notification = $request->user()
            ->notifications()
            ->where('tenant_id', $tenantId)
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

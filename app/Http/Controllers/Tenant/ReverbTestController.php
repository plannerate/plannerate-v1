<?php

namespace App\Http\Controllers\Tenant;

use App\Notifications\AppNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ReverbTestController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('tenant/ReverbTest', [
            'user' => $request->user()->only('id', 'name', 'email'),
        ]);
    }

    public function notify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:500'],
            'type' => ['required', 'in:info,success,warning,error'],
            'download_url' => ['nullable', 'string', 'max:255'],
            'download_name' => ['nullable', 'string', 'max:100'],
        ]);

        $request->user()->notify(new AppNotification(
            title: $validated['title'],
            message: $validated['message'],
            type: $validated['type'],
            downloadUrl: $validated['download_url'] ?? null,
            downloadName: $validated['download_name'] ?? null,
        ));

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Notificação enviada!',
        ]);
    }
}

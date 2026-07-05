<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20)
            ->through(fn ($notification) => $this->formatNotification($notification));

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
        ]);
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()->notifications()->where('id', $notification)->firstOrFail();

        if ($item->read_at === null) {
            $item->markAsRead();
        }

        $url = $item->data['url'] ?? route('notifications.index');

        return redirect($url);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'All notifications marked as read.');
    }

    /**
     * @return array<string, mixed>
     */
    public static function formatNotification(object $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->data['type'] ?? 'general',
            'title' => $notification->data['title'] ?? 'Notification',
            'message' => $notification->data['message'] ?? '',
            'url' => $notification->data['url'] ?? null,
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at,
        ];
    }
}

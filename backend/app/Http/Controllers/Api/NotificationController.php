<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->requireUser($request);

        $paginator = $user->notifications()->cursorPaginate(20);

        return $this->paginated($paginator, fn ($n) => [
            'id' => $n->id,
            'type' => $n->data['type'] ?? null,
            'title' => $n->data['title'] ?? null,
            'body' => $n->data['body'] ?? null,
            'url' => $n->data['url'] ?? null,
            'icon_url' => $n->data['icon_url'] ?? $n->data['cover_image_url'] ?? null,
            'read' => $n->read_at !== null,
            'created_at' => $n->created_at,
        ]);
    }

    public function unreadCount(Request $request)
    {
        $user = $this->requireUser($request);

        return $this->ok(['count' => $user->unreadNotifications()->count()]);
    }

    public function markRead(Request $request, string $id)
    {
        $user = $this->requireUser($request);
        $notification = $user->notifications()->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return $this->ok(['read' => true]);
    }

    public function markAllRead(Request $request)
    {
        $user = $this->requireUser($request);
        $user->unreadNotifications->markAsRead();

        return $this->ok(['read' => true]);
    }
}

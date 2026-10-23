<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function send(User|int $recipient, string $eventType, string $title, string $message, array $options = []): ?Notification
    {
        $userId = $recipient instanceof User ? $recipient->id : $recipient;
        $eventType = preg_match('/^[a-z0-9_.-]+$/', $eventType) ? $eventType : 'system.notice';
        $type = in_array($options['type'] ?? null, Notification::TYPES, true) ? $options['type'] : 'info';
        $icon = in_array($options['icon'] ?? null, Notification::ICONS, true) ? $options['icon'] : 'bell';
        $actionType = in_array($options['action_type'] ?? null, Notification::ACTION_TYPES, true)
            ? $options['action_type']
            : null;
        $actionId = filter_var($options['action_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $dedupeKey = isset($options['dedupe_key'])
            ? substr(hash('sha256', (string) $options['dedupe_key']), 0, 64)
            : null;

        $attributes = [
            'title' => mb_substr(trim(strip_tags($title)), 0, 255),
            'message' => trim(strip_tags($message)),
            'icon' => $icon,
            'type' => $type,
            'event_type' => mb_substr($eventType, 0, 80),
            'action_type' => $actionType,
            'action_id' => $actionType && $actionId !== false ? $actionId : null,
            'is_read' => false,
        ];

        try {
            if ($dedupeKey) {
                return Notification::firstOrCreate(
                    ['user_id' => $userId, 'dedupe_key' => $dedupeKey],
                    $attributes,
                );
            }

            return Notification::create(['user_id' => $userId, ...$attributes]);
        } catch (QueryException $exception) {
            if ($dedupeKey && ($existing = Notification::where('user_id', $userId)->where('dedupe_key', $dedupeKey)->first())) {
                return $existing;
            }

            Log::error('Notification creation failed.', [
                'user_id' => $userId,
                'event_type' => $eventType,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function sendToRole(string $role, string $eventType, string $title, string $message, array $options = []): void
    {
        User::query()
            ->where('role', $role)
            ->where('account_status', User::ACCOUNT_ACTIVE)
            ->eachById(fn (User $user) => $this->send($user, $eventType, $title, $message, $options));
    }
}

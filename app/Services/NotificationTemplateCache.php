<?php

namespace App\Services;

use App\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class NotificationTemplateCache
{
    private const TTL_SECONDS = 300;
    private const KEY_BY_ID = 'notification_templates:by_id:%s';
    private const KEY_RECENT = 'notification_templates:recent:%d';

    public function getById(string $id): ?NotificationTemplate
    {
        $key = sprintf(self::KEY_BY_ID, $id);

        return Cache::remember($key, self::TTL_SECONDS, function () use ($id) {
            return NotificationTemplate::query()->find($id);
        });
    }

    public function getOrFail(string $id): NotificationTemplate
    {
        $template = $this->getById($id);
        if ($template) {
            return $template;
        }

        throw (new ModelNotFoundException())
            ->setModel(NotificationTemplate::class, [$id]);
    }

    public function forget(string $id): void
    {
        Cache::forget(sprintf(self::KEY_BY_ID, $id));
    }

    /**
     * @return Collection<int, NotificationTemplate>
     */
    public function recent(int $limit = 10): Collection
    {
        $key = sprintf(self::KEY_RECENT, $limit);

        return Cache::remember($key, self::TTL_SECONDS, function () use ($limit) {
            return NotificationTemplate::query()
                ->latest('created_at')
                ->limit($limit)
                ->get();
        });
    }

    public function forgetRecent(int $limit = 10): void
    {
        Cache::forget(sprintf(self::KEY_RECENT, $limit));
    }
}

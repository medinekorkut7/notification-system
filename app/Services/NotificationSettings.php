<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NotificationSettings
{
    private const KEY = 'notifications.settings';

    public function get(string $name, ?string $default = null): ?string
    {
        $settings = $this->all();

        return $settings[$name] ?? $default;
    }

    /**
     * @return array<string, string|null>
     */
    public function all(): array
    {
        return Cache::remember(self::KEY, 60, function () {
            $rows = DB::table('notification_settings')->get();
            $values = [];
            foreach ($rows as $row) {
                $values[$row->name] = $row->value;
            }
            return $values;
        });
    }

    public function set(string $name, ?string $value): void
    {
        DB::table('notification_settings')->updateOrInsert(
            ['name' => $name],
            ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
        );

        Cache::forget(self::KEY);
    }
}

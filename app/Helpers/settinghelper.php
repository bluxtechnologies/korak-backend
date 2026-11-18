<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    /**
     * Get or set site settings stored in the settings table.
     *
     * setting('site_name') returns the stored value, or $default when missing.
     * setting(['site_name' => 'Korak']) creates or updates one or more keys.
     * setting() returns every setting as a key/value map.
     *
     * @param  string|array<string, mixed>|null  $key
     */
    function setting(string|array|null $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return Setting::allCached();
        }

        if (is_array($key)) {
            foreach ($key as $name => $value) {
                Setting::setValue((string) $name, $value);
            }

            return true;
        }

        return Setting::getValue($key, $default);
    }
}

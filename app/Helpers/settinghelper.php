<?php

use App\Models\Setting;
use Illuminate\Support\Str;

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

if (! function_exists('fake')) {
    /**
     * Fallback generator when Faker is not installed (e.g. composer --no-dev in production).
     */
    function fake(?string $locale = null): object
    {
        return new class
        {
            public function unique(): static
            {
                return $this;
            }

            public function __call(string $method, array $parameters): mixed
            {
                return match ($method) {
                    'userName' => 'user_'.Str::lower(Str::random(8)),
                    'safeEmail', 'email' => 'user_'.Str::lower(Str::random(8)).'@example.com',
                    'e164PhoneNumber', 'phoneNumber' => '+23480'.random_int(10000000, 99999999),
                    'sentence' => 'Sample test sentence '.Str::random(6),
                    'paragraph' => 'Sample test paragraph description text.',
                    'slug' => 'slug-'.Str::lower(Str::random(6)),
                    'company', 'name' => 'Korak Digital',
                    'randomElement' => isset($parameters[0]) && is_array($parameters[0]) && count($parameters[0]) > 0
                        ? $parameters[0][array_rand($parameters[0])]
                        : null,
                    default => 'test_'.Str::random(6),
                };
            }
        };
    }
}

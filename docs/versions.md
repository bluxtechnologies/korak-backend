# Pinned Dependency Versions — `korak-api`

Per Doc 02 (§4 Version-pinning rule), exact installed versions recorded from the runtime environment and `composer.lock`. Upgrades are deliberate, one dependency group at a time, with tests passing before and after.

## Runtime & Core
- **PHP**: `8.4.24` (minimum in `composer.json`: `^8.3`; target runtime per Doc 02: `8.4`)
- **Composer**: `2.10.3`
- **laravel/framework**: `13.42.0`
- **laravel/sanctum**: `4.4.0`
- **laravel/tinker**: `3.1.0`
- **nesbot/carbon**: `3.15.1`

## Testing
- **pestphp/pest**: `4.7.8`
- **pestphp/pest-plugin-laravel**: `4.1.0`
- **phpunit/phpunit**: `12.5.33`
- **brianium/paratest**: `7.20.0`
- **fakerphp/faker**: `1.24.1`
- **mockery/mockery**: `1.6.15`
- **nunomaduro/collision**: `8.9.6`

## Code Quality & Static Analysis
- **laravel/pint**: `1.32.2`
- **larastan/larastan**: `3.15.0`
- **phpstan/phpstan**: `2.3.5`

## Developer Tooling
- **laravel/pail**: `1.3.0`
- **laravel/pao**: `1.1.5`

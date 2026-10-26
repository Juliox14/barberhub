<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
# barberhub

## Customer account invitations

Barbería owners and administrators can create a personal customer invitation link from the agenda. A barber can issue one only for a customer with appointment history assigned to that barber. Links are manually shared, expire after 48 hours, and can be used once. For a new account, the link opens a form with the customer name and phone prefilled and editable, the invitation email fixed, and password and confirmation fields; submitting it creates the client account, updates and links the existing customer record, and signs the new account in. An existing account must sign in and explicitly confirm linkage; the invitation never changes its password. Existing appointment records remain attached to the same customer record.

## Barber PWA notifications

Push notifications are sent to the assigned barber for appointments created by someone else, reschedules and cancellations. They use generic copy and link to the tenant agenda; appointment and customer details are not included in the notification.

Configure these values in the deployment secret store (or local environment, never in source control):

```text
WEBPUSH_PUBLIC_KEY=<base64url VAPID public key>
WEBPUSH_PRIVATE_KEY=<base64url VAPID private key; keep secret>
WEBPUSH_SUBJECT=mailto:admin@example.com
QUEUE_CONNECTION=database
```

Generate a VAPID pair once with `Minishlink\WebPush\VAPID::createVapidKeys()` in a trusted setup environment, then keep the private key in a secret store. Do not regenerate keys on application startup or expose the private key in client code. The public key is used by the browser to subscribe; `WEBPUSH_SUBJECT` must be a `mailto:` address or an HTTPS URL.

Serve the application over HTTPS; service workers and browser push require a secure context (localhost is suitable for local development). On iPhone and iPad, install BarberHub from Safari using **Add to Home Screen** before enabling push.

Apply the pending database migrations with `php artisan migrate --force` (including the push-subscription and database-queue tables), then keep a queue worker running, for example `php artisan queue:work --tries=3`. A synchronous queue connection will not provide background delivery. Rebuild frontend assets with `npm run build` when deploying a release.

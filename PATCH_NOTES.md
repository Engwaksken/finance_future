# Finance Future CMS Management Patch

## Includes
- Article create/edit/delete in modals
- Article image uploads
- Site settings: site name, tagline, email, logo, favicon
- Manual MySQL backup download
- Optional daily database backups
- Backup retention days
- CMS maintenance mode and message
- Users CRUD in modals
- Roles & permissions
- Native Laravel/MySQL permission structure
- Maintenance page
- Scheduler configuration

## Required bootstrap/app.php aliases

Add imports:

```php
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\SiteMaintenanceMiddleware;
```

Inside `withMiddleware` keep `admin` and add:

```php
$middleware->alias([
    'admin' => \App\Http\Middleware\AdminMiddleware::class,
    'permission' => PermissionMiddleware::class,
]);

$middleware->web(append: [
    SiteMaintenanceMiddleware::class,
]);
```

## Sidebar
Add these links to `resources/views/layouts/admin.blade.php`:

```blade
<a href="{{ route('admin.users.index') }}" class="sidebar-link">
    <i class="fa-solid fa-users"></i><span>Users</span>
</a>

<a href="{{ route('admin.roles.index') }}" class="sidebar-link">
    <i class="fa-solid fa-user-shield"></i><span>Roles & Permissions</span>
</a>

<a href="{{ route('admin.settings.index') }}" class="sidebar-link">
    <i class="fa-solid fa-gear"></i><span>Site Settings</span>
</a>
```

## Add to global admin.css

```css
.modal-box.modal-lg{width:min(900px,96vw)}
.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.field-full{grid-column:1/-1}
.permission-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.permission-group{border:1px solid var(--border);border-radius:8px;padding:14px;display:flex;flex-direction:column;gap:9px}
.empty-state{text-align:center;padding:38px!important;color:var(--muted)}
.text-muted{color:var(--muted)}
@media(max-width:700px){.form-grid,.permission-grid{grid-template-columns:1fr}}
```

## Run

```powershell
php artisan migrate
php artisan db:seed --class=RolesPermissionsSeeder
php artisan storage:link
php artisan optimize:clear
npm run build
```

For scheduled backups in production, run Laravel scheduler every minute through cron/Task Scheduler.

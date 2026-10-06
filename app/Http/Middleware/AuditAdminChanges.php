<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Services\AuditSnapshot;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuditAdminChanges
{
    public function __construct(private AuditSnapshot $snapshot) {}

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route()->getName();
        $action = match (true) {
            Str::endsWith($route, '.store') => 'created',
            Str::endsWith($route, '.update') => 'updated',
            Str::endsWith($route, '.destroy') => 'deleted',
            default => null,
        };
        if (! $action || $request->isMethod('GET') || ! $request->user()) {
            return $next($request);
        }
        $actor = $request->user();
        $actorName = $actor->name;
        $subject = collect($request->route()->parameters())->first(fn ($value) => $value instanceof Model);
        $isSettings = $route === 'admin.settings.update';
        $section = $isSettings && is_string($request->input('section')) ? $request->input('section') : '';
        $before = $isSettings ? $this->snapshot->settings($section) : ($subject ? $this->snapshot->model($subject) : []);
        $label = $subject?->name ?? $subject?->title ?? $section;

        $response = $next($request);
        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        $subject ??= $request->attributes->get('audit_subject');
        $after = $isSettings ? $this->snapshot->settings($section) : [];
        if ($subject && $action !== 'deleted') {
            $fresh = $subject->fresh();
            $after = $fresh ? $this->snapshot->model($fresh) : [];
        } elseif ($subject && $action === 'deleted' && $subject->exists) {
            // A blocked delete leaves the model unchanged (soft deletes set exists=true).
            $fresh = $subject->newQuery()->find($subject->getKey());
            if ($fresh) {
                $after = $this->snapshot->model($fresh);
            }
        }
        $changes = $this->snapshot->changes($before, $after);
        if ($changes !== []) {
            AuditLog::create([
                'actor_id' => $actor->id,
                'actor_name' => $actorName,
                'action' => $action,
                'subject_type' => $isSettings ? 'Settings' : class_basename($subject),
                'subject_id' => $isSettings ? $section : (string) $subject->getKey(),
                'subject_label' => Str::limit($label ?: ($subject?->name ?? $subject?->title ?? 'Record'), 255, ''),
                'route' => $route,
                'ip_address' => $request->ip(),
                'changes' => $changes,
            ]);
        }

        return $response;
    }
}

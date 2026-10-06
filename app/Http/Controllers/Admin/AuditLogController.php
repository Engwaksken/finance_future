<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AdminPeriod;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $types = ['Article', 'Category', 'User', 'Role', 'PageSlide', 'Settings'];
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'action' => ['nullable', Rule::in(['created', 'updated', 'deleted'])],
            'subject_type' => ['nullable', Rule::in($types)],
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $period = AdminPeriod::fromRequest($request);
        $query = $period->apply(AuditLog::query())
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($query) => $query
                ->where('subject_label', 'like', '%'.trim($filters['q']).'%')
                ->orWhere('actor_name', 'like', '%'.trim($filters['q']).'%')
                ->orWhere('route', 'like', '%'.trim($filters['q']).'%')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $filters['action']))
            ->when($request->filled('subject_type'), fn ($query) => $query->where('subject_type', $filters['subject_type']))
            ->when($request->filled('actor_id'), fn ($query) => $query->where('actor_id', $filters['actor_id']));
        $stats = [
            'Audit Events' => (clone $query)->count(),
            'Created' => (clone $query)->where('action', 'created')->count(),
            'Updated' => (clone $query)->where('action', 'updated')->count(),
            'Deleted' => (clone $query)->where('action', 'deleted')->count(),
        ];
        $logs = $query->latest('id')->paginate(20)->withQueryString();
        $actors = User::whereIn('id', AuditLog::whereNotNull('actor_id')->select('actor_id'))->orderBy('name')->get(['id', 'name']);

        return view('admin.audit-logs.index', compact('logs', 'stats', 'period', 'actors', 'types'));
    }
}

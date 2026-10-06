<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Support\AdminPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SubscriberController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', 'in:active,inactive,unsubscribed'],
        ]);
        $period = AdminPeriod::fromRequest($request);
        $query = NewsletterSubscriber::query()
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.trim($filters['q']).'%')
                ->orWhere('email', 'like', '%'.trim($filters['q']).'%')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $filters['status']));
        $this->applySubscriptionPeriod($query, $period);
        $today = new AdminPeriod('daily', CarbonImmutable::today(), CarbonImmutable::tomorrow());
        $stats = [
            'Total Subscribers' => (clone $query)->count(),
            'Active Subscribers' => (clone $query)->where('status', 'active')->count(),
            'Inactive / Unsubscribed' => (clone $query)->where('status', '!=', 'active')->count(),
            'Subscribed Today' => $this->applySubscriptionPeriod(clone $query, $today)->count(),
        ];
        $subscribers = $query->orderByRaw('COALESCE(subscribed_at, created_at) DESC')->orderByDesc('id')->paginate(20)->withQueryString();

        return view('admin.subscribers.index', compact('subscribers', 'stats', 'period'));
    }

    private function applySubscriptionPeriod(Builder $query, AdminPeriod $period): Builder
    {
        if ($period->start) {
            $query->where(fn ($query) => $query
                ->where(fn ($query) => $period->apply($query, 'subscribed_at'))
                ->orWhere(fn ($query) => $period->apply($query->whereNull('subscribed_at'), 'created_at')));
        }

        return $query;
    }
}

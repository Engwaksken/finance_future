<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $subscriber = NewsletterSubscriber::firstOrCreate(
            ['email' => strtolower($data['email'])],
            ['name' => $data['name'] ?? null, 'status' => 'active', 'subscribed_at' => now()]
        );
        if ($subscriber->status !== 'active' || ! $subscriber->subscribed_at) {
            $subscriber->subscribed_at = now();
        }
        $subscriber->fill([
            'name' => $data['name'] ?? $subscriber->name,
            'status' => 'active',
            'unsubscribed_at' => null,
        ])->save();

        return back()->with('success', 'Thank you for subscribing.');
    }
}

<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\SiteSetting;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FinanceFutureSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['Finance', 'Banking, investment, markets, personal finance and financial inclusion.'],
            ['FinTech', 'Digital payments, mobile money, digital banking, lending and insurtech.'],
            ['Future Tech', 'AI, blockchain, automation, cybersecurity and emerging technologies.'],
            ['Policy & Regulation', 'Financial regulation, central banks, tax, data and digital-finance policy.'],
            ['Insights', 'Analysis, opinion, research and explainers.'],
            ['Interviews', 'Conversations with founders, investors, bankers and policymakers.'],
            ['Media', 'Podcasts, videos and webinars.'],
            ['Events', 'Conferences, webinars and public discussions.'],
        ];

        foreach ($categories as $i => [$name, $description]) {
            Category::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => $description,
                    'sort_order' => $i + 1,
                    'is_active' => true,
                    'is_featured' => $i < 5,
                ]
            );
        }

        foreach ([
            'Artificial Intelligence',
            'Digital Payments',
            'Mobile Money',
            'Blockchain',
            'Banking',
            'Investment',
            'Financial Inclusion',
            'Cybersecurity',
            'Uganda FinTech',
        ] as $name) {
            Tag::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }

        foreach ([
            ['site_name', 'Finance Future', 'general'],
            ['site_tagline', 'Finance, FinTech & Future Technology', 'general'],
            ['site_email', null, 'general'],
            ['backup_enabled', '0', 'backup'],
            ['backup_retention_days', '14', 'backup'],
            ['maintenance_enabled', '0', 'maintenance'],
            ['maintenance_message', 'We are currently performing maintenance. Please check back soon.', 'maintenance'],
        ] as [$key, $value, $group]) {
            SiteSetting::firstOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        }
    }
}

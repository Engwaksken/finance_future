<?php

namespace Database\Seeders;

use App\Models\Category;
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
    }
}

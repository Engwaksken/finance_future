<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminPeriod
{
    public const OPTIONS = ['all' => 'All time', 'daily' => 'Today', 'weekly' => 'This week', 'monthly' => 'This month', 'custom' => 'Custom period'];

    public function __construct(
        public readonly string $period,
        public readonly ?CarbonImmutable $start,
        public readonly ?CarbonImmutable $end,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $data = $request->validate([
            'period' => ['nullable', Rule::in(array_keys(self::OPTIONS))],
            'from' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $period = $data['period'] ?? 'all';
        $today = CarbonImmutable::today();
        [$start, $end] = match ($period) {
            'daily' => [$today, $today->addDay()],
            'weekly' => [$today->startOfWeek(), $today->startOfWeek()->addWeek()],
            'monthly' => [$today->startOfMonth(), $today->startOfMonth()->addMonth()],
            'custom' => [CarbonImmutable::createFromFormat('!Y-m-d', $data['from']), CarbonImmutable::createFromFormat('!Y-m-d', $data['to'])->addDay()],
            default => [null, null],
        };

        return new self($period, $start, $end);
    }

    public function apply(Builder $query, string $column = 'created_at'): Builder
    {
        if ($this->start) {
            $query->where($column, '>=', $this->start)->where($column, '<', $this->end);
        }

        return $query;
    }

    public function label(): string
    {
        return $this->period === 'custom'
            ? $this->start->format('d M Y').' – '.$this->end->subDay()->format('d M Y')
            : self::OPTIONS[$this->period];
    }
}

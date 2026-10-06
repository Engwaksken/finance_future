@props(['period'])
<div class="period-filter" x-data="{ period: @js($period->period) }">
    <div class="filter-field"><label for="filter-period">Period</label><select id="filter-period" name="period" x-model="period">@foreach(\App\Support\AdminPeriod::OPTIONS as $key => $label)<option value="{{ $key }}" @selected($period->period === $key)>{{ $label }}</option>@endforeach</select></div>
    <div class="filter-field" x-show="period === 'custom'" x-cloak><label for="filter-from">From</label><input id="filter-from" type="date" name="from" value="{{ request('from') }}" :disabled="period !== 'custom'" :required="period === 'custom'"></div>
    <div class="filter-field" x-show="period === 'custom'" x-cloak><label for="filter-to">Through</label><input id="filter-to" type="date" name="to" value="{{ request('to') }}" :disabled="period !== 'custom'" :required="period === 'custom'"></div>
</div>

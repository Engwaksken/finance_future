@extends('layouts.admin')

@section('title', 'Dashboard')

@section('heading', 'Dashboard')


@section('content')

<div class="stats-grid">

    <div class="stat-card border-blue">

        <div>

            <div class="stat-label">
                Published Articles
            </div>

            <div class="stat-value">
                {{ number_format($stats['published']) }}
            </div>

        </div>

        <div class="stat-icon">

            <i class="fa-regular fa-newspaper"></i>

        </div>

    </div>


    <div class="stat-card border-orange">

        <div>

            <div class="stat-label">
                Draft Articles
            </div>

            <div class="stat-value">
                {{ number_format($stats['drafts']) }}
            </div>

        </div>

        <div class="stat-icon">

            <i class="fa-solid fa-pen-to-square"></i>

        </div>

    </div>


    <div class="stat-card border-purple">

        <div>

            <div class="stat-label">
                Scheduled
            </div>

            <div class="stat-value">
                {{ number_format($stats['scheduled']) }}
            </div>

        </div>

        <div class="stat-icon">

            <i class="fa-regular fa-clock"></i>

        </div>

    </div>


    <div class="stat-card border-green">

        <div>

            <div class="stat-label">
                Categories
            </div>

            <div class="stat-value">
                {{ number_format($stats['categories']) }}
            </div>

        </div>

        <div class="stat-icon">

            <i class="fa-solid fa-layer-group"></i>

        </div>

    </div>


    <div class="stat-card border-cyan">

        <div>

            <div class="stat-label">
                Subscribers
            </div>

            <div class="stat-value">
                {{ number_format($stats['subscribers']) }}
            </div>

        </div>

        <div class="stat-icon">

            <i class="fa-regular fa-envelope"></i>

        </div>

    </div>


    <div class="stat-card border-red">

        <div>

            <div class="stat-label">
                Article Views
            </div>

            <div class="stat-value">
                {{ number_format($stats['views']) }}
            </div>

        </div>

        <div class="stat-icon">

            <i class="fa-regular fa-eye"></i>

        </div>

    </div>


    <div class="stat-card border-navy">

        <div>

            <div class="stat-label">
                Content Categories
            </div>

            <div class="stat-value">
                {{ number_format($stats['categories']) }}
            </div>

        </div>

        <div class="stat-icon">

            <i class="fa-solid fa-folder-tree"></i>

        </div>

    </div>


    <div class="stat-card border-grey">

        <div>

            <div class="stat-label">
                Total Content
            </div>

            <div class="stat-value">

                {{
                    number_format(
                        $stats['published']
                        + $stats['drafts']
                        + $stats['scheduled']
                    )
                }}

            </div>

        </div>

        <div class="stat-icon">

            <i class="fa-solid fa-file-lines"></i>

        </div>

    </div>

</div>


<div class="panel">

    <div class="panel-header">

        <div>

            <h2 class="panel-title">
                Recent Articles
            </h2>

            <div class="text-muted mt-2">
                Latest content activity
            </div>

        </div>


        <a href="{{ route('admin.articles.create') }}"
           class="btn btn-primary">

            <i class="fa-solid fa-plus"></i>

            New Article

        </a>

    </div>


    <div class="table-responsive">

        <table>

            <thead>

            <tr>

                <th>
                    Article
                </th>

                <th>
                    Category
                </th>

                <th>
                    Status
                </th>

                <th>
                    Updated
                </th>

                <th>
                    Action
                </th>

            </tr>

            </thead>


            <tbody>

            @forelse($recent as $article)

                <tr>

                    <td>

                        <a
                            href="{{ route('admin.articles.edit', $article) }}"
                            class="table-title">

                            {{ $article->title }}

                        </a>

                    </td>


                    <td>

                        {{ $article->category?->name ?? 'Uncategorised' }}

                    </td>


                    <td>

                        @php

                            $statusClass = match($article->status) {

                                'published' => 'badge-success',

                                'scheduled' => 'badge-info',

                                'review' => 'badge-warning',

                                'archived' => 'badge-grey',

                                default => 'badge-grey'

                            };

                        @endphp


                        <span class="badge {{ $statusClass }}">

                            {{ ucfirst($article->status) }}

                        </span>

                    </td>


                    <td class="text-muted">

                        {{ $article->updated_at->diffForHumans() }}

                    </td>


                    <td>

                        <a
                            href="{{ route('admin.articles.edit', $article) }}"
                            class="btn-icon">

                            <i class="fa-solid fa-pen"></i>

                        </a>

                    </td>

                </tr>

            @empty

                <tr>

                    <td colspan="5"
                        style="text-align:center;padding:35px;">

                        No articles available.

                    </td>

                </tr>

            @endforelse

            </tbody>

        </table>

    </div>

</div>

@endsection
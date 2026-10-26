@extends('layouts.app')
@section('title', 'Dashboard')

@push('styles')
    <link href="{{ asset('css/dashboard.css') }}?v={{ filemtime(public_path('css/dashboard.css')) }}" rel="stylesheet">
@endpush

@section('content')
<main class="container dtc-dashboard">
    <header class="dtc-dashboard__welcome">
        <div>
            <p class="dtc-dashboard__eyebrow">David Tyre Company</p>
            <h1>Dashboard</h1>
            <p class="dtc-dashboard__intro">A clear overview of registrations and customer activity.</p>
        </div>
        <nav class="dtc-dashboard__actions" aria-label="Dashboard shortcuts">
            <a href="{{ route('dtc.lists') }}" class="dtc-dashboard__action">
                <span class="fa fa-users" aria-hidden="true"></span>
                <span>View registrants</span>
                <span class="fa fa-arrow-right" aria-hidden="true"></span>
            </a>
            <a href="{{ route('claims.index') }}" class="dtc-dashboard__action">
                <span class="fa fa-clipboard" aria-hidden="true"></span>
                <span>Manage claims</span>
                <span class="fa fa-arrow-right" aria-hidden="true"></span>
            </a>
            @if (Auth::user()->isAdmin())
                <a href="{{ route('dtc_codes.index') }}" class="dtc-dashboard__action">
                    <span class="fa fa-ticket" aria-hidden="true"></span>
                    <span>DTC Codes</span>
                    <span class="fa fa-arrow-right" aria-hidden="true"></span>
                </a>
            @endif
        </nav>
    </header>

    <section class="dtc-dashboard__metrics" aria-label="Registration summary">
        @foreach ([
            ['Registrants', $total, 'fa-users', 'All registered customers'],
            ['Recent registrations', $recent, 'fa-calendar-check-o', 'Added in the last 30 days'],
            ['Email subscribers', $subscribed, 'fa-envelope-o', 'Customers opted in for updates'],
        ] as [$label, $value, $icon, $description])
            <article class="dtc-dashboard__metric">
                <div class="dtc-dashboard__metric-heading">
                    <span>{{ $label }}</span>
                    <span class="dtc-dashboard__metric-icon fa {{ $icon }}" aria-hidden="true"></span>
                </div>
                <strong class="dtc-dashboard__metric-value">{{ number_format($value) }}</strong>
                <p>{{ $description }}</p>
            </article>
        @endforeach
    </section>

    <section class="dtc-dashboard__panel dtc-dashboard__trend" aria-labelledby="dashboard-monthly-title">
        <div class="dtc-dashboard__panel-heading">
            <div>
                <p class="dtc-dashboard__panel-eyebrow">Registration activity</p>
                <h2 id="dashboard-monthly-title">Purchases per month</h2>
            </div>
            <span class="dtc-dashboard__panel-icon fa fa-line-chart" aria-hidden="true"></span>
        </div>
        <div class="dtc-dashboard__chart dtc-dashboard__chart--trend">
            <canvas id="chart-monthly" role="img" aria-label="Purchases per month"></canvas>
        </div>
    </section>

    <section class="dtc-dashboard__breakdown" aria-label="Registration and claim breakdown">
        @foreach ([
            ['tyres', 'Top tyres', 'Most registered tyre models', 'fa-circle-o'],
            ['stores', 'Top stores', 'Registrations by dealer', 'fa-map-marker'],
            ['claims', 'Claim status breakdown', 'Claims by current status', 'fa-clipboard'],
        ] as [$key, $title, $description, $icon])
            <article class="dtc-dashboard__panel dtc-dashboard__breakdown-panel">
                <div class="dtc-dashboard__panel-heading">
                    <div>
                        <h2>{{ $title }}</h2>
                        <p>{{ $description }}</p>
                    </div>
                    <span class="dtc-dashboard__panel-icon fa {{ $icon }}" aria-hidden="true"></span>
                </div>
                <div class="dtc-dashboard__chart dtc-dashboard__chart--doughnut">
                    <canvas id="chart-{{ $key }}" role="img" aria-label="{{ $title }}"></canvas>
                </div>
            </article>
        @endforeach
    </section>

    <section class="dtc-dashboard__panel dtc-dashboard__activity" aria-labelledby="dashboard-activity-title">
        <div class="dtc-dashboard__panel-heading">
            <div>
                <p class="dtc-dashboard__panel-eyebrow">Staff activity</p>
                <h2 id="dashboard-activity-title">Recent activity</h2>
                <p>Recent registrant and claim changes.</p>
            </div>
            <a class="dtc-dashboard__activity-link" href="{{ route('activity.index') }}">View all activity <span class="fa fa-arrow-right" aria-hidden="true"></span></a>
        </div>
        @forelse ($recentActivities as $activity)
            <div class="dtc-dashboard__activity-row">
                <span class="dtc-dashboard__activity-icon fa {{ $activity->entity_type === 'claim' ? 'fa-clipboard' : 'fa-user' }}" aria-hidden="true"></span>
                <div class="dtc-dashboard__activity-copy">
                    <strong>{{ $activity->summary }}</strong>
                    <span>
                        {{ $activity->actorLabel() }} &middot; {{ $activity->created_at->diffForHumans() }}
                    </span>
                </div>
                @if ($activity->entity_type === 'claim' && $activity->claim)
                    <a class="dtc-dashboard__activity-open" href="{{ route('claims.show', $activity->claim) }}" aria-label="Open {{ $activity->claim->claim_number }}"><span class="fa fa-angle-right" aria-hidden="true"></span></a>
                @elseif ($activity->entity_type === 'registrant' && $activity->registrant && ! $activity->registrant->trashed())
                    <a class="dtc-dashboard__activity-open" href="{{ route('dtc.show', $activity->registrant) }}" aria-label="Open registrant"><span class="fa fa-angle-right" aria-hidden="true"></span></a>
                @endif
            </div>
        @empty
            <p class="dtc-dashboard__activity-empty">No activity recorded yet.</p>
        @endforelse
    </section>
</main>
@endsection

@push('scripts')
    <script src="{{ asset('/js/chart.umd.js') }}"></script>
    <script>
        (function () {
            var charts = @json($charts);
            var palette = ['#0b2a63', '#fbbd00', '#3d5d91', '#929eaf', '#d1d7df', '#c98f00'];
            var monthlyCanvas = document.getElementById('chart-monthly');

            if (!window.Chart || !monthlyCanvas) {
                return;
            }

            new Chart(monthlyCanvas, {
                type: 'line',
                data: {
                    labels: charts.monthly.labels,
                    datasets: [{
                        label: 'Purchases',
                        data: charts.monthly.data,
                        borderColor: '#123a86',
                        backgroundColor: 'rgba(18, 58, 134, .10)',
                        pointBackgroundColor: '#fbbd00',
                        pointBorderColor: '#123a86',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        borderWidth: 3,
                        fill: true,
                        tension: .35
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: {
                        legend: { display: false },
                        tooltip: { padding: 12, displayColors: false }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: '#737b87' } },
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0, color: '#737b87' },
                            grid: { color: '#edf0f4' }
                        }
                    }
                }
            });

            [
                ['chart-tyres', 'tyres'],
                ['chart-stores', 'stores'],
                ['chart-claims', 'claims']
            ].forEach(function (config) {
                var canvas = document.getElementById(config[0]);
                if (!canvas) {
                    return;
                }

                new Chart(canvas, {
                    type: 'doughnut',
                    data: {
                        labels: charts[config[1]].labels,
                        datasets: [{
                            data: charts[config[1]].data,
                            backgroundColor: palette,
                            borderColor: '#fff',
                            borderWidth: 3,
                            hoverOffset: 5
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        cutout: '64%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { usePointStyle: true, pointStyle: 'circle', padding: 16, color: '#535c68' }
                            }
                        }
                    }
                });
            });
        })();
    </script>
@endpush

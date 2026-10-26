@extends('layouts.app')
@section('title', 'Activity')

@section('content')
<main class="container dtc-activity">
    <header class="dtc-activity__heading">
        <div>
            <p class="dtc-activity__eyebrow">Staff portal</p>
            <h1>Activity log</h1>
            <p>Review registrant and claim changes, notification retries, attachment downloads, and follow-up reminders.</p>
        </div>
        <span class="dtc-activity__count">{{ number_format($activities->total()) }} events</span>
    </header>

    <form method="GET" action="{{ route('activity.index') }}" class="dtc-activity__filters">
        <div class="dtc-activity__search">
            <label for="activity-search">Search activity</label>
            <input id="activity-search" class="form-control" type="search" name="search" value="{{ request('search') }}" placeholder="Claim number, DTC Code, record ID, or staff name">
        </div>
        <div>
            <label for="activity-type">Record type</label>
            <select id="activity-type" class="form-control" name="type">
                <option value="">All activity</option>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="activity-from">From</label>
            <input id="activity-from" class="form-control" type="date" name="date_from" value="{{ request('date_from') }}" max="{{ now(config('dtc.timezone'))->toDateString() }}">
        </div>
        <div>
            <label for="activity-to">To</label>
            <input id="activity-to" class="form-control" type="date" name="date_to" value="{{ request('date_to') }}" max="{{ now(config('dtc.timezone'))->toDateString() }}">
        </div>
        <div class="dtc-activity__filter-actions">
            <button class="btn btn-action btn-action--primary" type="submit"><span class="fa fa-search" aria-hidden="true"></span> Filter</button>
            @if (request()->query())
                <a class="btn btn-action" href="{{ route('activity.index') }}">Clear</a>
            @endif
        </div>
    </form>

    <section class="dtc-activity__list" aria-label="Activity entries">
        @forelse ($activities as $activity)
            <article class="dtc-activity__item">
                <span class="dtc-activity__icon {{ $activity->entity_type === 'claim' ? 'dtc-activity__icon--claim' : 'dtc-activity__icon--registrant' }} fa {{ $activity->entity_type === 'claim' ? 'fa-clipboard' : 'fa-user' }}" aria-hidden="true"></span>
                <div class="dtc-activity__content">
                    <p class="dtc-activity__summary">{{ $activity->summary }}</p>
                    <div class="dtc-activity__meta">
                        <span class="dtc-activity__type">{{ $types[$activity->entity_type] ?? 'Activity' }}</span>
                        <span>{{ $activity->actorLabel() }}</span>
                        <time datetime="{{ $activity->created_at->toIso8601String() }}">{{ $activity->created_at->format('M j, Y \a\t g:i A') }}</time>
                    </div>
                </div>
                <div class="dtc-activity__record">
                    @if ($activity->entity_type === 'claim')
                        @if ($activity->claim)
                            <a href="{{ route('claims.show', $activity->claim) }}">{{ $activity->claim->claim_number }}</a>
                        @else
                            Claim #{{ $activity->entity_id }}
                        @endif
                    @elseif ($activity->registrant && ! $activity->registrant->trashed())
                        <a href="{{ route('dtc.show', $activity->registrant) }}">Registrant #{{ $activity->dtc_id }}</a>
                    @else
                        Registrant #{{ $activity->dtc_id }}
                    @endif
                </div>
            </article>
        @empty
            <div class="dtc-activity__empty">
                <span class="fa fa-history" aria-hidden="true"></span>
                <strong>No activity found</strong>
                <span>New registrant edits, deletions, and claim updates will appear here.</span>
            </div>
        @endforelse
    </section>

    @if ($activities->hasPages())
        <div class="dtc-activity__pagination">{{ $activities->links() }}</div>
    @endif
</main>
@endsection

@extends('layouts.app')
@section('title', 'DTC Codes')

@section('content')
<main class="container dtc-code-page">
    <header class="dtc-code-page__heading">
        <div>
            <p class="dtc-code-page__eyebrow">Warranty registration</p>
            <h1 class="dtc-code-page__title">DTC Codes</h1>
            <p class="dtc-code-page__description">Create, find, and track the codes used for tyre registrations.</p>
        </div>
        <div class="dtc-code-page__actions">
            <a href="{{ route('dtc_codes.download') }}" class="btn btn-action dtc-code-btn dtc-code-btn--light">
                <span class="fa fa-download" aria-hidden="true"></span> &nbsp;Download CSV
            </a>
            <a href="{{ route('dtc_codes.create') }}" class="btn btn-action btn-action--primary dtc-code-btn dtc-code-btn--primary">
                <span class="fa fa-plus" aria-hidden="true"></span> &nbsp;Generate DTC Codes
            </a>
        </div>
    </header>

    <section class="dtc-code-page__metrics" aria-label="DTC Code totals">
        <article class="dtc-code-page__metric">
            <span class="dtc-code-page__metric-label"><span class="fa fa-qrcode" aria-hidden="true"></span> Total DTC Codes</span>
            <strong class="dtc-code-page__metric-value">{{ number_format($totalCodes) }}</strong>
        </article>
        <article class="dtc-code-page__metric">
            <span class="dtc-code-page__metric-label"><span class="fa fa-check-circle" aria-hidden="true"></span> Available</span>
            <strong class="dtc-code-page__metric-value">{{ number_format($availableCodes) }}</strong>
        </article>
        <article class="dtc-code-page__metric">
            <span class="dtc-code-page__metric-label"><span class="fa fa-link" aria-hidden="true"></span> Used for registration</span>
            <strong class="dtc-code-page__metric-value">{{ number_format($usedCodes) }}</strong>
        </article>
    </section>

    <section class="dtc-code-page__panel" aria-label="DTC Code list">
        <div class="dtc-code-page__toolbar">
            <div>
                <h2 class="dtc-code-page__toolbar-title">Code inventory</h2>
                <p class="dtc-code-page__toolbar-note">
                    @if (request('search'))
                        {{ number_format($dtcCodes->total()) }} matching {{ \Illuminate\Support\Str::plural('code', $dtcCodes->total()) }}
                    @else
                        Showing the latest generated DTC Codes
                    @endif
                </p>
            </div>
            <form class="dtc-code-page__search" method="GET" action="{{ route('dtc_codes.index') }}" role="search">
                <input type="search" class="form-control" name="search" placeholder="Search DTC Code" value="{{ request('search') }}" aria-label="Search DTC Codes">
                <button type="submit" class="btn btn-action btn-action--primary dtc-code-btn dtc-code-btn--primary" aria-label="Search DTC Codes">
                    <span class="fa fa-search" aria-hidden="true"></span><span class="hidden-xs"> Search</span>
                </button>
                @if (request('search'))
                    <a href="{{ route('dtc_codes.index') }}" class="btn btn-action dtc-code-btn dtc-code-btn--light" aria-label="Clear search">Clear</a>
                @endif
            </form>
        </div>

        <div class="dtc-code-page__table-wrap">
            <table class="table dtc-code-page__table">
                <thead>
                    <tr><th scope="col">DTC Code</th><th scope="col">Status</th></tr>
                </thead>
                <tbody>
                @forelse ($dtcCodes as $code)
                    <tr>
                        <td><span class="dtc-code-page__code">{{ $code->code }}</span></td>
                        <td>
                            <span class="dtc-code-page__status {{ $code->is_use ? 'dtc-code-page__status--used' : 'dtc-code-page__status--available' }}">
                                {{ $code->is_use ? 'Used' : 'Available' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="dtc-code-page__empty">
                            <span class="fa fa-search" aria-hidden="true"></span>
                            @if (request('search'))
                                No DTC Codes match “{{ request('search') }}”.
                            @else
                                No DTC Codes yet. Generate a batch to get started.
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($dtcCodes->hasPages())
            <div class="dtc-code-page__pagination">{{ $dtcCodes->links() }}</div>
        @endif
    </section>
</main>
@endsection

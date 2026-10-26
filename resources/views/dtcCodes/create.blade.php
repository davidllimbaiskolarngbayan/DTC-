@extends('layouts.app')
@section('title', 'Generate DTC Codes')

@section('content')
<main class="container dtc-code-page">
    <div class="dtc-code-form">
        <header class="dtc-code-page__heading">
            <div>
                <p class="dtc-code-page__eyebrow">Warranty registration</p>
                <h1 class="dtc-code-page__title">Generate DTC Codes</h1>
                <p class="dtc-code-page__description">Create a batch of DTC Codes ready to assign to tyre registrations.</p>
            </div>
        </header>

        <section class="dtc-code-form__card">
            <div class="dtc-code-form__header">
                <h2 class="dtc-code-page__toolbar-title">New code batch</h2>
                <p class="dtc-code-page__toolbar-note">Choose how many codes you need. You can download the list as a CSV after generation.</p>
            </div>
            <form method="POST" action="{{ route('dtc_codes.store') }}">
                @csrf
                <div class="dtc-code-form__body">
                    <label for="counter">Number of DTC Codes</label>
                    <input id="counter" type="number" class="form-control" name="counter" min="1" max="5000" value="{{ old('counter') }}" placeholder="e.g. 100" required>
                    <p class="dtc-code-form__hint">Enter a number from 1 to 5,000. Each code is distinct and starts as available.</p>
                </div>
                <footer class="dtc-code-form__footer">
                    <a href="{{ route('dtc_codes.index') }}" class="btn btn-action dtc-code-btn dtc-code-btn--light">Cancel</a>
                    <button type="submit" class="btn btn-action btn-action--primary dtc-code-btn dtc-code-btn--primary"><span class="fa fa-plus" aria-hidden="true"></span> &nbsp;Generate Codes</button>
                </footer>
            </form>
        </section>
    </div>
</main>
@endsection

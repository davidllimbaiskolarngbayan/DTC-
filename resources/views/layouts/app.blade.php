<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') - {{ config('dtc.brand_name') }}</title>

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link href="{{ asset('/css/app.css') }}" rel="stylesheet">
    <style>
        .btn-primary { background-color: #000000; border-color: #000000; }
        .navbar-default { background-color: #000000; border-color: #000000; border-radius: 0px; }
        .navbar-default .navbar-brand, .navbar-default .navbar-nav > li > a { color: #fff; }
        .navbar-default .navbar-brand { padding: 7px 15px; }
        .navbar-default .navbar-brand img { display: block; height: 36px; width: auto; }
        .navbar-default .navbar-nav > li > a:hover { color: #fff; }
        .navbar-default .navbar-nav > li.active > a,
        .navbar-default .navbar-nav > li.active > a:hover,
        .navbar-default .navbar-nav > li.active > a:focus { background-color: #222222; color: #fff; box-shadow: inset 0 -3px 0 #fff; }
        .navbar-default .navbar-nav > .open > a, .navbar-default .navbar-nav > .open > a:hover, .navbar-default .navbar-nav > .open > a:focus { background-color: #222222; color: #fff; }
        .navbar-default .navbar-toggle { border-color: #fff; }
        .navbar-default .navbar-toggle .icon-bar { background-color: #fff; }
        td.label-col { width: 25%; }
        .dtc-code-page { padding-bottom: 48px; color: #20252b; }
        .dtc-code-page__heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; margin: 36px 0 26px; }
        .dtc-code-page__eyebrow { margin: 0 0 7px; color: #8a6a00; font-size: 11px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; }
        .dtc-code-page__title { margin: 0; color: #17191b; font-size: 30px; font-weight: 700; letter-spacing: -.035em; }
        .dtc-code-page__description { margin: 8px 0 0; color: #737b83; font-size: 14px; }
        .dtc-code-page__actions { display: flex; flex-wrap: wrap; gap: 10px; }
        .dtc-code-page .dtc-code-btn { min-height: 42px; padding: 10px 15px; border: 1px solid #17191b; border-radius: 7px; background: #17191b; color: #fff; font-size: 13px; font-weight: 600; line-height: 20px; transition: background .15s ease, border-color .15s ease, transform .15s ease; }
        .dtc-code-page .dtc-code-btn:hover, .dtc-code-page .dtc-code-btn:focus { border-color: #30343a; background: #30343a; color: #fff; text-decoration: none; transform: translateY(-1px); }
        .dtc-code-page .dtc-code-btn--light { border-color: #dfe2e5; background: #fff; color: #292e33; }
        .dtc-code-page .dtc-code-btn--light:hover, .dtc-code-page .dtc-code-btn--light:focus { border-color: #b6bbc0; background: #f7f8f9; color: #17191b; }
        .dtc-code-page__metrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 15px; margin-bottom: 22px; }
        .dtc-code-page__metric { position: relative; min-height: 112px; overflow: hidden; padding: 20px 22px; border: 1px solid #e8e9eb; border-radius: 10px; background: #fff; box-shadow: 0 3px 12px rgba(23, 25, 27, .035); }
        .dtc-code-page__metric:after { position: absolute; right: -16px; bottom: -31px; width: 96px; height: 96px; border: 15px solid rgba(251, 189, 0, .11); border-radius: 50%; content: ""; }
        .dtc-code-page__metric-label { display: flex; align-items: center; gap: 8px; color: #747c84; font-size: 12px; font-weight: 600; }
        .dtc-code-page__metric-label .fa { color: #ad8500; }
        .dtc-code-page__metric-value { display: block; margin-top: 9px; color: #17191b; font-size: 28px; font-weight: 700; letter-spacing: -.04em; line-height: 1; }
        .dtc-code-page__panel { overflow: hidden; border: 1px solid #e5e7e9; border-radius: 10px; background: #fff; box-shadow: 0 4px 18px rgba(23, 25, 27, .045); }
        .dtc-code-page__toolbar { display: flex; align-items: center; justify-content: space-between; gap: 18px; padding: 16px 20px; border-bottom: 1px solid #eceef0; }
        .dtc-code-page__toolbar-title { margin: 0; color: #252a30; font-size: 14px; font-weight: 700; }
        .dtc-code-page__toolbar-note { margin: 4px 0 0; color: #858c93; font-size: 12px; }
        .dtc-code-page__search { display: flex; width: min(100%, 340px); gap: 8px; }
        .dtc-code-page__search .form-control { height: 40px; border-color: #dfe2e5; border-radius: 6px; box-shadow: none; font-size: 13px; }
        .dtc-code-page__search .form-control:focus { border-color: #c89c00; box-shadow: 0 0 0 3px rgba(251, 189, 0, .14); }
        .dtc-code-page__search .dtc-code-btn { min-height: 40px; padding: 9px 14px; }
        .dtc-code-page__table-wrap { overflow-x: auto; }
        .dtc-code-page__table { width: 100%; margin: 0; border: 0; }
        .dtc-code-page__table > thead > tr > th { padding: 13px 20px; border: 0; background: #f8f9fa; color: #747c84; font-size: 10px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
        .dtc-code-page__table > tbody > tr > td { padding: 13px 20px; border-top: 1px solid #f0f1f2; color: #383e44; font-size: 13px; vertical-align: middle; }
        .dtc-code-page__table > tbody > tr:hover { background: #fffdf6; }
        .dtc-code-page__code { display: inline-block; padding: 6px 10px; border: 1px solid #ece8d8; border-radius: 5px; background: #fcfbf6; color: #20252b; font-family: Consolas, "Courier New", monospace; font-size: 13px; font-weight: 700; letter-spacing: .1em; }
        .dtc-code-page__status { display: inline-flex; align-items: center; gap: 7px; padding: 5px 9px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .dtc-code-page__status:before { width: 6px; height: 6px; border-radius: 50%; background: currentColor; content: ""; }
        .dtc-code-page__status--available { background: #eaf7ef; color: #247144; }
        .dtc-code-page__status--used { background: #f0f1f2; color: #6e747b; }
        .dtc-code-page__empty { padding: 46px 20px !important; color: #7c848c !important; text-align: center; }
        .dtc-code-page__empty .fa { display: block; margin-bottom: 12px; color: #c8cdd1; font-size: 25px; }
        .dtc-code-page__pagination { padding: 8px 20px 18px; text-align: center; }
        .dtc-code-page__pagination .pagination { margin-bottom: 0; }
        .dtc-code-form { max-width: 680px; margin: 34px auto 56px; }
        .dtc-code-form__card { overflow: hidden; border: 1px solid #e5e7e9; border-radius: 11px; background: #fff; box-shadow: 0 6px 24px rgba(23, 25, 27, .06); }
        .dtc-code-form__header { padding: 24px 28px 20px; border-bottom: 1px solid #eceef0; }
        .dtc-code-form__header .dtc-code-page__title { font-size: 25px; }
        .dtc-code-form__body { padding: 26px 28px; }
        .dtc-code-form__body label { display: block; margin-bottom: 9px; color: #333940; font-size: 13px; font-weight: 700; }
        .dtc-code-form__body .form-control { height: 46px; border-color: #dfe2e5; border-radius: 6px; box-shadow: none; }
        .dtc-code-form__body .form-control:focus { border-color: #c89c00; box-shadow: 0 0 0 3px rgba(251, 189, 0, .14); }
        .dtc-code-form__hint { margin: 9px 0 0; color: #828990; font-size: 12px; line-height: 1.6; }
        .dtc-code-form__footer { display: flex; justify-content: flex-end; gap: 9px; padding: 17px 28px; border-top: 1px solid #eceef0; background: #fafafa; }
        @media (max-width: 767px) {
            .dtc-code-page__heading { align-items: stretch; flex-direction: column; margin-top: 26px; }
            .dtc-code-page__title { font-size: 26px; }
            .dtc-code-page__metrics { grid-template-columns: 1fr; gap: 10px; }
            .dtc-code-page__metric { min-height: 94px; padding: 16px 18px; }
            .dtc-code-page__toolbar { align-items: stretch; flex-direction: column; }
            .dtc-code-page__search { width: 100%; }
            .dtc-code-page__table > thead > tr > th, .dtc-code-page__table > tbody > tr > td { padding-right: 14px; padding-left: 14px; }
            .dtc-code-form { margin-top: 24px; }
            .dtc-code-form__header, .dtc-code-form__body { padding-right: 20px; padding-left: 20px; }
            .dtc-code-form__footer { padding-right: 20px; padding-left: 20px; }
        }
    </style>
    @stack('styles')
    <link href="{{ asset('/css/admin-ui.css') }}?v={{ filemtime(public_path('css/admin-ui.css')) }}" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-default">
        <div class="container">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#main-navbar">
                    <span class="sr-only">Toggle Navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="{{ url('/') }}"><img src="{{ asset('images/dtc-logo.svg') }}" alt="{{ config('dtc.brand_name') }}"></a>
            </div>

            <div class="collapse navbar-collapse" id="main-navbar">
                @auth
                    <ul class="nav navbar-nav">
                        <li class="{{ request()->routeIs('activity.*') ? 'active' : '' }}"><a href="{{ route('activity.index') }}">Activity</a></li>
                        <li class="{{ request()->routeIs('dtc.*') ? 'active' : '' }}"><a href="{{ route('dtc.lists') }}">Registrants</a></li>
                        <li class="{{ request()->routeIs('claims.*') ? 'active' : '' }}">
                            <a href="{{ route('claims.index') }}">
                                Claims
                                @if ($submittedClaimsCount > 0)
                                    <span class="label label-default">{{ $submittedClaimsCount }}</span>
                                    <span class="sr-only">{{ $submittedClaimsCount }} {{ $submittedClaimsCount === 1 ? 'submitted claim' : 'submitted claims' }} awaiting review</span>
                                @endif
                            </a>
                        </li>
                        @if (Auth::user()->isAdmin())
                            <li class="{{ request()->routeIs('dtc_codes.*') ? 'active' : '' }}"><a href="{{ route('dtc_codes.index') }}">DTC Codes</a></li>
                            <li class="{{ request()->routeIs('settings.claim-notifications*') ? 'active' : '' }}"><a href="{{ route('settings.claim-notifications') }}">Staff Alerts</a></li>
                            <li class="{{ request()->routeIs('users.*') ? 'active' : '' }}"><a href="{{ route('users.index') }}">Staff accounts</a></li>
                        @endif
                    </ul>

                    <ul class="nav navbar-nav navbar-right">
                        @if (Auth::user()->role === 'staff')
                            <li class="dropdown dtc-claim-notifications">
                                <a href="#" class="dropdown-toggle dtc-claim-notifications__toggle" data-toggle="dropdown" role="button" aria-expanded="false" aria-label="Claim alerts">
                                    <span class="fa fa-bell-o" aria-hidden="true"></span>
                                    <span class="visible-xs-inline"> Claim alerts</span>
                                    @if ($claimAlertCount > 0)
                                        <span class="dtc-claim-notifications__badge" aria-label="{{ $claimAlertCount }} unread claim {{ $claimAlertCount === 1 ? 'alert' : 'alerts' }}">{{ $claimAlertCount > 99 ? '99+' : $claimAlertCount }}</span>
                                    @endif
                                </a>
                                <ul class="dropdown-menu dtc-claim-notifications__menu" role="menu">
                                    <li class="dtc-claim-notifications__heading">
                                        <strong>Unread claim alerts</strong>
                                        <span>{{ number_format($claimAlertCount) }}</span>
                                    </li>
                                    @forelse ($claimAlerts as $claimAlert)
                                        <li>
                                            <form method="POST" action="{{ route('claims.alerts.open', $claimAlert->claim) }}">
                                                @csrf
                                                <button type="submit" class="dtc-claim-notifications__item">
                                                    <strong>{{ $claimAlert->claim->claim_number }}</strong>
                                                    <span>{{ $claimAlert->message }}: {{ $claimAlert->claim->subject }}</span>
                                                    <small>{{ $claimAlert->created_at->diffForHumans() }} &middot; Open and mark read</small>
                                                </button>
                                            </form>
                                        </li>
                                    @empty
                                        <li class="dtc-claim-notifications__empty">No unread claim alerts.</li>
                                    @endforelse
                                    <li class="dtc-claim-notifications__footer"><a href="{{ route('claims.index') }}">View all claims</a></li>
                                </ul>
                            </li>
                        @endif
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false"><i class="fa fa-user-o"></i> &nbsp;{{ Auth::user()->name }} <span class="caret"></span></a>
                            <ul class="dropdown-menu" role="menu">
                                <li><a href="{{ route('profile.show') }}">My Profile</a></li>
                                <li><a href="{{ route('password.edit') }}">Change Password</a></li>
                                <li><a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                    <form id="logout-form" method="POST" action="{{ route('logout') }}" style="display:none">@csrf</form>
                @else
                    <ul class="nav navbar-nav navbar-right">
                        <li><a href="{{ route('login') }}">Login</a></li>
                    </ul>
                @endauth
            </div>
        </div>
    </nav>

    <div class="container">
        @if (session('status'))
            <div class="alert alert-success" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any() && ! request()->routeIs('login'))
            <div class="alert alert-danger" role="alert">
                <strong>Whoops!</strong> There were some problems with your input.<br><br>
                <ul>@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
            </div>
        @endif
    </div>

    @yield('content')

    <footer class="dtc-admin-footer">
        <div class="container dtc-admin-footer__inner">
            <span class="dtc-admin-footer__brand">{{ config('dtc.brand_name') }}</span>
            <span>&copy; {{ now()->year }} {{ config('dtc.brand_name') }}. All rights reserved.</span>
        </div>
    </footer>

    <script src="{{ asset('/js/jquery.min.js') }}"></script>
    <script src="{{ asset('/js/bootstrap.min.js') }}"></script>
    @stack('scripts')
</body>
</html>

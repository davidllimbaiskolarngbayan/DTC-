@extends('layouts.app')
@section('title', 'Edit registrant')

@section('content')
<div class="container">
    <h3>Edit user</h3>
    <div class="panel panel-default">
        <div class="panel-body">
            <form class="form-horizontal" method="POST" action="{{ route('dtc.update', $user) }}">
                @csrf @method('PUT')
                @php
                    $fields = [
                        'dtc_card_no' => ['DTC Code', 'text', $user->dtc_card_no],
                        'first_name' => ['First name', 'text', $user->firstname],
                        'last_name' => ['Last name', 'text', $user->lastname],
                        'email' => ['Email', 'email', $user->email],
                        'contact_no' => ['Contact Number', 'text', $user->contact_no],
                        'vehicle_reg_id' => ['Registration ID', 'text', $user->vehicle_reg_id],
                        'brand' => ['Brand', 'text', $user->brand],
                        'model' => ['Model', 'text', $user->model],
                        'mileage' => ['Mileage', 'number', $user->mileage],
                        'tyre' => ['Tyre', 'text', $user->tyre],
                        'tyre_size' => ['Tyre Size', 'text', $user->tyre_size],
                        'rim_size' => ['Rim Size', 'text', $user->rim_size],
                        'quantity' => ['Quantity', 'number', $user->quantity],
                        'date_of_purchase' => ['Date of Purchase', 'date', $user->date_of_purchase->format('Y-m-d')],
                        'find_retailer' => ['Store Name', 'text', $user->store_name],
                        'state' => ['State', 'text', $user->state],
                    ];
                @endphp
                @foreach ($fields as $name => [$label, $type, $value])
                    <div class="form-group">
                        <label for="{{ $name }}" class="col-md-4 control-label">{{ $label }}</label>
                        <div class="col-md-6">
                            <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" class="form-control" value="{{ old($name, $value) }}">
                        </div>
                    </div>
                @endforeach
                <div class="form-group">
                    <div class="col-md-6 col-md-offset-4">
                        <button type="submit" class="btn btn-action btn-action--primary">Save</button>
                        <a class="btn btn-action" href="{{ route('dtc.show', $user) }}">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

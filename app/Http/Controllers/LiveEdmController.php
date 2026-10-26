<?php

namespace App\Http\Controllers;

use App\Models\Dtc;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class LiveEdmController extends Controller
{
    public function dtcThankYou(string $encUserId)
    {
        $dtc = $this->resolve($encUserId);

        return view('emails.registrations.thank-you', [
            'data' => $dtc->thankYouData(),
            'unsubscribeUrl' => $dtc->subscribe ? route('dtc.unsubscribe', $encUserId) : null,
        ]);
    }

    public function edit(string $encUserId)
    {
        $dtc = $this->resolve($encUserId);

        return view('unsubscribe.edit', ['encUserId' => $encUserId, 'subscribed' => $dtc->subscribe]);
    }

    public function update(string $encUserId)
    {
        $this->resolve($encUserId)->update(['subscribe' => false]);

        return redirect()->route('dtc.unsubscribed');
    }

    public function thankyou()
    {
        return view('unsubscribe.thankyou');
    }

    private function resolve(string $encUserId): Dtc
    {
        try {
            $id = Crypt::decryptString($encUserId);
        } catch (DecryptException) {
            abort(404);
        }

        return Dtc::findOrFail($id);
    }
}

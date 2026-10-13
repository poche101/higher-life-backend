<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['token' => 'required|string|max:500', 'platform' => 'required|in:android,ios']);

        DeviceToken::updateOrCreate(['token' => $data['token']], [
            'user_id' => $request->user()->id, 'platform' => $data['platform'], 'is_active' => true,
        ]);

        return response()->json(['message' => 'Device registered.']);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return new UserResource($request->user());
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'first_name' => 'sometimes|string|max:80',
            'last_name' => 'sometimes|string|max:80',
            'date_of_birth' => 'sometimes|nullable|date|before:today',
            'avatar' => 'sometimes|image|max:2048',
            'notifications_enabled' => 'sometimes|boolean',
            'notification_time' => 'sometimes|date_format:H:i',
            'reminder_frequency' => 'sometimes|in:daily,weekdays,weekly,off',
            'password' => 'sometimes|string|min:8|confirmed',
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) Storage::disk(config('espee.media_disk'))->delete($user->avatar);
            $data['avatar'] = $request->file('avatar')->store('avatars', config('espee.media_disk'));
        }

        $user->fill($data);
        if (isset($data['first_name']) || isset($data['last_name'])) {
            $user->name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
        }
        $user->save();

        return new UserResource($user->fresh());
    }

    public function destroy(Request $request)
    {
        $request->validate(['password' => 'required|string']);
        $user = $request->user();

        if (! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Password is incorrect.'], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Your account has been deleted.']);
    }
}

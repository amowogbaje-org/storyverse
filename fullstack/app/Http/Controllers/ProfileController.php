<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return view('profile.show', ['user' => $request->user(), 'currencies' => config('currencies')]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        $request->user()->update($data);

        return back()->with('status', 'Profile updated.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $request->user()->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $request->user()->update(['password' => Hash::make($data['password'])]);

        return back()->with('status', 'Password updated.');
    }

    public function requestAuthor(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'reader' && ($user->author_request_status ?? 'none') !== 'pending') {
            $user->update(['author_request_status' => 'pending', 'author_requested_at' => now()]);
        }

        return back()->with('status', 'Your request has been sent to an admin for review.');
    }
}

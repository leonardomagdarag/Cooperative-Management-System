<?php

namespace App\Http\Controllers\CRUD;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class UserController extends Controller
{
    // READ - show all users
    public function index()
    {
        $users = User::all();
        return view('user.index', compact('users'));
    }

    // CREATE - show create form
    public function create()
    {
        return view('users.create');
    }

    // STORE - save new user
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'position' => 'required|string|max:255',
        ]);

        User::create($request->all());

        return redirect()->route('users.index')->with('success', 'User added successfully.');
    }

    // SHOW - show one user
    public function show(User $user)
    {
        return view('users.show', compact('user'));
    }

    // EDIT - show edit form
    public function edit(user $user)
    {
        return view('users.edit', compact('user'));
    }

    // UPDATE - update an user
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'position' => 'required|string|max:255',
        ]);

        $user->update($request->all());

        return redirect()->route('users.index')->with('success', 'user updated successfully.');
    }

    // DELETE - remove an user
    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->route('users.index')->with('success', 'user deleted successfully.');
    }
}

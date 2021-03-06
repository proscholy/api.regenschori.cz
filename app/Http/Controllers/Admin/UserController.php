<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\User;
use App\Author;

use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(){
        $users = User::all();
        return view('admin.user.index', compact('users'));
    }

    public function create(){
        return view('admin.user.create');
    }

    public function store(Request $request)
    {
        $user = User::create([
            'email' => $request->email
        ]);

        return redirect()->route('admin.user.edit', ['user' => $user->id]);
    }

    public function edit(User $user)
    {
        return view('admin.user.edit', [
            'user' => $user,
            'roles' => Role::orderBy('name', 'desc')->get(),
            'all_authors' => Author::all(),
            'assigned_authors' => $user->assigned_authors
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        $user->delete();

        if ($request->has("redirect")) {
            return redirect($request->redirect);
        }

        return redirect()->back();
    }

    public function update(Request $request, User $user)
    {
        $user->update($request->all());

        // assign role
        $user->assignRole(Role::find($request->role));
        $user->save();

        // assign authors
        if ($request->assigned_authors !== NULL) {
            $user->assigned_authors()->sync($request->assigned_authors);
            $user->save();
        } else {
            $user->assigned_authors()->sync([]);
            $user->save();
        }

        // pass
        if ($request->new_pass != '') 
        {
            $user->password = Hash::make($request->new_pass);
            $user->save();
        }

        return redirect()->route('admin.user.index');
    }
}

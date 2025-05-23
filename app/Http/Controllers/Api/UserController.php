<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    // Get all users
    public function index()
    {
        $users = User::all();
        return response()->json(['success'=>true, 'data'=>$users]);
    }

    // Register/create user
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'password'  => 'required|string|min:6',
            'role'      => 'required|string|in:Admin,User',
            'phone'     => 'nullable|string|max:20',
            'address'   => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success'=>false, 'errors'=>$validator->errors()], 422);
        }

        $user = User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'role'      => $request->role,
            'phone'     => $request->phone,
            'address'   => $request->address
        ]);

        return response()->json(['success'=>true, 'data'=>$user]);
    }

    // Update user
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['success'=>false, 'message'=>'User not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'      => 'sometimes|required|string|max:255',
            'email'     => 'sometimes|required|email|unique:users,email,'.$id,
            'password'  => 'nullable|string|min:6',
            'role'      => 'sometimes|required|string|in:Admin,User',
            'phone'     => 'nullable|string|max:20',
            'address'   => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success'=>false, 'errors'=>$validator->errors()], 422);
        }

        $user->fill($request->except('password'));
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        $user->save();

        return response()->json(['success'=>true, 'data'=>$user]);
    }

    // Delete user
    public function destroy($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['success'=>false, 'message'=>'User not found'], 404);
        }
        $user->delete();
        return response()->json(['success'=>true, 'message'=>'User deleted']);
    }
}

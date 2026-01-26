<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /**
     * Show login form
     */
    public function showLogin()
    {
        // Auto-create admin user if none exists
        try {
            $userCount = User::count();
            
            if ($userCount == 0) {
                User::create([
                    'username' => 'admin',
                    'email' => 'admin@system.com',
                    'password' => Hash::make('admin123'),
                    'role' => 'Admin',
                    'first_name' => 'System',
                    'last_name' => 'Administrator',
                ]);
                session()->flash('success', 'System initialized! Default admin user created.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Database error: ' . $e->getMessage());
        }

        return view('auth.login');
    }

    /**
     * Handle login
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $username = $request->input('username');
        $password = $request->input('password');

        // Find user by username
        $user = User::where('username', $username)->first();

        if ($user) {
            // Check password - support both plain text (original system) and Bcrypt (Laravel)
            $passwordValid = false;
            
            // First, try plain text comparison (for existing users from original system)
            if ($user->password === $password) {
                $passwordValid = true;
                // Upgrade to Bcrypt for future logins
                $user->password = Hash::make($password);
                $user->save();
            }
            // If plain text doesn't match, try Bcrypt (for new users or upgraded passwords)
            else {
                // Check if password looks like a hash (starts with $2y$ for Bcrypt)
                if (strpos($user->password, '$2y$') === 0 || strpos($user->password, '$2a$') === 0) {
                    // Password is hashed, use Hash::check
                    try {
                        $passwordValid = Hash::check($password, $user->password);
                    } catch (\Exception $e) {
                        // If hash check fails, password is invalid
                        $passwordValid = false;
                    }
                }
            }
            
            if ($passwordValid) {
                // Store user data in session (matching original system)
                session([
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'role' => $user->role,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                ]);

                return redirect()->route('dashboard');
            }
        }

        return back()->with('error', 'Invalid username or password')->withInput();
    }

    /**
     * Handle logout
     */
    public function logout()
    {
        session()->flush();
        return redirect()->route('login');
    }
}

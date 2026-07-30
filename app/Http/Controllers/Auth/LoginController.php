<?php

namespace App\Http\Controllers\Auth;

use id;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Activity_logs;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        // Kalau sudah login, langsung redirect ke dashboard sesuai role
        if (Auth::check()) {
            return match (Auth::user()->role) {
                'admin' => redirect()->route('admin.dashboard.page'),
                'kasir' => redirect()->route('kasir.transaksi.page'),
                'gudang' => redirect()->route('gudang.purchase.page'),
                default => abort(403),
            };
        }

        return view('Login');
    }


    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            Activity_logs::create([
                'user_id'    => Auth::user()->id,
                'activity_type'        => 'login',
                'description' => 'User "' . Auth::user()->name . '" berhasil login.',
                
            ]);
            // Redirect sesuai role
            return match (Auth::user()->role) {
                'admin' => redirect()->route('admin.dashboard.page')->with('login_success', 'Berhasil login sebagai admin.'),
                'kasir' => redirect()->route('kasir.transaksi.page')->with('login_success', 'Berhasil login sebagai kasir.'),
                'gudang' => redirect()->route('gudang.purchase.page')->with('login_success', 'Berhasil login sebagai gudang.'),
                default => abort(403),
            };
        }

        return back()->with('error', 'Email atau password salah');
    }

    // Logout
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

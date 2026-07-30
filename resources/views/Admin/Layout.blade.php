<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite('resources/css/app.css')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-gray-100">

    @if (session()->has('login_success'))
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Login berhasil',
                text: @json(session('login_success')),
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true,
                heightAuto: false,
            });
        </script>
    @endif

    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-white shadow-lg flex flex-col h-screen">
            <!-- Top menu -->
            <div class="p-6 font-bold text-xl border-b">Admin Dashboard</div>
            <nav class="p-4 space-y-2 flex-1">
                <a href="{{ route('admin.dashboard.page') }}"
                    class="block px-4 py-2 rounded hover:bg-slate-300">Dashboard</a>
                <a href="{{ route('admin.product.page') }}"
                    class="block px-4 py-2 rounded hover:bg-slate-300">Products</a>
                <a href="{{ route('admin.supplier.page') }}"
                    class="block px-4 py-2 rounded hover:bg-slate-300">Suppliers</a>
                <a href="{{ route('admin.user.page') }}" 
                    class="block px-4 py-2 rounded hover:bg-slate-300">Users</a>
                <a href=" {{ route('admin.laporan.page') }}" 
                    class="block px-4 py-2 rounded hover:bg-slate-300">Laporan</a>
            </nav>

            <!-- Logout button di bawah -->
            <form method="GET" action="{{ route('logout') }}" class="p-4 mt-auto border-t">
                @csrf
                <button type="submit"
                    class="w-full flex text-left px-4 py-2 rounded hover:bg-slate-300 text-red-600 font-semibold">
                    Logout
                </button>
            </form>
        </aside>


        <!-- Main Content -->
        <main class="flex-1 p-6 h-screen overflow-y-auto">

            @yield('content')


        </main>
    </div>
    @yield('script')
</body>

</html>

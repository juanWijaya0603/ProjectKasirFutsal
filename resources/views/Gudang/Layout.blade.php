<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Gudang Dashboard</title>
    @vite('resources/css/app.css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- jQuery (wajib) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    @stack('styles')
</head>

<body class="bg-gray-100 text-gray-800">

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
        <aside class="w-64 bg-white shadow p-4 flex flex-col h-screen">
            <h2 class="text-lg font-semibold mb-4">Gudang Dashboard</h2>
            <nav class="space-y-2">
                <a href="{{ route('gudang.purchase.page') }}" class="block py-2 px-3 rounded hover:bg-gray-200">Purchase
                    Products</a>
                <a href="{{ route('gudang.laporan-purchase.page') }}"
                    class="block py-2 px-3 rounded hover:bg-gray-200">Purchase Reports</a>
            </nav>

            <!-- Logout button di bawah -->
            <form method="GET" action="{{ route('logout') }}" class="p-4 mt-auto border-t">
                @csrf
                <button type="submit"
                    class="w-full flex text-left px-4 py-2 rounded hover:bg-red-100 text-red-600 font-semibold">
                    Logout
                </button>
            </form>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 p-6 h-screen overflow-y-auto">
            @yield('content')
        </div>
    </div>

    @yield('script')
</body>

</html>

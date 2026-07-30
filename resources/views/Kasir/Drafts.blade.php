@extends('Kasir.Layout')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">
                Draft Transaksi
            </h1>

            <p class="mt-1 text-sm text-gray-600">
                Daftar transaksi yang belum dikonfirmasi pembayarannya.
            </p>
        </div>

        <a href="{{ route('kasir.transaksi.page') }}"
            class="rounded bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700">
            Buat Transaksi
        </a>
    </div>

    @if (session('success'))
        <div class="mb-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="overflow-hidden rounded bg-white shadow">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-gray-100 text-gray-700">
                    <tr>
                        <th class="px-4 py-3">Invoice</th>
                        <th class="px-4 py-3">Jumlah Item</th>
                        <th class="px-4 py-3">Total</th>
                        <th class="px-4 py-3">Terakhir Diubah</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($drafts as $draft)
                        <tr class="border-t">
                            <td class="px-4 py-3 font-medium">
                                {{ $draft->invoice_number }}
                            </td>

                            <td class="px-4 py-3">
                                {{ $draft->sale_items_count }} item
                            </td>

                            <td class="px-4 py-3 text-green-700">
                                Rp {{ number_format($draft->total_price, 0, ',', '.') }}
                            </td>

                            <td class="px-4 py-3 text-gray-600">
                                {{ $draft->updated_at->format('d/m/Y H:i') }}
                            </td>

                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('kasir.drafts.show', $draft) }}"
                                        class="rounded bg-blue-600 px-3 py-1.5 text-xs text-white hover:bg-blue-700">
                                        Lanjutkan
                                    </a>

                                    <form
                                        method="POST"
                                        action="{{ route('kasir.drafts.destroy', $draft) }}"
                                        onsubmit="return confirm('Batalkan draft ini?')">
                                        @csrf
                                        @method('DELETE')

                                        <button
                                            type="submit"
                                            class="rounded bg-red-600 px-3 py-1.5 text-xs text-white hover:bg-red-700">
                                            Batalkan
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5"
                                class="px-4 py-10 text-center text-gray-500">
                                Belum ada draft transaksi aktif.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $drafts->links() }}
    </div>
@endsection
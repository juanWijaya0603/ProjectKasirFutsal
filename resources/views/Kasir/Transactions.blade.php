@extends('Kasir.Layout')

@section('content')
    <div class="mb-5 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">
                {{ $draft ? 'Lanjutkan Draft Transaksi' : 'Buat Transaksi Penjualan' }}
            </h1>

            <div
                id="draft-info"
                class="mt-1 text-sm text-gray-600 {{ $draft ? '' : 'hidden' }}">
                Nomor draft:
                <span id="draft-invoice" class="font-semibold">
                    {{ $draft?->invoice_number }}
                </span>
            </div>
        </div>

        <a
            href="{{ route('kasir.drafts.index') }}"
            class="rounded bg-gray-700 px-4 py-2 text-sm text-white hover:bg-gray-800">
            Daftar Draft
        </a>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Daftar produk --}}
        <div class="lg:col-span-2">
            <div class="mb-4 flex items-center justify-end">
                <input
                    type="text"
                    id="search"
                    placeholder="Cari produk berdasarkan nama atau ID..."
                    class="w-full rounded border px-3 py-2 focus:ring-2 focus:ring-blue-500 md:w-1/2">
            </div>

            <div class="overflow-auto rounded bg-white shadow">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="px-4 py-2">ID</th>
                            <th class="px-4 py-2">Nama</th>
                            <th class="px-4 py-2">Stok</th>
                            <th class="px-4 py-2">Harga</th>
                            <th class="px-4 py-2">Aksi</th>
                        </tr>
                    </thead>

                    <tbody id="product-table">
                        @foreach ($products as $product)
                            <tr class="border-t">
                                <td class="px-4 py-2">
                                    {{ $product->id }}
                                </td>

                                <td class="px-4 py-2">
                                    {{ $product->name }}
                                </td>

                                <td class="px-4 py-2">
                                    {{ $product->stock }}
                                </td>

                                <td class="px-4 py-2 text-green-600">
                                    Rp {{ number_format($product->price, 0, ',', '.') }}
                                </td>

                                <td class="px-4 py-2">
                                    <button
                                        type="button"
                                        class="add-to-cart rounded px-3 py-1 text-sm text-white
                                            {{ $product->stock > 0
                                                ? 'bg-blue-600 hover:bg-blue-700'
                                                : 'cursor-not-allowed bg-gray-400' }}"
                                        data-id="{{ $product->id }}"
                                        data-name="{{ $product->name }}"
                                        data-price="{{ $product->price }}"
                                        data-stock="{{ $product->stock }}"
                                        @disabled($product->stock <= 0)>
                                        {{ $product->stock > 0 ? 'Tambah' : 'Stok Habis' }}
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Keranjang --}}
        <div>
            <div class="sticky top-6 rounded bg-white p-4 shadow">
                <h2 class="mb-3 text-lg font-semibold">
                    Item Transaksi
                </h2>

                <div
                    id="cart-empty"
                    class="rounded bg-gray-50 p-4 text-center text-sm text-gray-500">
                    Keranjang masih kosong.
                </div>

                <div
                    id="cart-items"
                    class="max-h-96 space-y-3 overflow-y-auto text-sm">
                </div>

                <div class="mt-4 border-t pt-3">
                    <div class="flex justify-between font-semibold">
                        <span>Total</span>

                        <span>
                            Rp <span id="total">0</span>
                        </span>
                    </div>
                </div>

                {{-- Status penyimpanan draft --}}
                <div
                    id="draft-save-status"
                    class="mt-4 rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">
                    Keranjang belum memiliki draft tersimpan.
                </div>

                <button
                    type="button"
                    id="save-draft"
                    data-transaction-action
                    class="mt-4 w-full rounded bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                    {{ $draft ? 'Perbarui Draft' : 'Simpan Draft' }}
                </button>

                <button
                    type="button"
                    id="confirm-payment"
                    data-transaction-action
                    class="mt-2 w-full rounded bg-green-600 px-4 py-2 text-white hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50">
                    Konfirmasi Pembayaran
                </button>

                <button
                    type="button"
                    id="cancel-draft"
                    data-transaction-action
                    class="mt-2 w-full rounded bg-red-600 px-4 py-2 text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50
                        {{ $draft ? '' : 'hidden' }}">
                    Batalkan Draft
                </button>
            </div>
        </div>
    </div>

    @include('partials.errorAlert')
@endsection

@section('script')
    <script>
        const csrfToken = @json(csrf_token());
        const draftBaseUrl = @json(url('/kasir/drafts'));
        const transactionPageUrl = @json(route('kasir.transaksi.page'));
        const draftsPageUrl = @json(route('kasir.drafts.index'));

        let draftId = @json($draft?->id);
        let draftInvoice = @json($draft?->invoice_number);

        /*
         * Draft yang dibuka dari database dianggap sudah tersimpan.
         * Perubahan berikutnya akan mengubah nilai ini menjadi true.
         */
        let hasUnsavedChanges = false;
        let isProcessing = false;

        const initialCart = @json($draftItems);

        let cart = initialCart.map(item => ({
            product_id: String(item.product_id),
            name: item.name,
            price: Number(item.price),
            quantity: Number(item.quantity),
            stock: Number(item.stock),
        }));

        function escapeHtml(value) {
            const element = document.createElement('div');
            element.textContent = value;

            return element.innerHTML;
        }

        function formatRupiah(value) {
            return Number(value).toLocaleString('id-ID');
        }

        function updateCart() {
            const container = document.getElementById('cart-items');
            const emptyMessage = document.getElementById('cart-empty');
            const totalElement = document.getElementById('total');

            container.innerHTML = '';

            let total = 0;

            cart.forEach((item, index) => {
                const subtotal = item.price * item.quantity;
                total += subtotal;

                const stockWarning = item.quantity > item.stock
                    ? `
                        <p class="mt-1 text-xs text-red-600">
                            Jumlah melebihi stok saat ini (${item.stock}).
                        </p>
                    `
                    : '';

                container.innerHTML += `
                    <div class="rounded border p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">
                                    ${escapeHtml(item.name)}
                                </p>

                                <p class="text-xs text-gray-500">
                                    Rp ${formatRupiah(item.price)} per item
                                </p>

                                ${stockWarning}
                            </div>

                            <button
                                type="button"
                                onclick="deleteItem(${index})"
                                class="text-xs text-red-600 hover:underline">
                                Hapus
                            </button>
                        </div>

                        <div class="mt-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    onclick="decreaseItem(${index})"
                                    class="h-7 w-7 rounded bg-gray-200 hover:bg-gray-300">
                                    −
                                </button>

                                <span class="min-w-8 text-center">
                                    ${item.quantity}
                                </span>

                                <button
                                    type="button"
                                    onclick="increaseItem(${index})"
                                    class="h-7 w-7 rounded bg-gray-200 hover:bg-gray-300">
                                    +
                                </button>
                            </div>

                            <span class="font-medium">
                                Rp ${formatRupiah(subtotal)}
                            </span>
                        </div>
                    </div>
                `;
            });

            emptyMessage.classList.toggle(
                'hidden',
                cart.length > 0
            );

            totalElement.textContent = formatRupiah(total);

            updateActionState();
        }

        function updateSaveStatus() {
            const statusElement = document.getElementById(
                'draft-save-status'
            );

            if (hasUnsavedChanges) {
                statusElement.className =
                    'mt-4 rounded border border-amber-300 ' +
                    'bg-amber-50 px-3 py-2 text-sm text-amber-800';

                statusElement.textContent =
                    'Perubahan keranjang belum disimpan. ' +
                    'Jangan tutup atau refresh halaman sebelum menekan ' +
                    (draftId
                        ? '"Perbarui Draft".'
                        : '"Simpan Draft".');

                return;
            }

            if (draftId) {
                statusElement.className =
                    'mt-4 rounded border border-green-300 ' +
                    'bg-green-50 px-3 py-2 text-sm text-green-800';

                statusElement.textContent =
                    'Semua perubahan draft sudah tersimpan.';

                return;
            }

            statusElement.className =
                'mt-4 rounded border border-gray-200 ' +
                'bg-gray-50 px-3 py-2 text-sm text-gray-600';

            statusElement.textContent =
                'Keranjang belum memiliki draft tersimpan.';
        }

        function updateActionState() {
            const saveButton = document.getElementById('save-draft');
            const confirmButton = document.getElementById(
                'confirm-payment'
            );

            if (isProcessing) {
                saveButton.disabled = true;
                confirmButton.disabled = true;

                return;
            }

            saveButton.disabled = cart.length === 0;

            /*
             * Pembayaran hanya dapat dikonfirmasi jika:
             * - draft sudah tersimpan;
             * - keranjang tidak kosong;
             * - tidak ada perubahan yang belum disimpan.
             */
            confirmButton.disabled =
                !draftId ||
                cart.length === 0 ||
                hasUnsavedChanges;
        }

        function markCartAsChanged() {
            hasUnsavedChanges = true;

            updateSaveStatus();
            updateActionState();
        }

        function addProduct(product) {
            const existingItem = cart.find(
                item => item.product_id === product.product_id
            );

            if (existingItem) {
                if (existingItem.quantity >= existingItem.stock) {
                    alert(
                        'Jumlah produk sudah mencapai stok yang tersedia.'
                    );

                    return;
                }

                existingItem.quantity++;
            } else {
                cart.push({
                    ...product,
                    quantity: 1,
                });
            }

            markCartAsChanged();
            updateCart();
        }

        function increaseItem(index) {
            const item = cart[index];

            if (item.quantity >= item.stock) {
                alert(
                    'Jumlah produk sudah mencapai stok yang tersedia.'
                );

                return;
            }

            item.quantity++;

            markCartAsChanged();
            updateCart();
        }

        function decreaseItem(index) {
            if (cart[index].quantity > 1) {
                cart[index].quantity--;
            } else {
                cart.splice(index, 1);
            }

            markCartAsChanged();
            updateCart();
        }

        function deleteItem(index) {
            cart.splice(index, 1);

            markCartAsChanged();
            updateCart();
        }

        function setProcessing(processing) {
            isProcessing = processing;

            document
                .querySelectorAll('[data-transaction-action]')
                .forEach(button => {
                    button.disabled = processing;
                });

            if (!processing) {
                updateActionState();
            }
        }

        function getErrorMessage(data, fallbackMessage) {
            if (data?.errors) {
                const firstError = Object.values(data.errors)[0];

                if (
                    Array.isArray(firstError) &&
                    firstError.length > 0
                ) {
                    return firstError[0];
                }
            }

            return data?.message || fallbackMessage;
        }

        async function sendJson(url, options = {}) {
            const response = await fetch(url, {
                ...options,

                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    ...(options.headers || {}),
                },
            });

            const data = await response.json().catch(() => ({
                success: false,
                message:
                    'Server memberikan respons yang tidak valid.',
            }));

            if (!response.ok || data.success === false) {
                throw new Error(
                    getErrorMessage(
                        data,
                        'Terjadi kesalahan saat memproses transaksi.'
                    )
                );
            }

            return data;
        }

        function synchronizeCartFromServer(data) {
            if (!Array.isArray(data.items)) {
                return;
            }

            /*
             * Setelah draft disimpan, harga dan total dari server
             * menjadi sumber data utama.
             */
            cart = data.items.map(item => ({
                product_id: String(item.product_id),
                name: item.name,
                price: Number(item.price),
                quantity: Number(item.quantity),
                stock: Number(item.stock),
            }));

            updateCart();
        }

        async function saveDraft() {
            if (cart.length === 0) {
                throw new Error(
                    'Keranjang transaksi masih kosong.'
                );
            }

            const isUpdating = Boolean(draftId);

            const url = isUpdating
                ? `${draftBaseUrl}/${draftId}`
                : draftBaseUrl;

            const method = isUpdating
                ? 'PUT'
                : 'POST';

            const data = await sendJson(url, {
                method,

                body: JSON.stringify({
                    items: cart.map(item => ({
                        product_id: item.product_id,
                        quantity: item.quantity,
                    })),
                }),
            });

            draftId = data.sale_id;
            draftInvoice = data.invoice;

            synchronizeCartFromServer(data);

            document
                .getElementById('draft-info')
                .classList.remove('hidden');

            document
                .getElementById('draft-invoice')
                .textContent = draftInvoice;

            document
                .getElementById('save-draft')
                .textContent = 'Perbarui Draft';

            document
                .getElementById('cancel-draft')
                .classList.remove('hidden');

            if (data.edit_url) {
                window.history.replaceState(
                    {},
                    '',
                    data.edit_url
                );
            }

            hasUnsavedChanges = false;

            updateSaveStatus();
            updateActionState();

            alert(
                `${data.message}\n` +
                `Nomor draft: ${data.invoice}\n` +
                `Total: Rp ${formatRupiah(data.total_price)}`
            );

            return data;
        }

        async function confirmPayment() {
            if (cart.length === 0) {
                alert(
                    'Keranjang transaksi masih kosong.'
                );

                return;
            }

            if (!draftId) {
                alert(
                    'Simpan transaksi sebagai draft terlebih dahulu ' +
                    'sebelum mengonfirmasi pembayaran.'
                );

                return;
            }

            if (hasUnsavedChanges) {
                alert(
                    'Terdapat perubahan keranjang yang belum disimpan. ' +
                    'Tekan "Perbarui Draft" dan periksa total terbaru ' +
                    'sebelum mengonfirmasi pembayaran.'
                );

                return;
            }

            const displayedTotal = document
                .getElementById('total')
                .textContent;

            const confirmed = window.confirm(
                `Pastikan pembayaran sudah diterima.\n\n` +
                `Invoice: ${draftInvoice}\n` +
                `Total: Rp ${displayedTotal}\n\n` +
                `Konfirmasi pembayaran sekarang?`
            );

            if (!confirmed) {
                return;
            }

            setProcessing(true);

            try {
                /*
                 * Tidak memanggil saveDraft() di sini.
                 *
                 * Harga dan total yang dikonfirmasi adalah nilai
                 * draft yang sebelumnya sudah disimpan.
                 */
                const data = await sendJson(
                    `${draftBaseUrl}/${draftId}/confirm`,
                    {
                        method: 'POST',
                        body: JSON.stringify({}),
                    }
                );

                hasUnsavedChanges = false;

                alert(
                    `${data.message}\n` +
                    `Nomor invoice: ${data.invoice}\n` +
                    `Total: Rp ${formatRupiah(data.total_price)}`
                );

                cart = [];
                updateCart();

                window.location.href = transactionPageUrl;
            } catch (error) {
                alert(error.message);
            } finally {
                setProcessing(false);
            }
        }

        async function cancelDraft() {
            if (!draftId) {
                return;
            }

            const confirmed = window.confirm(
                `Draft ${draftInvoice} akan dibatalkan.\n` +
                'Stok produk tidak akan berubah.\n\n' +
                'Lanjutkan?'
            );

            if (!confirmed) {
                return;
            }

            setProcessing(true);

            try {
                const data = await sendJson(
                    `${draftBaseUrl}/${draftId}`,
                    {
                        method: 'DELETE',
                        body: JSON.stringify({}),
                    }
                );

                hasUnsavedChanges = false;

                alert(data.message);

                window.location.href = draftsPageUrl;
            } catch (error) {
                alert(error.message);
            } finally {
                setProcessing(false);
            }
        }

        /*
         * Browser modern menampilkan peringatan bawaan.
         * Isi pesan dialog tidak dapat dikustomisasi.
         */
        window.addEventListener('beforeunload', function (event) {
            if (!hasUnsavedChanges) {
                return;
            }

            event.preventDefault();
            event.returnValue = '';
        });

        document.addEventListener('DOMContentLoaded', function () {
            document
                .querySelectorAll('.add-to-cart')
                .forEach(button => {
                    button.addEventListener('click', function () {
                        addProduct({
                            product_id: String(
                                button.dataset.id
                            ),

                            name: button.dataset.name,

                            price: Number(
                                button.dataset.price
                            ),

                            stock: Number(
                                button.dataset.stock
                            ),
                        });
                    });
                });

            const searchInput = document.getElementById('search');

            const productRows = document.querySelectorAll(
                '#product-table tr'
            );

            searchInput.addEventListener('input', function () {
                const searchValue = this.value.toLowerCase();

                productRows.forEach(row => {
                    const rowText = row.innerText.toLowerCase();

                    row.style.display = rowText.includes(
                        searchValue
                    ) ? '' : 'none';
                });
            });

            document
                .getElementById('save-draft')
                .addEventListener('click', async function () {
                    setProcessing(true);

                    try {
                        await saveDraft();
                    } catch (error) {
                        alert(error.message);
                    } finally {
                        setProcessing(false);
                    }
                });

            document
                .getElementById('confirm-payment')
                .addEventListener(
                    'click',
                    confirmPayment
                );

            document
                .getElementById('cancel-draft')
                .addEventListener(
                    'click',
                    cancelDraft
                );

            updateCart();
            updateSaveStatus();
            updateActionState();
        });
    </script>
@endsection
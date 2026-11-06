<div>
    @php
        $inputCls = 'w-full px-3.5 py-2 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white';
        $modalCls = 'modal-scroll-lock fixed inset-0 z-50 flex items-center justify-center overflow-y-auto overscroll-contain p-4 bg-slate-900/50 backdrop-blur-sm';
    @endphp

    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Daftar Stok Barang Saya</h1>
            <p class="text-sm text-slate-500 mt-1">Kelola persediaan barang dan pantau stok yang menipis</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button wire:click="openImportModal" class="px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-sm font-semibold rounded-lg shadow-sm transition">Import Excel</button>
            <button wire:click="exportExcel" wire:loading.attr="disabled" class="px-3.5 py-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-sm font-semibold rounded-lg shadow-sm transition">
                <span wire:loading.remove wire:target="exportExcel">Export Excel</span>
                <span wire:loading wire:target="exportExcel">Mengekspor...</span>
            </button>
            <button wire:click="openAiModal" class="px-3.5 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold rounded-lg shadow-sm transition">Generate AI</button>
            <button wire:click="openCreateModal" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-lg shadow-sm transition">+ Tambah Stok</button>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-sm text-emerald-700 font-medium">
            {{ session('success') }}
        </div>
    @endif

    <!-- Filters -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 mb-6 flex flex-col sm:flex-row gap-4 items-center justify-between">
        <div class="w-full sm:w-72">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari nama, SKU, atau deskripsi..." class="{{ $inputCls }}">
        </div>
        <div class="flex items-center gap-3 w-full sm:w-auto">
            <label class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Status:</label>
            <select wire:model.live="statusFilter" class="px-3 py-2 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                <option value="">Semua Status</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Stock List Container -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        @forelse ($stocks as $stock)
            @php
                $statusEnum = \App\Features\Stock\Enums\StockStatus::tryFrom($stock->status);
            @endphp
            <div wire:key="stock-{{ $stock->id }}" class="flex flex-col sm:flex-row sm:items-center gap-4 p-4 border-b border-slate-100 last:border-b-0 hover:bg-slate-50/80 transition">
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('stocks.show', $stock->id) }}" class="text-base font-semibold text-slate-900 hover:text-blue-600 transition">{{ $stock->name }}</a>
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full border {{ $statusEnum?->badgeClass() ?? 'bg-slate-100 text-slate-700' }}">{{ $statusEnum?->label() ?? $stock->status }}</span>
                    </div>
                    <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                        @if ($stock->sku)
                            <span>SKU: <span class="font-mono font-semibold text-slate-700">{{ $stock->sku }}</span></span>
                        @endif
                        <span>Stok: <span class="font-semibold text-slate-700">{{ $stock->quantity }} {{ $stock->unit }}</span> (min. {{ $stock->min_stock }})</span>
                        @if (!is_null($stock->price))
                            <span>Harga: Rp {{ number_format((float) $stock->price, 0, ',', '.') }}</span>
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-1 flex-shrink-0">
                    <button type="button" wire:click="adjustQuantity({{ $stock->id }}, -1)" wire:loading.attr="disabled" title="Kurangi 1" aria-label="Kurangi 1"
                        class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-rose-50 hover:text-rose-700 hover:border-rose-200 disabled:opacity-50 text-base font-bold transition">&minus;</button>
                    <button type="button" wire:click="adjustQuantity({{ $stock->id }}, 1)" wire:loading.attr="disabled" title="Tambah 1" aria-label="Tambah 1"
                        class="w-8 h-8 inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-200 disabled:opacity-50 text-base font-bold transition">+</button>
                </div>

                <div class="flex items-center gap-2 flex-shrink-0">
                    <button type="button" wire:click="openEditModal({{ $stock->id }})" class="px-3 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-50 border border-blue-200 rounded-lg transition">Edit Stok</button>
                    <button type="button" wire:click="confirmDelete({{ $stock->id }})" class="px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 border border-rose-200 rounded-lg transition">Hapus Stok</button>
                </div>
            </div>
        @empty
            <div class="py-16 text-center">
                <p class="text-sm font-semibold text-slate-700">Belum ada stok ditemukan</p>
                <p class="text-xs text-slate-400 mt-1">Tambah stok baru atau generate otomatis menggunakan AI</p>
            </div>
        @endforelse

        @if ($stocks->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $stocks->links() }}
            </div>
        @endif
    </div>

    <!-- Create / Edit Modal -->
    @if ($showFormModal)
        <div x-data="{ show: true }" x-show="show" x-cloak class="{{ $modalCls }}">
            <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 max-h-[90vh] overflow-hidden flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 bg-blue-600">
                    <h3 class="text-lg font-bold text-white">{{ $editingStockId ? 'Edit Stok' : 'Tambah Stok Baru' }}</h3>
                    <button type="button" @click="show = false; $wire.closeFormModal()" class="text-white/80 hover:text-white text-xl leading-none">&times;</button>
                </div>

                <form wire:submit.prevent="saveStock" class="flex flex-col flex-1 min-h-0">
                    <div class="space-y-4 px-6 py-5 overflow-y-auto flex-1">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Nama Barang</label>
                            <input wire:model="formName" type="text" placeholder="Contoh: Kabel USB-C 1 meter" class="{{ $inputCls }}">
                            @error('formName') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">SKU</label>
                                <input wire:model="formSku" type="text" placeholder="Contoh: ELK-001" class="{{ $inputCls }}">
                                @error('formSku') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Satuan</label>
                                <input wire:model="formUnit" type="text" placeholder="pcs" class="{{ $inputCls }}">
                                @error('formUnit') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Jumlah Stok</label>
                                <input wire:model="formQuantity" type="number" min="0" step="1" class="{{ $inputCls }}">
                                @error('formQuantity') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Stok Minimum</label>
                                <input wire:model="formMinStock" type="number" min="0" step="1" class="{{ $inputCls }}">
                                @error('formMinStock') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Harga (Rp)</label>
                                <input wire:model="formPrice" type="number" min="0" step="any" class="{{ $inputCls }}">
                                @error('formPrice') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <p class="text-xs text-slate-400">Status stok (Available / Low Stock / Out of Stock) dihitung otomatis dari jumlah dan stok minimum.</p>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Deskripsi (opsional)</label>
                            <textarea wire:model="formDescription" rows="3" placeholder="Spesifikasi atau catatan barang..." class="{{ $inputCls }}"></textarea>
                            @error('formDescription') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 px-6 py-4 bg-blue-50 border-t border-blue-100">
                        <button type="button" @click="show = false; $wire.closeFormModal()" class="px-4 py-2 text-sm font-semibold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 rounded-lg transition">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" class="px-6 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-75 rounded-lg shadow-sm transition">
                            <span wire:loading.remove wire:target="saveStock">Simpan</span>
                            <span wire:loading wire:target="saveStock">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Import Modal -->
    @if ($showImportModal)
        <div x-data="{ show: true }" x-show="show" x-cloak class="{{ $modalCls }}">
            <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden">
                <div class="flex items-center justify-between px-6 py-4 bg-slate-700">
                    <h3 class="text-lg font-bold text-white">Import Stok dari File Excel</h3>
                    <button type="button" @click="show = false; $wire.closeImportModal()" class="text-white/80 hover:text-white text-xl leading-none">&times;</button>
                </div>

                <form wire:submit.prevent="importExcel">
                    <div class="px-6 py-5 space-y-3">
                        <p class="text-xs text-slate-500">
                            Kolom A: No | B: Name | C: SKU | D: Description | E: Quantity | F: Unit | G: Price | H: Min Stock | I: Status (opsional, dihitung otomatis jika kosong)
                        </p>
                        <input wire:model="importFile" type="file" accept=".xlsx,.xls,.csv" class="block w-full text-sm text-slate-600">
                        @error('importFile') <span class="text-xs text-rose-600 block">{{ $message }}</span> @enderror
                        <div wire:loading wire:target="importFile" class="text-xs text-blue-600 font-semibold">Mengunggah file...</div>
                    </div>

                    <div class="flex items-center justify-end gap-3 px-6 py-4 bg-slate-50 border-t border-slate-100">
                        <button type="button" @click="show = false; $wire.closeImportModal()" class="px-4 py-2 text-sm font-semibold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 rounded-lg transition">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" class="px-5 py-2 text-sm font-semibold text-white bg-slate-700 hover:bg-slate-800 disabled:opacity-75 rounded-lg shadow-sm transition">
                            <span wire:loading.remove wire:target="importExcel">Import</span>
                            <span wire:loading wire:target="importExcel">Memproses...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- AI Stock Generator Modal -->
    @if ($showAiModal)
        <div x-data="{ show: true }" x-show="show" x-cloak class="{{ $modalCls }}">
            <div class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl border border-slate-200 max-h-[90vh] overflow-hidden flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 bg-violet-600">
                    <div>
                        <h3 class="text-lg font-bold text-white">Generate Stok Otomatis dengan AI</h3>
                        <p class="text-xs text-violet-100">Minta AI membuatkan beberapa item stok terstruktur sesuai kebutuhan Anda</p>
                    </div>
                    <button type="button" @click="show = false; $wire.closeAiModal()" class="text-white/80 hover:text-white text-xl leading-none">&times;</button>
                </div>

                <div class="px-6 py-5 space-y-4 overflow-y-auto flex-1">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Perintah untuk AI</label>
                        <textarea wire:model="aiPrompt" rows="3" placeholder="Contoh: Buatkan 5 barang ATK untuk kantor kecil beserta harga dan stok awalnya..." class="{{ $inputCls }}"></textarea>
                        @error('aiPrompt') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-end gap-3">
                        <div class="w-28">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Jumlah</label>
                            <input wire:model="aiCount" type="number" min="1" class="{{ $inputCls }}">
                            @error('aiCount') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <button type="button" wire:click="generateWithAi" wire:loading.attr="disabled" class="px-5 py-2 text-sm font-semibold text-white bg-violet-600 hover:bg-violet-700 disabled:opacity-75 rounded-lg shadow-sm transition">
                            <span wire:loading.remove wire:target="generateWithAi">Generate</span>
                            <span wire:loading wire:target="generateWithAi">Memproses...</span>
                        </button>
                    </div>

                    <div wire:loading wire:target="generateWithAi" class="text-xs text-violet-700 font-medium animate-pulse">
                        Menghubungi AI untuk menyusun item stok... Mohon tunggu beberapa detik.
                    </div>

                    @if ($aiErrorMessage)
                        <div class="px-4 py-3 rounded-lg bg-rose-50 border border-rose-200 text-sm text-rose-700">{{ $aiErrorMessage }}</div>
                    @endif

                    @if (!empty($aiGeneratedItems))
                        <div>
                            <p class="text-sm font-semibold text-slate-700 mb-2">Pilih stok yang ingin ditambahkan:</p>
                            <div class="space-y-2">
                                @foreach ($aiGeneratedItems as $index => $item)
                                    <label wire:key="ai-item-{{ $index }}" class="flex items-start gap-3 p-3 rounded-lg border border-slate-200 hover:bg-slate-50 cursor-pointer">
                                        <input type="checkbox" wire:model="aiSelectedIndices" value="{{ $index }}" class="mt-1 rounded border-slate-300">
                                        <div class="min-w-0 text-sm">
                                            <span class="font-bold text-slate-900">{{ $item['name'] }}</span>
                                            <span class="text-slate-400">{{ $item['quantity'] ?? 0 }} {{ $item['unit'] ?? 'pcs' }}@if (!is_null($item['price'] ?? null)) &bull; Rp {{ number_format((float) $item['price'], 0, ',', '.') }}@endif</span>
                                            @if (!empty($item['description']))
                                                <p class="text-xs text-slate-500 mt-0.5">{{ $item['description'] }}</p>
                                            @endif
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex items-center justify-end gap-3 px-6 py-4 bg-violet-50 border-t border-violet-100">
                    <button type="button" @click="show = false; $wire.closeAiModal()" class="px-4 py-2 text-sm font-semibold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 rounded-lg transition">Tutup</button>
                    @if (!empty($aiGeneratedItems))
                        <button type="button" wire:click="saveAiGenerated" wire:loading.attr="disabled" class="px-5 py-2 text-sm font-semibold text-white bg-violet-600 hover:bg-violet-700 disabled:opacity-75 rounded-lg shadow-sm transition">Simpan ke Stok Saya</button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Delete Stock Confirmation Modal -->
    @if ($confirmingStockDeletionId)
        <div x-data="{ show: true }" x-show="show" x-cloak class="{{ $modalCls }}">
            <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 bg-rose-600">
                    <h3 class="text-base font-bold text-white">Konfirmasi Hapus Stok</h3>
                    <p class="text-xs text-rose-100">Tindakan ini tidak dapat dibatalkan</p>
                </div>
                <div class="px-6 py-5">
                    <p class="text-sm text-slate-600">Apakah Anda yakin ingin menghapus stok ini secara permanen dari daftar stok Anda?</p>
                </div>
                <div class="flex items-center justify-end gap-3 px-6 py-4 bg-rose-50 border-t border-rose-100">
                    <button type="button" @click="show = false; $wire.cancelDelete()" class="px-4 py-2 text-xs font-semibold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 rounded-lg transition">Batal</button>
                    <button type="button" wire:click="deleteStock" wire:loading.attr="disabled" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 disabled:opacity-75 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                        <span wire:loading.remove wire:target="deleteStock">Ya, Hapus Stok</span>
                        <span wire:loading wire:target="deleteStock">Menghapus...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
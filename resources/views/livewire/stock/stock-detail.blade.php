<div>
    <link rel="stylesheet" href="https://uicdn.toast.com/editor/latest/toastui-editor.min.css" />
    <script src="https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js"></script>

    @php
        $statusEnum = \App\Features\Stock\Enums\StockStatus::tryFrom($stock->status);
        $badge = $statusEnum?->badgeClass() ?? 'bg-slate-100 text-slate-700';
        $statusLabel = $statusEnum?->label() ?? $stock->status;
        $coverUrl = $stock->coverImageUrl();
        $inputCls = 'w-full px-3.5 py-2 text-sm rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white';
    @endphp

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('stocks.index') }}" class="p-2 rounded-xl text-slate-500 hover:text-slate-800 hover:bg-slate-200 transition" title="Kembali ke Daftar Stok">&larr;</a>
            <div>
                <div class="flex items-center gap-2.5">
                    <h1 class="text-2xl font-bold text-slate-900 truncate max-w-md sm:max-w-xl">{{ $stock->name }}</h1>
                    <span class="px-2.5 py-0.5 text-xs font-semibold rounded-full border {{ $badge }}">{{ $statusLabel }}</span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Dibuat pada {{ $stock->created_at ? $stock->created_at->format('d M Y H:i') : '-' }}</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button wire:click="openEditModal" wire:loading.attr="disabled" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-75 text-white text-sm font-semibold rounded-xl shadow-sm transition">Edit Stok</button>
            <button wire:click="adjustQuantity(-1)" wire:loading.attr="disabled" class="px-3 py-2 text-xs font-semibold rounded-lg border bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100 disabled:opacity-50 transition">&minus; 1</button>
            <button wire:click="adjustQuantity(1)" wire:loading.attr="disabled" class="px-3 py-2 text-xs font-semibold rounded-lg border bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100 disabled:opacity-50 transition">+ 1</button>
            <button wire:click="confirmDeleteStock" wire:loading.attr="disabled" class="px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200 text-sm font-semibold rounded-xl transition">Hapus</button>
        </div>
    </div>

    <!-- Cover -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-8">
        <input type="file" wire:model="coverPhoto" id="coverPhotoInput" accept="image/*" class="hidden">

        @if ($coverUrl)
            <div class="relative group h-64 sm:h-80 w-full bg-slate-900 overflow-hidden">
                <img src="{{ $coverUrl }}?v={{ time() }}" alt="Cover Stok" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-slate-900/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-3">
                    <button type="button" onclick="document.getElementById('coverPhotoInput').click()" class="px-4 py-2 bg-white text-slate-800 text-xs font-semibold rounded-xl shadow-lg hover:bg-slate-100 transition">Ganti Cover</button>
                    <button type="button" wire:click="confirmDeleteCover" class="px-4 py-2 bg-rose-600 text-white text-xs font-semibold rounded-xl shadow-lg hover:bg-rose-700 transition">Hapus Cover</button>
                </div>
            </div>
        @else
            <div class="p-8 text-center bg-gradient-to-b from-slate-50 to-white flex flex-col items-center justify-center border-b border-slate-100">
                <h3 class="text-sm font-bold text-slate-800">Tambahkan Cover Gambar untuk Barang Ini</h3>
                <p class="text-xs text-slate-400 mt-1 max-w-sm">Mendukung format gambar PNG, JPG, GIF hingga ukuran 5MB untuk visualisasi barang.</p>
                <button type="button" onclick="document.getElementById('coverPhotoInput').click()" class="mt-4 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-xl shadow-sm transition">Upload Cover Gambar</button>
            </div>
        @endif

        <div wire:loading wire:target="coverPhoto" class="p-3 bg-blue-50 border-t border-blue-100 text-center text-xs text-blue-700 font-semibold animate-pulse">
            Sedang mengunggah dan memproses gambar cover...
        </div>
        @error('coverPhoto')
            <div class="p-3 bg-rose-50 border-t border-rose-100 text-center text-xs text-rose-600 font-medium">{{ $message }}</div>
        @enderror
    </div>

    <!-- Metadata -->
    <div class="space-y-6">
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Status Stok</span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $badge }}">{{ $statusLabel }}</span>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Jumlah Stok</span>
                <div class="text-sm font-semibold text-slate-800">{{ $stock->quantity }} {{ $stock->unit }} <span class="text-xs font-normal text-slate-400">(min. {{ $stock->min_stock }})</span></div>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">SKU</span>
                <div class="text-sm font-semibold text-slate-800 font-mono">{{ $stock->sku ?? '-' }}</div>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Harga</span>
                <div class="text-sm font-semibold text-slate-800">{{ !is_null($stock->price) ? 'Rp '.number_format((float) $stock->price, 0, ',', '.') : '-' }}</div>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 col-span-2">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block mb-1">Terakhir Diperbarui</span>
                <div class="text-sm font-semibold text-slate-800">{{ $stock->updated_at ? $stock->updated_at->format('d M Y H:i') : '-' }}</div>
            </div>
        </div>

        <!-- Deskripsi -->
        <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-slate-200">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-6">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Deskripsi Barang</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Diformat dengan Toast UI Editor / Markdown</p>
                </div>
                <button wire:click="openEditModal" class="px-3 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-50 border border-blue-200 rounded-lg transition">Ubah Deskripsi</button>
            </div>

            @if (!empty($stock->description))
                <div wire:ignore
                     x-data="{
                         init() {
                             if (window.toastui && toastui.Editor) {
                                 toastui.Editor.factory({
                                     el: this.$refs.viewerContainer,
                                     viewer: true,
                                     initialValue: @js($stock->description)
                                 });
                             }
                         }
                     }"
                     class="prose prose-slate max-w-none text-slate-800">
                    <div x-ref="viewerContainer"></div>
                </div>
            @else
                <div class="py-12 text-center text-slate-400">
                    <p class="text-sm font-medium text-slate-600">Belum ada deskripsi untuk barang ini</p>
                    <p class="text-xs text-slate-400 mt-1">Tambahkan spesifikasi, catatan penyimpanan, atau detail lain.</p>
                    <button wire:click="openEditModal" class="mt-4 px-3.5 py-1.5 text-xs font-semibold text-blue-600 bg-blue-50 hover:bg-blue-100 rounded-lg transition">+ Tambahkan Deskripsi Sekarang</button>
                </div>
            @endif
        </div>
    </div>

    <!-- Edit Modal -->
    @if ($showEditModal)
        <div x-data="{ show: true }" x-show="show" x-cloak
             class="modal-scroll-lock fixed inset-0 z-50 flex items-center justify-center overflow-y-auto overscroll-contain p-4 bg-slate-900/50 backdrop-blur-sm">
            <div class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl border border-slate-200 max-h-[90vh] overflow-hidden flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 bg-blue-600">
                    <div>
                        <h3 class="text-lg font-bold text-white">Ubah Data Stok</h3>
                        <p class="text-xs text-blue-100">Edit nama, SKU, jumlah, harga, dan deskripsi barang</p>
                    </div>
                    <button type="button" @click="show = false; $wire.closeEditModal()" class="text-white/80 hover:text-white text-xl leading-none">&times;</button>
                </div>

                <form wire:submit.prevent="saveStock" class="flex flex-col flex-1 min-h-0">
                    <div class="space-y-5 px-6 py-5 overflow-y-auto flex-1">
                        <div>
                            <label class="block text-sm font-semibold text-slate-800 mb-1">Nama Barang</label>
                            <input wire:model="name" type="text" class="{{ $inputCls }}">
                            @error('name') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-800 mb-1">SKU</label>
                                <input wire:model="sku" type="text" class="{{ $inputCls }}">
                                @error('sku') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-800 mb-1">Satuan</label>
                                <input wire:model="unit" type="text" class="{{ $inputCls }}">
                                @error('unit') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-800 mb-1">Jumlah Stok</label>
                                <input wire:model="quantity" type="number" min="0" step="1" class="{{ $inputCls }}">
                                @error('quantity') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-800 mb-1">Stok Minimum</label>
                                <input wire:model="minStock" type="number" min="0" step="1" class="{{ $inputCls }}">
                                @error('minStock') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-800 mb-1">Harga (Rp)</label>
                                <input wire:model="price" type="number" min="0" step="any" class="{{ $inputCls }}">
                                @error('price') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <p class="text-xs text-slate-400">Status stok dihitung otomatis dari jumlah dan stok minimum.</p>

                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-sm font-semibold text-slate-800">Deskripsi Barang (Toast UI Editor)</label>
                                <span class="text-xs text-slate-400">Markdown &amp; WYSIWYG</span>
                            </div>
                            <div wire:ignore
                                 x-data="{
                                     desc: @entangle('description'),
                                     editor: null,
                                     init() {
                                         this.editor = new toastui.Editor({
                                             el: this.$refs.detailEditorContainer,
                                             height: '320px',
                                             initialEditType: 'wysiwyg',
                                             previewStyle: 'vertical',
                                             initialValue: this.desc || '',
                                             placeholder: 'Tuliskan rincian barang dalam format teks atau markdown...',
                                             events: {
                                                 change: () => { this.desc = this.editor.getMarkdown(); }
                                             }
                                         });
                                         this.$watch('desc', value => {
                                             if (this.editor && value !== this.editor.getMarkdown()) {
                                                 this.editor.setMarkdown(value || '', false);
                                             }
                                         });
                                     }
                                 }"
                                 class="rounded-xl border border-slate-300 overflow-hidden bg-white">
                                <div x-ref="detailEditorContainer"></div>
                            </div>
                            @error('description') <span class="text-xs text-rose-600 mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 px-6 py-4 bg-blue-50 border-t border-blue-100">
                        <button type="button" @click="show = false; $wire.closeEditModal()" class="px-4 py-2 text-sm font-semibold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 rounded-lg transition">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" class="px-6 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:opacity-75 rounded-lg shadow-sm transition">
                            <span wire:loading.remove wire:target="saveStock">Simpan Perubahan</span>
                            <span wire:loading wire:target="saveStock">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Konfirmasi Hapus Stok -->
    @if ($confirmingStockDeletion)
        <div x-data="{ show: true }" x-show="show" x-cloak
             class="modal-scroll-lock fixed inset-0 z-50 flex items-center justify-center overflow-y-auto overscroll-contain p-4 bg-slate-900/50 backdrop-blur-sm">
            <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 bg-rose-600">
                    <h3 class="text-base font-bold text-white">Konfirmasi Hapus Stok</h3>
                    <p class="text-xs text-rose-100">Tindakan ini tidak dapat dibatalkan</p>
                </div>
                <div class="px-6 py-5">
                    <p class="text-sm text-slate-600">Apakah Anda yakin ingin menghapus stok ini secara permanen?</p>
                </div>
                <div class="flex items-center justify-end gap-3 px-6 py-4 bg-rose-50 border-t border-rose-100">
                    <button type="button" @click="show = false; $wire.cancelDeleteStock()" class="px-4 py-2 text-xs font-semibold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 rounded-lg transition">Batal</button>
                    <button type="button" wire:click="deleteStock" wire:loading.attr="disabled" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 disabled:opacity-75 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                        <span wire:loading.remove wire:target="deleteStock">Ya, Hapus Stok</span>
                        <span wire:loading wire:target="deleteStock">Menghapus...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Konfirmasi Hapus Cover -->
    @if ($confirmingCoverDeletion)
        <div x-data="{ show: true }" x-show="show" x-cloak
             class="modal-scroll-lock fixed inset-0 z-50 flex items-center justify-center overflow-y-auto overscroll-contain p-4 bg-slate-900/50 backdrop-blur-sm">
            <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 bg-rose-600">
                    <h3 class="text-base font-bold text-white">Konfirmasi Hapus Cover</h3>
                    <p class="text-xs text-rose-100">Gambar cover akan dihapus dari stok ini</p>
                </div>
                <div class="px-6 py-5">
                    <p class="text-sm text-slate-600">Apakah Anda yakin ingin menghapus gambar cover dari stok ini?</p>
                </div>
                <div class="flex items-center justify-end gap-3 px-6 py-4 bg-rose-50 border-t border-rose-100">
                    <button type="button" @click="show = false; $wire.cancelDeleteCover()" class="px-4 py-2 text-xs font-semibold text-slate-600 bg-white hover:bg-slate-100 border border-slate-200 rounded-lg transition">Batal</button>
                    <button type="button" wire:click="deleteCoverPhoto" wire:loading.attr="disabled" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 disabled:opacity-75 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                        <span wire:loading.remove wire:target="deleteCoverPhoto">Ya, Hapus Cover</span>
                        <span wire:loading wire:target="deleteCoverPhoto">Menghapus...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
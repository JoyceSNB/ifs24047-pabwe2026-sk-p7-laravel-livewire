<div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <div class="flex items-center space-x-2">
                <a href="{{ route('admin.users') }}" class="p-1 rounded-lg text-slate-600 hover:bg-slate-200 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <h1 class="text-2xl font-bold text-slate-900">Stok Barang Pengguna: {{ $user->name }}</h1>
            </div>
            <p class="text-sm text-slate-600 mt-1 ml-7">{{ $user->email }} &bull; Mode Lihat Saja (Read-Only Admin)</p>
        </div>

        <div>
            <button wire:click="exportExcel" wire:loading.attr="disabled" class="inline-flex items-center space-x-1.5 px-3.5 py-2 bg-slate-700 hover:bg-slate-800 disabled:opacity-75 text-white text-sm font-semibold rounded-lg shadow-sm transition">
                <span wire:loading.remove wire:target="exportExcel">Export Excel Stok Ini</span>
                <span wire:loading wire:target="exportExcel">Mengekspor...</span>
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-slate-200 mb-6 flex flex-col sm:flex-row gap-4 items-center justify-between">
        <div class="w-full sm:w-72">
            <input aria-label="Cari stok pengguna" wire:model.live.debounce.300ms="search" type="text" placeholder="Cari nama, SKU, atau deskripsi..." class="w-full px-3.5 py-2 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="flex items-center space-x-3 w-full sm:w-auto">
            <label class="text-xs font-semibold text-slate-600 uppercase tracking-wider">Status:</label>
            <select aria-label="Filter status stok" wire:model.live="statusFilter" class="px-3 py-2 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                <option value="">Semua Status</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- Table of user's stocks -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Tabel stok pengguna">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold uppercase tracking-wider text-slate-600">
                        <th class="py-3.5 px-6">No</th>
                        <th class="py-3.5 px-6">Nama Barang</th>
                        <th class="py-3.5 px-6">SKU</th>
                        <th class="py-3.5 px-6 text-right">Jumlah</th>
                        <th class="py-3.5 px-6 text-right">Harga</th>
                        <th class="py-3.5 px-6">Status</th>
                        <th class="py-3.5 px-6">Dibuat Pada</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($stocks as $index => $stock)
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-4 px-6 text-slate-600 text-xs">
                                {{ $stocks->firstItem() + $index }}
                            </td>
                            <td class="py-4 px-6 font-semibold text-slate-900">
                                {{ $stock->name }}
                            </td>
                            <td class="py-4 px-6 text-slate-600 font-mono text-xs">
                                {{ $stock->sku ?? '-' }}
                            </td>
                            <td class="py-4 px-6 text-right text-slate-700">
                                {{ $stock->quantity }} {{ $stock->unit }}
                            </td>
                            <td class="py-4 px-6 text-right text-slate-700">
                                {{ !is_null($stock->price) ? 'Rp '.number_format((float) $stock->price, 0, ',', '.') : '-' }}
                            </td>
                            <td class="py-4 px-6">
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full border {{ \App\Features\Stock\Enums\StockStatus::tryFrom($stock->status)?->badgeClass() ?? 'bg-slate-100 text-slate-700' }}">
                                    {{ \App\Features\Stock\Enums\StockStatus::tryFrom($stock->status)?->label() ?? $stock->status }}
                                </span>
                            </td>
                            <td class="py-4 px-6 text-slate-600 text-xs">
                                {{ $stock->created_at ? $stock->created_at->format('d M Y H:i') : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-600">
                                Pengguna ini belum memiliki stok barang.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-slate-100">
            {{ $stocks->links() }}
        </div>
    </div>
</div>
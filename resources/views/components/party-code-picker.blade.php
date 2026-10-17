@props([
    'name',
    'label',
    'parties',
    'selectedId' => null,
    'required' => true,
])

@php
    $items = $parties->map(fn ($party) => [
        'id' => $party->id,
        'code' => (string) ($party->code ?? ''),
        'name' => (string) $party->name,
    ])->values();
    $resolvedId = old($name, $selectedId);
    $selected = $items->first(fn ($row) => (string) $row['id'] === (string) $resolvedId);
@endphp

<div
    class="block"
    x-data="partyCodePicker({{ Js::from($items) }}, {{ Js::from($selected) }})"
    x-init="if (partyId) { $nextTick(() => $refs.hiddenInput?.dispatchEvent(new Event('change', { bubbles: true }))); }"
    @keydown.escape.window="open = false"
>
    <span class="label">{{ $label }}</span>
    <input
        type="hidden"
        name="{{ $name }}"
        x-ref="hiddenInput"
        x-model="partyId"
        value="{{ $resolvedId ?? '' }}"
        @if ($required) required @endif
        {{ $attributes }}
    >
    <div class="relative flex gap-2">
        <div class="relative w-36 shrink-0 sm:w-40">
            <input
                type="text"
                class="input font-mono"
                placeholder="{{ __('Code') }}"
                x-model="query"
                @input="onQueryInput()"
                @focus="open = filtered.length > 0"
                @keydown.down.prevent="highlightNext()"
                @keydown.up.prevent="highlightPrev()"
                @keydown.enter.prevent="pickHighlighted()"
                autocomplete="off"
                aria-autocomplete="list"
                :aria-expanded="open.toString()"
                aria-controls="{{ $name }}-party-list"
            >
        </div>
        <input
            type="text"
            class="input min-w-0 flex-1 bg-slate-50 text-slate-800"
            :value="partyName"
            readonly
            tabindex="-1"
            placeholder="{{ __('Name') }}"
            aria-label="{{ __('Party name') }}"
        >
        <ul
            id="{{ $name }}-party-list"
            role="listbox"
            x-cloak
            x-show="open && filtered.length"
            x-transition
            @click.outside="open = false"
            class="absolute left-0 top-full z-20 mt-1 max-h-56 w-full min-w-[18rem] overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg sm:min-w-[24rem]"
        >
            <template x-for="(party, index) in filtered.slice(0, 30)" :key="party.id">
                <li
                    role="option"
                    @click="pick(party)"
                    @mouseenter="highlight = index"
                    :class="highlight === index ? 'bg-brand-50' : 'hover:bg-slate-50'"
                    class="cursor-pointer px-3 py-2"
                >
                    <span class="font-mono text-slate-700" x-text="party.code || '—'"></span>
                    <span class="mx-1 text-slate-400">·</span>
                    <span class="text-slate-900" x-text="party.name"></span>
                </li>
            </template>
        </ul>
    </div>
</div>

@once
    @push('scripts')
        <script>
            function partyCodePicker(parties, selected) {
                return {
                    parties,
                    partyId: selected?.id ? String(selected.id) : '',
                    partyName: selected?.name ?? '',
                    query: selected?.code ?? '',
                    open: false,
                    highlight: 0,
                    get filtered() {
                        const q = this.query.trim().toLowerCase();
                        if (q === '') {
                            return this.parties;
                        }

                        return this.parties.filter((party) => {
                            const code = (party.code || '').toLowerCase();
                            const name = (party.name || '').toLowerCase();

                            return code.includes(q) || name.includes(q) || code.startsWith(q);
                        });
                    },
                    onQueryInput() {
                        this.open = true;
                        this.highlight = 0;
                        const exact = this.parties.find(
                            (party) => (party.code || '').toLowerCase() === this.query.trim().toLowerCase()
                        );
                        if (exact) {
                            this.applyParty(exact, false);
                        } else {
                            this.partyId = '';
                            this.partyName = '';
                        }
                    },
                    pick(party) {
                        this.applyParty(party, true);
                        this.open = false;
                    },
                    applyParty(party, syncQuery) {
                        this.partyId = String(party.id);
                        this.partyName = party.name;
                        if (syncQuery) {
                            this.query = party.code || '';
                        }
                        this.$nextTick(() => {
                            this.$refs.hiddenInput?.dispatchEvent(new Event('change', { bubbles: true }));
                            this.$dispatch('party-selected', { id: this.partyId });
                        });
                    },
                    pickHighlighted() {
                        const list = this.filtered.slice(0, 30);
                        if (list.length === 0) {
                            return;
                        }
                        this.pick(list[this.highlight] ?? list[0]);
                    },
                    highlightNext() {
                        const max = Math.min(this.filtered.length, 30) - 1;
                        this.highlight = Math.min(this.highlight + 1, max);
                    },
                    highlightPrev() {
                        this.highlight = Math.max(this.highlight - 1, 0);
                    },
                };
            }
        </script>
    @endpush
@endonce

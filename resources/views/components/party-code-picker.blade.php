@props([
    'name',
    'label',
    'parties',
    'selectedId' => null,
    'required' => true,
    'createUrl' => null,
    'createLabel' => null,
])

@php
    $items = $parties->map(fn ($party) => [
        'id' => $party->id,
        'code' => (string) ($party->code ?? ''),
        'name' => (string) $party->name,
    ])->values();
    $resolvedId = old($name, $selectedId);
    $selected = $items->first(fn ($row) => (string) $row['id'] === (string) $resolvedId);
    $createLabel ??= __('Add new');
    $canCreate = filled($createUrl);
@endphp

<div
    class="block"
    x-data="partyCodePicker({{ Js::from($items) }}, {{ Js::from($selected) }}, {{ Js::from([
        'createUrl' => $createUrl,
        'csrf' => csrf_token(),
        'creatingLabel' => __('Saving…'),
        'errorLabel' => __('Could not save. Check the name and try again.'),
    ]) }})"
    x-init="if (partyId) { $nextTick(() => $refs.hiddenInput?.dispatchEvent(new Event('change', { bubbles: true }))); }"
    @keydown.escape.window="open = false; creating = false"
>
    <div class="flex flex-wrap items-end justify-between gap-2">
        <span class="label">{{ $label }}</span>
        @if ($canCreate)
            <button
                type="button"
                class="text-sm font-semibold text-brand-700 hover:underline"
                data-testid="party-quick-add"
                @click="creating = true; createError = ''; $nextTick(() => $refs.createName?.focus())"
            >{{ $createLabel }}</button>
        @endif
    </div>
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

    @if ($canCreate)
        <div
            x-cloak
            x-show="creating"
            class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-4 sm:items-center"
            role="dialog"
            aria-modal="true"
            :aria-label="{{ Js::from($createLabel) }}"
            data-testid="party-quick-add-dialog"
        >
            <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-lg" @click.outside="!saving && (creating = false)">
                <h2 class="text-lg font-semibold text-slate-900">{{ $createLabel }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ __('Saved to masters and selected on this document.') }}</p>
                <div class="mt-4 space-y-3">
                    <label class="block">
                        <span class="label">{{ __('Name') }}</span>
                        <input type="text" class="input" x-ref="createName" x-model="createName" maxlength="191" required>
                    </label>
                    <label class="block">
                        <span class="label">{{ __('Code') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                        <input type="text" class="input font-mono" x-model="createCode" maxlength="40">
                    </label>
                    <label class="block">
                        <span class="label">{{ __('Phone') }} <span class="font-normal text-slate-500">({{ __('optional') }})</span></span>
                        <input type="tel" class="input" x-model="createPhone" maxlength="20">
                    </label>
                    <p x-cloak x-show="createError" class="text-sm text-red-700" x-text="createError"></p>
                </div>
                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button type="button" class="btn-secondary w-full sm:w-auto" @click="creating = false" :disabled="saving">{{ __('Cancel') }}</button>
                    <button type="button" class="btn-primary w-full sm:w-auto" data-testid="party-quick-add-save" @click="saveNew()" :disabled="saving || !createName.trim()">
                        <span x-text="saving ? opts.creatingLabel : {{ Js::from(__('Save and select')) }}"></span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

@once
    @push('scripts')
        <script>
            function partyCodePicker(parties, selected, opts) {
                opts = opts || {};
                return {
                    parties: parties || [],
                    opts,
                    partyId: selected?.id ? String(selected.id) : '',
                    partyName: selected?.name ?? '',
                    query: selected?.code ?? '',
                    open: false,
                    highlight: 0,
                    creating: false,
                    saving: false,
                    createName: '',
                    createCode: '',
                    createPhone: '',
                    createError: '',
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
                        } else if (this.partyId !== '') {
                            this.partyId = '';
                            this.partyName = '';
                            this.$nextTick(() => {
                                this.$refs.hiddenInput?.dispatchEvent(new Event('change', { bubbles: true }));
                            });
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
                    async saveNew() {
                        if (!this.opts.createUrl || !this.createName.trim() || this.saving) {
                            return;
                        }
                        this.saving = true;
                        this.createError = '';
                        try {
                            const body = new URLSearchParams();
                            body.set('name', this.createName.trim());
                            if (this.createCode.trim()) {
                                body.set('code', this.createCode.trim());
                            }
                            if (this.createPhone.trim()) {
                                body.set('phone', this.createPhone.trim());
                            }
                            const res = await fetch(this.opts.createUrl, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': this.opts.csrf || '',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                                },
                                body: body.toString(),
                                credentials: 'same-origin',
                            });
                            const data = await res.json().catch(() => ({}));
                            if (!res.ok) {
                                const first = data?.errors ? Object.values(data.errors).flat()[0] : null;
                                throw new Error(first || data?.message || this.opts.errorLabel);
                            }
                            const party = {
                                id: data.id,
                                code: data.code || '',
                                name: data.name || this.createName.trim(),
                            };
                            this.parties = [party].concat(this.parties.filter((p) => String(p.id) !== String(party.id)));
                            this.pick(party);
                            this.creating = false;
                            this.createName = '';
                            this.createCode = '';
                            this.createPhone = '';
                        } catch (err) {
                            this.createError = err.message || this.opts.errorLabel;
                        } finally {
                            this.saving = false;
                        }
                    },
                };
            }
        </script>
    @endpush
@endonce

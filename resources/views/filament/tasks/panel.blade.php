{{--
    The open task, in a panel on the right (ProjectBoard, and the drivers' MyTasks in the portal;
    ?task=<id>). Every field saves on change. $task['limited'] (drivers): status, subtasks ticked,
    comments and own files only; the rest is shown read-only.
    Close with Esc, the X, the backdrop, or a swipe to the right on a phone.
--}}
@php
    use App\Support\Tasks;
    $limited = $task['limited'];
    $peopleJs = collect($people)->map(fn ($name, $user) => ['user' => (string) $user, 'name' => $name])->values();
@endphp
<div class="myat-tk-panel-wrap" wire:key="panel-{{ $task['id'] }}"
    x-data="{ startX: null, dx: 0 }"
    x-on:keydown.escape.window="if (! $event.target.closest('textarea, input')) $wire.closePanel()">
    <div class="myat-tk-backdrop" wire:click="closePanel"></div>
    <aside class="myat-tk-panel" role="dialog" aria-label="{{ $task['title'] }}"
        x-on:touchstart.passive="startX = $event.touches[0].clientX; dx = 0"
        x-on:touchmove.passive="if (startX !== null) { dx = Math.max(0, $event.touches[0].clientX - startX); $el.style.transform = dx > 10 ? `translateX(${dx}px)` : '' }"
        x-on:touchend="if (dx > 110) { $wire.closePanel() } else { $el.style.transform = '' } startX = null">

        <header class="myat-tk-panel-h">
            <select class="myat-tk-status-select" style="--c: {{ Tasks::STATUSES[$task['status']][1] ?? '#64748b' }}" x-on:change="$wire.saveField('Status', $event.target.value)" aria-label="Κατάσταση">
                @foreach (Tasks::STATUSES as $key => [$label])
                    <option value="{{ $key }}" @selected($task['status'] === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <span class="myat-tk-panel-id">
                @if ($limited && $task['project'])<span class="myat-tk-mine-project" style="--project: {{ $task['project']['Color'] ?: '#0096ff' }}">{{ trim($task['project']['Emoji'].' '.$task['project']['Name']) }}</span> · @endif#{{ $task['id'] }}
            </span>
            @unless ($limited)
            <button type="button" class="myat-tk-iconbtn" title="Αντιγραφή συνδέσμου"
                x-data="{ copied: false }" x-on:click="navigator.clipboard?.writeText(window.location.href); copied = true; setTimeout(() => copied = false, 1500)" :class="copied && 'is-on'">
                <x-filament::icon icon="heroicon-m-link" />
            </button>
            @endunless
            @if ($task['canDelete'])
                <button type="button" class="myat-tk-iconbtn is-danger" title="Διαγραφή εργασίας"
                    x-on:click="if (confirm('Διαγραφή της εργασίας;')) $wire.deleteTask()">
                    <x-filament::icon icon="heroicon-m-trash" />
                </button>
            @endif
            <button type="button" class="myat-tk-iconbtn" wire:click="closePanel" title="Κλείσιμο (Esc)">
                <x-filament::icon icon="heroicon-m-x-mark" />
            </button>
        </header>

        <div class="myat-tk-panel-body">
            {{-- Title --}}
            @if ($limited)
                <h2 class="myat-tk-title-input is-static">{{ $task['title'] }}</h2>
            @else
            <textarea class="myat-tk-title-input" rows="1" maxlength="250" x-data
                x-init="requestAnimationFrame(() => { $el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px' })" x-on:input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
                x-on:keydown.enter.prevent="$el.blur()"
                x-on:blur="if ($el.value.trim() && $el.value !== @js($task['title'])) $wire.saveField('Title', $el.value)">{{ $task['title'] }}</textarea>
            @endif

            {{-- Fields --}}
            <dl class="myat-tk-fields">
                <dt>Προτεραιότητα</dt>
                <dd>
                    @if ($limited)
                        <span class="myat-tk-prio" style="--c: {{ Tasks::PRIORITIES[$task['priority']][1] ?? '#0ea5e9' }}">{{ Tasks::PRIORITIES[$task['priority']][0] ?? $task['priority'] }}</span>
                    @else
                    <div class="myat-tk-prio-pick">
                        @foreach (Tasks::PRIORITIES as $key => [$label, $color])
                            <button type="button" class="{{ $task['priority'] === $key ? 'is-on' : '' }}" style="--c: {{ $color }}" wire:click="saveField('Priority', '{{ $key }}')">{{ $label }}</button>
                        @endforeach
                    </div>
                    @endif
                </dd>

                <dt>Προθεσμία</dt>
                <dd class="myat-tk-inline">
                    @if ($limited)
                        {{ $task['due'] ? \Illuminate\Support\Carbon::parse($task['due'])->format('d/m/Y') : '—' }}
                    @else
                    <input type="date" class="myat-tk-input" value="{{ $task['due'] }}" x-on:change="$wire.saveField('DueDate', $event.target.value)">
                    @if ($task['due'])
                        <button type="button" class="myat-tk-linkbtn" wire:click="saveField('DueDate', '')">Αφαίρεση</button>
                    @endif
                    @endif
                </dd>

                <dt>Υπεύθυνοι</dt>
                <dd>
                    <div class="myat-tk-people" x-data="{ open: false }" x-on:click.outside="open = false">
                        @foreach ($task['assignees'] as $u)
                            <span class="myat-tk-chip">
                                <span class="myat-tk-avatar">{{ Tasks::initials($people[$u] ?? $u) }}</span>{{ $people[$u] ?? $u }}
                                @unless ($limited)<button type="button" wire:click="toggleAssignee(@js($u))" title="Αφαίρεση"><x-filament::icon icon="heroicon-m-x-mark" /></button>@endunless
                            </span>
                        @endforeach
                        @unless ($limited)
                        <button type="button" class="myat-tk-chip is-add" x-on:click="open = ! open"><x-filament::icon icon="heroicon-m-plus" /> Προσθήκη</button>
                        <div class="myat-tk-pop" x-show="open" x-cloak x-transition.opacity>
                            @foreach (Tasks::peopleByGroup() as $groupLabel => $members)
                                @continue($members === [])
                                <span class="myat-tk-pop-h">{{ $groupLabel }}</span>
                                @foreach ($members as $u => $name)
                                    <button type="button" wire:click="toggleAssignee(@js((string) $u))" class="{{ in_array((string) $u, $task['assignees'], true) ? 'is-on' : '' }}">
                                        <span class="myat-tk-avatar {{ $groupLabel === 'Οδηγοί' ? 'is-driver' : '' }}">{{ Tasks::initials($name) }}</span>{{ $name }}
                                        @if (in_array((string) $u, $task['assignees'], true))<x-filament::icon icon="heroicon-m-check" class="myat-tk-pop-check" />@endif
                                    </button>
                                @endforeach
                            @endforeach
                        </div>
                        @endunless
                    </div>
                </dd>

                @if (! $limited || $task['transfer'])
                <dt>Transfer</dt>
                <dd class="myat-tk-inline">
                    @if ($limited)
                        #{{ $task['transfer'] }}
                    @else
                    <input type="text" inputmode="numeric" class="myat-tk-input is-short" placeholder="Αρ. transfer" value="{{ $task['transfer'] }}"
                        x-on:change="$wire.saveField('TransferID', $event.target.value)">
                    @if ($task['transferUrl'])
                        <a href="{{ $task['transferUrl'] }}" target="_blank" class="myat-tk-linkbtn">Άνοιγμα #{{ $task['transfer'] }} ↗</a>
                    @endif
                    @error('transfer')<span class="myat-tk-error">{{ $message }}</span>@enderror
                    @endif
                </dd>
                @endif
            </dl>

            {{-- Description (Markdown) --}}
            <section class="myat-tk-sec" x-data="{ editing: false, preview: false, html: '' }">
                <h3>Περιγραφή
                    @unless ($limited)<button type="button" class="myat-tk-linkbtn" x-show="! editing" x-on:click="editing = true; $nextTick(() => $refs.desc.focus())">Επεξεργασία</button>@endunless
                </h3>
                <div x-show="! editing" class="myat-tk-md {{ $task['description'] === '' ? 'is-empty' : '' }}" @unless ($limited) x-on:dblclick="editing = true" @endunless>
                    @if ($task['description'] === '')
                        <span>{{ $limited ? '—' : 'Προσθέστε περιγραφή…' }}</span>
                    @else
                        {{ $task['html'] }}
                    @endif
                </div>
                @unless ($limited)
                <div x-show="editing" x-cloak>
                    <div class="myat-tk-seg is-small">
                        <button type="button" :class="! preview && 'is-active'" x-on:click="preview = false">Κείμενο</button>
                        <button type="button" :class="preview && 'is-active'" x-on:click="html = await $wire.previewMarkdown($refs.desc.value); preview = true">Προεπισκόπηση</button>
                    </div>
                    <textarea x-ref="desc" x-show="! preview" class="myat-tk-textarea" rows="8" maxlength="20000"
                        placeholder="Markdown: **έντονα**, *πλάγια*, - λίστα, [σύνδεσμος](https://…)">{{ $task['description'] }}</textarea>
                    <div x-show="preview" class="myat-tk-md" x-html="html"></div>
                    <div class="myat-tk-add-actions">
                        <button type="button" class="myat-tk-btn is-primary" x-on:click="$wire.saveField('Description', $refs.desc.value); editing = false; preview = false">Αποθήκευση</button>
                        <button type="button" class="myat-tk-btn" x-on:click="$refs.desc.value = @js($task['description']); editing = false; preview = false">Άκυρο</button>
                    </div>
                </div>
                @endunless
            </section>

            {{-- Subtasks --}}
            @if (! $limited || $task['subtasks'] !== [])
            <section class="myat-tk-sec">
                <h3>Υποεργασίες
                    @if ($task['subtasks'] !== [])<span class="myat-tk-count">{{ $task['subDone'] }}/{{ count($task['subtasks']) }}</span>@endif
                </h3>
                @if ($task['subtasks'] !== [])
                    <div class="myat-tk-progress"><span style="width: {{ round($task['subDone'] / count($task['subtasks']) * 100) }}%"></span></div>
                @endif
                <ul class="myat-tk-subtasks">
                    @foreach ($task['subtasks'] as $s)
                        <li wire:key="sub-{{ $s['id'] }}" class="{{ $s['done'] ? 'is-done' : '' }}" x-data="{ editing: false }">
                            <input type="checkbox" @checked($s['done']) wire:click="toggleSubtask({{ $s['id'] }})">
                            <span x-show="! editing" @unless ($limited) x-on:dblclick="editing = true; $nextTick(() => $refs.t.focus())" @endunless>{{ $s['title'] }}</span>
                            @unless ($limited)
                            <input x-show="editing" x-cloak x-ref="t" class="myat-tk-input" value="{{ $s['title'] }}" maxlength="250"
                                x-on:keydown.enter.prevent="$wire.renameSubtask({{ $s['id'] }}, $el.value); editing = false" x-on:keydown.escape="editing = false"
                                x-on:blur="if (editing) { $wire.renameSubtask({{ $s['id'] }}, $el.value); editing = false }">
                            <button type="button" class="myat-tk-iconbtn" wire:click="deleteSubtask({{ $s['id'] }})" title="Διαγραφή"><x-filament::icon icon="heroicon-m-x-mark" /></button>
                            @endunless
                        </li>
                    @endforeach
                </ul>
                @unless ($limited)
                <form class="myat-tk-inline" x-data="{ t: '' }" x-on:submit.prevent="if (t.trim()) { $wire.addSubtask(t); t = '' }">
                    <input class="myat-tk-input" x-model="t" maxlength="250" placeholder="Νέα υποεργασία… (Enter)">
                </form>
                @endunless
            </section>
            @endif

            {{-- Attachments --}}
            <section class="myat-tk-sec" x-data="{ over: false }"
                x-on:dragover.prevent="over = true" x-on:dragleave.prevent="over = false"
                x-on:drop.prevent="over = false; $wire.uploadMultiple('uploads', [...$event.dataTransfer.files])">
                <h3>Συνημμένα @if ($task['files'] !== [])<span class="myat-tk-count">{{ count($task['files']) }}</span>@endif</h3>
                <ul class="myat-tk-files">
                    @foreach ($task['files'] as $f)
                        <li wire:key="file-{{ $f['id'] }}">
                            <x-filament::icon :icon="$f['image'] ? 'heroicon-o-photo' : 'heroicon-o-document'" />
                            <a href="{{ $f['url'] }}" target="_blank">{{ $f['name'] }}</a>
                            <span class="myat-tk-meta">{{ \Illuminate\Support\Number::fileSize($f['size'], 0) }} · {{ $f['by'] }}</span>
                            @if ($f['canDelete'])
                            <button type="button" class="myat-tk-iconbtn" title="Διαγραφή" x-on:click="if (confirm('Διαγραφή του αρχείου;')) $wire.deleteFile({{ $f['id'] }})">
                                <x-filament::icon icon="heroicon-m-trash" />
                            </button>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <label class="myat-tk-drop" :class="over && 'is-over'">
                    <input type="file" multiple wire:model="uploads" class="sr-only" @if ($limited) accept="image/*,application/pdf" @endif>
                    <x-filament::icon icon="heroicon-o-arrow-up-tray" />
                    <span wire:loading.remove wire:target="uploads">@if ($limited) <u>Φωτογραφία ή αρχείο</u> @else Σύρετε αρχεία εδώ ή <u>επιλέξτε</u> @endif (έως {{ Tasks::MAX_FILE_KB / 1024 }} MB)</span>
                    <span wire:loading wire:target="uploads">Ανέβασμα…</span>
                </label>
                @error('uploads.*')<span class="myat-tk-error">{{ $message }}</span>@enderror
            </section>

            {{-- Comments --}}
            <section class="myat-tk-sec">
                <h3>Σχόλια @if ($task['comments'] !== [])<span class="myat-tk-count">{{ count($task['comments']) }}</span>@endif</h3>
                <ul class="myat-tk-comments">
                    @foreach ($task['comments'] as $c)
                        <li wire:key="c-{{ $c['id'] }}" x-data="{ editing: false }">
                            <span class="myat-tk-avatar is-lg">{{ $c['initials'] }}</span>
                            <div class="myat-tk-comment">
                                <div class="myat-tk-comment-h">
                                    <b>{{ $c['name'] }}</b>
                                    <span class="myat-tk-meta">{{ $c['when'] }}{{ $c['edited'] ? ' · επεξεργάστηκε' : '' }}</span>
                                </div>
                                <div x-show="! editing" class="myat-tk-comment-body">{{ $c['html'] }}</div>
                                @if ($c['mine'])
                                    <div x-show="editing" x-cloak>
                                        <textarea x-ref="e" class="myat-tk-textarea" rows="3" maxlength="5000">{{ $c['body'] }}</textarea>
                                        <div class="myat-tk-add-actions">
                                            <button type="button" class="myat-tk-btn is-primary" x-on:click="$wire.updateComment({{ $c['id'] }}, $refs.e.value); editing = false">Αποθήκευση</button>
                                            <button type="button" class="myat-tk-btn" x-on:click="editing = false">Άκυρο</button>
                                        </div>
                                    </div>
                                @endif
                                <div class="myat-tk-comment-actions" x-show="! editing">
                                    <button type="button" x-on:click="$dispatch('myat-reply', { user: @js($c['user']), name: @js($c['name']) })">Απάντηση</button>
                                    @if ($c['mine'])<button type="button" x-on:click="editing = true">Επεξεργασία</button>@endif
                                    @if ($c['canDelete'])<button type="button" x-on:click="if (confirm('Διαγραφή του σχολίου;')) $wire.deleteComment({{ $c['id'] }})">Διαγραφή</button>@endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                {{-- New comment with @mentions --}}
                <div class="myat-tk-compose"
                    x-data="{
                        body: '', people: @js($peopleJs), q: null, at: 0, pick: 0,
                        get matches() {
                            if (this.q === null) return []
                            const n = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
                            return this.people.filter((p) => n(p.name).includes(n(this.q)) || n(p.user).includes(n(this.q))).slice(0, 6)
                        },
                        typed(el) {
                            const before = el.value.slice(0, el.selectionStart)
                            const m = before.match(/(^|\s)@([^\s@\[\]()]{0,30})$/u)
                            this.q = m ? m[2] : null
                            this.at = m ? el.selectionStart - m[2].length - 1 : 0
                            this.pick = 0
                        },
                        insert(p) {
                            const el = this.$refs.box, end = el.selectionStart
                            const tag = `@[${p.name}](${p.user}) `
                            this.body = this.body.slice(0, this.at) + tag + this.body.slice(end)
                            this.q = null
                            this.$nextTick(() => { el.focus(); el.selectionStart = el.selectionEnd = this.at + tag.length })
                        },
                        send() { if (this.body.trim()) { $wire.addComment(this.body); this.body = ''; this.q = null } },
                    }"
                    x-on:myat-reply.window="body = `@[${$event.detail.name}](${$event.detail.user}) ` + body; $nextTick(() => $refs.box.focus())">
                    <textarea x-ref="box" x-model="body" class="myat-tk-textarea" rows="3" maxlength="5000"
                        placeholder="Γράψτε σχόλιο… (@ για αναφορά, Ctrl+Enter για αποστολή)"
                        x-on:input="typed($el)" x-on:click="typed($el)"
                        x-on:keydown.down="if (matches.length) { $event.preventDefault(); pick = (pick + 1) % matches.length }"
                        x-on:keydown.up="if (matches.length) { $event.preventDefault(); pick = (pick + matches.length - 1) % matches.length }"
                        x-on:keydown.enter="if (matches.length) { $event.preventDefault(); insert(matches[pick]) } else if ($event.ctrlKey || $event.metaKey) { $event.preventDefault(); send() }"
                        x-on:keydown.escape.stop="q = null"></textarea>
                    <div class="myat-tk-pop is-up" x-show="matches.length" x-cloak>
                        <template x-for="(p, i) in matches" :key="p.user">
                            <button type="button" :class="i === pick && 'is-on'" x-on:mousedown.prevent="insert(p)" x-text="p.name"></button>
                        </template>
                    </div>
                    <div class="myat-tk-add-actions">
                        <button type="button" class="myat-tk-btn is-primary" x-on:click="send()" :disabled="! body.trim()">Αποστολή</button>
                    </div>
                </div>
            </section>

            <p class="myat-tk-panel-foot">
                Δημιουργήθηκε από {{ $task['createdBy'] ?: '—' }} · {{ $task['createdAt'] }}
                @if ($task['completedAt']) · Ολοκληρώθηκε {{ $task['completedAt'] }} @endif
            </p>
        </div>
    </aside>
</div>

@if ($withAssets)
    @once
        <link rel="stylesheet" href="{{ $cssUrl }}">
        @if ($alpineUrl)
            <script defer src="{{ $alpineUrl }}"></script>
        @endif
    @endonce
@endif

@once
<script>
    window.aeChat = window.aeChat || function (config) {
        return {
            open: !config.floating,
            input: '',
            loading: false,
            messages: [],
            files: [],

            async request(url, fields, files = []) {
                const body = new FormData();

                if (config.token) body.append('token', config.token);
                Object.entries(fields).forEach(([key, value]) => body.append(key, value));
                files.forEach(file => body.append('files[]', file));

                const response = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': config.csrf,
                    },
                    body,
                });

                return { ok: response.ok, data: await response.json().catch(() => ({})) };
            },

            canSend() {
                return !this.loading && (this.input.trim() !== '' || this.files.length > 0);
            },

            pickFiles(event) {
                const room = config.maxFiles - this.files.length;
                this.files.push(...Array.from(event.target.files).slice(0, Math.max(room, 0)));
                event.target.value = '';
            },

            async send() {
                const text = this.input.trim();
                // FormData exige o File original, não o Proxy reativo do Alpine
                const files = this.files.map(file => (window.Alpine && Alpine.raw) ? Alpine.raw(file) : file);

                if (!this.canSend()) {
                    return;
                }

                this.messages.push({ role: 'user', text, files: files.map(file => file.name) });
                this.input = '';
                this.files = [];
                this.loading = true;
                this.scroll();

                try {
                    const { ok, data } = await this.request(config.endpoint, { message: text }, files);

                    if (!ok || data.error) {
                        this.messages.push({ role: 'assistant', error: true, text: data.error || data.message || config.errorMessage });
                    } else {
                        this.messages.push({
                            role: 'assistant',
                            text: typeof data.content === 'string' ? data.content : JSON.stringify(data.content, null, 2),
                            json: typeof data.content !== 'string',
                            tokens: config.showTokens && data.token_usage ? data.token_usage.total_token_count : null,
                        });
                    }
                } catch (e) {
                    this.messages.push({ role: 'assistant', error: true, text: config.errorMessage });
                } finally {
                    this.loading = false;
                    this.scroll();
                    this.$nextTick(() => this.$refs.input && this.$refs.input.focus());
                }
            },

            async reset() {
                await this.request(config.resetEndpoint, {}).catch(() => null);
                this.messages = [];
                this.files = [];
            },

            keydown(event) {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    this.send();
                }
            },

            scroll() {
                this.$nextTick(() => {
                    const el = this.$refs.messages;
                    if (el) el.scrollTop = el.scrollHeight;
                });
            },
        };
    };
</script>
@endonce

<div {{ $attributes->class(['ae-root', 'ae-floating' => $floating]) }}
     x-data="aeChat(@js($config + ['csrf' => csrf_token()]))">
    <section class="ae-chat" @if ($floating) x-show="open" x-cloak x-transition @endif
             style="height: {{ $height }}" aria-label="{{ $title }}">
        <div class="ae-chat-header">
            <span>{{ $title }}</span>
            <div class="ae-chat-header-actions">
                <button type="button" class="ae-icon-btn" @click="reset()" x-show="messages.length" x-cloak>
                    {{ __('agent-engine::ui.new_conversation') }}
                </button>
                @if ($floating)
                    <button type="button" class="ae-icon-btn" @click="open = false" aria-label="{{ __('agent-engine::ui.close_chat') }}">✕</button>
                @endif
            </div>
        </div>

        <div class="ae-chat-messages" x-ref="messages" aria-live="polite">
            <template x-for="(message, index) in messages" :key="index">
                <div class="ae-msg" :class="{
                        'ae-msg-user': message.role === 'user',
                        'ae-msg-assistant': message.role === 'assistant',
                        'ae-msg-error': message.error,
                    }">
                    <div class="ae-bubble">
                        <template x-if="message.json"><pre x-text="message.text"></pre></template>
                        <template x-if="!message.json && message.text"><span x-text="message.text"></span></template>
                        <template x-if="message.files && message.files.length">
                            <ul class="ae-msg-files">
                                <template x-for="name in message.files"><li x-text="name"></li></template>
                            </ul>
                        </template>
                    </div>
                    <span class="ae-msg-meta" x-show="message.tokens" x-text="message.tokens + ' ' + {{ \Illuminate\Support\Js::from(__('agent-engine::ui.tokens')) }}"></span>
                </div>
            </template>

            <div class="ae-msg ae-msg-assistant" x-show="loading" x-cloak aria-label="{{ __('agent-engine::ui.thinking') }}">
                <div class="ae-bubble ae-typing"><span></span><span></span><span></span></div>
            </div>
        </div>

        @if ($allowFiles)
            <ul class="ae-chips" x-show="files.length" x-cloak>
                <template x-for="(file, index) in files" :key="index">
                    <li class="ae-chip">
                        <span x-text="file.name"></span>
                        <button type="button" @click="files.splice(index, 1)" aria-label="{{ __('agent-engine::ui.remove_file') }}">×</button>
                    </li>
                </template>
            </ul>
        @endif

        <form class="ae-chat-form" @submit.prevent="send()">
            @if ($allowFiles)
                <input type="file" x-ref="file" multiple accept="{{ $accept }}" hidden @change="pickFiles($event)">
                <button type="button" class="ae-attach" @click="$refs.file.click()" :disabled="loading || files.length >= {{ (int) $config['maxFiles'] }}"
                        title="{{ __('agent-engine::ui.attach') }}" aria-label="{{ __('agent-engine::ui.attach') }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                    </svg>
                </button>
            @endif
            <textarea x-ref="input" x-model="input" @keydown="keydown($event)" rows="1"
                      placeholder="{{ $placeholder }}" aria-label="{{ $placeholder }}"></textarea>
            <button type="submit" class="ae-btn ae-btn-primary" :disabled="!canSend()">
                {{ __('agent-engine::ui.send') }}
            </button>
        </form>
    </section>

    @if ($floating)
        <button type="button" class="ae-launcher" @click="open = !open; if (open) $nextTick(() => $refs.input.focus())"
                :aria-label="open ? {{ \Illuminate\Support\Js::from(__('agent-engine::ui.close_chat')) }} : {{ \Illuminate\Support\Js::from(__('agent-engine::ui.open_chat')) }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
        </button>
    @endif
</div>

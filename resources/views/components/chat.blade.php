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

            async request(url, body) {
                const response = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': config.csrf,
                    },
                    body: JSON.stringify(Object.assign({ token: config.token }, body)),
                });

                return { ok: response.ok, data: await response.json().catch(() => ({})) };
            },

            async send() {
                const text = this.input.trim();

                if (!text || this.loading) {
                    return;
                }

                this.messages.push({ role: 'user', text });
                this.input = '';
                this.loading = true;
                this.scroll();

                try {
                    const { ok, data } = await this.request(config.endpoint, { message: text });

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
                        <template x-if="!message.json"><span x-text="message.text"></span></template>
                    </div>
                    <span class="ae-msg-meta" x-show="message.tokens" x-text="message.tokens + ' ' + {{ \Illuminate\Support\Js::from(__('agent-engine::ui.tokens')) }}"></span>
                </div>
            </template>

            <div class="ae-msg ae-msg-assistant" x-show="loading" x-cloak aria-label="{{ __('agent-engine::ui.thinking') }}">
                <div class="ae-bubble ae-typing"><span></span><span></span><span></span></div>
            </div>
        </div>

        <form class="ae-chat-form" @submit.prevent="send()">
            <textarea x-ref="input" x-model="input" @keydown="keydown($event)" rows="1"
                      placeholder="{{ $placeholder }}" aria-label="{{ $placeholder }}"></textarea>
            <button type="submit" class="ae-btn ae-btn-primary" :disabled="loading || !input.trim()">
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

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useData, withBase } from 'vitepress';
import previewsManifest from '../../previews.json';

interface PreviewEntry {
  name: string;
  code: string;
  html: string;
  previewUrl: string;
}

const props = withDefaults(
  defineProps<{
    name: string;
    title?: string;
    command?: string;
    minHeight?: number;
  }>(),
  {
    title: '',
    command: '',
    minHeight: 160,
  },
);

const { isDark } = useData();
const activeTab = ref<'preview' | 'code'>('preview');
const copiedCode = ref(false);
const copiedCommand = ref(false);
const iframeRef = ref<HTMLIFrameElement | null>(null);
const iframeHeight = ref<number>(props.minHeight);

const manifest = previewsManifest as Record<string, PreviewEntry>;
const entry = computed<PreviewEntry | null>(() => manifest[props.name] ?? null);

const iframeSrc = computed(() =>
  entry.value ? withBase(`/${entry.value.previewUrl}`) : '',
);

function syncIframeTheme() {
  const win = iframeRef.value?.contentWindow;
  if (!win) return;
  win.postMessage({ type: 'blatui-theme', dark: isDark.value }, '*');
}

function handleMessage(event: MessageEvent) {
  if (
    event.data &&
    event.data.type === 'blatui-preview-resize' &&
    event.data.name === props.name &&
    typeof event.data.height === 'number'
  ) {
    iframeHeight.value = Math.max(props.minHeight, event.data.height);
  }
}

async function copyText(text: string, target: 'code' | 'command') {
  if (!text || typeof navigator === 'undefined' || !navigator.clipboard) return;
  await navigator.clipboard.writeText(text);
  if (target === 'code') {
    copiedCode.value = true;
    setTimeout(() => {
      copiedCode.value = false;
    }, 1800);
  } else {
    copiedCommand.value = true;
    setTimeout(() => {
      copiedCommand.value = false;
    }, 1800);
  }
}

watch(isDark, () => {
  syncIframeTheme();
});

onMounted(() => {
  window.addEventListener('message', handleMessage);
});

onBeforeUnmount(() => {
  window.removeEventListener('message', handleMessage);
});
</script>

<template>
  <div class="bp-card">
    <div class="bp-toolbar">
      <div class="bp-tabs" role="tablist">
        <button
          type="button"
          role="tab"
          :aria-selected="activeTab === 'preview'"
          :class="['bp-tab', { active: activeTab === 'preview' }]"
          @click="activeTab = 'preview'"
        >
          Preview
        </button>
        <button
          type="button"
          role="tab"
          :aria-selected="activeTab === 'code'"
          :class="['bp-tab', { active: activeTab === 'code' }]"
          @click="activeTab = 'code'"
        >
          Code
        </button>
        <span v-if="title" class="bp-title">{{ title }}</span>
      </div>

      <div class="bp-actions">
        <button
          v-if="command"
          type="button"
          class="bp-cmd-btn"
          :title="`Copy command: ${command}`"
          @click="copyText(command, 'command')"
        >
          <code class="bp-cmd-text">$ {{ command }}</code>
          <span class="bp-copy-badge">{{ copiedCommand ? 'Copied!' : 'Copy' }}</span>
        </button>

        <button
          v-if="entry"
          type="button"
          class="bp-copy-btn"
          @click="copyText(entry.code, 'code')"
        >
          {{ copiedCode ? 'Copied Blade!' : 'Copy Blade' }}
        </button>
      </div>
    </div>

    <div v-if="!entry" class="bp-missing">
      Preview <code>{{ name }}</code> not found. Run <code>composer docs:render</code> to compile examples.
    </div>

    <template v-else>
      <div v-show="activeTab === 'preview'" class="bp-preview-pane">
        <iframe
          ref="iframeRef"
          class="bp-iframe"
          :src="iframeSrc"
          :style="{ height: `${iframeHeight}px` }"
          :title="title || `BlatUI preview: ${name}`"
          loading="lazy"
          @load="syncIframeTheme"
        />
      </div>

      <div v-show="activeTab === 'code'" class="bp-code-pane">
        <pre class="bp-pre"><code>{{ entry.code }}</code></pre>
      </div>
    </template>
  </div>
</template>

<style scoped>
.bp-card {
  margin: 1.25rem 0;
  border: 1px solid var(--vp-c-divider);
  border-radius: 10px;
  overflow: hidden;
  background: var(--vp-c-bg);
}

.bp-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.5rem 0.75rem;
  background: var(--vp-c-bg-soft);
  border-bottom: 1px solid var(--vp-c-divider);
}

.bp-tabs {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.bp-tab {
  padding: 0.3rem 0.75rem;
  border-radius: 6px;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--vp-c-text-2);
  background: transparent;
  border: 1px solid transparent;
  cursor: pointer;
  transition: all 0.15s ease;
}

.bp-tab:hover {
  color: var(--vp-c-text-1);
}

.bp-tab.active {
  color: var(--vp-c-text-1);
  background: var(--vp-c-bg);
  border-color: var(--vp-c-divider);
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.bp-title {
  margin-left: 0.5rem;
  font-size: 0.8125rem;
  color: var(--vp-c-text-2);
}

.bp-actions {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.bp-cmd-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.25rem 0.6rem;
  border-radius: 6px;
  border: 1px solid var(--vp-c-divider);
  background: var(--vp-c-bg);
  font-size: 0.75rem;
  color: var(--vp-c-text-2);
  cursor: pointer;
}

.bp-cmd-btn:hover {
  border-color: var(--vp-c-brand-1);
  color: var(--vp-c-text-1);
}

.bp-cmd-text {
  font-family: var(--vp-font-family-mono);
  font-size: 0.75rem;
}

.bp-copy-badge {
  font-weight: 600;
  color: var(--vp-c-brand-1);
}

.bp-copy-btn {
  padding: 0.25rem 0.65rem;
  border-radius: 6px;
  border: 1px solid var(--vp-c-divider);
  background: var(--vp-c-bg);
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--vp-c-text-2);
  cursor: pointer;
}

.bp-copy-btn:hover {
  border-color: var(--vp-c-brand-1);
  color: var(--vp-c-text-1);
}

.bp-preview-pane {
  width: 100%;
  background: var(--vp-c-bg);
}

.bp-iframe {
  display: block;
  width: 100%;
  border: 0;
  background: transparent;
}

.bp-code-pane {
  margin: 0;
  padding: 1rem;
  background: var(--vp-code-block-bg);
  overflow-x: auto;
}

.bp-pre {
  margin: 0;
  font-family: var(--vp-font-family-mono);
  font-size: 0.8125rem;
  line-height: 1.6;
  color: var(--vp-c-text-1);
}

.bp-missing {
  padding: 1.5rem;
  font-size: 0.875rem;
  color: var(--vp-c-danger-1);
}
</style>

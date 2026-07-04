<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    editable: {
        type: Boolean,
        default: false,
    },
    showForm: {
        type: Boolean,
        default: true,
    },
    submitting: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
    maxFiles: {
        type: Number,
        default: 3,
    },
    formHeading: {
        type: String,
        default: 'Add evidence',
    },
    compact: {
        type: Boolean,
        default: false,
    },
    embedded: {
        type: Boolean,
        default: false,
    },
});

const title = defineModel('title', { type: String, default: '' });
const description = defineModel('description', { type: String, default: '' });
const files = defineModel('files', { type: Array, default: () => [] });

const emit = defineEmits(['submit', 'remove']);

const fileInputKey = ref(0);
const dragActive = ref(false);

const evidenceStats = computed(() => {
    const count = props.items.length;
    const bytes = props.items.reduce((sum, ev) => sum + Number(ev.file_size || 0), 0);
    return { count, bytes };
});

const groupedEvidence = computed(() => {
    const groups = new Map();

    for (const ev of props.items) {
        const stamp = ev.created_at ? String(ev.created_at).slice(0, 16) : 'unknown';
        const key = `${ev.title}|${ev.description ?? ''}|${stamp}`;

        if (!groups.has(key)) {
            groups.set(key, {
                key,
                title: ev.title,
                description: ev.description,
                created_at: ev.created_at,
                entries: [],
            });
        }

        groups.get(key).entries.push(ev);
    }

    return Array.from(groups.values()).sort(
        (a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0),
    );
});

function formatFileSize(bytes) {
    if (bytes == null || bytes === '') return '';
    const n = Number(bytes);
    if (!Number.isFinite(n) || n <= 0) return '';
    if (n < 1024) return `${n} B`;
    if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
    return `${(n / (1024 * 1024)).toFixed(1)} MB`;
}

function formatDate(value) {
    if (!value) return '';
    try {
        return new Date(value).toLocaleString(undefined, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });
    } catch {
        return '';
    }
}

function fileMeta(source) {
    const mime = source.mime_type || source.type || '';
    const name = source.original_filename || source.name || '';

    if (mime.startsWith('image/') || /\.(jpe?g|png|gif|webp)$/i.test(name)) {
        return { kind: 'image', label: 'Image', icon: '🖼', bg: 'bg-violet-50', text: 'text-violet-800', ring: 'ring-violet-100' };
    }
    if (mime.includes('pdf') || /\.pdf$/i.test(name)) {
        return { kind: 'pdf', label: 'PDF', icon: '📄', bg: 'bg-rose-50', text: 'text-rose-800', ring: 'ring-rose-100' };
    }
    if (mime.includes('spreadsheet') || mime.includes('excel') || /\.xlsx?$/i.test(name)) {
        return { kind: 'sheet', label: 'Spreadsheet', icon: '📊', bg: 'bg-emerald-50', text: 'text-emerald-800', ring: 'ring-emerald-100' };
    }
    if (mime.includes('word') || /\.docx?$/i.test(name)) {
        return { kind: 'doc', label: 'Document', icon: '📝', bg: 'bg-sky-50', text: 'text-sky-800', ring: 'ring-sky-100' };
    }
    if (mime.includes('zip') || /\.zip$/i.test(name)) {
        return { kind: 'archive', label: 'Archive', icon: '📦', bg: 'bg-amber-50', text: 'text-amber-900', ring: 'ring-amber-100' };
    }

    return { kind: 'file', label: 'File', icon: '📎', bg: 'bg-slate-50', text: 'text-slate-800', ring: 'ring-slate-200' };
}

function applySelectedFiles(fileList) {
    const picked = fileList && fileList.length ? Array.from(fileList).slice(0, props.maxFiles) : [];
    files.value = picked;
}

function onFileInput(event) {
    applySelectedFiles(event.target.files);
}

function onDragOver(event) {
    event.preventDefault();
    dragActive.value = true;
}

function onDragLeave() {
    dragActive.value = false;
}

function onDrop(event) {
    event.preventDefault();
    dragActive.value = false;
    applySelectedFiles(event.dataTransfer?.files);
}

function removeDraftFile(index) {
    files.value = files.value.filter((_, i) => i !== index);
    fileInputKey.value += 1;
}

function clearDraft() {
    title.value = '';
    description.value = '';
    files.value = [];
    fileInputKey.value += 1;
}

function canRemoveEntry(entry) {
    return props.editable && entry?.can_remove !== false;
}

function onSubmit(event) {
    event.preventDefault();
    emit('submit');
}
</script>

<template>
    <div class="space-y-5">
        <div
            v-if="!compact"
            class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4"
        >
            <div>
                <h3 class="text-base font-semibold text-slate-900">Supporting evidence</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Upload proof of work for this commitment package — up to {{ maxFiles }} files per submission.
                </p>
            </div>
            <div v-if="evidenceStats.count" class="flex flex-wrap gap-2">
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800 ring-1 ring-emerald-100">
                    {{ evidenceStats.count }} file{{ evidenceStats.count === 1 ? '' : 's' }}
                </span>
                <span
                    v-if="evidenceStats.bytes"
                    class="rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200"
                >
                    {{ formatFileSize(evidenceStats.bytes) }} total
                </span>
            </div>
        </div>

        <div v-if="groupedEvidence.length" class="space-y-4">
            <article
                v-for="group in groupedEvidence"
                :key="group.key"
                class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
            >
                <div class="border-b border-slate-100 bg-slate-50/80 px-4 py-3">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-semibold text-slate-900">{{ group.title || 'Untitled evidence' }}</p>
                            <p v-if="group.description" class="mt-1 text-sm text-slate-600">{{ group.description }}</p>
                        </div>
                        <p v-if="group.created_at" class="shrink-0 text-[11px] text-slate-500">
                            {{ formatDate(group.created_at) }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="entry in group.entries"
                        :key="entry.id"
                        class="flex flex-col rounded-lg border border-slate-200 bg-slate-50/50 p-3 transition hover:border-slate-300 hover:bg-white hover:shadow-sm"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-lg ring-1"
                                :class="[fileMeta(entry).bg, fileMeta(entry).text, fileMeta(entry).ring]"
                                aria-hidden="true"
                            >
                                {{ fileMeta(entry).icon }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-900">
                                    {{ entry.original_filename || entry.title || 'Attachment' }}
                                </p>
                                <p class="mt-0.5 text-[11px] text-slate-500">
                                    <span
                                        class="mr-2 inline-block rounded-full px-2 py-0.5 font-semibold ring-1"
                                        :class="[fileMeta(entry).bg, fileMeta(entry).text, fileMeta(entry).ring]"
                                    >
                                        {{ fileMeta(entry).label }}
                                    </span>
                                    <span v-if="entry.file_size">{{ formatFileSize(entry.file_size) }}</span>
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <a
                                v-if="entry.file_url"
                                :href="entry.file_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center rounded-md bg-blue-600 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-blue-700"
                            >
                                Open
                            </a>
                            <a
                                v-if="entry.download_url || entry.file_url"
                                :href="entry.download_url || `${entry.file_url}?download=1`"
                                class="inline-flex items-center rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Download
                            </a>
                            <SecondaryButton
                                v-if="canRemoveEntry(entry)"
                                type="button"
                                class="text-[11px] text-rose-700 ring-rose-200"
                                @click="emit('remove', entry.id)"
                            >
                                Remove
                            </SecondaryButton>
                        </div>

                        <div
                            v-if="fileMeta(entry).kind === 'image' && entry.file_url"
                            class="mt-3 overflow-hidden rounded-md border border-slate-200 bg-white"
                        >
                            <img
                                :src="entry.file_url"
                                :alt="entry.original_filename || entry.title"
                                class="max-h-36 w-full object-cover object-center"
                                loading="lazy"
                            />
                        </div>
                    </div>
                </div>
            </article>
        </div>

        <div
            v-else-if="!groupedEvidence.length && !compact"
            class="rounded-xl border border-dashed border-slate-300 bg-slate-50/60 px-6 py-8 text-center"
        >
            <p class="text-3xl" aria-hidden="true">📁</p>
            <p class="mt-2 text-sm font-medium text-slate-700">No evidence uploaded yet</p>
            <p class="mt-1 text-xs text-slate-500">
                {{ showForm ? `Use the form below to attach up to ${maxFiles} supporting files.` : 'Supporting documents will appear here once added.' }}
            </p>
        </div>

        <form
            v-if="showForm && !embedded"
            class="rounded-xl border border-emerald-200 bg-gradient-to-b from-emerald-50/80 to-white p-4 shadow-sm sm:p-5"
            @submit="onSubmit"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-semibold text-emerald-950">{{ formHeading }}</p>
                <span class="rounded-full bg-white px-2.5 py-0.5 text-[11px] font-semibold text-emerald-800 ring-1 ring-emerald-200">
                    Max {{ maxFiles }} files
                </span>
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div>
                    <InputLabel value="Subject / title" />
                    <TextInput
                        v-model="title"
                        type="text"
                        class="mt-1 block w-full text-sm"
                        placeholder="e.g. Q3 accomplishment report"
                    />
                </div>
                <div>
                    <InputLabel value="Description (optional)" />
                    <TextInput
                        v-model="description"
                        type="text"
                        class="mt-1 block w-full text-sm"
                        placeholder="Short note about this evidence"
                    />
                </div>

                <div class="md:col-span-2">
                    <InputLabel value="Attachments" />
                    <div
                        class="mt-1 rounded-xl border-2 border-dashed px-4 py-6 text-center transition"
                        :class="dragActive
                            ? 'border-emerald-400 bg-emerald-50/80'
                            : 'border-slate-300 bg-white hover:border-emerald-300 hover:bg-emerald-50/30'"
                        @dragover="onDragOver"
                        @dragleave="onDragLeave"
                        @drop="onDrop"
                    >
                        <p class="text-2xl" aria-hidden="true">⬆</p>
                        <p class="mt-2 text-sm font-medium text-slate-800">
                            Drag files here or choose from your device
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            jpg, png, gif, webp, pdf, doc, docx, xls, xlsx, txt, zip · up to 12 MB each
                        </p>
                        <label class="mt-4 inline-flex cursor-pointer items-center rounded-md bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700">
                            Browse files
                            <input
                                :key="fileInputKey"
                                type="file"
                                multiple
                                class="sr-only"
                                @change="onFileInput"
                            />
                        </label>
                    </div>

                    <div v-if="files.length" class="mt-3 space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Selected ({{ files.length }}/{{ maxFiles }})</p>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <div
                                v-for="(f, i) in files"
                                :key="i"
                                class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3"
                            >
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-base ring-1"
                                    :class="[fileMeta(f).bg, fileMeta(f).text, fileMeta(f).ring]"
                                >
                                    {{ fileMeta(f).icon }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-medium text-slate-900">{{ f.name }}</p>
                                    <p class="text-[11px] text-slate-500">{{ formatFileSize(f.size) }}</p>
                                </div>
                                <button
                                    type="button"
                                    class="shrink-0 text-xs font-semibold text-rose-700 hover:underline"
                                    @click="removeDraftFile(i)"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                    </div>

                    <InputError class="mt-2" :message="error" />
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <PrimaryButton type="submit" class="bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500" :disabled="submitting">
                    {{ submitting ? 'Uploading…' : 'Save evidence' }}
                </PrimaryButton>
                <SecondaryButton
                    v-if="title || description || files.length"
                    type="button"
                    @click="clearDraft"
                >
                    Clear
                </SecondaryButton>
            </div>
        </form>

        <div
            v-else-if="showForm && embedded"
            class="rounded-xl border border-emerald-200 bg-gradient-to-b from-emerald-50/80 to-white p-4 shadow-sm sm:p-5"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-semibold text-emerald-950">{{ formHeading }}</p>
                <span class="rounded-full bg-white px-2.5 py-0.5 text-[11px] font-semibold text-emerald-800 ring-1 ring-emerald-200">
                    Max {{ maxFiles }} files
                </span>
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div>
                    <InputLabel value="Subject / title" />
                    <TextInput
                        v-model="title"
                        type="text"
                        class="mt-1 block w-full text-sm"
                        placeholder="e.g. Q3 accomplishment report"
                    />
                </div>
                <div>
                    <InputLabel value="Description (optional)" />
                    <TextInput
                        v-model="description"
                        type="text"
                        class="mt-1 block w-full text-sm"
                        placeholder="Short note about this evidence"
                    />
                </div>

                <div class="md:col-span-2">
                    <InputLabel value="Attachments" />
                    <div
                        class="mt-1 rounded-xl border-2 border-dashed px-4 py-6 text-center transition"
                        :class="dragActive
                            ? 'border-emerald-400 bg-emerald-50/80'
                            : 'border-slate-300 bg-white hover:border-emerald-300 hover:bg-emerald-50/30'"
                        @dragover="onDragOver"
                        @dragleave="onDragLeave"
                        @drop="onDrop"
                    >
                        <p class="text-2xl" aria-hidden="true">⬆</p>
                        <p class="mt-2 text-sm font-medium text-slate-800">
                            Drag files here or choose from your device
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            jpg, png, gif, webp, pdf, doc, docx, xls, xlsx, txt, zip · up to 12 MB each
                        </p>
                        <label class="mt-4 inline-flex cursor-pointer items-center rounded-md bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700">
                            Browse files
                            <input
                                :key="fileInputKey"
                                type="file"
                                multiple
                                class="sr-only"
                                @change="onFileInput"
                            />
                        </label>
                    </div>

                    <div v-if="files.length" class="mt-3 space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Selected ({{ files.length }}/{{ maxFiles }})</p>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <div
                                v-for="(f, i) in files"
                                :key="i"
                                class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-3"
                            >
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-base ring-1"
                                    :class="[fileMeta(f).bg, fileMeta(f).text, fileMeta(f).ring]"
                                >
                                    {{ fileMeta(f).icon }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-xs font-medium text-slate-900">{{ f.name }}</p>
                                    <p class="text-[11px] text-slate-500">{{ formatFileSize(f.size) }}</p>
                                </div>
                                <button
                                    type="button"
                                    class="shrink-0 text-xs font-semibold text-rose-700 hover:underline"
                                    @click="removeDraftFile(i)"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
                    </div>

                    <InputError class="mt-2" :message="error" />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Team {
    id: number;
    name: string;
    post_auto_hide_days: number | null;
    monthly_token_limit: number | null;
    relevancy_prompt: string | null;
    positive_keywords: string | null;
    negative_keywords: string | null;
}

interface Webhook {
    id: number;
    name: string;
    url: string;
    event: string;
    is_active: boolean;
    last_triggered_at: string | null;
    failure_count: number;
    secret: string | null;
}

interface Props {
    team: Team;
    webhooks: {
        data: Webhook[];
    };
}

const props = defineProps<Props>();

const page = usePage();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Team Settings', href: '/team-settings' }];

// Get tab from URL query parameter
const urlParams = new URLSearchParams(window.location.search);
const activeTab = ref(urlParams.get('tab') || 'general');

// Update URL when tab changes
watch(activeTab, (newTab) => {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', newTab);
    window.history.replaceState({}, '', url);
});

const settingsForm = useForm({
    name: props.team.name,
    post_auto_hide_days: props.team.post_auto_hide_days,
    monthly_token_limit: props.team.monthly_token_limit,
    relevancy_prompt: props.team.relevancy_prompt || '',
    positive_keywords: props.team.positive_keywords || '',
    negative_keywords: props.team.negative_keywords || '',
});

const submitSettings = () => {
    settingsForm.put('/team-settings');
};

// Webhook modal state
const showWebhookModal = ref(false);
const editingWebhook = ref<Webhook | null>(null);

const webhookForm = useForm({
    name: '',
    url: '',
    event: 'NEW_POSTS',
    is_active: true,
    secret: '',
});

const openCreateWebhookModal = () => {
    editingWebhook.value = null;
    webhookForm.reset();
    webhookForm.clearErrors();
    webhookForm.defaults({
        name: '',
        url: '',
        event: 'NEW_POSTS',
        is_active: true,
        secret: '',
    });
    showWebhookModal.value = true;
};

const openEditWebhookModal = (webhook: Webhook) => {
    editingWebhook.value = webhook;
    webhookForm.reset();
    webhookForm.clearErrors();
    webhookForm.defaults({
        name: webhook.name,
        url: webhook.url,
        event: webhook.event,
        is_active: webhook.is_active,
        secret: webhook.secret || '',
    });
    showWebhookModal.value = true;
};

const submitWebhook = () => {
    if (editingWebhook.value) {
        webhookForm.put(`/webhooks/${editingWebhook.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showWebhookModal.value = false;
            },
        });
    } else {
        webhookForm.post('/webhooks', {
            preserveScroll: true,
            onSuccess: () => {
                showWebhookModal.value = false;
            },
        });
    }
};

const deleteWebhook = (id: number) => {
    if (confirm('Are you sure you want to delete this webhook?')) {
        router.delete(`/webhooks/${id}`, {
            preserveScroll: true,
        });
    }
};

const testProcessing = ref<number | null>(null);

const sendTestWebhook = (webhookId: number) => {
    testProcessing.value = webhookId;
    router.post(`/webhooks/${webhookId}/test`, {}, {
        preserveScroll: true,
        onFinish: () => {
            testProcessing.value = null;
        },
    });
};

const formatEvent = (event: string) => {
    const map: Record<string, string> = {
        NEW_POSTS: 'New Posts Found',
        HIGH_RELEVANCY_POST: 'High Relevancy Post',
        CONTENT_GENERATED: 'Content Generated',
    };
    return map[event] || event;
};

const modalTitle = computed(() => editingWebhook.value ? 'Edit Webhook' : 'Create Webhook');
const modalDescription = computed(() => editingWebhook.value
    ? 'Update your webhook configuration.'
    : 'Set up a webhook to receive notifications about events.');
const submitButtonText = computed(() => {
    if (webhookForm.processing) {
        return editingWebhook.value ? 'Updating...' : 'Creating...';
    }
    return editingWebhook.value ? 'Update Webhook' : 'Create Webhook';
});

// Import/Export functionality
const importForm = useForm({
    file: null as File | null,
});

const fileInputRef = ref<HTMLInputElement | null>(null);
const exportProcessing = ref(false);
const importResult = ref<{
    success: boolean;
    message: string;
} | null>(null);

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        importForm.file = target.files[0];
        importResult.value = null;
    }
};

const exportSources = () => {
    exportProcessing.value = true;

    // Create a hidden form to handle the download with CSRF token
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/team-settings/export-sources';
    form.style.display = 'none';

    // Add CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (csrfToken) {
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = csrfToken;
        form.appendChild(csrfInput);
    }

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);

    // Reset processing state after a short delay
    setTimeout(() => {
        exportProcessing.value = false;
    }, 1000);
};

const submitImport = () => {
    if (!importForm.file) {
        return;
    }

    const formData = new FormData();
    formData.append('file', importForm.file);

    importForm.post('/team-settings/import-sources', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            importForm.file = null;
            if (fileInputRef.value) {
                fileInputRef.value.value = '';
            }
        },
    });
};
</script>

<template>
    <Head title="Team Settings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold">Team Settings</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Configure your team's preferences and defaults.</p>
            </div>

            <Tabs v-model="activeTab" class="w-full">
                <TabsList class="mb-6">
                    <TabsTrigger value="general">General</TabsTrigger>
                    <TabsTrigger value="keywords">Keyword Filters</TabsTrigger>
                    <TabsTrigger value="ai">AI</TabsTrigger>
                    <TabsTrigger value="webhooks">Webhooks</TabsTrigger>
                    <TabsTrigger value="import-export">Import / Export</TabsTrigger>
                </TabsList>

                <!-- General Tab -->
                <TabsContent value="general">
                    <form @submit.prevent="submitSettings" class="max-w-2xl space-y-8">
                        <div class="space-y-6">
                            <h2 class="text-lg font-medium">Team Information</h2>

                            <div class="space-y-2">
                                <Label for="name">Team Name</Label>
                                <Input id="name" v-model="settingsForm.name" type="text" />
                                <InputError :message="settingsForm.errors.name" />
                            </div>
                        </div>

                        <div class="space-y-6">
                            <h2 class="text-lg font-medium">Post Management</h2>

                            <div class="space-y-2">
                                <Label for="post_auto_hide_days">Auto-hide Posts After (days)</Label>
                                <Input id="post_auto_hide_days" v-model.number="settingsForm.post_auto_hide_days" type="number" min="0" max="365" />
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Automatically hide posts after this many days. Leave empty to disable.
                                </p>
                                <InputError :message="settingsForm.errors.post_auto_hide_days" />
                            </div>
                        </div>

                        <div class="flex items-center gap-4 pt-4">
                            <Button type="submit" :disabled="settingsForm.processing">
                                {{ settingsForm.processing ? 'Saving...' : 'Save Settings' }}
                            </Button>
                        </div>
                    </form>
                </TabsContent>

                <!-- Keyword Filters Tab -->
                <TabsContent value="keywords">
                    <form @submit.prevent="submitSettings" class="max-w-2xl space-y-8">
                        <div class="space-y-6">
                            <div>
                                <h2 class="text-lg font-medium">Keyword Filtering</h2>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Filter posts during source parsing based on keywords found in their titles.
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="positive_keywords">Positive Keywords (Include)</Label>
                                <textarea
                                    id="positive_keywords"
                                    v-model="settingsForm.positive_keywords"
                                    rows="6"
                                    class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                    placeholder="climate&#10;renewable&#10;sustainability"
                                ></textarea>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Enter one keyword per line. When set, only posts containing at least one of these keywords will be included.
                                </p>
                                <InputError :message="settingsForm.errors.positive_keywords" />
                            </div>

                            <div class="space-y-2">
                                <Label for="negative_keywords">Negative Keywords (Exclude)</Label>
                                <textarea
                                    id="negative_keywords"
                                    v-model="settingsForm.negative_keywords"
                                    rows="6"
                                    class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                    placeholder="sponsored&#10;advertisement&#10;promotion"
                                ></textarea>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Enter one keyword per line. Posts containing any of these keywords will be excluded. Negative keywords take priority over positive keywords.
                                </p>
                                <InputError :message="settingsForm.errors.negative_keywords" />
                            </div>
                        </div>

                        <div class="flex items-center gap-4 pt-4">
                            <Button type="submit" :disabled="settingsForm.processing">
                                {{ settingsForm.processing ? 'Saving...' : 'Save Settings' }}
                            </Button>
                        </div>
                    </form>
                </TabsContent>

                <!-- AI Tab -->
                <TabsContent value="ai">
                    <form @submit.prevent="submitSettings" class="max-w-2xl space-y-8">
                        <div class="space-y-6">
                            <div>
                                <h2 class="text-lg font-medium">AI Configuration</h2>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Customize how AI evaluates and processes your content.
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="relevancy_prompt">Custom Relevancy Prompt</Label>
                                <textarea
                                    id="relevancy_prompt"
                                    v-model="settingsForm.relevancy_prompt"
                                    rows="6"
                                    class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                    placeholder="You are a news analyst. Rate the relevancy of content for a business news monitoring system..."
                                ></textarea>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Customize how the AI evaluates post relevancy. This prompt is used when summarizing and scoring new posts.
                                </p>
                                <InputError :message="settingsForm.errors.relevancy_prompt" />
                            </div>

                            <div class="space-y-2">
                                <Label for="monthly_token_limit">Monthly Token Limit</Label>
                                <Input id="monthly_token_limit" v-model.number="settingsForm.monthly_token_limit" type="number" min="0" />
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Maximum tokens allowed per month. Leave empty for unlimited.
                                </p>
                                <InputError :message="settingsForm.errors.monthly_token_limit" />
                            </div>
                        </div>

                        <div class="flex items-center gap-4 pt-4">
                            <Button type="submit" :disabled="settingsForm.processing">
                                {{ settingsForm.processing ? 'Saving...' : 'Save Settings' }}
                            </Button>
                        </div>
                    </form>
                </TabsContent>

                <!-- Webhooks Tab -->
                <TabsContent value="webhooks">
                    <div class="space-y-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-medium">Webhooks</h2>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Configure webhooks to receive notifications about events.
                                </p>
                            </div>
                            <Button @click="openCreateWebhookModal">Add Webhook</Button>
                        </div>

                        <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                            Name
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                            URL
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                            Event
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                            Status
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                            Last Triggered
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                            Failures
                                        </th>
                                        <th
                                            class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                        >
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                    <tr v-for="webhook in webhooks.data" :key="webhook.id">
                                        <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                            {{ webhook.name }}
                                        </td>
                                        <td class="max-w-xs truncate px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            {{ webhook.url }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            <span class="rounded bg-gray-100 px-2 py-1 text-xs dark:bg-gray-700">
                                                {{ formatEvent(webhook.event) }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm">
                                            <span
                                                :class="
                                                    webhook.is_active
                                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                        : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                "
                                                class="rounded-full px-2 py-1 text-xs"
                                            >
                                                {{ webhook.is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            {{ webhook.last_triggered_at || 'Never' }}
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            <span
                                                :class="webhook.failure_count > 0 ? 'text-red-600 dark:text-red-400' : ''"
                                                class="font-medium"
                                            >
                                                {{ webhook.failure_count }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium space-x-2">
                                            <button
                                                @click="sendTestWebhook(webhook.id)"
                                                :disabled="testProcessing === webhook.id"
                                                class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                            >
                                                {{ testProcessing === webhook.id ? 'Testing...' : 'Test' }}
                                            </button>
                                            <button
                                                @click="openEditWebhookModal(webhook)"
                                                class="text-blue-600 hover:text-blue-900 dark:text-blue-400"
                                            >
                                                Edit
                                            </button>
                                            <button @click="deleteWebhook(webhook.id)" class="text-red-600 hover:text-red-900 dark:text-red-400">
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="webhooks.data.length === 0">
                                        <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                            No webhooks configured. Click "Add Webhook" to set up notifications.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </TabsContent>

                <!-- Import / Export Tab -->
                <TabsContent value="import-export">
                    <div class="max-w-2xl space-y-8">
                        <!-- Success Message -->
                        <div
                            v-if="page.props.flash?.success && activeTab === 'import-export'"
                            class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20"
                        >
                            <div class="flex items-start">
                                <svg class="h-5 w-5 text-green-600 dark:text-green-400 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div class="flex-1">
                                    <h3 class="text-sm font-medium text-green-800 dark:text-green-200">
                                        Import Successful
                                    </h3>
                                    <p class="mt-1 text-sm text-green-700 dark:text-green-300">
                                        {{ page.props.flash.success }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Error Message -->
                        <div
                            v-if="page.props.flash?.error && activeTab === 'import-export'"
                            class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20"
                        >
                            <div class="flex items-start">
                                <svg class="h-5 w-5 text-red-600 dark:text-red-400 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <div class="flex-1">
                                    <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                                        Import Failed
                                    </h3>
                                    <p class="mt-1 text-sm text-red-700 dark:text-red-300">
                                        {{ page.props.flash.error }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Export Section -->
                        <div class="space-y-6">
                            <div>
                                <h2 class="text-lg font-medium">Export Sources</h2>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Download all your team's sources as a JSON file. The export will include up to 1000 sources with their configuration and tags.
                                </p>
                            </div>

                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-6 dark:border-gray-700 dark:bg-gray-800">
                                <div class="space-y-4">
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        <p class="font-medium mb-2">The export will include:</p>
                                        <ul class="list-disc list-inside space-y-1 ml-2">
                                            <li>Internal name, type, and URL</li>
                                            <li>CSS selectors (for website sources)</li>
                                            <li>Keywords and monitoring interval</li>
                                            <li>Active status and notification settings</li>
                                            <li>Associated tags</li>
                                        </ul>
                                    </div>

                                    <Button
                                        @click="exportSources"
                                        :disabled="exportProcessing"
                                        class="w-full sm:w-auto"
                                    >
                                        {{ exportProcessing ? 'Exporting...' : 'Export Sources' }}
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-gray-200 dark:border-gray-700"></div>

                        <!-- Import Section -->
                        <div class="space-y-6">
                            <div>
                                <h2 class="text-lg font-medium">Import Sources</h2>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Upload a JSON file to import sources into your team. Sources with duplicate URLs will be skipped automatically.
                                </p>
                            </div>

                            <form @submit.prevent="submitImport" class="space-y-6">
                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-6 dark:border-gray-700 dark:bg-gray-800">
                                    <div class="space-y-4">
                                        <div class="text-sm text-gray-600 dark:text-gray-400">
                                            <p class="font-medium mb-2">Import requirements:</p>
                                            <ul class="list-disc list-inside space-y-1 ml-2">
                                                <li>File must be in valid JSON format</li>
                                                <li>Maximum 1000 sources per import</li>
                                                <li>Maximum file size: 10MB</li>
                                                <li>Duplicate sources (same URL) will be skipped</li>
                                                <li>Tags will be created automatically if they don't exist</li>
                                            </ul>
                                        </div>

                                        <div class="space-y-2">
                                            <Label for="import_file">Select JSON File</Label>
                                            <Input
                                                id="import_file"
                                                ref="fileInputRef"
                                                type="file"
                                                accept=".json,application/json"
                                                @change="handleFileSelect"
                                                :disabled="importForm.processing"
                                            />
                                            <InputError :message="importForm.errors.file" />
                                            <p v-if="importForm.file" class="text-sm text-gray-600 dark:text-gray-400">
                                                Selected: {{ importForm.file.name }} ({{ (importForm.file.size / 1024).toFixed(2) }} KB)
                                            </p>
                                        </div>

                                        <Button
                                            type="submit"
                                            :disabled="!importForm.file || importForm.processing"
                                            class="w-full sm:w-auto"
                                        >
                                            {{ importForm.processing ? 'Importing...' : 'Import Sources' }}
                                        </Button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </TabsContent>
            </Tabs>
        </div>

        <!-- Webhook Create/Edit Modal -->
        <Dialog v-model:open="showWebhookModal">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ modalTitle }}</DialogTitle>
                    <DialogDescription>{{ modalDescription }}</DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitWebhook" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="webhook_name">Name</Label>
                        <Input id="webhook_name" v-model="webhookForm.name" type="text" placeholder="E.g., Slack Notification" />
                        <InputError :message="webhookForm.errors.name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="webhook_url">Webhook URL</Label>
                        <Input id="webhook_url" v-model="webhookForm.url" type="url" placeholder="https://example.com/webhook" />
                        <InputError :message="webhookForm.errors.url" />
                    </div>

                    <div class="space-y-2">
                        <Label for="webhook_event">Event</Label>
                        <Select v-model="webhookForm.event">
                            <SelectTrigger>
                                <SelectValue placeholder="Select event" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="NEW_POSTS">New Posts Found</SelectItem>
                                <SelectItem value="HIGH_RELEVANCY_POST">High Relevancy Post</SelectItem>
                                <SelectItem value="CONTENT_GENERATED">Content Generated</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="webhookForm.errors.event" />
                    </div>

                    <div class="space-y-2">
                        <Label for="webhook_secret">Secret (Optional)</Label>
                        <Input id="webhook_secret" v-model="webhookForm.secret" type="text" placeholder="Used for HMAC signature verification" />
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            If provided, the webhook payload will be signed using HMAC-SHA256 and included in the X-Webhook-Signature header.
                        </p>
                        <InputError :message="webhookForm.errors.secret" />
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox id="webhook_is_active" :default-value="webhookForm.is_active" @update:model-value="webhookForm.is_active = $event" />
                        <Label for="webhook_is_active" class="cursor-pointer">Active</Label>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="showWebhookModal = false">Cancel</Button>
                        <Button type="submit" :disabled="webhookForm.processing">
                            {{ submitButtonText }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

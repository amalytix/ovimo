<script setup lang="ts">
import TeamInvitationController from '@/actions/App/Http/Controllers/TeamInvitationController';
import TeamMemberController from '@/actions/App/Http/Controllers/TeamMemberController';
import InputError from '@/components/InputError.vue';
import LinkedInConnectButton from '@/components/Integrations/LinkedInConnectButton.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import { disconnect as disconnectLinkedIn } from '@/routes/integrations/linkedin';
import { type BreadcrumbItem } from '@/types';
import type { SocialIntegration } from '@/types/social';
import { Form, Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, PlayCircle, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface TeamUser {
    id: number;
    name: string;
    email: string;
    two_factor_enabled: boolean;
}

interface PendingInvitation {
    id: number;
    email: string;
    created_at: string;
    expires_at: string;
}

interface Team {
    id: number;
    name: string;
    owner_id: number;
    post_auto_hide_days: number | null;
    monthly_token_limit: number | null;
    relevancy_prompt: string | null;
    positive_keywords: string | null;
    negative_keywords: string | null;
    openai_api_key_masked: string | null;
    openai_model: string | null;
    gemini_api_key_masked: string | null;
    gemini_image_model: string | null;
    gemini_image_size: string | null;
    has_openai: boolean;
    has_gemini: boolean;
    users: TeamUser[];
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
    pendingInvitations: PendingInvitation[];
    isOwner: boolean;
    webhooks: {
        data: Webhook[];
    };
    integrations: {
        linkedin: SocialIntegration[];
    };
}

const props = defineProps<Props>();

const page = usePage();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Team Settings', href: '/team-settings' },
];
const linkedinIntegrations = computed(() => props.integrations.linkedin || []);

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
    openai_api_key: '',
    openai_model: props.team.openai_model || 'gpt-5-mini',
    gemini_api_key: '',
    gemini_image_model:
        props.team.gemini_image_model || 'gemini-3-pro-image-preview',
    gemini_image_size: props.team.gemini_image_size || '1K',
});

const clearOpenAIKey = ref(false);
const clearGeminiKey = ref(false);

const submitSettings = () => {
    settingsForm.transform((data) => {
        const payload = { ...data } as Record<string, unknown>;

        if (payload.openai_api_key === '' && !clearOpenAIKey.value) {
            delete payload.openai_api_key;
        }

        if (payload.gemini_api_key === '' && !clearGeminiKey.value) {
            delete payload.gemini_api_key;
        }

        return payload;
    });

    settingsForm.put('/team-settings', {
        onFinish: () => {
            clearOpenAIKey.value = false;
            clearGeminiKey.value = false;
            settingsForm.reset('openai_api_key', 'gemini_api_key');
        },
    });
};

const handleOpenAIInput = () => {
    clearOpenAIKey.value = false;
};

const handleGeminiInput = () => {
    clearGeminiKey.value = false;
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
    router.post(
        `/webhooks/${webhookId}/test`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                testProcessing.value = null;
            },
        },
    );
};

const disconnectIntegration = (integrationId: number) => {
    if (!confirm('Disconnect this LinkedIn profile?')) {
        return;
    }

    router.delete(disconnectLinkedIn.url(integrationId), {
        preserveScroll: true,
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

const formatDateTime = (value: string | null | undefined) => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
};

const modalTitle = computed(() =>
    editingWebhook.value ? 'Edit Webhook' : 'Create Webhook',
);
const modalDescription = computed(() =>
    editingWebhook.value
        ? 'Update your webhook configuration.'
        : 'Set up a webhook to receive notifications about events.',
);
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
    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');
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

// Team Members functionality
const userToRemove = ref<TeamUser | null>(null);
const invitationToRevoke = ref<PendingInvitation | null>(null);
const showLeaveDialog = ref(false);

const removeUser = () => {
    if (!userToRemove.value) return;
    router.delete(TeamMemberController.destroy.url(userToRemove.value.id), {
        onSuccess: () => {
            userToRemove.value = null;
        },
    });
};

const revokeInvitation = () => {
    if (!invitationToRevoke.value) return;
    router.delete(
        TeamInvitationController.destroy.url(invitationToRevoke.value.id),
        {
            onSuccess: () => {
                invitationToRevoke.value = null;
            },
        },
    );
};

const leaveTeam = () => {
    router.post(TeamMemberController.leave.url(), {}, {
        onSuccess: () => {
            showLeaveDialog.value = false;
        },
    });
};

const formatExpiry = (expiresAt: string) => {
    const expiry = new Date(expiresAt);
    const now = new Date();
    const diffMs = expiry.getTime() - now.getTime();
    const diffHours = Math.round(diffMs / (1000 * 60 * 60));

    if (diffHours < 0) {
        return 'expired';
    } else if (diffHours < 1) {
        return 'in less than an hour';
    } else if (diffHours === 1) {
        return 'in 1 hour';
    } else if (diffHours < 24) {
        return `in ${diffHours} hours`;
    } else {
        const diffDays = Math.round(diffHours / 24);
        return diffDays === 1 ? 'in 1 day' : `in ${diffDays} days`;
    }
};
</script>

<template>
    <Head title="Team Settings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold">Team Settings</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Configure your team's preferences and defaults.
                </p>
            </div>

            <Tabs v-model="activeTab" class="w-full">
                <TabsList class="mb-6">
                    <TabsTrigger value="general">General</TabsTrigger>
                    <TabsTrigger value="members">Members</TabsTrigger>
                    <TabsTrigger value="keywords">Keyword Filters</TabsTrigger>
                    <TabsTrigger value="ai">AI</TabsTrigger>
                    <TabsTrigger value="integrations">Integrations</TabsTrigger>
                    <TabsTrigger value="webhooks">Webhooks</TabsTrigger>
                    <TabsTrigger value="import-export"
                        >Import / Export</TabsTrigger
                    >
                </TabsList>

                <!-- General Tab -->
                <TabsContent value="general">
                    <form
                        @submit.prevent="submitSettings"
                        class="max-w-2xl space-y-8"
                    >
                        <div class="space-y-6">
                            <h2 class="text-lg font-medium">
                                Team Information
                            </h2>

                            <div class="space-y-2">
                                <Label for="name">Team Name</Label>
                                <Input
                                    id="name"
                                    v-model="settingsForm.name"
                                    type="text"
                                />
                                <InputError
                                    :message="settingsForm.errors.name"
                                />
                            </div>
                        </div>

                        <div class="space-y-6">
                            <h2 class="text-lg font-medium">Post Management</h2>

                            <div class="space-y-2">
                                <Label for="post_auto_hide_days"
                                    >Auto-hide Posts After (days)</Label
                                >
                                <Input
                                    id="post_auto_hide_days"
                                    v-model.number="
                                        settingsForm.post_auto_hide_days
                                    "
                                    type="number"
                                    min="0"
                                    max="365"
                                />
                                <p
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Automatically hide posts after this many
                                    days. Leave empty to disable.
                                </p>
                                <InputError
                                    :message="
                                        settingsForm.errors.post_auto_hide_days
                                    "
                                />
                            </div>
                        </div>

                        <div class="flex items-center gap-4 pt-4">
                            <Button
                                type="submit"
                                :disabled="settingsForm.processing"
                            >
                                {{
                                    settingsForm.processing
                                        ? 'Saving...'
                                        : 'Save Settings'
                                }}
                            </Button>
                        </div>
                    </form>
                </TabsContent>

                <!-- Members Tab -->
                <TabsContent value="members">
                    <div class="max-w-4xl space-y-8">
                        <!-- Team Members -->
                        <div>
                            <div class="mb-4 flex items-center justify-between">
                                <div>
                                    <h2 class="text-lg font-medium">Team Members</h2>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        People who have access to this team.
                                    </p>
                                </div>
                                <Dialog v-model:open="showLeaveDialog">
                                    <DialogTrigger as-child>
                                        <Button
                                            v-if="!isOwner && team.users.length > 1"
                                            variant="outline"
                                        >
                                            Leave Team
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>Leave team?</DialogTitle>
                                            <DialogDescription>
                                                Are you sure you want to leave
                                                {{ team.name }}? You will no longer have
                                                access to this team's data.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <DialogFooter>
                                            <DialogClose as-child>
                                                <Button variant="outline">Cancel</Button>
                                            </DialogClose>
                                            <Button
                                                variant="destructive"
                                                @click="leaveTeam"
                                            >
                                                Leave Team
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            </div>

                            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                                                Name
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                                                Email
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                                                2FA
                                            </th>
                                            <th v-if="isOwner" class="px-6 py-3 text-right text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                        <tr v-for="user in team.users" :key="user.id">
                                            <td class="px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-900 dark:text-white">
                                                {{ user.name }}
                                                <Badge v-if="user.id === team.owner_id" variant="secondary" class="ml-2">
                                                    Owner
                                                </Badge>
                                            </td>
                                            <td class="px-6 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                                {{ user.email }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <Badge
                                                    :variant="user.two_factor_enabled ? 'default' : 'outline'"
                                                    :class="user.two_factor_enabled ? 'bg-green-600' : ''"
                                                >
                                                    {{ user.two_factor_enabled ? 'Enabled' : 'Disabled' }}
                                                </Badge>
                                            </td>
                                            <td v-if="isOwner" class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap">
                                                <Dialog>
                                                    <DialogTrigger as-child>
                                                        <Button
                                                            v-if="user.id !== team.owner_id"
                                                            variant="ghost"
                                                            size="sm"
                                                            class="text-destructive hover:text-destructive"
                                                            @click="userToRemove = user"
                                                        >
                                                            Remove
                                                        </Button>
                                                    </DialogTrigger>
                                                    <DialogContent>
                                                        <DialogHeader>
                                                            <DialogTitle>Remove team member?</DialogTitle>
                                                            <DialogDescription>
                                                                Are you sure you want to remove
                                                                {{ userToRemove?.name }} from the team?
                                                            </DialogDescription>
                                                        </DialogHeader>
                                                        <DialogFooter>
                                                            <DialogClose as-child>
                                                                <Button variant="outline">Cancel</Button>
                                                            </DialogClose>
                                                            <Button variant="destructive" @click="removeUser">
                                                                Remove
                                                            </Button>
                                                        </DialogFooter>
                                                    </DialogContent>
                                                </Dialog>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Pending Invitations (Owner only) -->
                        <div v-if="isOwner && pendingInvitations.length > 0">
                            <div class="mb-4">
                                <h2 class="text-lg font-medium">Pending Invitations</h2>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Invitations that haven't been accepted yet.
                                </p>
                            </div>

                            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                                                Email
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                                                Expires
                                            </th>
                                            <th class="px-6 py-3 text-right text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900">
                                        <tr v-for="invitation in pendingInvitations" :key="invitation.id">
                                            <td class="px-6 py-4 text-sm whitespace-nowrap text-gray-900 dark:text-white">
                                                {{ invitation.email }}
                                            </td>
                                            <td class="px-6 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                                {{ formatExpiry(invitation.expires_at) }}
                                            </td>
                                            <td class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap">
                                                <Dialog>
                                                    <DialogTrigger as-child>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            class="text-destructive hover:text-destructive"
                                                            @click="invitationToRevoke = invitation"
                                                        >
                                                            Revoke
                                                        </Button>
                                                    </DialogTrigger>
                                                    <DialogContent>
                                                        <DialogHeader>
                                                            <DialogTitle>Revoke invitation?</DialogTitle>
                                                            <DialogDescription>
                                                                Are you sure you want to revoke the invitation for
                                                                {{ invitationToRevoke?.email }}?
                                                            </DialogDescription>
                                                        </DialogHeader>
                                                        <DialogFooter>
                                                            <DialogClose as-child>
                                                                <Button variant="outline">Cancel</Button>
                                                            </DialogClose>
                                                            <Button variant="destructive" @click="revokeInvitation">
                                                                Revoke
                                                            </Button>
                                                        </DialogFooter>
                                                    </DialogContent>
                                                </Dialog>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Invite Member (Owner only) -->
                        <div v-if="isOwner">
                            <div class="mb-4">
                                <h2 class="text-lg font-medium">Invite Team Member</h2>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Send an invitation to join your team.
                                </p>
                            </div>

                            <Form
                                v-bind="TeamInvitationController.store.form()"
                                class="space-y-4"
                                v-slot="{ errors, processing, recentlySuccessful }"
                            >
                                <div class="flex gap-4">
                                    <div class="flex-1">
                                        <Label for="email" class="sr-only">Email address</Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            name="email"
                                            placeholder="colleague@example.com"
                                            required
                                        />
                                        <InputError class="mt-2" :message="errors.email" />
                                    </div>
                                    <Button :disabled="processing">
                                        Send Invitation
                                    </Button>
                                </div>

                                <Transition
                                    enter-active-class="transition ease-in-out"
                                    enter-from-class="opacity-0"
                                    leave-active-class="transition ease-in-out"
                                    leave-to-class="opacity-0"
                                >
                                    <p v-show="recentlySuccessful" class="text-sm text-green-600">
                                        Invitation sent successfully.
                                    </p>
                                </Transition>
                            </Form>
                        </div>
                    </div>
                </TabsContent>

                <!-- Keyword Filters Tab -->
                <TabsContent value="keywords">
                    <form
                        @submit.prevent="submitSettings"
                        class="max-w-2xl space-y-8"
                    >
                        <div class="space-y-6">
                            <div>
                                <h2 class="text-lg font-medium">
                                    Keyword Filtering
                                </h2>
                                <p
                                    class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    Filter posts during source parsing based on
                                    keywords found in their titles.
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="positive_keywords"
                                    >Positive Keywords (Include)</Label
                                >
                                <textarea
                                    id="positive_keywords"
                                    v-model="settingsForm.positive_keywords"
                                    rows="6"
                                    class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    placeholder="climate&#10;renewable&#10;sustainability"
                                ></textarea>
                                <p
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Enter one keyword per line. When set, only
                                    posts containing at least one of these
                                    keywords will be included.
                                </p>
                                <InputError
                                    :message="
                                        settingsForm.errors.positive_keywords
                                    "
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="negative_keywords"
                                    >Negative Keywords (Exclude)</Label
                                >
                                <textarea
                                    id="negative_keywords"
                                    v-model="settingsForm.negative_keywords"
                                    rows="6"
                                    class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    placeholder="sponsored&#10;advertisement&#10;promotion"
                                ></textarea>
                                <p
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Enter one keyword per line. Posts containing
                                    any of these keywords will be excluded.
                                    Negative keywords take priority over
                                    positive keywords.
                                </p>
                                <InputError
                                    :message="
                                        settingsForm.errors.negative_keywords
                                    "
                                />
                            </div>
                        </div>

                        <div class="flex items-center gap-4 pt-4">
                            <Button
                                type="submit"
                                :disabled="settingsForm.processing"
                            >
                                {{
                                    settingsForm.processing
                                        ? 'Saving...'
                                        : 'Save Settings'
                                }}
                            </Button>
                        </div>
                    </form>
                </TabsContent>

                <!-- AI Tab -->
                <TabsContent value="ai">
                    <form
                        @submit.prevent="submitSettings"
                        class="max-w-3xl space-y-10"
                    >
                        <div class="space-y-8">
                            <div>
                                <h2 class="text-lg font-medium">
                                    AI Providers
                                </h2>
                                <p
                                    class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    Store per-team credentials for OpenAI and Gemini. Keys stay hidden; leave the fields blank to keep existing values.
                                </p>
                            </div>

                            <div class="grid gap-6 md:grid-cols-2">
                                <div class="space-y-4 rounded-lg border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                                OpenAI
                                            </h3>
                                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                                Used for summarization, analysis, and content generation.
                                            </p>
                                        </div>
                                        <Badge :variant="props.team.has_openai ? 'default' : 'secondary'">
                                            {{ props.team.has_openai ? 'Configured' : 'Not configured' }}
                                        </Badge>
                                    </div>

                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Current key: {{ props.team.openai_api_key_masked || 'None' }}
                                    </p>

                                    <div class="space-y-2">
                                        <Label for="openai_api_key">API Key</Label>
                                        <Input
                                            id="openai_api_key"
                                            v-model="settingsForm.openai_api_key"
                                            type="password"
                                            autocomplete="off"
                                            placeholder="sk-..."
                                            @input="handleOpenAIInput"
                                        />
                                        <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                            <span>Leave empty to keep current.</span>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                @click="
                                                    clearOpenAIKey.value = true;
                                                    settingsForm.openai_api_key = '';
                                                "
                                            >
                                                Clear key
                                            </Button>
                                            <a
                                                href="https://platform.openai.com/settings/organization/api-keys"
                                                target="_blank"
                                                rel="noreferrer"
                                                class="text-blue-600 hover:underline dark:text-blue-300"
                                            >
                                                Get a key
                                            </a>
                                        </div>
                                        <InputError :message="settingsForm.errors.openai_api_key" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="openai_model">Default Model</Label>
                                        <Input
                                            id="openai_model"
                                            v-model="settingsForm.openai_model"
                                            type="text"
                                            placeholder="gpt-5.1"
                                        />
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Used for long-form generation. Summaries still use gpt-5-mini unless overridden in code.
                                        </p>
                                        <InputError :message="settingsForm.errors.openai_model" />
                                    </div>
                                </div>

                                <div class="space-y-4 rounded-lg border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                                    <div class="flex items-start justify-between gap-3">
        
                                        <div>
                                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">
                                                Google Gemini
                                            </h3>
                                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                                Used for AI image generation.
                                            </p>
                                        </div>
                                        <Badge :variant="props.team.has_gemini ? 'default' : 'secondary'">
                                            {{ props.team.has_gemini ? 'Configured' : 'Not configured' }}
                                        </Badge>
                                    </div>

                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Current key: {{ props.team.gemini_api_key_masked || 'None' }}
                                    </p>

                                    <div class="space-y-2">
                                        <Label for="gemini_api_key">API Key</Label>
                                        <Input
                                            id="gemini_api_key"
                                            v-model="settingsForm.gemini_api_key"
                                            type="password"
                                            autocomplete="off"
                                            placeholder="AIza..."
                                            @input="handleGeminiInput"
                                        />
                                        <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                                            <span>Leave empty to keep current.</span>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                @click="
                                                    clearGeminiKey.value = true;
                                                    settingsForm.gemini_api_key = '';
                                                "
                                            >
                                                Clear key
                                            </Button>
                                            <a
                                                href="https://aistudio.google.com/app/apikey"
                                                target="_blank"
                                                rel="noreferrer"
                                                class="text-blue-600 hover:underline dark:text-blue-300"
                                            >
                                                Get a key
                                            </a>
                                        </div>
                                        <InputError :message="settingsForm.errors.gemini_api_key" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="gemini_image_model">Image Model</Label>
                                        <Input
                                            id="gemini_image_model"
                                            v-model="settingsForm.gemini_image_model"
                                            type="text"
                                            placeholder="gemini-3-pro-image-preview"
                                        />
                                        <InputError :message="settingsForm.errors.gemini_image_model" />
                                    </div>

                                    <div class="space-y-2">
        
                                        <Label for="gemini_image_size">Image Size</Label>
                                        <Select
                                            v-model="settingsForm.gemini_image_size"
                                            name="gemini_image_size"
                                            id="gemini_image_size"
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Choose size" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="1K">1K</SelectItem>
                                                <SelectItem value="2K">2K</SelectItem>
                                                <SelectItem value="4K">4K</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Higher sizes produce larger images and may take longer.
                                        </p>
                                        <InputError :message="settingsForm.errors.gemini_image_size" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div>
                                <h3 class="text-lg font-medium">Content Controls</h3>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Adjust how AI scores relevancy and how many tokens the team can spend.
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="relevancy_prompt">Custom Relevancy Prompt</Label>
                                <textarea
                                    id="relevancy_prompt"
                                    v-model="settingsForm.relevancy_prompt"
                                    rows="6"
                                    class="flex w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    placeholder="You are a news analyst. Rate the relevancy of content for a business news monitoring system..."
                                ></textarea>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Used when summarizing and scoring new posts.
                                </p>
                                <InputError :message="settingsForm.errors.relevancy_prompt" />
                            </div>

                            <div class="space-y-2">
                                <Label for="monthly_token_limit">Monthly Token Limit</Label>
                                <Input
                                    id="monthly_token_limit"
                                    v-model.number="settingsForm.monthly_token_limit"
                                    type="number"
                                    min="0"
                                />
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Maximum tokens allowed per month. Leave empty for unlimited.
                                </p>
                                <InputError :message="settingsForm.errors.monthly_token_limit" />
                            </div>
                        </div>

                        <div class="flex items-center gap-4 pt-2">
                            <Button
                                type="submit"
                                :disabled="settingsForm.processing"
                            >
                                {{
                                    settingsForm.processing
                                        ? 'Saving...'
                                        : 'Save Settings'
                                }}
                            </Button>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Keys are stored per team. Features are disabled until keys are configured.
                            </p>
                        </div>
                    </form>
                </TabsContent>

                <!-- Integrations Tab -->
                <TabsContent value="integrations">
                    <div class="space-y-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-medium">
                                    LinkedIn Integrations
                                </h2>
                                <p
                                    class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    Connect LinkedIn profiles to publish content
                                    directly from Ovimo.
                                </p>
                            </div>
                            <LinkedInConnectButton />
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div
                                v-for="integration in linkedinIntegrations"
                                :key="integration.id"
                                class="rounded-lg border bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                            >
                                <div class="flex items-start justify-between">
                                    <div>
                                        <p
                                            class="text-sm font-semibold text-gray-900 dark:text-white"
                                        >
                                            {{
                                                integration.platform_username ||
                                                integration.platform_user_id
                                            }}
                                        </p>
                                        <p
                                            class="text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            Connected
                                            {{
                                                formatDateTime(
                                                    integration.created_at,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <span
                                        class="rounded-full px-3 py-1 text-xs font-medium"
                                        :class="
                                            integration.is_active
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                        "
                                    >
                                        {{
                                            integration.is_active
                                                ? 'Active'
                                                : 'Inactive'
                                        }}
                                    </span>
                                </div>
                                <div
                                    class="mt-3 space-y-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    <p>
                                        Scopes:
                                        {{
                                            integration.scopes?.join(', ') ||
                                            'n/a'
                                        }}
                                    </p>
                                    <p>
                                        Token expires:
                                        {{
                                            formatDateTime(
                                                integration.token_expires_at,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div class="mt-4 flex justify-end">
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        @click="
                                            disconnectIntegration(
                                                integration.id,
                                            )
                                        "
                                    >
                                        Disconnect
                                    </Button>
                                </div>
                            </div>

                            <div
                                v-if="!linkedinIntegrations.length"
                                class="rounded-lg border border-dashed p-4 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400"
                            >
                                No LinkedIn profiles connected yet. Click
                                "Connect LinkedIn" to get started.
                            </div>
                        </div>
                    </div>
                </TabsContent>

                <!-- Webhooks Tab -->
                <TabsContent value="webhooks">
                    <div class="space-y-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-medium">Webhooks</h2>
                                <p
                                    class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    Configure webhooks to receive notifications
                                    about events.
                                </p>
                            </div>
                            <Button @click="openCreateWebhookModal"
                                >Add Webhook</Button
                            >
                        </div>

                        <div
                            class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700"
                        >
                            <table
                                class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                            >
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                        >
                                            Name
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                        >
                                            URL
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                        >
                                            Event
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                        >
                                            Status
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                        >
                                            Last Triggered
                                        </th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                        >
                                            Failures
                                        </th>
                                        <th
                                            class="px-6 py-3 text-right text-xs font-medium tracking-wider text-gray-500 uppercase dark:text-gray-400"
                                        >
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-900"
                                >
                                    <tr
                                        v-for="webhook in webhooks.data"
                                        :key="webhook.id"
                                    >
                                        <td
                                            class="px-6 py-4 text-sm font-medium whitespace-nowrap text-gray-900 dark:text-white"
                                        >
                                            {{ webhook.name }}
                                        </td>
                                        <td
                                            class="max-w-xs truncate px-6 py-4 text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            {{ webhook.url }}
                                        </td>
                                        <td
                                            class="px-6 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"
                                        >
                                            <span
                                                class="rounded bg-gray-100 px-2 py-1 text-xs dark:bg-gray-700"
                                            >
                                                {{ formatEvent(webhook.event) }}
                                            </span>
                                        </td>
                                        <td
                                            class="px-6 py-4 text-sm whitespace-nowrap"
                                        >
                                            <span
                                                :class="
                                                    webhook.is_active
                                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                        : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
                                                "
                                                class="rounded-full px-2 py-1 text-xs"
                                            >
                                                {{
                                                    webhook.is_active
                                                        ? 'Active'
                                                        : 'Inactive'
                                                }}
                                            </span>
                                        </td>
                                        <td
                                            class="px-6 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"
                                        >
                                            {{
                                                webhook.last_triggered_at ||
                                                'Never'
                                            }}
                                        </td>
                                        <td
                                            class="px-6 py-4 text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"
                                        >
                                            <span
                                                :class="
                                                    webhook.failure_count > 0
                                                        ? 'text-red-600 dark:text-red-400'
                                                        : ''
                                                "
                                                class="font-medium"
                                            >
                                                {{ webhook.failure_count }}
                                            </span>
                                        </td>
                                        <td
                                            class="space-x-2 px-6 py-4 text-right text-sm font-medium whitespace-nowrap"
                                        >
                                            <button
                                                @click="
                                                    sendTestWebhook(webhook.id)
                                                "
                                                :disabled="
                                                    testProcessing ===
                                                    webhook.id
                                                "
                                                class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                            >
                                                <span
                                                    class="inline-flex items-center gap-1.5"
                                                >
                                                    <PlayCircle
                                                        class="h-4 w-4"
                                                    />
                                                    <span>{{
                                                        testProcessing ===
                                                        webhook.id
                                                            ? 'Testing...'
                                                            : 'Test'
                                                    }}</span>
                                                </span>
                                            </button>
                                            <button
                                                @click="
                                                    openEditWebhookModal(
                                                        webhook,
                                                    )
                                                "
                                                class="text-blue-600 hover:text-blue-900 dark:text-blue-400"
                                            >
                                                <span
                                                    class="inline-flex items-center gap-1.5"
                                                >
                                                    <Pencil class="h-4 w-4" />
                                                    <span>Edit</span>
                                                </span>
                                            </button>
                                            <button
                                                @click="
                                                    deleteWebhook(webhook.id)
                                                "
                                                class="text-red-600 hover:text-red-900 dark:text-red-400"
                                            >
                                                <span
                                                    class="inline-flex items-center gap-1.5"
                                                >
                                                    <Trash2 class="h-4 w-4" />
                                                    <span>Delete</span>
                                                </span>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="webhooks.data.length === 0">
                                        <td
                                            colspan="7"
                                            class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                                        >
                                            No webhooks configured. Click "Add
                                            Webhook" to set up notifications.
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
                            v-if="
                                page.props.flash?.success &&
                                activeTab === 'import-export'
                            "
                            class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20"
                        >
                            <div class="flex items-start">
                                <svg
                                    class="mt-0.5 mr-3 h-5 w-5 text-green-600 dark:text-green-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                    ></path>
                                </svg>
                                <div class="flex-1">
                                    <h3
                                        class="text-sm font-medium text-green-800 dark:text-green-200"
                                    >
                                        Import Successful
                                    </h3>
                                    <p
                                        class="mt-1 text-sm text-green-700 dark:text-green-300"
                                    >
                                        {{ page.props.flash.success }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Error Message -->
                        <div
                            v-if="
                                page.props.flash?.error &&
                                activeTab === 'import-export'
                            "
                            class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20"
                        >
                            <div class="flex items-start">
                                <svg
                                    class="mt-0.5 mr-3 h-5 w-5 text-red-600 dark:text-red-400"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                                    ></path>
                                </svg>
                                <div class="flex-1">
                                    <h3
                                        class="text-sm font-medium text-red-800 dark:text-red-200"
                                    >
                                        Import Failed
                                    </h3>
                                    <p
                                        class="mt-1 text-sm text-red-700 dark:text-red-300"
                                    >
                                        {{ page.props.flash.error }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Export Section -->
                        <div class="space-y-6">
                            <div>
                                <h2 class="text-lg font-medium">
                                    Export Sources
                                </h2>
                                <p
                                    class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    Download all your team's sources as a JSON
                                    file. The export will include up to 1000
                                    sources with their configuration and tags.
                                </p>
                            </div>

                            <div
                                class="rounded-lg border border-gray-200 bg-gray-50 p-6 dark:border-gray-700 dark:bg-gray-800"
                            >
                                <div class="space-y-4">
                                    <div
                                        class="text-sm text-gray-600 dark:text-gray-400"
                                    >
                                        <p class="mb-2 font-medium">
                                            The export will include:
                                        </p>
                                        <ul
                                            class="ml-2 list-inside list-disc space-y-1"
                                        >
                                            <li>
                                                Internal name, type, and URL
                                            </li>
                                            <li>
                                                CSS selectors (for website
                                                sources)
                                            </li>
                                            <li>
                                                Keywords and monitoring interval
                                            </li>
                                            <li>
                                                Active status and notification
                                                settings
                                            </li>
                                            <li>Associated tags</li>
                                        </ul>
                                    </div>

                                    <Button
                                        @click="exportSources"
                                        :disabled="exportProcessing"
                                        class="w-full sm:w-auto"
                                    >
                                        {{
                                            exportProcessing
                                                ? 'Exporting...'
                                                : 'Export Sources'
                                        }}
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <div
                            class="border-t border-gray-200 dark:border-gray-700"
                        ></div>

                        <!-- Import Section -->
                        <div class="space-y-6">
                            <div>
                                <h2 class="text-lg font-medium">
                                    Import Sources
                                </h2>
                                <p
                                    class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                                >
                                    Upload a JSON file to import sources into
                                    your team. Sources with duplicate URLs will
                                    be skipped automatically.
                                </p>
                            </div>

                            <form
                                @submit.prevent="submitImport"
                                class="space-y-6"
                            >
                                <div
                                    class="rounded-lg border border-gray-200 bg-gray-50 p-6 dark:border-gray-700 dark:bg-gray-800"
                                >
                                    <div class="space-y-4">
                                        <div
                                            class="text-sm text-gray-600 dark:text-gray-400"
                                        >
                                            <p class="mb-2 font-medium">
                                                Import requirements:
                                            </p>
                                            <ul
                                                class="ml-2 list-inside list-disc space-y-1"
                                            >
                                                <li>
                                                    File must be in valid JSON
                                                    format
                                                </li>
                                                <li>
                                                    Maximum 1000 sources per
                                                    import
                                                </li>
                                                <li>Maximum file size: 10MB</li>
                                                <li>
                                                    Duplicate sources (same URL)
                                                    will be skipped
                                                </li>
                                                <li>
                                                    Tags will be created
                                                    automatically if they don't
                                                    exist
                                                </li>
                                            </ul>
                                        </div>

                                        <div class="space-y-2">
                                            <Label for="import_file"
                                                >Select JSON File</Label
                                            >
                                            <Input
                                                id="import_file"
                                                ref="fileInputRef"
                                                type="file"
                                                accept=".json,application/json"
                                                @change="handleFileSelect"
                                                :disabled="
                                                    importForm.processing
                                                "
                                            />
                                            <InputError
                                                :message="
                                                    importForm.errors.file
                                                "
                                            />
                                            <p
                                                v-if="importForm.file"
                                                class="text-sm text-gray-600 dark:text-gray-400"
                                            >
                                                Selected:
                                                {{ importForm.file.name }} ({{
                                                    (
                                                        importForm.file.size /
                                                        1024
                                                    ).toFixed(2)
                                                }}
                                                KB)
                                            </p>
                                        </div>

                                        <Button
                                            type="submit"
                                            :disabled="
                                                !importForm.file ||
                                                importForm.processing
                                            "
                                            class="w-full sm:w-auto"
                                        >
                                            {{
                                                importForm.processing
                                                    ? 'Importing...'
                                                    : 'Import Sources'
                                            }}
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
                    <DialogDescription>{{
                        modalDescription
                    }}</DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitWebhook" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="webhook_name">Name</Label>
                        <Input
                            id="webhook_name"
                            v-model="webhookForm.name"
                            type="text"
                            placeholder="E.g., Slack Notification"
                        />
                        <InputError :message="webhookForm.errors.name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="webhook_url">Webhook URL</Label>
                        <Input
                            id="webhook_url"
                            v-model="webhookForm.url"
                            type="url"
                            placeholder="https://example.com/webhook"
                        />
                        <InputError :message="webhookForm.errors.url" />
                    </div>

                    <div class="space-y-2">
                        <Label for="webhook_event">Event</Label>
                        <Select v-model="webhookForm.event">
                            <SelectTrigger>
                                <SelectValue placeholder="Select event" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="NEW_POSTS"
                                    >New Posts Found</SelectItem
                                >
                                <SelectItem value="HIGH_RELEVANCY_POST"
                                    >High Relevancy Post</SelectItem
                                >
                                <SelectItem value="CONTENT_GENERATED"
                                    >Content Generated</SelectItem
                                >
                            </SelectContent>
                        </Select>
                        <InputError :message="webhookForm.errors.event" />
                    </div>

                    <div class="space-y-2">
                        <Label for="webhook_secret">Secret (Optional)</Label>
                        <Input
                            id="webhook_secret"
                            v-model="webhookForm.secret"
                            type="text"
                            placeholder="Used for HMAC signature verification"
                        />
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            If provided, the webhook payload will be signed
                            using HMAC-SHA256 and included in the
                            X-Webhook-Signature header.
                        </p>
                        <InputError :message="webhookForm.errors.secret" />
                    </div>

                    <div class="flex items-center gap-2">
                        <Checkbox
                            id="webhook_is_active"
                            :default-value="webhookForm.is_active"
                            @update:model-value="webhookForm.is_active = $event"
                        />
                        <Label for="webhook_is_active" class="cursor-pointer"
                            >Active</Label
                        >
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showWebhookModal = false"
                            >Cancel</Button
                        >
                        <Button
                            type="submit"
                            :disabled="webhookForm.processing"
                        >
                            {{ submitButtonText }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

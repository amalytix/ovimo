<script setup lang="ts">
import { dashboard, login, register } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);
</script>

<template>
    <Head title="Ovimo - AI-Powered News Monitoring & Content Creation">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <div class="min-h-screen bg-white text-gray-900">
        <!-- Header -->
        <header class="border-b border-gray-200">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <div class="flex items-center">
                        <span class="text-2xl font-bold">Ovimo</span>
                    </div>
                    <nav class="hidden items-center gap-8 md:flex">
                        <a
                            href="#features"
                            class="text-sm font-medium text-gray-600 hover:text-gray-900"
                            >Features</a
                        >
                        <a
                            href="#how-it-works"
                            class="text-sm font-medium text-gray-600 hover:text-gray-900"
                            >How it Works</a
                        >
                        <a
                            href="#faq"
                            class="text-sm font-medium text-gray-600 hover:text-gray-900"
                            >FAQ</a
                        >
                    </nav>
                    <div class="flex items-center gap-4">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="dashboard()"
                            class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800"
                        >
                            Dashboard
                        </Link>
                        <template v-else>
                            <Link
                                :href="login()"
                                class="text-sm font-medium text-gray-600 hover:text-gray-900"
                            >
                                Log in
                            </Link>
                            <Link
                                v-if="canRegister"
                                :href="register()"
                                class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-800"
                            >
                                Register
                            </Link>
                        </template>
                    </div>
                </div>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="py-20 lg:py-32">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-3xl text-center">
                    <h1
                        class="text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl"
                    >
                        AI-Powered News Monitoring & Content Creation
                    </h1>
                    <p class="mt-6 text-lg leading-8 text-gray-600">
                        Monitor your favorite content sources, let AI summarize
                        and score relevancy, then transform discoveries into
                        your own thought leadership pieces.
                    </p>
                    <div class="mt-10 flex items-center justify-center gap-4">
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="rounded-md bg-gray-900 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-gray-800"
                        >
                            Get Started Free
                        </Link>
                        <a
                            href="#features"
                            class="rounded-md border border-gray-300 px-6 py-3 text-sm font-semibold text-gray-900 hover:bg-gray-50"
                        >
                            Learn More
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="border-t border-gray-200 py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">
                        Everything you need to stay informed and create content
                    </h2>
                    <p class="mt-4 text-lg text-gray-600">
                        Ovimo combines automated monitoring with AI-powered
                        insights to streamline your content workflow.
                    </p>
                </div>
                <div class="mt-16 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    <!-- Feature 1 -->
                    <div class="rounded-lg border border-gray-200 p-8">
                        <div
                            class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-gray-100"
                        >
                            <svg
                                class="h-6 w-6 text-gray-900"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                                />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold">
                            Automated Monitoring
                        </h3>
                        <p class="mt-2 text-gray-600">
                            Track RSS feeds and XML sitemaps automatically with
                            configurable intervals. Set it once and let Ovimo do
                            the work.
                        </p>
                    </div>
                    <!-- Feature 2 -->
                    <div class="rounded-lg border border-gray-200 p-8">
                        <div
                            class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-gray-100"
                        >
                            <svg
                                class="h-6 w-6 text-gray-900"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
                                />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold">AI Summarization</h3>
                        <p class="mt-2 text-gray-600">
                            GPT-5.1 powered summaries with intelligent relevancy
                            scoring. Quickly understand what matters to you.
                        </p>
                    </div>
                    <!-- Feature 3 -->
                    <div class="rounded-lg border border-gray-200 p-8">
                        <div
                            class="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-gray-100"
                        >
                            <svg
                                class="h-6 w-6 text-gray-900"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                />
                            </svg>
                        </div>
                        <h3 class="text-lg font-semibold">Content Creation</h3>
                        <p class="mt-2 text-gray-600">
                            Generate blog posts, LinkedIn posts, and YouTube
                            scripts using customizable AI prompts tailored to
                            your voice.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section
            id="how-it-works"
            class="border-t border-gray-200 bg-gray-50 py-20"
        >
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">
                        How It Works
                    </h2>
                    <p class="mt-4 text-lg text-gray-600">
                        From monitoring to content creation in four simple
                        steps.
                    </p>
                </div>
                <div class="mt-16 grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    <!-- Step 1 -->
                    <div class="text-center">
                        <div
                            class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border-2 border-gray-900 text-2xl font-bold"
                        >
                            1
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">
                            Add Your Sources
                        </h3>
                        <p class="mt-2 text-gray-600">
                            Configure RSS feeds or XML sitemaps with your
                            preferred monitoring intervals.
                        </p>
                    </div>
                    <!-- Step 2 -->
                    <div class="text-center">
                        <div
                            class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border-2 border-gray-900 text-2xl font-bold"
                        >
                            2
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">
                            Automatic Discovery
                        </h3>
                        <p class="mt-2 text-gray-600">
                            Ovimo checks your sources and discovers new posts
                            with built-in duplicate detection.
                        </p>
                    </div>
                    <!-- Step 3 -->
                    <div class="text-center">
                        <div
                            class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border-2 border-gray-900 text-2xl font-bold"
                        >
                            3
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">AI Analysis</h3>
                        <p class="mt-2 text-gray-600">
                            Each post is summarized and scored for relevancy
                            based on your team's interests.
                        </p>
                    </div>
                    <!-- Step 4 -->
                    <div class="text-center">
                        <div
                            class="mx-auto flex h-16 w-16 items-center justify-center rounded-full border-2 border-gray-900 text-2xl font-bold"
                        >
                            4
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">
                            Create Content
                        </h3>
                        <p class="mt-2 text-gray-600">
                            Select posts and use AI prompts to generate your own
                            content pieces.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section id="faq" class="border-t border-gray-200 py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">
                        Frequently Asked Questions
                    </h2>
                </div>
                <div class="mt-16 grid gap-8 sm:grid-cols-2">
                    <!-- FAQ 1 -->
                    <div>
                        <h3 class="text-lg font-semibold">
                            What sources can I monitor?
                        </h3>
                        <p class="mt-2 text-gray-600">
                            Currently, Ovimo supports websites with RSS feeds
                            and XML sitemaps. We're actively working on adding
                            support for social media platforms like LinkedIn,
                            Twitter, and YouTube.
                        </p>
                    </div>
                    <!-- FAQ 2 -->
                    <div>
                        <h3 class="text-lg font-semibold">
                            How does AI summarization work?
                        </h3>
                        <p class="mt-2 text-gray-600">
                            When new posts are discovered, Ovimo uses GPT-5.1 to
                            generate concise summaries and assign relevancy
                            scores (1-10) based on your team's defined
                            interests.
                        </p>
                    </div>
                    <!-- FAQ 3 -->
                    <div>
                        <h3 class="text-lg font-semibold">
                            Can I collaborate with my team?
                        </h3>
                        <p class="mt-2 text-gray-600">
                            Yes! Ovimo is built with multi-tenant architecture.
                            You can create teams, invite members, and share
                            sources, prompts, and content across your
                            organization.
                        </p>
                    </div>
                    <!-- FAQ 4 -->
                    <div>
                        <h3 class="text-lg font-semibold">
                            What content types can I create?
                        </h3>
                        <p class="mt-2 text-gray-600">
                            Ovimo supports generating blog posts, LinkedIn
                            posts, and YouTube video scripts. Each type can have
                            its own customized prompt templates.
                        </p>
                    </div>
                    <!-- FAQ 5 -->
                    <div>
                        <h3 class="text-lg font-semibold">
                            How are token limits handled?
                        </h3>
                        <p class="mt-2 text-gray-600">
                            Each user has 1 million tokens per month, and teams
                            have 10 million. Usage is tracked and displayed in
                            your dashboard. You'll receive warnings when
                            approaching limits.
                        </p>
                    </div>
                    <!-- FAQ 6 -->
                    <div>
                        <h3 class="text-lg font-semibold">
                            Can I customize AI prompts?
                        </h3>
                        <p class="mt-2 text-gray-600">
                            Absolutely! Create unlimited prompt templates with
                            placeholders for briefing text, source content, and
                            target language. Tailor the AI output to match your
                            brand voice.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="border-t border-gray-200 bg-gray-900 py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <h2
                        class="text-3xl font-bold tracking-tight text-white sm:text-4xl"
                    >
                        Start Monitoring Today
                    </h2>
                    <p class="mt-4 text-lg text-gray-300">
                        Join content creators who are staying ahead with
                        AI-powered insights.
                    </p>
                    <div class="mt-8">
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="inline-block rounded-md bg-white px-6 py-3 text-sm font-semibold text-gray-900 shadow-sm hover:bg-gray-100"
                        >
                            Create Your Free Account
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-gray-200 py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div
                    class="flex flex-col items-center justify-between gap-4 sm:flex-row"
                >
                    <p class="text-sm text-gray-600">
                        &copy; {{ new Date().getFullYear() }} Ovimo. All rights
                        reserved.
                    </p>
                    <div class="flex gap-6">
                        <Link
                            :href="login()"
                            class="text-sm text-gray-600 hover:text-gray-900"
                        >
                            Log in
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="text-sm text-gray-600 hover:text-gray-900"
                        >
                            Register
                        </Link>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>

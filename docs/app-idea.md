# Ovimo

## Overview

I want to build a news monitoring and content creation service called Ovimo. It is a Laravel based SaaS app.

Here is an example user story:

With the help of Ovimo the user monitors various websites, LinkedIn and Twitter profiles from different content creators. Ovimo checks those automatically each hour.

Suddenly Google launches a new feature and Ovimo finds posts from a LinkedIn Profile, a blog post from a website and a Twitter/X post about it.

The user sees those findings on a page in our system. The users understands that this is something he wants to write about himself, e.g. on his own LinkedIn profile page and/or with a blog post.

The user selects those 3 posts and decides to create a LinkedIn post himself about it.

Ovimo scrapes the data from those 3 posts.

Ovimo then uses a pre-defined prompt the user has created before. Ovimo adds the content from those 3 posts to the prompt and sends this e.g. to OpenAI to create a content piece from it.

The piece is then shown in Ovimo.

## Overall Navigation

The main navigation should have the following items:

- Sources
- Posts (default)
- Content
- Prompts
- Settings

## Sources

A "source" is e.g. a specific website or YouTube channel.

Each source can have multiple "posts". A post can be e.g. a blog post our YouTube video or LinkedIn Post.

Each source belongs to a team.

### Displaying sources

- The user should be able to view all existing sources
- We show a paginated table with the following columns:
  - Internal name
  - Type
  - Tags
  - URL or Profile Name or Handle (depends on type)
  - Number of posts in database
  - Date/Time last checked
  - Options:
    - edit source
    - remove source (with confirmation modal)
    - check source (immediate check of this source for new posts)

### Adding a news source

It should be possible for the user to add differnt type of news sources:

Here are some examples for news sources:

- Websites
  - Website has an RSS feed
  - Website has an XML sitemap
  - Websites without RSS or XML: Here we use a regex to identify new articles
- YouTube Channels
- LinkedIn Profiles
- Twitter Profiles
- Instgram Profiles
- ...

For each source the user can define at least the following:

- Monitoring interval
  - Every 10 minutes
  - Every x hours
  - Daily
  - ...
- Type
  - Website (RSS)
  - Website (XML Sitemap)
  - Website (Regex)
  - YouTube
  - LinkedIn
  - Twitter
  - Instgram
- URL, handle, username, ... (depends on Type)
- Internal name
- Active (Boolean flag)
- Notify (Boolean flag, the user can choose to get notified via email if new posts are found for this news)
- Tags (a source can have zero, one or multiple tags)
- ...

### Monitoring Sources

- The system should automatically monitor each source in the user defined interval.
- The system should store existing posts in a database.
- New posts should be added when found.
- Duplicates should be avoided.
- If new post is found we should try to summarize the content of the post via AI and store a short summary in our database
- For this we can use OpenAI. I will provide an OpenAI API key.

For each post we should store at least the following:

- Unique URI (e.g. an URL)
- Summary
- source_id
- created_at
- is_read (false by default). Here I would like to allow the user to mark a new found post as read or unread
- is_hidden (false by default): The user can hide posts.
- status (allowed values: "not_relevant", "create_content")
- ...

### Showing posts

It should be possible to show all posts with the following columns:

- Checkbox
- Source Internal Name
- Source Tags
- Post URI (linked to post itself)
- Date / Time first found
- AI Summary (show first 3 lines as clamp with option to expand)
- Status
- Options
  - Create content
  - Mark as read
  - Mark as hidden

Display options:

- If a post has is_read == false then show it in bold text

The following should be possible:

Filtering:

The user can filter the table / results by
  - One or more sources
  - One or mor tags
  - Keywords (match by URI OR AI summary)
  - Show by read / unread
  - Show also hidden posts

Actions:

The user can select multiple posts

For selected posts the user has the option to:
  - Hide posts
  - Mark as read posts
  - Create new content piece based on selected posts

For each post the user can with one click:

- Hide post
- Mark as read
- Toggle status
- Create content

## Content

Here Ovimo should help the user to create his own content based on found posts.

A content piece can be based on multiple posts found.

For one or more posts it should be possible to create a new content piece for the user.

### Create a new content piece

Each content piece should have the following elements / fields:

- Internal name
- Briefing Text (plaintext)
- Channel
  - Blog post
  - LinkedIn post
  - YouTube video script
- Target language (enum of "German" and "English")
- Status
  - Not started
  - Draft
  - Final
- Prompt (id of the prompt selected)
- Sources (contains multiple post_ids)
- Full text (plaintext)

On the posts page the user might have selected multiple posts around the same topic to create a new content piece.

The user selects a pre-defined prompt to create the content piece.

If the user decided to create a new content piece he is presented with a form showing the fields above.

After filling the form the user has 2 options:

- Save content piece
- Create content with AI

If the user chooses "create content with AI" then the following should happen:

- Get the prompt text based on the prompt_id
- Replace placeholders with actual content (if available)
  - Replace ### BRIEFING_TEXT ### with briefing text from content piece
  - Replace ### POSTS_CONTENT ### with the content from each post added as a source. For each source show a) the content and b) the URL to the post
  - Replace ### TARGET_LANGUAGE ### with selected value from content piece

Send the prompt to OpenAI.

Save the result in "Full text" field in the content piece.

Render the result in Markdown.

Allow the user to edit it.

### Commenting

It should be possible to add comments to each content piece. Similar to the app "Linear" which is an app for task management. Users can leave comments here to dicuss something.

Each content piece can have multiple comments.

## Prompts

The user can create and edit multiple prompts which help the user to create content using AI.

Each prompt should have the following fields:

- Internal name
- Channel
  - Blog post
  - LinkedIn post
  - YouTube video script
- Prompt Text

Each prompt text can have one or more placeholders which get replaced during later usage.

Manadatory Placeholders:

- ### BRIEFING_TEXT ###
- ### POSTS_CONTENT ### (containts the URL and content of multiple posts)
- ### TARGET_LANGUAGE ### (contains the language the new content piece should be in, e.g. "German")

We might add more placeholders over time.

## Notifications

A user should have the possibility to get notified, e.g. via eMail about new posts.

Ideally we group all posts from a single source in one notification.

The user can globally activate or deactivate notifications in his profile settings.

It should be possible to send each new post to a webhook URL via a POST request.

The POST body should containt the following data:

- Source Name
- Post URL
- Post Found At (date/time)

Use a queue to send those POST request. 3 retries max with 60s waiting time in between retries.

## FAQ

Reviewing the app idea document to identify areas that need clarification before drafting the product requirements document.

### Teams & User Management

1. What is a "team"? Is it multi-user workspaces, or single-user?

We use Laravel's built-in team feature. A user can be part of multiple teams.

A team can have multiple users.

2. What user roles/permissions are needed (admin, member, viewer)?

We need "admin" and "member" permissions.

The "admin" permission is later for the admin managing the full app, e.g. all members, check logs, ...

3. How do users join teams (invites, self-registration)?

We use Laravel's built-in team management here.

4. Is this single-user initially, or multi-user from the start?

This should be a multi-tenant app. So multi-user from the start.

### AI & OpenAI Integration

5. Which OpenAI model (GPT-4, GPT-3.5-turbo, etc.)?

Please use GPT 5.1 https://platform.openai.com/docs/models/gpt-5.1

API model name: gpt-5.1

Use it via the v1/responses API.

Ideally we are API agnostic and also support the Anthropic API later.

6. How to handle API failures, rate limits, and token limits?

Not in scope for the MVP.

7. Should AI summarization be optional per source or always on?

Allow the user to activate this if he adds a new source.

8. Cost management: per-user limits, team budgets, usage tracking?

Yes, we should log token usage per user and team.

Allow an admin to set limits on user and team limit.

The monthly limit for each user is 1.000.000 tokens
The monthly limit for each team is 10.000.000 tokens.

Make those limits configurable for new users and new teams.

### Content Scraping & Monitoring

9. How to access LinkedIn/Twitter/Instagram (official APIs, scraping, or both)?

In MVP we only support website with RSS feed or XML sitepmap.

10. Authentication: OAuth for social platforms, or scraping only?

Out of scope for the MVP.

11. How to handle scraping failures and retries?

Add retry feature with up to 3 retries.

12. Rate limiting: per-source limits, delays between checks?

Out of scope for the MVP.

13. Legal/compliance: robots.txt, terms of service, rate limits?

Out of scope for the MVP.

### Data Management

14. Post retention: keep forever, hide after X days, or user-configurable?

Allow the user to define this in the team's settings. It should be possible to hide posts after x days.

We should never delete any post to allow for duplicate detection.

15. Duplicate detection: exact URL match, or also similar content?

Exact URL match.

16. Post content storage: full content, summary only, or both?

For MVP: Summary only. Later we might also store the full content.

17. Storage limits: per user/team, or unlimited?

No limits

### Monitoring & Background Jobs

18. Monitoring system: Laravel queues, scheduled tasks, or both?

Probably both. The system architect should decide here. It should be possible to monitor each source based on the user defined intervals.

Those monitoring should happen in the background.

19. Failed job handling: retries, alerts, manual retry?

Use best practices here. Add detailed logging for the admin.

20. Immediate check: synchronous or queued?

Should also queued based. But let's have a separate queue for immediate checks to avoid longer waiting times if the regular queues are full.

### Posts Page
21. Default sorting: newest first, unread first, or user preference?

Newest first.

22. (deleted)

23. Status field: can users set it directly, or is it auto/managed?

The user can manage it.

24. Bulk actions UI: toolbar, dropdown, or both?

Let's do both.

### Content Pieces

25. Content list view: table, cards, or both?

Let's do a table first.

26. Content editor: markdown, rich text, or plain text?

Let's use Plaintext for now.

27. Export: copy to clipboard, download, or publish integrations?

Copy to clipboard for MVP.

28. Version history: track edits, or single version?

Create a data model which supports versioning later. But use a single version for now during MVP.

### Prompts

29. Prompt management: CRUD pages, or inline editing?

CRUD pages.

30. Prompt validation: validate placeholders, or free-form?

We should check for the available of mandatory prompts when creating or updating a prompt.

### Settings Page

31. What belongs in Settings: profile, team, API keys, notifications, preferences?

Settings define the teams' settings.

Here the user can choose the following:

- Notifications for this team are enabled or disabled for all team members
- Webhook URL where each new post found gets sent via a POST request

### MVP Scope

32. Which source types for MVP (all listed, or start with RSS/Website)?

We start with two types:

- Website (XML Sitemap)
- Website (RSS feed)


33. Which social platforms for MVP (LinkedIn, Twitter, Instagram, or start with one)?

Not in MVP.

34. Comments: in MVP or later?

Later.

### Notifications

35. Notify users of new posts? If yes: email, in-app, or both?

Only support webhooks for

### Technical Details
36. Regex for websites: user-provided patterns, or predefined templates?

User provided. e.g. a regex which finds all Headlines and Links to the post.

Do we need 2 regexes then?

However in MVP we do not use this source type yet.

37. Post content extraction: full HTML, cleaned text, or markdown?

Ideally we convert the content to markdown.

### 1. Teams & multi-tenancy

- Which Laravel team package should we use? (Jetstream, Spark, or a custom implementation?)

Let's use Jetstream.

- On registration, should users automatically create a team, or join an existing one?

By default, every registered user will belong to a "Personal" team.

- Should users be able to switch between teams in the UI?

Use Jetstream default UI here.

### 2. Post archiving
- When posts are hidden after X days, should they:

  - Still be visible in the UI with an "hidden" filter?

Yes. 

  - Be completely hidden but kept in the database?

  No.

- Should hidden posts still be searchable/filterable?

No.

### 3. Content piece status workflow

- Can users move between statuses freely (Not started → Draft → Final), or is there a required sequence?

They can change it freely.

- Can users revert from Final back to Draft?

Yes.

### 4. AI summarization failures
- If AI summarization fails (API error, timeout, etc.), should the post:
  - Still be saved without a summary?
  - Be saved with a placeholder like "Summary unavailable"?
  - Be retried later automatically?

It should be saved with a placeholder like "Summary unavailable".

### 5. Webhook payload
- Should the webhook POST body include:
  - The AI summary?
  - Source tags?
  - Post status?
- Or only Source Name, Post URL, and Post Found At?

Add the AI summary to the POST body as well.

### 6. Token usage visibility
- Should users see their current token usage (this month, remaining)?
- Or is this admin-only?

Show the current month's and previous month token usage as a number in 2 cards.

### 7. Content piece sources display
- In the content piece detail view, should linked posts show:
  - Just the post title/URL as links?
  - A preview with summary?
  - Full post details?

A preview with summary.

### 8. Post status values
- Are "not_relevant" and "create_content" the only statuses, or should there be more (e.g., "reviewing", "archived")?
- Can users add custom statuses, or is this a fixed enum?

In MVP only use "not_relevant" and "create_content"

### 9. Immediate check queue
- Should immediate checks use:
  - A separate queue connection (e.g., `immediate`)?
  - The same queue with higher priority?
  - A dedicated queue worker?

If we can use the same queue with higher priortiy this would be perfect.

### 10. Post retention/archiving
- When hiding posts after X days, should we:
  - Keep the summary and all metadata?
  - Keep only metadata (URL, dates, source)?
  - Keep everything but mark as hidden?

Keep everything but mark as hidden

### 11. OpenAI API key storage
- Should OpenAI API keys be:
  - Stored per team (each team has its own key)?
  - Stored globally in the application config?
  - Stored per user?

Stored globally in the application config. Later we might allow storing keys on team level. If a team key is available, then this should be use.d

### 12. Prompt placeholders
- Beyond the mandatory placeholders (`### BRIEFING_TEXT ###`, `### POSTS_CONTENT ###`, `### TARGET_LANGUAGE ###`), are there any optional placeholders to support in MVP?

Not yet.

- Should the system validate that all mandatory placeholders exist in the prompt before allowing content creation?

Yes. Please validate directly after prompt creation or update.

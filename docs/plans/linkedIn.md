# Share content feature

I would like to add a "Share content" feature supporting multiple platforms.

First platform should be LinkedIn (Post as a member).

It should be possible to add more platforms (Instagram, Twitter, etc.) in the future.

Please create a detailed plan to extend Ovimo with the following features:

- A user can authenticate a LinkedIn profile via OAuth 2.0
- A user can schedule a content piece incl. an image or PDF on LinkedIn

Here are more details how to integrate LinkedIn.

## LinkedIn

API to create a post: https://learn.microsoft.com/de-de/linkedin/consumer/integrations/self-serve/share-on-linkedin

The LinkedIn API uses OAuth 2.0 for member (user) authorization and API authentication. Applications must be authorized and authenticated before they can fetch data from LinkedIn or get access to LinkedIn member data.

## Authentication

API to authenticate a user: https://learn.microsoft.com/en-us/linkedin/shared/authentication/authentication?source=recommendations

Member Authorization Permissions are granted by a LinkedIn member to access members resources on LinkedIn. Permissions are authorization consents to access LinkedIn resources. The LinkedIn platform uses permissions to protect and prevent abuse of member data. Your application must have the appropriate permissions before it can access data. To see the list of permissions, descriptions and access details, refer to Getting Access to LinkedIn APIs page.

### Authorization Code Flow (3-legged OAuth)

https://learn.microsoft.com/en-us/linkedin/shared/authentication/authorization-code-flow?source=recommendations&tabs=HTTPS1

Authorized redirect URLs for the Ovimo app:

https://ovimo.ai/integrations/linkedin-member
https://ovimo.ai/integrations/linkedin-page

### .env variables

The following .env variables are defined in .env file:

LINKEDIN_CLIENT_ID=xxxx
LINKEDIN_CLIENT_SECRET=xxx
LINKEDIN_CLIENT_REDIRECT_URL=https://ovimo.ai/integrations/linkedin-member

## Code examples (Python)

## Full library from Postiz (Typescript)

https://raw.githubusercontent.com/gitroomhq/postiz-app/refs/heads/main/libraries/nestjs-libraries/src/integrations/social/linkedin.provider.ts

Idea is to fully recreate this functionality in PHP.

### Create Post

Example calls to create a post on LinkedIn. This requires a member-based token with the following
scopes (r_liteprofile, w_member_social), which is provided by the Sign in with LinkedIn and Share on LinkedIn
API products.

The steps include:
1. Fetching the authenticated member's profile to obtain the member's identifier (a person URN)
2. Create a post using /ugcPosts endpoint (legacy) or /posts endpoint (new)

https://raw.githubusercontent.com/linkedin-developers/linkedin-api-python-client/refs/heads/main/examples/create_posts.py

### Get Profile

https://raw.githubusercontent.com/linkedin-developers/linkedin-api-python-client/refs/heads/main/examples/get_profile.py

### OAuth Member

Example call to fetch the member profile for the authorized member.

The 3-legged member access token should include the 'r_liteprofile' scope, which
is part of the Sign In With LinkedIn API product.

This example illustrates a basic example of the oauth authorization code flow.

https://raw.githubusercontent.com/linkedin-developers/linkedin-api-python-client/refs/heads/main/examples/oauth_member_auth_redirect.py

## Outdated PHP library (8 years old, no longer maintained)

https://www.zoonman.com/projects/linkedin-client/

## Appendix

Explanation of the Postiz library:

Here’s what that `LinkedinProvider` class does in plain language:

---

### Overall role

`LinkedinProvider` is a NestJS “social provider” for LinkedIn that:

* Handles OAuth authentication for LinkedIn members.
* Resolves company pages / mentions.
* Uploads media (images, videos, PDFs) to LinkedIn using their new REST APIs.
* Creates:

  * Main LinkedIn posts (optionally with media or a PDF carousel),
  * Follow-up comments as “threaded” posts,
  * Reposts from other LinkedIn accounts.

It extends `SocialAbstract` and implements `SocialProvider`, and is identified as:

* `identifier = 'linkedin'`
* `name = 'LinkedIn'`
* Uses a restricted concurrency (`maxConcurrentJob = 2`) because of LinkedIn posting limits. ([GitHub][1])

---

### OAuth & member authentication

#### Scopes and configuration

It defines LinkedIn OAuth scopes:

```ts
scopes = [
  'openid',
  'profile',
  'w_member_social',
  'r_basicprofile',
  'rw_organization_admin',
  'w_organization_social',
  'r_organization_social',
];
```

Environment variables used:

* `LINKEDIN_CLIENT_ID`
* `LINKEDIN_CLIENT_SECRET`
* `FRONTEND_URL` (for redirect URI) ([GitHub][1])

#### `generateAuthUrl()`

* Generates a random `state` and `codeVerifier` using `makeId()`.
* Builds the LinkedIn authorization URL with:

  * `response_type=code`
  * `client_id`
  * `redirect_uri` = `<FRONTEND_URL>/integrations/social/linkedin`
  * `state` = random string
  * `scope` = joined scope list
* Returns `{ url, codeVerifier, state }` for the frontend to start the login flow. ([GitHub][1])

#### `authenticate({ code, codeVerifier, refresh? })`

1. Exchanges the authorization `code` for tokens at:

   * `https://www.linkedin.com/oauth/v2/accessToken`
2. Sends:

   * `grant_type=authorization_code`
   * `code`
   * `redirect_uri` (with `?refresh=...` if present)
   * client id/secret.
3. Gets back:

   * `access_token`
   * `expires_in`
   * `refresh_token`
   * `scope`
4. Validates that the returned `scope` includes all required scopes via `this.checkScopes`.
5. Fetches user info:

   * `https://api.linkedin.com/v2/userinfo` → id (`sub`), `name`, `picture`
   * `https://api.linkedin.com/v2/me` → `vanityName` (username / public handle)
6. Returns a normalized auth object:

   * `{ id, accessToken, refreshToken, expiresIn, name, picture, username: vanityName }`. ([GitHub][1])

#### `refreshToken(refresh_token)`

Very similar to `authenticate`, but:

* Uses `grant_type=refresh_token` to get a new access token and refresh token.
* Afterwards refetches `me` and `userinfo` to return updated metadata (id, name, picture, vanityName). ([GitHub][1])

---

### Company pages & mentions

#### `company(token, { url })`

* Takes a LinkedIn company page URL like
  `https://www.linkedin.com/company/some-company/`.
* Uses regex to extract the vanity part (`some-company`).
* Calls `https://api.linkedin.com/v2/organizations?q=vanityName&vanityName=<vanity>`.
* Returns the first organization as an “option”:
  `{ label: localizedName, value: '@[Name](urn:li:organization:<id>)' }`
  → this format is used for mention insertion in post text. ([GitHub][1])

#### `mention(token, { query })` (override)

* For a search query (company name/vanity), calls:

  * `https://api.linkedin.com/v2/organizations?q=vanityName&vanityName=<query>&projection=...`
* Maps results to:

  * `{ id, label: localizedName, image: logo URL }`
* Used for autocomplete / suggestions when tagging companies.

#### `mentionFormat(idOrHandle, name)`

* Produces mention text in the internal format:
  `@[Name](urn:li:organization:<idOrHandle>)`.

---

### Text sanitization

#### `fixText(text)`

* Keeps organization mention tokens like `@[Name](urn:li:organization:123)` intact.
* Splits the text around those mentions, then escapes special characters (, >, #, ~, _, |, [ ], *, (, ), { }, @, etc.) in the non-mention parts.
* Reassembles the string so:

  * Mentions stay valid for LinkedIn.
  * Everything else is safely escaped for markdown / LinkedIn. ([GitHub][1])

---

### Media preparation & upload

Media supported:

* Images
* Videos (`.mp4`)
* PDFs (for “carousel” style posts)

#### `uploadPicture(fileName, accessToken, personId, picture, type)`

1. Determines endpoint:

   * If filename includes `mp4` → `/rest/videos`
   * If filename includes `pdf` → `/rest/documents`
   * Else → `/rest/images`
2. Calls `.../rest/{endpoint}?action=initializeUpload` with:

   * `owner` = `urn:li:person:{id}` or `urn:li:organization:{id}`
   * For videos, also sends `fileSizeBytes`, and flags for captions/thumbnail.
3. Pulls out:

   * `uploadUrl` or `uploadInstructions[0].uploadUrl`
   * The resulting `image` / `video` / `document` URN.
4. Uploads the file in 2 MB chunks via `PUT`, collecting `etag` headers.
5. If it’s a video, calls:

   * `https://api.linkedin.com/rest/videos?action=finalizeUpload` with the `video` URN and the collected `etags`.
6. Returns the final media URN (`video || image || document`). ([GitHub][1])

#### `prepareMediaBuffer(mediaUrl)`

* If it’s a video (`.mp4` in URL):

  * Reads the bytes and returns them as a raw `Buffer`.
* If it’s an image:

  * Fetches it, uses `sharp` to:

    * Handle GIF (`animated` flag)
    * Convert to JPEG
    * Resize to width 1000
  * Returns the processed JPEG buffer.

#### `processMediaForPosts(postDetails, accessToken, personId, type)`

* Iterates over all `postDetails` and their `media`.
* For each media:

  * If it already has a `buffer` (e.g. from PDF generation), use that.
  * Otherwise call `prepareMediaBuffer()`.
  * Upload via `uploadPicture()` and collect `(postId → [mediaId...])`.
* Returns a mapping: `{ [postId]: mediaUrn[] }`.

#### PDF carousel conversion

##### `convertImagesToPdfCarousel(postDetails, firstPost)`

* Collects all image media (`.jpg`, `.jpeg`, `.png`) from all posts.
* If none → returns original posts.
* Otherwise:

  1. Reads all images into buffers, gets their dimensions via `sharp`.
  2. Uses the first image’s dimensions as page size.
  3. Uses `image-to-pdf` to stitch all buffers into a single PDF stream.
  4. Converts the stream into a `Buffer` (`streamToBuffer()`).
  5. Creates:

     * First post: media replaced with one fake PDF media object (`path: "carousel.pdf", buffer: pdfBuffer`).
     * All other posts: media cleared.
* Returns the modified post list.

This supports a “post_as_images_carousel” setting: many images → one PDF “document” post.

---

### Building and sending LinkedIn posts

#### `buildPostContent(isPdf, mediaIds)`

* If no media IDs → returns `{}`.
* If one media ID:

  * If `isPdf`: content with `media` and a `title: 'slides.pdf'`.
  * Else: basic single media object.
* If many media IDs:

  * Builds a `multiImage` structure: `{ content: { multiImage: { images: [ { id }, ... ] } } }`.

#### `createLinkedInPostPayload(id, type, message, mediaIds, isPdf)`

* Builds the full payload for posts on `rest/posts`:

  * `author`: `urn:li:person:{id}` or `urn:li:organization:{id}`
  * `commentary`: `fixText(message)`
  * `visibility`: `'PUBLIC'`
  * `distribution`: main feed, no specific targets
  * `content`: from `buildPostContent(...)`
  * `lifecycleState`: `'PUBLISHED'`
  * `isReshareDisabledByAuthor`: `false`.

#### `createMainPost(...)`

* Sends `POST` to `https://api.linkedin.com/rest/posts` with the payload and correct headers (`LinkedIn-Version`, `X-Restli-Protocol-Version`, etc.).
* Checks for HTTP 200 / 201.
* Returns the `x-restli-id` header (LinkedIn’s post URN/path).

#### `createCommentPost(...)`

* Sends `POST` to:

  * `https://api.linkedin.com/v2/socialActions/{parentPostId}/comments`
* Body includes:

  * `actor`: person/organization URN
  * `object`: `parentPostId`
  * `message.text`: `fixText(post.message)`
* Returns the created comment object’s `object` field (comment ID / URN).

#### `createPostResponse(postId, originalPostId, isMainPost)`

* Normalizes LinkedIn IDs into the app’s `PostResponse` structure:

  * For main posts: `releaseURL` uses `https://www.linkedin.com/feed/update/<postId>`
  * For comments: `https://www.linkedin.com/embed/feed/update/<postId>`.
* Always returns `{ status: 'posted', postId, id: originalPostId, releaseURL }`.

#### `post(id, accessToken, postDetails, integration, type = 'personal')`

This is the main high-level method the app calls.

1. Stores original `postDetails`, takes `firstPost`.
2. If `firstPost.settings?.post_as_images_carousel`:

   * Calls `convertImagesToPdfCarousel()` → turns all images into one PDF on the first post.
3. Runs `processMediaForPosts()` on the (possibly modified) posts → gets mapping of postId → mediaUrns.
4. For the first (main) post:

   * Collects its media URNs.
   * Calls `createMainPost()` → gets `mainPostId`.
   * Starts `responses` with a “main post” `PostResponse`.
5. For each remaining post:

   * Creates a comment on the main post via `createCommentPost()`.
   * Adds a secondary `PostResponse` (treated as comments/replies).
6. Returns the `PostResponse[]` array for all created LinkedIn items.

Effectively: first entry becomes the main LinkedIn post, remaining entries are comments in a thread, possibly with the images merged into a single PDF carousel post.

---

### Reposts from other accounts

#### `repostPostUsers(...)` (decorated with `@PostPlug`)

* Described as “Add Re-posters”.

* For a different LinkedIn integration (`integration`), it:

  * Creates a new post on `/rest/posts` with:

    * `author` = that integration’s account (person or organization URN),
    * `reshareContext.parent = postId` (the original post’s ID),
    * Empty `commentary`.
  * Uses `integration.token` as bearer token.

* Essentially: automates “reshare this post from additional LinkedIn accounts”.

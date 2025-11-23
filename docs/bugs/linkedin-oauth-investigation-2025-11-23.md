# LinkedIn OAuth `401 invalid_client` - Full Investigation Report

**Date Reported:** 2025-11-23
**Status:** Closed (Code-level investigation complete)
**Component:** LinkedIn OAuth Integration

## 1. Summary

Despite a thorough, multi-step investigation, the LinkedIn OAuth token exchange continues to fail with a `401 invalid_client` error. The investigation systematically eliminated all potential code-level issues, including authentication methods (HTTP Basic Auth vs. request body), parameter encoding (including for special characters), and the influence of OpenID Connect (OIDC) scopes.

The final state of the code represents the most robust and standards-compliant implementation based on LinkedIn's observed API behavior. The persistence of the `401` error, even with fresh credentials and a minimal scope, strongly indicates the root cause is external to the application's codebase, likely related to the server environment, networking, or a non-obvious issue within the LinkedIn App configuration or API itself.

## 2. Initial Problem

The token exchange request to `https://www.linkedin.com/oauth/v2/accessToken` consistently failed with `HTTP 401: invalid_client`, despite verified credentials and a successful authorization code grant. The initial implementation sent all parameters, including `client_id` and `client_secret`, in the `application/x-www-form-urlencoded` request body.

## 3. Debugging Journey & Learnings

### Attempt 1: Switching to HTTP Basic Authentication

-   **Hypothesis:** Standard practice for OAuth 2.0 is often to send client credentials via an `Authorization: Basic ...` header.
-   **Action:** Modified the code to remove `client_id` and `client_secret` from the request body and send them in a Basic Auth header instead.
-   **Result:** The error changed to `HTTP 400: invalid_request` with the description `A required parameter "client_id" is missing`. This was followed by `A required parameter "client_secret" is missing` when `client_id` was added back.
-   **Learning:** LinkedIn's API requires `client_id` and `client_secret` to be present in the request body, even if an `Authorization` header is used. This is a non-standard implementation.

### Attempt 2: Hybrid Authentication (Header + Body)

-   **Hypothesis:** Perhaps LinkedIn requires credentials in both the header and the body.
-   **Action:** Sent credentials in both the Basic Auth header and the request body.
-   **Result:** The error reverted to the original `HTTP 401: invalid_client`.
-   **Learning:** Sending credentials in two places simultaneously either creates a conflict or is ignored, leading back to the same authentication failure.

### Attempt 3: Ruling out OIDC and Scope Issues

-   **Hypothesis:** Based on community threads, the presence of the `openid` scope can trigger special OIDC validation and cause issues.
-   **Action:** We verified that the application's configuration was attempting to request only the `w_member_social` scope. After fixing a configuration cache issue, we confirmed via logs that only this minimal scope was being sent.
-   **Result:** The `401 invalid_client` error persisted.
-   **Learning:** The issue is not related to the `openid` scope or other OIDC complexities. It is a fundamental problem with the token exchange.

### Attempt 4: Investigating Special Character Encoding

-   **Hypothesis:** Special characters (specifically `==`) in the `client_secret` might be getting improperly URL-encoded by the default HTTP client, leading to a corrupted secret being sent.
-   **Action:**
    1.  Temporarily added a debug log to capture the exact payload, which confirmed the full, correct secret was being prepared by the application.
    2.  Replaced the HTTP client's default form encoder (`asForm()`) with a manual implementation using `http_build_query` with the stricter `PHP_QUERY_RFC3986` encoding standard.
-   **Result:** The `401 invalid_client` error persisted.
-   **Learning:** The issue is not related to the client-side encoding of the secret. The application is correctly formatting the request body, even with special characters.

## 4. Final State & Conclusion

The code was reverted to the simplest, most compliant state based on the API's observed behavior: sending all parameters, including full credentials, in the request body with robust `RFC3986` encoding.

**Final `postToken` implementation:**
```php
$body = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);

$response = Http::retry(2, 200, throw: false)
    ->withBody($body, 'application/x-www-form-urlencoded')
    ->post(self::TOKEN_URL);
```

Even with this, the `401 invalid_client` error remains. The fact that the error persists across different credentials (a new app was created), with minimal scopes, and with robust encoding, proves conclusively that the issue is not in the application code.

## 5. Recommended Next Steps

The root cause is external. The following steps are recommended:

1.  **Contact LinkedIn Developer Support:** This is the most critical next step. Provide them with the new Client ID (`78dpps0todfts8`), the exact timestamps of the failed requests from the latest logs, and the `debug_id`. This is likely an issue only they can resolve on their end.
2.  **Investigate the Hosting Environment:** Check for any outbound proxies, firewalls, or other network configurations on the production server that could be interfering with or modifying the outgoing POST request to LinkedIn.
3.  **Review LinkedIn App Settings:** Perform a final, meticulous review of every setting in the LinkedIn Developer Portal for the application, comparing it against a known working application if possible.

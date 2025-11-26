<?php

namespace App\Console\Commands;

use App\Services\LinkedIn\LinkedInOAuthService;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Console\Command;

class LinkedInOAuthDebug extends Command
{
    protected $signature = 'linkedin:debug
                            {action=auth : Action to perform: auth, exchange, curl, test-curl}
                            {--code= : Authorization code from LinkedIn callback}
                            {--verifier= : PKCE code verifier used during authorization}';

    protected $description = 'Debug LinkedIn OAuth token exchange - captures exact HTTP requests';

    private const TOKEN_URL = 'https://www.linkedin.com/oauth/v2/accessToken';

    public function __construct(private readonly LinkedInOAuthService $linkedInOAuthService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        return match ($this->argument('action')) {
            'auth' => $this->generateAuthUrl(),
            'exchange' => $this->exchangeCode(),
            'curl' => $this->generateCurlCommand(),
            'test-curl' => $this->testWithCurl(),
            'raw' => $this->exchangeWithRawSecret(),
            default => $this->error('Unknown action. Use: auth, exchange, curl, test-curl, or raw') ?? 1,
        };
    }

    private function generateAuthUrl(): int
    {
        $this->info('Generating LinkedIn OAuth authorization URL...');
        $this->newLine();

        $verifier = $this->linkedInOAuthService->generateCodeVerifier();
        $state = $this->linkedInOAuthService->generateState();

        $authUrl = $this->linkedInOAuthService->generateAuthUrl($state, $verifier);

        $this->warn('SAVE THIS VERIFIER - you will need it for the exchange step:');
        $this->line($verifier);
        $this->newLine();

        $this->info('Authorization URL:');
        $this->line($authUrl);
        $this->newLine();

        $this->info('Next steps:');
        $this->line('1. Open the URL above in your browser');
        $this->line('2. Authorize the application');
        $this->line('3. Copy the "code" parameter from the redirect URL');
        $this->line('4. Run: php artisan linkedin:debug exchange --code="YOUR_CODE" --verifier="'.$verifier.'"');

        return 0;
    }

    private function exchangeCode(): int
    {
        $code = $this->option('code');
        $verifier = $this->option('verifier');

        if (! $code || ! $verifier) {
            $this->error('Both --code and --verifier are required for exchange action');

            return 1;
        }

        $this->info('Attempting token exchange with request capture...');
        $this->newLine();

        $clientId = config('services.linkedin.client_id');
        $clientSecret = config('services.linkedin.client_secret');
        $redirectUri = config('services.linkedin.redirect_uri');

        // Display credential info (masked)
        $this->table(['Parameter', 'Value'], [
            ['client_id', $clientId],
            ['client_secret', str_repeat('*', strlen($clientSecret) - 4).substr($clientSecret, -4)],
            ['client_secret_length', strlen($clientSecret)],
            ['redirect_uri', $redirectUri],
            ['code_length', strlen($code)],
            ['verifier_length', strlen($verifier)],
        ]);
        $this->newLine();

        // Check for whitespace issues
        if ($clientSecret !== trim($clientSecret)) {
            $this->error('WARNING: Client secret has leading/trailing whitespace!');
        }

        // Hex dump of secret for invisible character detection
        $this->warn('Client secret hex dump (for invisible char detection):');
        $this->line(bin2hex($clientSecret));
        $this->newLine();

        // Build payload
        $payload = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code_verifier' => $verifier,
        ];

        // Build and show the exact form body
        $formBody = http_build_query($payload, '', '&', PHP_QUERY_RFC3986);
        $this->info('Exact form body that will be sent:');
        $this->line($formBody);
        $this->newLine();

        // Capture the request/response using Guzzle middleware
        $this->info('Sending request with full capture...');
        $this->newLine();

        $container = [];
        $history = Middleware::history($container);

        $stack = HandlerStack::create();
        $stack->push($history);

        $client = new Client(['handler' => $stack]);

        try {
            $response = $client->post(self::TOKEN_URL, [
                'form_params' => $payload,
                'http_errors' => false,
            ]);

            // Show captured request details
            if (! empty($container)) {
                $transaction = $container[0];
                /** @var Request $request */
                $request = $transaction['request'];

                $this->warn('=== CAPTURED REQUEST ===');
                $this->line('Method: '.$request->getMethod());
                $this->line('URI: '.(string) $request->getUri());
                $this->newLine();

                $this->line('Headers:');
                foreach ($request->getHeaders() as $name => $values) {
                    $this->line("  $name: ".implode(', ', $values));
                }
                $this->newLine();

                $this->line('Body:');
                $this->line((string) $request->getBody());
                $this->newLine();
            }

            // Show response
            $this->warn('=== RESPONSE ===');
            $this->line('Status: '.$response->getStatusCode().' '.$response->getReasonPhrase());
            $this->newLine();

            $this->line('Response Headers:');
            foreach ($response->getHeaders() as $name => $values) {
                $this->line("  $name: ".implode(', ', $values));
            }
            $this->newLine();

            $body = (string) $response->getBody();
            $this->line('Response Body:');
            $this->line($body);
            $this->newLine();

            if ($response->getStatusCode() === 200) {
                $this->info('SUCCESS! Token exchange completed.');
                $json = json_decode($body, true);
                if (isset($json['access_token'])) {
                    $this->info('Access token received: '.substr($json['access_token'], 0, 20).'...');
                }
            } else {
                $this->error('FAILED with status '.$response->getStatusCode());
            }

        } catch (\Throwable $e) {
            $this->error('Exception: '.$e->getMessage());

            return 1;
        }

        return 0;
    }

    private function generateCurlCommand(): int
    {
        $code = $this->option('code');
        $verifier = $this->option('verifier');

        if (! $code || ! $verifier) {
            $this->error('Both --code and --verifier are required for curl action');

            return 1;
        }

        $clientId = config('services.linkedin.client_id');
        $clientSecret = config('services.linkedin.client_secret');
        $redirectUri = config('services.linkedin.redirect_uri');

        $this->info('Copy and run this curl command:');
        $this->newLine();

        // Build curl command with proper escaping
        $curl = sprintf(
            "curl -v -X POST '%s' \\\n".
            "  -H 'Content-Type: application/x-www-form-urlencoded' \\\n".
            "  -d 'grant_type=authorization_code' \\\n".
            "  -d 'code=%s' \\\n".
            "  -d 'redirect_uri=%s' \\\n".
            "  -d 'client_id=%s' \\\n".
            "  -d 'client_secret=%s' \\\n".
            "  -d 'code_verifier=%s'",
            self::TOKEN_URL,
            $code,
            rawurlencode($redirectUri),
            $clientId,
            $clientSecret,
            $verifier
        );

        $this->line($curl);

        return 0;
    }

    private function testWithCurl(): int
    {
        $code = $this->option('code');
        $verifier = $this->option('verifier');

        if (! $code || ! $verifier) {
            $this->error('Both --code and --verifier are required for test-curl action');

            return 1;
        }

        $clientId = config('services.linkedin.client_id');
        $clientSecret = config('services.linkedin.client_secret');
        $redirectUri = config('services.linkedin.redirect_uri');

        $this->info('Executing curl command directly from PHP...');
        $this->newLine();

        // Build form data
        $postData = http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code_verifier' => $verifier,
        ], '', '&', PHP_QUERY_RFC3986);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::TOKEN_URL,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_VERBOSE => true,
            CURLOPT_STDERR => fopen('php://temp', 'w+'),
            CURLOPT_HEADER => true,
        ]);

        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Get verbose output
        $verboseHandle = curl_getinfo($ch, CURLINFO_PRIVATE);
        rewind(curl_getinfo($ch, CURLINFO_PRIVATE) ?: fopen('php://temp', 'r'));

        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        curl_close($ch);

        $this->warn('=== CURL RESPONSE ===');
        $this->line('HTTP Status: '.$httpCode);
        $this->newLine();

        $this->line('Response Headers:');
        $this->line($headers);

        $this->line('Response Body:');
        $this->line($body);
        $this->newLine();

        if ($httpCode === 200) {
            $this->info('SUCCESS via curl!');
        } else {
            $this->error('FAILED via curl with status '.$httpCode);
        }

        return 0;
    }

    /**
     * Test with different encoding strategies to find what LinkedIn actually expects
     */
    private function exchangeWithRawSecret(): int
    {
        $code = $this->option('code');
        $verifier = $this->option('verifier');

        if (! $code || ! $verifier) {
            $this->error('Both --code and --verifier are required');

            return 1;
        }

        $clientId = config('services.linkedin.client_id');
        $clientSecret = config('services.linkedin.client_secret');
        $redirectUri = config('services.linkedin.redirect_uri');

        $this->info('Testing different encoding strategies...');
        $this->newLine();

        // Strategy 1: Use Guzzle's default form_params (no manual encoding)
        $this->warn('=== Strategy 1: Guzzle form_params (default) ===');
        $payload = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code_verifier' => $verifier,
        ];

        $client = new Client;
        $response = $client->post(self::TOKEN_URL, [
            'form_params' => $payload,
            'http_errors' => false,
        ]);

        $this->line('Status: '.$response->getStatusCode());
        $this->line('Body: '.(string) $response->getBody());
        $this->newLine();

        if ($response->getStatusCode() === 200) {
            $this->info('SUCCESS with Strategy 1!');

            return 0;
        }

        // Strategy 2: Don't encode the = signs in the secret
        $this->warn('=== Strategy 2: Custom encoding (preserve = in secret) ===');

        // Build body manually, encoding everything except = in the secret
        $parts = [
            'grant_type=authorization_code',
            'code='.rawurlencode($code),
            'redirect_uri='.rawurlencode($redirectUri),
            'client_id='.rawurlencode($clientId),
            'client_secret='.$clientSecret, // NOT encoded
            'code_verifier='.rawurlencode($verifier),
        ];
        $rawBody = implode('&', $parts);

        $this->line('Body: '.$rawBody);

        $response = $client->post(self::TOKEN_URL, [
            'body' => $rawBody,
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'http_errors' => false,
        ]);

        $this->line('Status: '.$response->getStatusCode());
        $this->line('Body: '.(string) $response->getBody());
        $this->newLine();

        if ($response->getStatusCode() === 200) {
            $this->info('SUCCESS with Strategy 2!');

            return 0;
        }

        // Strategy 3: Try without PKCE at all
        $this->warn('=== Strategy 3: Without PKCE (code_verifier) ===');

        $noPkcePayload = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];

        $response = $client->post(self::TOKEN_URL, [
            'form_params' => $noPkcePayload,
            'http_errors' => false,
        ]);

        $this->line('Status: '.$response->getStatusCode());
        $this->line('Body: '.(string) $response->getBody());
        $this->newLine();

        if ($response->getStatusCode() === 200) {
            $this->info('SUCCESS with Strategy 3 (no PKCE)!');

            return 0;
        }

        // Strategy 4: HTTP Basic Auth header (without credentials in body)
        $this->warn('=== Strategy 4: HTTP Basic Auth header ===');

        $basicAuth = base64_encode($clientId.':'.$clientSecret);
        $this->line('Authorization: Basic '.$basicAuth);

        $basicPayload = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'code_verifier' => $verifier,
        ];

        $response = $client->post(self::TOKEN_URL, [
            'form_params' => $basicPayload,
            'headers' => [
                'Authorization' => 'Basic '.$basicAuth,
            ],
            'http_errors' => false,
        ]);

        $this->line('Status: '.$response->getStatusCode());
        $this->line('Body: '.(string) $response->getBody());
        $this->newLine();

        if ($response->getStatusCode() === 200) {
            $this->info('SUCCESS with Strategy 4 (Basic Auth)!');

            return 0;
        }

        // Strategy 5: Basic Auth header WITH credentials also in body (hybrid)
        $this->warn('=== Strategy 5: Basic Auth + Body credentials ===');

        $response = $client->post(self::TOKEN_URL, [
            'form_params' => $payload, // Full payload with client_id and client_secret
            'headers' => [
                'Authorization' => 'Basic '.$basicAuth,
            ],
            'http_errors' => false,
        ]);

        $this->line('Status: '.$response->getStatusCode());
        $this->line('Body: '.(string) $response->getBody());
        $this->newLine();

        if ($response->getStatusCode() === 200) {
            $this->info('SUCCESS with Strategy 5 (Basic Auth + Body)!');

            return 0;
        }

        $this->error('All strategies failed.');
        $this->newLine();
        $this->warn('Possible causes:');
        $this->line('1. Client secret is incorrect - verify in LinkedIn Developer Portal');
        $this->line('2. App needs verification/approval in LinkedIn Developer Portal');
        $this->line('3. Required Products not enabled: "Share on LinkedIn", "Sign In with LinkedIn"');
        $this->line('4. App is in restricted/suspended state');
        $this->line('5. LinkedIn API temporary issue');

        return 1;
    }
}

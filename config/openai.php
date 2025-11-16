<?php

return [
    'api_key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-4o'),
    'default_user_token_limit' => (int) env('DEFAULT_USER_TOKEN_LIMIT', 1000000),
    'default_team_token_limit' => (int) env('DEFAULT_TEAM_TOKEN_LIMIT', 10000000),
];

<?php

namespace App\Actions\Fortify;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'monthly_token_limit' => config('openai.default_user_token_limit'),
            ]);

            $team = Team::create([
                'name' => 'Personal',
                'owner_id' => $user->id,
                'monthly_token_limit' => config('openai.default_team_token_limit'),
            ]);

            $team->users()->attach($user->id, ['role' => 'admin']);

            $user->update(['current_team_id' => $team->id]);

            return $user;
        });
    }
}

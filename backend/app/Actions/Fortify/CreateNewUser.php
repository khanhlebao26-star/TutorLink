<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use App\Http\Requests\RegisterRequest;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        $request = RegisterRequest::create('/', 'POST', $input);

        $data = Validator::make(
            $input,
            $request->rules(),
            $request->messages(),
        )->validate();

        return User::create([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'password_hash' => $data['password'],
            'role' => $data['role'],
            'status' => 'active',
            'date_of_birth' => $data['date_of_birth'],
            'terms_version' => $data['terms_version'] ?? null,
        ]);
    }
}
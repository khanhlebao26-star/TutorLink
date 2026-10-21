<?php

namespace App\Actions\Fortify;

use App\Http\Requests\UpdateMeRequest;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        $data = Validator::make(
            $input,
            (new UpdateMeRequest)->rules(),
        )->validate();

        $user->update($data);
    }
}

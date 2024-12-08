<?php

namespace App\Http\ValidationRules\Turnstile;


use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

class Turnstile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => config('ninja.cloudflare.turnstile.secret'),
            'response' => $value,
            'remoteip' => request()->ip(),
        ]);        

        $data = $response->json();

        if($data['success']){

        }
        else {
            $fail("Captcha failed");
        }


    }

}

<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PatientAuthController extends Controller
{
    public function showLoginForm(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user && $this->userIsPatient($user)) {
            return redirect()->route('patient.portal');
        }

        return view('patient-portal.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required'    => 'Ingresa tu correo electrónico.',
            'email.email'       => 'Escribe un correo electrónico válido.',
            'password.required' => 'Ingresa tu contraseña.',
        ]);

        $email = mb_strtolower(trim((string) $credentials['email']));
        $remember = $request->boolean('remember');

        if (! Auth::attempt(['email' => $email, 'password' => $credentials['password']], $remember)) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no coinciden con nuestros registros.',
            ]);
        }

        $request->session()->regenerate();

        $user = $request->user();

        if (! $user || ! $this->userIsPatient($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'Este acceso es exclusivo para cuentas de paciente.',
            ]);
        }

        return redirect()->intended(route('patient.portal'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('patient.login');
    }

    private function userIsPatient(mixed $user): bool
    {
        if (! is_object($user)) {
            return false;
        }

        if (method_exists($user, 'isPatient') && $user->isPatient()) {
            return true;
        }

        $role = $user->role ?? null;

        if ($role instanceof UserRole) {
            return $role === UserRole::Patient;
        }

        return UserRole::tryFromFlexible(is_string($role) ? $role : null) === UserRole::Patient;
    }
}
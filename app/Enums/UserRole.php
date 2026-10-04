<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel, HasColor
{
    case Admin = 'administrador';
    case Agent = 'agente';
    case Marketing = 'marketing';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Agent => 'Agente Comercial',
            self::Marketing => 'Analista de Marketing',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Agent => 'info',
            self::Marketing => 'warning',
        };
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function isAgent(): bool
    {
        return $this === self::Agent;
    }

    public function isMarketing(): bool
    {
        return $this === self::Marketing;
    }
}
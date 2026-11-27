<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel, HasColor
{
    case Admin = 'administrador';
    case ZoneManager = 'gerente_zona';
    case Agent = 'agente';
    case Therapist = 'terapeuta';
    case Marketing = 'marketing';
    case EegTechnician = 'eeg_technician';
    case Patient = 'paciente';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::ZoneManager => 'Gerente de Zona / Sucursales',
            self::Agent => 'Agente Comercial',
            self::Therapist => 'Terapeuta',
            self::Marketing => 'Analista de Marketing',
            self::EegTechnician => 'Técnico de EEG',
            self::Patient => 'Paciente',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Admin => 'danger',
            self::ZoneManager => 'primary',
            self::Agent => 'info',
            self::Therapist => 'success',
            self::Marketing => 'warning',
            self::EegTechnician => 'gray',
            self::Patient => 'teal',
        };
    }

    public static function tryFromFlexible(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $normalized = strtolower(trim($value));

        if ($enum = self::tryFrom($normalized)) {
            return $enum;
        }

        return match ($normalized) {
            'admin', 'superadmin', 'super_admin' => self::Admin,
            'zone_manager', 'manager', 'gerente', 'regional_manager', 'clinic_manager' => self::ZoneManager,
            'agent', 'sales' => self::Agent,
            'therapist', 'clinician' => self::Therapist,
            'marketing', 'mkt' => self::Marketing,
            'eeg_technician', 'eeg', 'tecnico_eeg', 'técnico_eeg' => self::EegTechnician,
            'patient', 'paciente' => self::Patient,
            default => null,
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function isZoneManager(): bool
    {
        return $this === self::ZoneManager;
    }

    public function isAgent(): bool
    {
        return $this === self::Agent;
    }

    public function isTherapist(): bool
    {
        return $this === self::Therapist;
    }

    public function isMarketing(): bool
    {
        return $this === self::Marketing;
    }

    public function isEegTechnician(): bool
    {
        return $this === self::EegTechnician;
    }

    public function isPatient(): bool
    {
        return $this === self::Patient;
    }

    /**
     * Acceso exclusivo al portal/vista del paciente.
     */
    public function canAccessPatientPortal(): bool
    {
        return $this === self::Patient;
    }

    /**
     * Configuración global del sistema: regiones, alta/baja de clínicas, usuarios globales.
     */
    public function canManageInfrastructure(): bool
    {
        return $this === self::Admin;
    }

    /**
     * Supervisión operativa zonal (no equivale a administración única de sucursal).
     */
    public function canManageAssignedClinics(): bool
    {
        return in_array($this, [self::Admin, self::ZoneManager], true);
    }

    /**
     * Operación clínica (pacientes, sesiones, notas y seguimiento).
     */
    public function canAccessClinicalOperations(): bool
    {
        return in_array($this, [self::Admin, self::ZoneManager, self::Therapist], true);
    }

    /**
     * Captación y métricas comerciales/marketing (placeholders reservados para leads).
     */
    public function canAccessMarketing(): bool
    {
        return in_array($this, [self::Admin, self::ZoneManager, self::Marketing, self::Agent], true);
    }

    /**
     * Consulta, solicitud, captura o revisión de estudios EEG según el rol.
     */
    public function canAccessEegStudies(): bool
    {
        return in_array($this, [
            self::Admin,
            self::ZoneManager,
            self::Therapist,
            self::EegTechnician,
        ], true);
    }
}
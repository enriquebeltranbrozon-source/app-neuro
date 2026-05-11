@component('mail::message')
# Nuevo prospecto para valoración

Se ha recibido una nueva solicitud desde el sitio web de **Neurofeedback Center**.

**Datos del prospecto:**
* **Nombre:** {{ $lead->name }}
* **Teléfono:** [{{ $lead->phone }}](https://wa.me/{{ preg_replace('/[^0-9]/', '', $lead->phone) }})
* **Correo:** {{ $lead->email }}

**Perfil Clínico:**
* **Paciente:** {{ $lead->patient_type }}
* **Edad:** {{ $lead->age }} años
* **Síntoma principal:** {{ $lead->main_symptom }}
* **Ciudad:** {{ $lead->city }}

@component('mail::button', ['url' => 'https://wa.me/' . preg_replace('/[^0-9]/', '', $lead->phone)])
Contactar por WhatsApp
@endcomponent

Atentamente,<br>
Sistema NFC
@endcomponent
@props(['url'])
<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block;">
            @if (trim($slot) === 'Laravel')
                <img src="{{ asset('logo.png') }}" class="logo" alt="NFC Logo" style="height: 60px; width: auto;">
            @else
                {{ $slot }}
            @endif
        </a>
    </td>
</tr>

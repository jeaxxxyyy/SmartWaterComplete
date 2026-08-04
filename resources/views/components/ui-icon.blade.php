@php($iconName = $name ?? 'dashboard')
<svg class="ui-icon ui-icon-{{ $iconName }} {{ $class ?? '' }}" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    @switch($iconName)
        @case('dashboard')
            <rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="5" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="12" width="7" height="9" rx="2"/>
            @break
        @case('monitoring')
            <path d="M3 12h3.2l2.2-5 4.1 10 2.8-6H21"/>
            @break
        @case('history')
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.2 2"/>
            @break
        @case('billing')
            <path d="M6 3h12v18l-3-1.8L12 21l-3-1.8L6 21V3Z"/><path d="M9 8h6M9 12h6M9 16h3"/>
            @break
        @case('leak')
            <path d="M12 3 5 6v5c0 4.6 2.8 8 7 10 4.2-2 7-5.4 7-10V6l-7-3Z"/><path d="M12 8v5M12 16.5h.01"/>
            @break
        @case('notifications')
            <path d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9Z"/><path d="M10 21h4"/>
            @break
        @case('settings')
            <circle cx="9" cy="8" r="3"/><path d="M3.5 19c.6-3.3 2.4-5 5.5-5 1.4 0 2.5.3 3.4.9"/><circle cx="17" cy="17" r="3"/><path d="M17 12.5v1M17 20.5v1M12.5 17h1M20.5 17h1M13.8 13.8l.7.7M19.5 19.5l.7.7M20.2 13.8l-.7.7M14.5 19.5l-.7.7"/>
            @break
        @case('flow')
            <path d="M4 17a9 9 0 0 1 16 0"/><path d="m12 14 4-4"/><circle cx="12" cy="17" r="1.5"/>
            @break
        @case('usage')
            <path d="M12 3s-6 6.7-6 11a6 6 0 0 0 12 0c0-4.3-6-11-6-11Z"/><path d="M8.5 15.5c2.2-1.4 4.7-1.4 7 0"/>
            @break
        @case('safety')
            <path d="M12 3 5 6v5c0 4.6 2.8 8 7 10 4.2-2 7-5.4 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/>
            @break
        @case('connection')
            <path d="M4.9 9.8a10 10 0 0 1 14.2 0M7.8 12.7a6 6 0 0 1 8.4 0M10.7 15.6a2 2 0 0 1 2.6 0"/><circle cx="12" cy="19" r="1"/>
            @break
        @case('refresh')
            <path d="M20 7v5h-5"/><path d="M4 17v-5h5"/><path d="M6.1 8.5A7 7 0 0 1 18.7 7L20 12M4 12l1.3 5a7 7 0 0 0 12.6-1.5"/>
            @break
        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/>
            @break
        @case('sidebar-collapse')
            <rect x="3" y="3" width="18" height="18" rx="3"/><path d="M9 3v18M15 9l-3 3 3 3"/>
            @break
    @endswitch
</svg>

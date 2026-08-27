<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-5">
@switch($type)
    @case('intro_contact')
        <path d="M21 12a8 8 0 0 1-8 8H6l-4 2 1.5-4A9 9 0 1 1 21 12Z"/><path d="M8 12h.01M12 12h.01M16 12h.01"/>
        @break
    @case('visit')
        <path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>
        @break
    @case('quotation_sent')
        <path d="M6 2h9l4 4v16H6Z"/><path d="M14 2v5h5M9 12h6M9 16h6"/>
        @break
    @case('meeting')
        <circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2"/><path d="M3 20c0-4 2-6 6-6s6 2 6 6M15 15c3 0 5 1.5 5 4"/>
        @break
    @case('order')
        <path d="M3 3h2l2 12h10l3-8H6"/><circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/>
        @break
    @case('collection')
        <rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M7 9h.01M17 15h.01"/>
        @break
    @case('approval_special_price')
        <path d="M20 13 13 20l-9-9V4h7Z"/><circle cx="8.5" cy="8.5" r="1"/><path d="m14 10-4 4"/>
        @break
    @case('approval_discount')
        <circle cx="8" cy="8" r="2"/><circle cx="16" cy="16" r="2"/><path d="m18 6-12 12"/>
        @break
    @case('approval_credit_limit')
        <rect x="2.5" y="5" width="19" height="14" rx="2"/><path d="M2.5 10h19M7 15h3"/>
        @break
    @case('approval_payment_term')
        <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/><path d="M12 14v3l2 1"/>
        @break
    @case('sample_sent')
        <path d="m4 7 8-4 8 4-8 4Z"/><path d="M4 7v10l8 4 8-4V7M12 11v10"/>
        @break
    @case('approval_complaint_settlement')
        <path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-5"/>
        @break
    @case('approval_return')
        <path d="m9 7-5 5 5 5"/><path d="M4 12h10a6 6 0 0 1 6 6"/>
        @break
    @case('approval_marketing_support')
        <path d="m3 11 14-6v14L3 13Z"/><path d="M7 14v5h4l-1-4M19 9c1 1 1 5 0 6"/>
        @break
    @case('approval_budget')
        <path d="M4 7h16v13H4Z"/><path d="M7 7V4h10v3M4 11h16"/><circle cx="12" cy="15" r="2"/>
        @break
    @case('approval_custom_project')
        <rect x="3" y="6" width="18" height="14" rx="2"/><path d="M9 6V3h6v3M3 11h18M10 11v2h4v-2"/>
        @break
    @default
        <circle cx="12" cy="12" r="9"/><path d="M12 8v4l3 2"/>
@endswitch
</svg>

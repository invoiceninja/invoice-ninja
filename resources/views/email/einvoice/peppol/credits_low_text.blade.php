{{ ctrans('texts.notification_credits_low') }}

{{ ctrans('texts.notification_credits_low_text') }}

@if($is_hosted)
    <a href="{{ url('/#/settings/e_invoice') }}">{{ ctrans('texts.learn_more') }}</a>
@endif

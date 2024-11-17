{{ ctrans('texts.notification_no_credits') }}

{{ ctrans('texts.notification_no_credits_text') }}

@if($is_hosted)
    <a href="{{ url('/#/settings/e_invoice') }}">{{ ctrans('texts.learn_more') }}</a>
@endif

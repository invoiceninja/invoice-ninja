@php
    $primary_color = isset($settings) ? $settings->primary_color : '#4caf50';
    $email_alignment = isset($settings) && $settings?->email_alignment ? $settings->email_alignment : 'center';
    $email_preferences = isset($url) && str_contains($url ?? '', '/#/') ? config('ninja.react_url').'/#/settings/user_details/notifications' : config('ninja.app_url');
@endphp

<!DOCTYPE html
    PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en-US">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <style>
        @import url("https://use.typekit.net/zxn7pho.css");
    </style>

    <style type="text/css">
        :root {
            color-scheme: light dark;
            supported-color-schemes: light dark;
        }
        @if(isset($settings) && $settings->email_style === 'dark')
            body,
            [data-ogsc] {
                background-color: #1a1a1a !important;
                color: #ffffff !important;
            }
            div, tr, td,
            [data-ogsc] div,
            [data-ogsc] tr,
            [data-ogsc] td {
                border-color: #222222 !important;
            }
            h1, h2, h3, p, td,
            [data-ogsc] h1, [data-ogsc] h2, [data-ogsc] h3, [data-ogsc] p, [data-ogsc] td, {
                color: #ffffff !important;
            }
            p,
            [data-ogsc] p {
                color: #bbbbbc !important;
            }
            .dark-bg-base,
            [data-ogsc] .dark-bg-base {
                background-color: #222222 !important;
            }
            .dark-bg,
            [data-ogsc] .dark-bg {
                background-color: #3a3a3c !important;
            }
            .logo-dark,
            [data-ogsc] .logo-dark {
                display: block !important;
            }
            .logo-light,
            [data-ogsc] .logo-light {
                display: none !important;
            }
            .btn-white,
            [data-ogsc] .btn-white {
                background-color: #000 !important;
                mso-padding-alt: 40px;
                mso-border-alt: 40px solid #fefefe;
                mso-padding-alt: 0;
                mso-ansi-font-size:20px !important;
                mso-line-height-alt:150%;
                mso-border-left-alt: 20 #fefefe 0;
                mso-border-right-alt: 20 #fefefe 0;
            }
        @endif
        /** Content-specific styles. **/
        #content .button {
            display: inline-block;
            background-color: {{ $primary_color }};
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-decoration: none;
            font-size: 13px;
            padding: 15px 70px;
            font-weight: 600;
            margin-bottom: 30px;
        }
        #content h1 {
            font-family: 'canada-type-gibson', 'roboto', Arial, Helvetica, sans-serif;
            font-weight: 600;
            font-size: 32px;
            margin-top: 5px;
            margin-bottom: 30px;
        }
        #content > p {
            font-size: 16px;
            color: red;
        }
        #content .center {
            text-align: center;
        }
        .stamp {
            transform: rotate(12deg);
            color: #555;
            font-size: 3rem;
            font-weight: 700;
            border: 0.25rem solid #555;
            display: inline-block;
            padding: 0.25rem 1rem;
            text-transform: uppercase;
            border-radius: 1rem;
            font-family: 'Courier';
            mix-blend-mode: multiply;
            z-index:200 !important;
            position:  fixed;
            text-align: center;
        }
        .is-paid {
            color:  #D23;
            border: 1rem double  #D23;
            transform: rotate(-5deg);
            font-size: 6rem;
            font-family: "Open sans", Helvetica, Arial, sans-serif;
            border-radius: 0;
            padding: 0.5rem;
            opacity: 0.2;
            z-index:200 !important;
            position:  fixed;
        } 

        .new_button a {
            background-color: {{ $primary_color }};
        }

    </style>
</head>

<body class="body"
      style="margin: 0; padding: 0; font-family: 'roboto', Arial, Helvetica, sans-serif; color: #3b3b3b;-webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td>
            <table class="dark-bg" align="center" border="0" cellpadding="0" cellspacing="0" width="570"
                   style="border: 1px solid #c2c2c2; background-color:#f9f9f9">

                <!--[if mso]>
                <tr class="dark-bg" style="margin-top:0px; border: none;">
                <td style="border: none;"></td>
                </tr>
                <![endif]-->


                <tr>
                    <td align="center">
                        <div class="dark-bg"
                             style="background-color:#f9f9f9; padding-bottom: 20px; margin-top:20px;">
                            @if($logo && strpos($logo, 'blank.png') === false)
                            <img class="" src="{{ $logo ?? '' }}" width="50%" height="" alt=" " border="0" style="width: 50%; max-width: 570px; height: auto; display: block;" class="g-img">
                            @endif
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="dark-bg-base" id="content"
                             style="padding: 20px; text-align: {{ $email_alignment }}">
                                {{ $slot }}
                        </div> <!-- End replaceable content. -->
                    </td>
                </tr>

                <!--[if mso]>
                <tr class="dark-bg" style="margin-top:20px; border: none; border-bottom-color: {{ $primary_color }};">
                <td style="border: none; border-bottom: none; padding: 20px;"></td>
                </tr>
                <![endif]-->

                <tr class="dark-bg"
                    style="background-color: {{ $primary_color }};" width="100%">
                    <td width="100%">
                        <div style="text-align: center; margin-top: 25px;">
                            <h2
                                style="color: #ffffff; font-family: 'canada-type-gibson', 'roboto', Arial, Helvetica, sans-serif; font-weight: 500; font-size: 26px;">
                                Questions? We're here to help!</h2>
                        </div>

                        <div style="text-align:center; margin-bottom: 35px; margin-top: 25px;">

                        <!--[if mso]>
                        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="text-align: center;" valign="center">
                        <tr>
                        <td>
                        <![endif]-->
                            <a href="https://forum.invoiceninja.com" target="_blank" class="btn-white"
                               style="vertical-align: middle;display: inline-block;background-color: #ffffff; color: {{ $primary_color }}; display: inline-block; text-decoration: none;  width: 100px; text-align: center; font-size: 12px; height: 35px; line-height: 35px; margin-left: 10px; margin-right: 10px;">
                                <img style="width: 13px; margin-right: 4px; display: inline-block; vertical-align:middle;" src="{{ asset('images/emails/forum.png') }}" width="13">
                                <span>Forums</span>
                            </a>
                        <!--[if mso]>
                        </td>
                        <![endif]-->


                        <!--[if mso]>
                        
                        <td>
                        <![endif]-->   
                            <a href="http://slack.invoiceninja.com/" target="_blank" class="btn-white"
                               style="vertical-align: middle;display: inline-block;background-color: #ffffff; color: {{ $primary_color }}; display: inline-block; text-decoration: none;  width: 100px; text-align: center; font-size: 12px; height: 35px; line-height: 35px; margin-left: 10px; margin-right: 10px;">
                                <img style="width: 13px; margin-right: 4px; display: inline-block; vertical-align:middle;" src="{{ asset('images/emails/slack.png') }}" width="13">
                                <span>Slack</span>
                            </a>
                        <!--[if mso]>
                        </td>
                        <![endif]-->

                        <!--[if mso]>
                        
                        <td>
                        <![endif]-->   
                            <a href="https://www.invoiceninja.com/contact/" target="_blank" class="btn-white"
                               style="vertical-align: middle;display: inline-block;background-color: #ffffff; color: {{ $primary_color }}; display: inline-block; text-decoration: none;  width: 100px; text-align: center; font-size: 12px; height: 35px; line-height: 35px; margin-left: 10px; margin-right: 10px;">
                                <img style="width: 13px; margin-right: 4px; display: inline-block; vertical-align:middle;" src="{{ asset('images/emails/email.png') }}" width="13">
                                <span>E-mail</span>
                            </a>
                        <!--[if mso]>
                        </td>
                        <![endif]-->

                        <!--[if mso]>
                        
                        <td>
                        <![endif]-->     
                            <a href="https://invoiceninja.github.io/" target="_blank" class="btn-white"
                               style="vertical-align: middle;display: inline-block;background-color: #ffffff; color: {{ $primary_color }}; display: inline-block; text-decoration: none;  width: 100px; text-align: center; font-size: 12px; height: 35px; line-height: 35px; margin-left: 10px; margin-right: 10px;">
                                <span>Support Docs</span>
                            </a>
                        <!--[if mso]>
                        </td>
                        </tr>
                        </table>
                        <![endif]-->

                        </div>

                    </td>
                </tr>
                <tr>
                    <td class="dark-bg-base"
                        style="background-color: #242424;">
                        <div style="padding-top: 10px;padding-bottom: 10px;">
                            <p style="text-align: center; color: #ffffff; font-size: 10px;
                            font-family: Verdana, Geneva, Tahoma, sans-serif;">© {{ date('Y') }} Invoice Ninja, All Rights Reserved
                            </p>

                            <!-- <a href="{{ $email_preferences }}">
                                <p style="text-align: center; color: #ffffff; font-size: 10px; font-family: Verdana, Geneva, Tahoma, sans-serif;">
                                    {{ ctrans('texts.email_preferences') }}
                                </p>
                            </a> -->
                            
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>

</html>
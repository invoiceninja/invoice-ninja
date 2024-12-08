<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4" id="blockonomics-payment">
   
<meta name="amount" content="{{ $amount }}" />
<meta name="btc_amount" content="{{ $btc_amount }}" />
<meta name="btc_address" content="{{ $btc_address }}" />
<meta name="currency" content="{{ $currency }}" />
<meta name="error" content="{{ $error }}" />

    @if ($error)
        <div class="alert alert-danger my-10 mx-4">{!! $error !!}</div>
    @else
    <div class="alert alert-failure mb-4" hidden id="errors"></div>
    <div class="blockonomics-payment-wrapper">
        <div class="initial-state">
        <div class="invoice-info-wrapper">
            <div class="invoice-number">Invoice #{{$invoice_number}}</div>
            <div class="invoice-amount">{{$amount}} {{$currency}}</div>
        </div>
        <div class="sections-wrapper">
            <div class="scan-section">
                <div class="title">Scan</div>
                <span class="input-wrapper">
                    <a href="bitcoin:{{$btc_address}}?amount={{$btc_amount}}" id="qr-code-link" target="_blank">
                        <div id="qrcode-container"></div>
                    </a>
                </span>
                <a href="bitcoin:{{$btc_address}}?amount={{$btc_amount}}" target="_blank" id="open-in-wallet-link">Open in Wallet</a>
            </div>
            <div class="copy-section">
                <div class="title">Copy</div>
                <span>To pay, send bitcoin to this address:</span>
                <span class="input-wrapper">
                    <input onclick='copyToClipboard("btc-address", this, true)' class="full-width-input" id="btc-address" value="{{$btc_address}}" readonly>
                    <img onclick='copyToClipboard("btc-address", this)' src="{{ 'data:image/svg+xml;base64,' . base64_encode('<svg width="22" height="24" viewBox="0 0 22 24" fill="none" xmlns="http://www.w3.org/2000/svg" ><path d="M15.5 1H3.5C2.4 1 1.5 1.9 1.5 3V17H3.5V3H15.5V1ZM18.5 5H7.5C6.4 5 5.5 5.9 5.5 7V21C5.5 22.1 6.4 23 7.5 23H18.5C19.6 23 20.5 22.1 20.5 21V7C20.5 5.9 19.6 5 18.5 5ZM18.5 21H7.5V7H18.5V21Z" fill="#000"/></svg>') }}" class="icon" alt="Copy Icon">
                </span>
                <span>Amount of bitcoin (BTC) to send:</span>
                <span class="input-wrapper">
                    <div class="full-width-input" id="btc-amount" onclick='copyToClipboard("btc-amount", this, true)'>
                        {{$btc_amount}}
                    </div>
                    <img onclick='copyToClipboard("btc-amount", this)' src="{{ 'data:image/svg+xml;base64,' . base64_encode('<svg width="22" height="24" viewBox="0 0 22 24" fill="none" xmlns="http://www.w3.org/2000/svg" ><path d="M15.5 1H3.5C2.4 1 1.5 1.9 1.5 3V17H3.5V3H15.5V1ZM18.5 5H7.5C6.4 5 5.5 5.9 5.5 7V21C5.5 22.1 6.4 23 7.5 23H18.5C19.6 23 20.5 22.1 20.5 21V7C20.5 5.9 19.6 5 18.5 5ZM18.5 21H7.5V7H18.5V21Z" fill="#000"/></svg>') }}" class="icon" alt="Copy Icon">
                </span>
                <div class="btc-value-wrapper">
                    <div class="btc-value">1 BTC = {{$btc_price}} {{$currency}}, updates in <span id="countdown"></span></div>
                    <span class="icon-refresh" onclick='refreshBTCPrice()'></span>
                </div>
            </div>
        </div>
    </div>
    @endif

    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="company_gateway_id" value="{{ $company_gateway_id }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="token">
        <input type="hidden" name="amount" value="{{ $amount }}">
        <input type="hidden" name="btc_price" value="{{ $btc_price }}">
        <input type="hidden" name="btc_amount" value="{{ $btc_amount }}">
        <input type="hidden" name="currency" value="{{ $currency }}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="txid" value="">
    </form>

    
    <style type="text/css">
        .sections-wrapper {
            display: flex;
            flex-direction: row;
            justify-content: space-around;
            /* Mobile devices */
            @media (max-width: 768px) {
                flex-direction: column; /* Change to column on smaller screens */
            }
        }
        .copy-section {
            width: 60%;
            @media (max-width: 768px) {
                width: 100%; /* Full width on smaller screens */
            }
        }
        .title {
            font-size: 17px;
            font-weight: bold;
            margin-bottom: 6px;
        }
        #open-in-wallet-link {
            text-align: center;
            text-decoration: underline;
            width: 100%;
            justify-content: center;
            display: flex;
            margin-top: 10px;
            margin-bottom: 20px;
             &:hover {
                text-decoration: none;
            }
        }
        .invoice-info-wrapper {
            width: 100%;
            text-transform: uppercase;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
        }    
        .invoice-number {
            width: 50%;
            float: left;
            text-align: left;
        }    
        .invoice-amount {
            width: 50%;
            float: right;
            text-align: right;
            text-transform: uppercase;
            margin-bottom: 20px;
        }    
        .blockonomics-payment-wrapper {
            display: flex;
            justify-content: center;
            width: 100%;
        }
        .initial-state {
            justify-content: center;
            display: flex;
            flex-direction: column;
            width: 100%;
            padding: 24px;
        }
        .input-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: row;
            width: 100%;
            margin-bottom: 10px;
        }
        .full-width-input {
            width: 100%;
            max-width: 400px;
            padding: 10px;
            text-align: left;
            border: 1px solid #ccc;
            border-radius: 5px;
            color: #444;
            cursor: pointer;
            position: relative;
        }
        .icon {
            cursor: pointer;
            width: 28px;
            margin-left: 5px;
        }
        .icon-refresh::before {
            content: '\27F3';
            cursor: pointer;
            margin-left: 5px;
            width: 28px;
            display: flex;
            font-size: 32px;
            margin-bottom: 5px;
        }
        .btc-value {
            font-size: 14px;
            text-align: center;
        }
        .btc-value-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: row;
        }
        @keyframes rotating {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }
        .rotating {
            animation: rotating 2s linear infinite;
        }
    </style>

</div>

@assets
    @vite('resources/js/clients/payments/blockonomics.js')
@endassets
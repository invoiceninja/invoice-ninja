/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { wait, instant } from '../wait';

class Blockonomics {
    constructor() {
        // Bind the method to the instance
        this.copyToClipboard = this.copyToClipboard.bind(this);
        this.refreshBTCPrice = this.refreshBTCPrice.bind(this);
    }

    copyToClipboard(elementId, passedElement, shouldGrabNextElementSibling) {
        const element = shouldGrabNextElementSibling
            ? passedElement.nextElementSibling
            : passedElement;
        const originalIcon = element.src; // Store the original icon

        const tempInput = document.createElement('input');
        const elementWithId = document.getElementById(elementId);
        const { value, innerText } = elementWithId || {};
        const text = value || innerText;

        tempInput.value = text;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);

        element.src =
            'data:image/svg+xml;base64,' +
            btoa(`
                    <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M4.04706 14C4.04706 8.55609 8.46025 4.1429 13.9042 4.1429C19.3482 4.1429 23.7613 8.55609 23.7613 14C23.7613 19.444 19.3482 23.8572 13.9042 23.8572C8.46025 23.8572 4.04706 19.444 4.04706 14Z" stroke="#000" stroke-width="2.19048" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M9.52325 14L12.809 17.2858L18.2852 11.8096" stroke="#000" stroke-width="2.19048" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                `);

        // Change the icon back to the original after 5 seconds
        setTimeout(() => {
            element.src = originalIcon;
        }, 5000);
    }

    async refreshBTCPrice() {
        const refreshIcon = document.querySelector('.icon-refresh');
        refreshIcon.classList.add('rotating');
        document.getElementsByClassName('btc-value')[0].innerHTML =
            'Refreshing...';

        try {
            const newPrice = await getBTCPrice();
            if (newPrice) {
                // Update the text content of the countdown span to the new bitcoin price
                const currency = document.querySelector(
                    'meta[name="currency"]'
                ).content;
                document.getElementsByClassName('btc-value')[0].innerHTML =
                    '1 BTC = ' +
                    (newPrice || 'N/A') +
                    ' ' +
                    currency +
                    ", updates in <span id='countdown'></span>";
                const newBtcAmount = (
                    document.querySelector('meta[name="amount"]').content /
                    newPrice
                ).toFixed(10);

                // set the value of the input field and the text content of the span to the new bitcoin amount
                document.querySelector('input[name="btc_price"]').value =
                    newPrice;
                document.querySelector('input[name="btc_amount"]').value =
                    newBtcAmount;
                document.getElementById('btc-amount').textContent =
                    newBtcAmount;

                const btcAddress = document.querySelector(
                    'meta[name="btc_address"]'
                ).content;

                // set the href attribute of the link to the new bitcoin amount
                const qrCodeLink = document.getElementById('qr-code-link');
                const openInWalletLink = document.getElementById(
                    'open-in-wallet-link'
                );
                qrCodeLink.href = `bitcoin:${btcAddress}?amount=${newBtcAmount}`;
                openInWalletLink.href = `bitcoin:${btcAddress}?amount=${newBtcAmount}`;

                // fetch and display the new QR code
                fetchAndDisplayQRCode(newBtcAmount);
                startTimer(600); // Restart timer for 10 minutes (600 seconds)
            }
        } finally {
            refreshIcon.classList.remove('rotating');
        }
    }

    handle() {
        window.copyToClipboard = this.copyToClipboard;
        window.refreshBTCPrice = this.refreshBTCPrice;

        const startTimer = (seconds) => {
            const countDownDate = new Date().getTime() + seconds * 1000;
            document.getElementById('countdown').innerHTML =
                '10' + ':' + '00' + ' min';

            const updateCountdown = () => {
                const now = new Date().getTime();
                const distance = countDownDate - now;

                const isRefreshing = document
                    .getElementsByClassName('btc-value')[0]
                    .innerHTML.includes('Refreshing');
                if (isRefreshing) {
                    return;
                }

                if (distance < 0) {
                    refreshBTCPrice();
                    return;
                }

                const minutes = Math.floor(
                    (distance % (1000 * 60 * 60)) / (1000 * 60)
                );
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                const formattedMinutes = String(minutes).padStart(2, '0');
                const formattedSeconds = String(seconds).padStart(2, '0');
                document.getElementById('countdown').innerHTML =
                    formattedMinutes + ':' + formattedSeconds + ' min';
            };

            clearInterval(window.countdownInterval);
            window.countdownInterval = setInterval(updateCountdown, 1000);
        };

        const getBTCPrice = async () => {
            try {
                const currency = document.querySelector(
                    'meta[name="currency"]'
                ).content;
                const response = await fetch(
                    `/api/v1/get-btc-price?currency=${currency}`
                ); // New endpoint to call server-side function
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                const data = await response.json();
                return data.price;
            } catch (error) {
                console.error(
                    'There was a problem with the BTC price fetch operation:',
                    error
                );
                // Handle error appropriately
            }
        };

        const connectToWebsocket = () => {
            const btcAddress = document.querySelector(
                'meta[name="btc_address"]'
            ).content;
            const webSocketUrl = `wss://www.blockonomics.co/payment/${btcAddress}`;
            const ws = new WebSocket(webSocketUrl);

            ws.onmessage = function (event) {
                const data = JSON.parse(event.data);
                console.log('Payment status:', data.status);
                const isPaymentUnconfirmed = data.status === 0;
                const isPaymentPartiallyConfirmed = data.status === 1;
                // TODO: Do we need to handle Payment confirmed status?
                // This usually takes too long to happen, so we can just wait for the unconfirmed status?
                if (isPaymentUnconfirmed || isPaymentPartiallyConfirmed) {
                    document.querySelector('input[name="txid"]').value =
                        data.txid || '';
                    document.getElementById('server-response').submit();
                }
            };
        };

        const fetchAndDisplayQRCode = async (newBtcAmount = null) => {
            try {
                const btcAddress = document.querySelector(
                    'meta[name="btc_address"]'
                ).content;
                const btcAmount = newBtcAmount || '{{$btc_amount}}';
                const qrString = encodeURIComponent(
                    `bitcoin:${btcAddress}?amount=${btcAmount}`
                );
                const response = await fetch(
                    `/api/v1/get-blockonomics-qr-code?qr_string=${qrString}`
                );
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const svgText = await response.text();
                document.getElementById('qrcode-container').innerHTML = svgText;
            } catch (error) {
                console.error('Error fetching QR code:', error);
                document.getElementById('qrcode-container').textContent =
                    'Error loading QR code';
            }
        };

        startTimer(600); // Start timer for 10 minutes (600 seconds)
        connectToWebsocket();
        fetchAndDisplayQRCode();
    }
}

function boot() {
    new Blockonomics().handle();
    window.bootBlockonomics = boot;
}

instant() ? boot() : wait('#blockonomics-payment').then(() => boot());

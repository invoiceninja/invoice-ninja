var s=Object.defineProperty;var o=(n,e,t)=>e in n?s(n,e,{enumerable:!0,configurable:!0,writable:!0,value:t}):n[e]=t;var r=(n,e,t)=>(o(n,typeof e!="symbol"?e+"":e,t),t);/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */class c{constructor(){r(this,"setupStripe",()=>(this.stripeConnect?this.stripe=Stripe(this.key,{stripeAccount:this.stripeConnect}):this.stripe=Stripe(this.key),this));r(this,"getFormData",()=>({country:document.getElementById("country").value,currency:document.getElementById("currency").value,routing_number:document.getElementById("routing-number").value,account_number:document.getElementById("account-number").value,account_holder_name:document.getElementById("account-holder-name").value,account_holder_type:document.querySelector('input[name="account-holder-type"]:checked').value}));r(this,"handleError",e=>{document.getElementById("save-button").disabled=!1,document.querySelector("#save-button > svg").classList.add("hidden"),document.querySelector("#save-button > span").classList.remove("hidden"),this.errors.textContent="",this.errors.textContent=e,this.errors.hidden=!1});r(this,"handleSuccess",e=>{document.getElementById("gateway_response").value=JSON.stringify(e),document.getElementById("server_response").submit()});r(this,"handleSubmit",e=>{if(!document.getElementById("accept-terms").checked){errors.textContent="You must accept the mandate terms prior to making payment.",errors.hidden=!1;return}document.getElementById("save-button").disabled=!0,document.querySelector("#save-button > svg").classList.remove("hidden"),document.querySelector("#save-button > span").classList.add("hidden"),e.preventDefault(),this.errors.textContent="",this.errors.hidden=!0,this.stripe.createToken("bank_account",this.getFormData()).then(t=>t.hasOwnProperty("error")?this.handleError(t.error.message):this.handleSuccess(t))});var e;this.errors=document.getElementById("errors"),this.key=document.querySelector('meta[name="stripe-publishable-key"]').content,this.stripe_connect=(e=document.querySelector('meta[name="stripe-account-id"]'))==null?void 0:e.content}handle(){document.getElementById("save-button").addEventListener("click",e=>this.handleSubmit(e))}}new c().setupStripe().handle();
@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.pro_plan_call_to_action'))

@section('body')

<style>
/* Toggle A */
input:checked ~ .dot {
  transform: translateX(100%);
  background-color: #48bb78;
}
/* Toggle B */
input:checked ~ .dot {
  transform: translateX(100%);
  background-color: #48bb78;
}
</style>
<div id="datadiv" class="container flex flex-wrap pt-2 pb-10 m-auto mt-2 md:mt-5 lg:px-16 xl:px-16" x-data="{show: true}">
    <div class="w-full px-0 lg:px-4">
      <h2 class="px-12 text-base font-bold text-center md:text-2xl text-blue-700">
        Choose your plan
      </h2>
      <p class="py-1 text-sm text-center text-blue-700 mb-10">
  

            <!-- Toggle B -->
            <div class="flex items-center justify-center w-full mb-12"">
              
              <label for="toggleB" class="flex items-center cursor-pointer">
                <!-- toggle -->
                <div class="relative">
                  <!-- input -->
                  <input type="checkbox" id="toggleB" class="sr-only" @click="show = !show">
                  <!-- line -->
                  <div class="block bg-gray-600 w-14 h-8 rounded-full"></div>
                  <!-- dot -->
                  <div class="dot absolute left-1 top-1 bg-white w-6 h-6 rounded-full transition"></div>
                </div>
                <!-- label -->
                <div class="ml-3 text-gray-700 font-medium">
                 Monthly vs Annual
                </div>
              </label>

            </div>

        </p>

      <!-- monthly Plans -->

      <div class="flex flex-wrap items-center justify-center py-4 pt-0" x-show=" show ">
        <div class="w-full p-4 md:w-1/2 lg:w-1/2">
          <label class="flex flex-col rounded-lg shadow-lg relative cursor-pointer hover:shadow-2xl">
            <div class="w-full px-4 py-8 rounded-t-lg bg-blue-500">
              <h3 class="mx-auto text-base font-semibold text-center underline text-white group-hover:text-white">
                Pro Plan
              </h3>
              <p class="text-5xl font-bold text-center text-white">
                $12
              </p>
              <p class="text-xs text-center uppercase text-white">
                monthly
              </p>

              <div class="py-2 text-sm my-3 text-white">Unlimited clients, invoices, quotes, recurring invoices</div>
              <hr>
              <div class="py-2 text-sm my-3 text-white">11 Professional invoice & quote template designs</div>
              <hr>
              <div class="py-2 text-sm my-3 text-white">Remove "Created by Invoice Ninja" from invoices</div>
              <hr>
              <div class="py-2 text-sm my-3 text-white">Enable emails to be sent via Gmail</div>
              <hr>
              <div class="py-2 text-sm my-3 text-white">Integrate with Zapier, Integromat or API</div>
              <hr>
              <div class="py-2 text-sm my-3 text-white">+ Much more!</div>

            </div>

            <div class="flex flex-col items-center justify-center w-full h-full py-6 rounded-b-lg bg-blue-700">
              <p class="text-xl text-white">
                Single User
              </p>
              <button id="handleProMonthlyClick" class="w-5/6 py-2 mt-2 font-semibold text-center uppercase bg-white border border-transparent rounded text-blue-500">
                Purchase
              </button>
            </div>
          </label>
        </div>


        <div class="w-full p-4 md:w-1/2 lg:w-1/2">
          <label class="flex flex-col rounded-lg shadow-lg relative cursor-pointer hover:shadow-2xl">
            <div class="w-full px-4 py-8 rounded-t-lg bg-blue-500">
              <h3 class="mx-auto text-base font-semibold text-center underline text-white group-hover:text-white">
                Enterprise Plan
              </h3>
              <p class="text-5xl font-bold text-center text-white" id="m_plan_price">
                $14
              </p>
              <p class="text-xs text-center uppercase text-white">
                monthly
              </p>

              <div class="py-2 text-sm my-3 text-white">Multiple users and advanced permissions per user</div>
              <hr>
              <div class="py-2 text-sm my-3 text-white">Attach documents to emails & client side portal!</div>
              <hr>
              <div class="py-2 text-sm my-3 text-white">Branded client portal: "https://billing.yourcompany.com"</div>
              <hr>
              <div class="py-2 text-sm my-3 text-white">Priority support</div>
              <hr>
              <div class="py-2 text-sm my-3 text-white">+ Much more!</div>

            </div>
            <div class="flex flex-col items-center justify-center w-full h-full py-6 rounded-b-lg bg-blue-700">
              <p class="text-xl text-white">
                <select id="users_monthly" class="bg-white text-black appearance-none border-none inline-block py-0 pl-3 pr-2 rounded leading-tight w-full">
                  <option value="7LDdwRb1YK" selected>1-2 Users</option>
                  <option value="MVyb8mdvAZ">3-5 Users</option>
                  <option value="WpmbkR5azJ">6-10 Users</option>
                  <option value="k8mepY2aMy">11-20 Users</option>
                </select>
              </p>
              <button id="handleMonthlyClick" class="w-5/6 py-2 mt-2 font-semibold text-center uppercase bg-white border border-transparent rounded text-blue-500">
                Purchase
              </button>
            </div>
          </label>
        </div>

      </div>

      <!-- Annual Plans -->
      <div class="flex flex-wrap items-center justify-center py-4 pt-0" x-show=" !show ">
        <div class="w-full p-4 md:w-1/2 lg:w-1/2">
          <label class="flex flex-col rounded-lg shadow-lg relative cursor-pointer hover:shadow-2xl">
            <div class="w-full px-4 py-8 rounded-t-lg bg-blue-500">
              <h3 class="mx-auto text-base font-semibold text-center underline text-white group-hover:text-white">
                Pro Plan
              </h3>
              <p class="text-5xl font-bold text-center text-white">
                $120
              </p>
              <p class="text-xs text-center uppercase text-white">
                yearly
              </p>

              <div class="py-2 text-sm my-3 text-white">Unlimited clients, invoices, quotes, recurring invoices</div>
              <hr>
              <div class="py-2 text-sm my-3 text-white">11 Professional invoice & quote template designs</div>
              <hr>
              <div class="py-2 text-sm my-3 text-white">Remove "Created by Invoice Ninja" from invoices</div>
              <hr>
              <div class="py-2 text-sm my-3 text-white">Enable emails to be sent via Gmail</div>
              <hr>
              <div class="py-2 text-sm my-3 text-white">Integrate with Zapier, Integromat or API</div>
              <hr>
              <div class="py-2 text-sm my-3 text-white">+ Much more!</div>

            </div>
            <div
              class="flex flex-col items-center justify-center w-full h-full py-6 rounded-b-lg bg-blue-700"
            >
              <p class="text-xl text-white">
                Buy 10 months get 2 free!
              </p>
                <button id="handleProYearlyClick" class="w-5/6 py-2 mt-2 font-semibold text-center uppercase bg-white border border-transparent rounded text-blue-500">
                Purchase
              </button>
            </div>
          </label>
        </div>

        <div class="w-full p-4 md:w-1/2 lg:w-1/2">
          <label class="flex flex-col rounded-lg shadow-lg relative cursor-pointer hover:shadow-2xl">
            <div class="w-full px-4 py-8 rounded-t-lg bg-blue-500">
              <h3 class="mx-auto text-base font-semibold text-center underline text-white group-hover:text-white">
                Enterprise Plan
              </h3>
              <p class="text-5xl font-bold text-center text-white" id="y_plan_price">
                $140
              </p>
              <p class="text-xs text-center uppercase text-white">
                yearly
              </p>

              <div class="py-2 text-sm my-3 text-white">Multiple users and advanced permissions per user</div>
              <hr>
              <div class="py-2 text-sm my-3 text-white">Attach documents to emails & client side portal!</div>
              <hr>
              <div class="py-2 text-sm my-3 text-white">Branded client portal: "https://billing.yourcompany.com"</div>
              <hr>
              <div class="py-2 text-sm my-3 text-white">Priority support</div>
              <hr>
              <div class="py-2 text-sm my-3 text-white">Integrate Your Frinancial Accounts and Sync Banking Transactions via Yodlee or Nodigen Banking Platforms</div>
              <hr>
              <div class="py-2 text-sm my-3 text-white">+ Much more!</div>

            </div>
            <div
              class="flex flex-col items-center justify-center w-full h-full py-6 rounded-b-lg bg-blue-700"
            >
              <p class="text-xl text-white">
                <select id="users_yearly" class="bg-white text-black appearance-none border-none inline-block py-0 pl-3 pr-2 rounded leading-tight w-full">
                  <option value="LYqaQWldnj" selected>1-2 Users</option>
                  <option value="kQBeX6mbyK">3-5 Users</option>
                  <option value="GELe32Qd69">6-10 Users</option>
                  <option value="MVyb86oevA">11-20 Users</option>
                </select>
              </p>
              <button id="handleYearlyClick" class="w-5/6 py-2 mt-2 font-semibold text-center uppercase bg-white border border-transparent rounded text-blue-500" >
                Purchase
              </button>
            </div>
          </label>
        </div>

      </div>

    </div>
  </div>
@endsection

@push('footer')

<script type="text/javascript">

var users_yearly = 'LYqaQWldnj';
var users_monthly = '7LDdwRb1YK';

document.getElementById('users_yearly').options[0].selected = true;
document.getElementById('users_monthly').options[0].selected = true;

document.getElementById("toggleB").addEventListener('change', function() {

  document.getElementById('users_yearly').options[0].selected = true;
  document.getElementById('users_monthly').options[0].selected = true;
  document.getElementById('y_plan_price').innerHTML = price_map.get('LYqaQWldnj');
  document.getElementById('m_plan_price').innerHTML = price_map.get('7LDdwRb1YK');

  users_yearly = 'LYqaQWldnj';
  users_monthly = '7LDdwRb1YK';

});

document.getElementById('users_yearly').addEventListener('change', function() {
  users_yearly = this.value;
  document.getElementById('y_plan_price').innerHTML = price_map.get(this.value);
});

document.getElementById('users_monthly').addEventListener('change', function() {
  users_monthly = this.value;
  document.getElementById('m_plan_price').innerHTML = price_map.get(this.value);

});

document.getElementById('handleYearlyClick').addEventListener('click', function() {
  document.getElementById("toggleB").checked = false;
  location.href = 'https://invoiceninja.invoicing.co/client/subscriptions/' + users_yearly + '/purchase';
});

document.getElementById('handleMonthlyClick').addEventListener('click', function() {
  document.getElementById("toggleB").checked = false;
  location.href = 'https://invoiceninja.invoicing.co/client/subscriptions/' + users_monthly + '/purchase';
});

document.getElementById('handleProMonthlyClick').addEventListener('click', function() {
  document.getElementById("toggleB").checked = false;
  location.href = 'https://invoiceninja.invoicing.co/client/subscriptions/WJxbojagwO/purchase';
});

document.getElementById('handleProYearlyClick').addEventListener('click', function() {
  document.getElementById("toggleB").checked = false;
  location.href = 'https://invoiceninja.invoicing.co/client/subscriptions/q9wdL9wejP/purchase';
});
const price_map = new Map();
//monthly
price_map.set('7LDdwRb1YK', '$14');
price_map.set('MVyb8mdvAZ', '$26');
price_map.set('WpmbkR5azJ', '$36');
price_map.set('k8mepY2aMy', '$44');
//yearly
price_map.set('LYqaQWldnj', '$140');
price_map.set('kQBeX6mbyK', '$260');
price_map.set('GELe32Qd69', '$360');
price_map.set('MVyb86oevA', '$440');

</script>

@endpush

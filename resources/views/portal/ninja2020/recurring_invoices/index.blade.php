@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.recurring_invoices'))

@section('body')
    <div class="flex flex-col">
        @livewire('recurring-invoices-table', ['company_id' => $company->id, 'db' => $company->db])
    </div>
@endsection

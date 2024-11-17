@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.view_credit'))

@section('body')
    <div class="bg-white shadow sm:rounded-lg mb-4" translate>
        <div class="px-4 py-5 sm:p-6">
            <div class="sm:flex sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        {{ ctrans('texts.entity_number_placeholder', ['entity' => ctrans('texts.credit'), 'entity_number' => $credit->number]) }}
                    </h3>
                </div>
            </div>
        </div>
    </div>

@include('portal.ninja2020.components.entity-documents', ['entity' => $credit])
@livewire('pdf-slot', ['class' => get_class($credit), 'entity_id' => $credit->id, 'invitation_id' => $invitation->id ?? false, 'db' => $credit->company->db])

@endsection

@section('footer')

    <script type="text/javascript">

        document.addEventListener('DOMContentLoaded', () => {

            @if($key)
                window.history.pushState({}, "", "{{ url("client/credit/{$key}") }}");
            @endif

        });

    </script>
@endsection

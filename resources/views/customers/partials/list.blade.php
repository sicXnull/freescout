@if (count($customers))
    <div class="container">
        <div class="card-list margin-top">
            @foreach ($customers as $customer)
                <a href="{{ route('customers.update', ['id' => $customer->id]) }}" class="card hover-shade">
                    <img src="{{ $customer->getPhotoUrl() }}" />
                    <h4>{{ $customer->first_name }} {{ $customer->last_name }}</h4>
                    <p class="text-truncate"><small>{{ $customer->getEmailOrPhone() }}</small></p>
                </a>
            @endforeach
        </div>
    </div>
@else
    @include('partials/empty', ['empty_text' => __('No customers found')])
@endif
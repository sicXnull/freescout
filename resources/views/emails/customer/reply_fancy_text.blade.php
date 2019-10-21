{{ App\Misc\Mail::REPLY_SEPARATOR_TEXT }}
@foreach ($threads as $thread)
-----------------------------------------------------------
## {{ $thread->getFromName($mailbox) }} @if ($loop->last){{ __('sent a message') }}@else {{ __('replied') }}@endif, {{ __('on :date', ['date' => App\Customer::dateFormat($thread->created_at, 'M j @ H:i')]) }} ({{ \Config::get('app.timezone') }}):
{{-- Html2Text\Html2Text::convert($thread->body) - this was causing "AttValue: " expected in Entity" error sometimes --}}{{ (new Html2Text\Html2Text($thread->body))->getText() }}
@if ($thread->source_via == App\Thread::PERSON_USER)

{{-- Html2Text\Html2Text::convert($conversation->mailbox->signature) --}}{{ (new Html2Text\Html2Text($conversation->getSignatureProcessed(['thread' => $thread])))->getText() }}
@endif
@endforeach
@if (\App\Option::get('email_branding'))
-----------------------------------------------------------
{!! __('Support powered by :app_name — Free open source help desk & shared mailbox', ['app_name' => \Config::get('app.name')]) !!}
@endif
@php($reason = $blueprint->applicant->getAttribute('gatehouse_reason'))
@php($question = trim((string) $settings->get('ernestdefoe-gatehouse.question')))
<x-mail::plain.notification>
<x-slot:body>
{!! $translator->trans('ernestdefoe-gatehouse.email.admin_new.body', ['applicant' => $blueprint->applicant->display_name, 'email' => $blueprint->applicant->email]) !!}

@if ($reason)
{!! $question ? $translator->trans('ernestdefoe-gatehouse.email.admin_new.answer_to', ['question' => $question]) : $translator->trans('ernestdefoe-gatehouse.email.admin_new.answer') !!}

{!! $reason !!}

@endif
{!! $translator->trans('ernestdefoe-gatehouse.email.admin_new.button') !!}: {{ $url->to('admin')->base() }}#/extension/ernestdefoe-gatehouse
</x-slot:body>
</x-mail::plain.notification>

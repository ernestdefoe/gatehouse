@php($reason = $blueprint->applicant->getAttribute('gatehouse_reason'))
@php($question = trim((string) $settings->get('ernestdefoe-gatehouse.question')))
<x-mail::html.notification>
    <x-slot:body>
        <p>{{ $translator->trans('ernestdefoe-gatehouse.email.admin_new.body', ['applicant' => $blueprint->applicant->display_name, 'email' => $blueprint->applicant->email]) }}</p>
        @if ($reason)
            <p><strong>{{ $question ? $translator->trans('ernestdefoe-gatehouse.email.admin_new.answer_to', ['question' => $question]) : $translator->trans('ernestdefoe-gatehouse.email.admin_new.answer') }}</strong></p>
            <blockquote style="white-space: pre-line">{{ $reason }}</blockquote>
        @endif
        <p><a href="{{ $url->to('admin')->base() }}#/extension/ernestdefoe-gatehouse">{{ $translator->trans('ernestdefoe-gatehouse.email.admin_new.button') }}</a></p>
    </x-slot:body>
</x-mail::html.notification>

<x-sendportal.text-field name="name" :label="__('Campaign Name')" :value="$campaign->name ?? old('name')" />
<x-sendportal.text-field name="subject" :label="__('Email Subject')" :value="$campaign->subject ?? old('subject')" />

<div class="form-group row">
    <label for="campaign-sender-picker" class="col-sm-3 col-form-label">
        {{ __('Saved Sender') }}
    </label>
    <div class="col-sm-9">
        <select id="campaign-sender-picker" class="form-control">
            <option value="">{{ __('Choose a saved sender') }}</option>
            @foreach (($senders ?? collect()) as $sender)
                <option
                    value="{{ $sender->id }}"
                    data-from-name="{{ $sender->from_name }}"
                    data-from-email="{{ $sender->from_email }}"
                >{{ $sender->label }} — {{ $sender->from_email }}</option>
            @endforeach
        </select>
    </div>
</div>

<x-sendportal.text-field name="from_name" :label="__('From Name')" :value="$campaign->from_name ?? old('from_name')" />
<x-sendportal.text-field name="from_email" :label="__('From Email')" type="email" :value="$campaign->from_email ?? old('from_email')" />

<x-sendportal.select-field name="template_id" :label="__('Template')" :options="$templates" :value="$campaign->template_id ?? old('template_id')" />

<x-sendportal.select-field name="email_service_id" :label="__('Email Service')" :options="$emailServices->pluck('formatted_name', 'id')" :value="$campaign->email_service_id ?? old('email_service_id')" />

<x-sendportal.checkbox-field name="is_open_tracking" :label="__('Track Opens')" value="1" :checked="$campaign->is_open_tracking ?? true" />
<x-sendportal.checkbox-field name="is_click_tracking" :label="__('Track Clicks')" value="1" :checked="$campaign->is_click_tracking ?? true" />

<x-sendportal.textarea-field name="content" :label="__('Content')">{{ $campaign->content ?? old('content') }}</x-sendportal.textarea-field>

<div class="form-group row">
    <div class="offset-sm-3 col-sm-9">
        <a href="{{ route('sendportal.campaigns.index') }}" class="btn btn-light">{{ __('Return to Campaigns') }}</a>
        <button type="submit" class="btn btn-primary">{{ __('Save and continue') }}</button>
    </div>
</div>

@include('sendportal::layouts.partials.summernote')

@push('js')
    <script>

        $(function () {
            const smtp = {{
                $emailServices->filter(function ($service) {
                    return $service->type_id === \Sendportal\Base\Models\EmailServiceType::SMTP;
                })
                ->pluck('id')
            }};

            let service_id = $('select[name="email_service_id"]').val();

            toggleTracking(smtp.includes(parseInt(service_id, 10)));

            $('select[name="email_service_id"]').on('change', function () {
              toggleTracking(smtp.includes(parseInt(this.value, 10)));
            });

            $('#campaign-sender-picker').on('change', function () {
                const option = this.options[this.selectedIndex];
                $('input[name="from_name"]').val(option.dataset.fromName || '');
                $('input[name="from_email"]').val(option.dataset.fromEmail || '');
            });
        });

        function toggleTracking(disable) {
            let $open = $('input[name="is_open_tracking"]');
            let $click = $('input[name="is_click_tracking"]');

            if (disable) {
                $open.attr('disabled', 'disabled');
                $click.attr('disabled', 'disabled');
            } else {
                $open.removeAttr('disabled');
                $click.removeAttr('disabled');
            }
        }

    </script>
@endpush

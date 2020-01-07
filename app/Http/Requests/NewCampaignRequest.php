<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NewCampaignRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->isAdmin() || auth()->user()->isAgencyUser();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required',
            'order' => 'required|numeric',
            'start' => 'required|date|nullable',
            'end' => 'required|date|nullable',
            'expires' => 'nullable',
            'status' => 'alpha|required',
            'agency' => 'required',
            'dealership' => 'required',
            'enable_adf_crm_export' => 'boolean',
            'enable_text_to_value' => 'boolean',
            'adf_crm_export' => 'required_if:enable_adf_crm_export,true',
            'enable_lead_alerts' => 'boolean',
            'lead_alert_emails' => 'required_if:enable_lead_alerts,true',
            'enable_client_passthrough' => 'boolean',
            'passthrough_email' => 'required_if:enable_client_passthrough,true',
            'enable_service_dept' => 'boolean',
            'service_dept_email' => 'required_if:enable_service_dept,true',
            'enable_sms_on_callback' => 'boolean',
            'sms_on_callback_number' => 'required_if:enable_service_dept,true',
            'phone_number_id' => 'nullable',
            'tags' => 'nullable|array',
        ];
    }
}

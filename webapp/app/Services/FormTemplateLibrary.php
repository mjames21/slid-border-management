<?php

namespace App\Services;

use App\Models\DynamicForm;

class FormTemplateLibrary
{
    public const DEFAULT_TEMPLATE = 'icao_doc_9303_border_movement';

    public function all(): array
    {
        return [
            'icao_doc_9303_border_movement' => [
                'name' => 'Full ICAO Doc 9303 Inspection Template',
                'description' => 'Comprehensive MRTD inspection template; keep all fields or select only what a post needs.',
                'reporting_module' => DynamicForm::MODULE_IMMIGRATION,
                'standard_reference' => DynamicForm::standardReferenceForModule(DynamicForm::MODULE_IMMIGRATION),
                'form_id' => 'slid_icao_doc_9303_full_inspection',
                'title' => 'ICAO Doc 9303 Full Inspection',
                'fields' => $this->icaoDoc9303BorderMovementFields(),
            ],
            'border_movement_basic' => [
                'name' => 'Basic Border Movement Report',
                'description' => 'Compact entry/exit report for posts that do not need full document capture.',
                'reporting_module' => DynamicForm::MODULE_IMMIGRATION,
                'standard_reference' => DynamicForm::standardReferenceForModule(DynamicForm::MODULE_IMMIGRATION),
                'form_id' => 'slid_border_movement_report',
                'title' => 'SLID Border Movement Report',
                'fields' => $this->basicBorderMovementFields(),
            ],
            'customs_goods_declaration' => [
                'name' => 'Customs Goods Declaration',
                'description' => 'Offline declaration and inspection record for goods, cargo, duties, and seizures.',
                'reporting_module' => DynamicForm::MODULE_CUSTOMS,
                'standard_reference' => DynamicForm::standardReferenceForModule(DynamicForm::MODULE_CUSTOMS),
                'form_id' => 'border_customs_goods_declaration',
                'title' => 'Customs Goods Declaration',
                'fields' => $this->customsGoodsDeclarationFields(),
            ],
            'security_incident_report' => [
                'name' => 'Border Security Incident Report',
                'description' => 'Operational incident report for alerts, patrols, interceptions, and referrals.',
                'reporting_module' => DynamicForm::MODULE_SECURITY,
                'standard_reference' => DynamicForm::standardReferenceForModule(DynamicForm::MODULE_SECURITY),
                'form_id' => 'border_security_incident_report',
                'title' => 'Border Security Incident Report',
                'fields' => $this->securityIncidentFields(),
            ],
            'health_quarantine_screening' => [
                'name' => 'Health / Quarantine Screening',
                'description' => 'Point-of-entry health screening for symptoms, referrals, isolation, and follow-up.',
                'reporting_module' => DynamicForm::MODULE_HEALTH,
                'standard_reference' => DynamicForm::standardReferenceForModule(DynamicForm::MODULE_HEALTH),
                'form_id' => 'border_health_quarantine_screening',
                'title' => 'Health / Quarantine Screening',
                'fields' => $this->healthQuarantineScreeningFields(),
            ],
        ];
    }

    public function get(?string $key): array
    {
        $templates = $this->all();

        return $templates[$key ?: self::DEFAULT_TEMPLATE] ?? $templates[self::DEFAULT_TEMPLATE];
    }

    /**
     * Summarize a standards template for library cards and onboarding screens.
     *
     * @param  array{name?: string, description?: string, fields?: array<int, array<string, mixed>>}  $template
     * @return array{field_count: int, required_count: int, sections: array<int, array{title: string, purpose: string, field_count: int, required_count: int, fields: array<int, array{id: string, label: string, type: string, required: bool, purpose: string}>}>}
     */
    public function summarize(array $template): array
    {
        $sections = [];
        $currentKey = 'general';
        $sections[$currentKey] = [
            'title' => 'General',
            'purpose' => (string) ($template['description'] ?? 'Core report fields.'),
            'field_count' => 0,
            'required_count' => 0,
            'fields' => [],
        ];

        foreach ($template['fields'] ?? [] as $field) {
            $type = (string) ($field['type'] ?? 'text');
            $label = trim((string) ($field['label'] ?? $field['id'] ?? 'Question'));

            if ($type === 'note') {
                $currentKey = (string) ($field['id'] ?? str($label)->slug('_'));
                $sections[$currentKey] = [
                    'title' => $label,
                    'purpose' => $this->fieldPurpose($field),
                    'field_count' => 0,
                    'required_count' => 0,
                    'fields' => [],
                ];
                continue;
            }

            $required = !empty($field['required']);
            $sections[$currentKey]['field_count']++;
            $sections[$currentKey]['required_count'] += $required ? 1 : 0;
            $sections[$currentKey]['fields'][] = [
                'id' => (string) ($field['id'] ?? ''),
                'label' => $label,
                'type' => $type,
                'required' => $required,
                'purpose' => $this->fieldPurpose($field),
            ];
        }

        $sections = collect($sections)
            ->filter(fn (array $section): bool => $section['field_count'] > 0)
            ->values()
            ->all();

        return [
            'field_count' => collect($sections)->sum('field_count'),
            'required_count' => collect($sections)->sum('required_count'),
            'sections' => $sections,
        ];
    }

    public function fieldPurpose(array $field): string
    {
        $hint = trim((string) ($field['hint'] ?? ''));

        if ($hint !== '') {
            return $hint;
        }

        $label = trim((string) ($field['label'] ?? $field['id'] ?? 'This field'));
        $id = strtolower((string) ($field['id'] ?? ''));

        if (str_contains($id, 'mrz')) {
            return 'Supports machine-readable-zone capture, validation, and comparison with the visible document data.';
        }

        if (str_contains($id, 'document') || str_contains($id, 'passport')) {
            return 'Records travel document details needed for identity, validity, and inspection review.';
        }

        if (str_contains($id, 'location') || str_contains($id, 'border') || str_contains($id, 'post')) {
            return 'Links the report to the border post, route, or operational location for maps and supervision.';
        }

        if (str_contains($id, 'incident') || str_contains($id, 'security')) {
            return 'Captures the incident facts needed for referral, escalation, and follow-up ownership.';
        }

        if (str_contains($id, 'health') || str_contains($id, 'symptom') || str_contains($id, 'screening')) {
            return 'Captures point-of-entry health observations, public health actions, and referral decisions.';
        }

        if (str_contains($id, 'customs') || str_contains($id, 'goods') || str_contains($id, 'duty')) {
            return 'Captures customs declaration, inspection, duty, and enforcement details.';
        }

        return "Captures {$label} for the structured border report and downstream review.";
    }

    private function icaoDoc9303BorderMovementFields(): array
    {
        // Practical border capture template organized around Doc 9303 VIZ, MRZ, eMRTD and inspection concepts.
        return [
            ['id' => 'section_operation_context', 'type' => 'note', 'label' => 'Operation Context', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'inspection_datetime', 'type' => 'datetime', 'label' => 'Inspection Date and Time', 'hint' => '', 'required' => true, 'options' => ''],
            ['id' => 'movement_type', 'type' => 'select_one', 'label' => 'Movement Type', 'hint' => '', 'required' => true, 'options' => "entry|Entry\nexit|Exit\ntransit|Transit\nrefusal|Refusal"],
            ['id' => 'border_control_point', 'type' => 'text', 'label' => 'Border Control Point', 'hint' => 'Post, counter, lane, or checkpoint name.', 'required' => false, 'options' => ''],
            ['id' => 'inspection_mode', 'type' => 'select_one', 'label' => 'Inspection Mode', 'hint' => '', 'required' => true, 'options' => "primary|Primary inspection\nsecondary|Secondary inspection\nsupervisor_review|Supervisor review"],
            ['id' => 'case_reference', 'type' => 'text', 'label' => 'Case Reference', 'hint' => 'Optional internal reference number.', 'required' => false, 'options' => ''],

            ['id' => 'section_document_classification', 'type' => 'note', 'label' => 'Document Classification', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'mrtd_form_factor', 'type' => 'select_one', 'label' => 'MRTD Form Factor', 'hint' => 'TD3 passports, TD1/TD2 official documents, or machine readable visa.', 'required' => true, 'options' => "td3|TD3 passport or passport-size MRTD\ntd2|TD2 machine readable official travel document\ntd1|TD1 machine readable official travel document\nmrv_a|Machine readable visa A\nmrv_b|Machine readable visa B\nemergency|Emergency travel document\nunknown|Unknown"],
            ['id' => 'document_category', 'type' => 'select_one', 'label' => 'Document Category', 'hint' => '', 'required' => true, 'options' => "passport|Passport\nidentity_document|Identity document\nvisa|Visa\ncrew_member|Crew member certificate\nrefugee_document|Refugee travel document\nother|Other travel document"],
            ['id' => 'document_type_code', 'type' => 'text', 'label' => 'Document Type Code', 'hint' => 'Code shown in VIZ or MRZ, for example P, I, V, A, C.', 'required' => true, 'options' => ''],
            ['id' => 'issuing_state_or_org', 'type' => 'text', 'label' => 'Issuing State or Organization', 'hint' => 'Three-letter code where available, for example SLE, LBR, GIN.', 'required' => true, 'options' => ''],
            ['id' => 'document_number', 'type' => 'text', 'label' => 'Document Number', 'hint' => '', 'required' => true, 'options' => ''],
            ['id' => 'document_number_check_digit', 'type' => 'text', 'label' => 'Document Number Check Digit', 'hint' => 'Single MRZ check digit if available.', 'required' => false, 'options' => ''],
            ['id' => 'date_of_issue', 'type' => 'date', 'label' => 'Date of Issue', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'document_expiry_date', 'type' => 'date', 'label' => 'Document Expiry Date', 'hint' => '', 'required' => true, 'options' => ''],
            ['id' => 'expiry_date_check_digit', 'type' => 'text', 'label' => 'Expiry Date Check Digit', 'hint' => 'Single MRZ check digit if available.', 'required' => false, 'options' => ''],
            ['id' => 'issuing_authority', 'type' => 'text', 'label' => 'Issuing Authority', 'hint' => 'Authority printed in the visual inspection zone.', 'required' => false, 'options' => ''],
            ['id' => 'personal_number', 'type' => 'text', 'label' => 'Personal Number or Optional Data', 'hint' => 'Use only where printed or encoded by the issuing state.', 'required' => false, 'options' => ''],
            ['id' => 'personal_number_check_digit', 'type' => 'text', 'label' => 'Personal Number Check Digit', 'hint' => '', 'required' => false, 'options' => ''],

            ['id' => 'section_viz_holder_identity', 'type' => 'note', 'label' => 'VIZ Holder Identity', 'hint' => 'Visual Inspection Zone fields from the document face.', 'required' => false, 'options' => ''],
            ['id' => 'surname_primary_identifier', 'type' => 'text', 'label' => 'Surname or Primary Identifier', 'hint' => 'Capture as printed in the VIZ.', 'required' => true, 'options' => ''],
            ['id' => 'given_names_secondary_identifier', 'type' => 'text', 'label' => 'Given Names or Secondary Identifier', 'hint' => 'Capture as printed in the VIZ.', 'required' => false, 'options' => ''],
            ['id' => 'full_name_viz', 'type' => 'text', 'label' => 'Full Name in VIZ', 'hint' => 'Use when the document does not separate surname and given names clearly.', 'required' => false, 'options' => ''],
            ['id' => 'nationality_code', 'type' => 'text', 'label' => 'Nationality Code', 'hint' => 'Three-letter nationality code where available.', 'required' => true, 'options' => ''],
            ['id' => 'date_of_birth', 'type' => 'date', 'label' => 'Date of Birth', 'hint' => '', 'required' => true, 'options' => ''],
            ['id' => 'date_of_birth_check_digit', 'type' => 'text', 'label' => 'Date of Birth Check Digit', 'hint' => 'Single MRZ check digit if available.', 'required' => false, 'options' => ''],
            ['id' => 'sex', 'type' => 'select_one', 'label' => 'Sex', 'hint' => 'Use the value shown in the document.', 'required' => false, 'options' => "m|M\nf|F\nx|X\nunspecified|Unspecified"],
            ['id' => 'place_of_birth', 'type' => 'text', 'label' => 'Place of Birth', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'holder_signature_present', 'type' => 'select_one', 'label' => 'Holder Signature Present', 'hint' => '', 'required' => false, 'options' => "yes|Yes\nno|No\nnot_applicable|Not applicable"],
            ['id' => 'holder_photo_present', 'type' => 'select_one', 'label' => 'Holder Photo Present', 'hint' => '', 'required' => true, 'options' => "yes|Yes\nno|No\nunclear|Unclear"],
            ['id' => 'holder_photo_quality', 'type' => 'select_one', 'label' => 'Holder Photo Quality', 'hint' => '', 'required' => false, 'options' => "clear|Clear\ndamaged|Damaged\naltered|Suspected altered\nnot_visible|Not visible"],

            ['id' => 'section_mrz_capture', 'type' => 'note', 'label' => 'MRZ Capture', 'hint' => 'Machine Readable Zone fields and check digits.', 'required' => false, 'options' => ''],
            ['id' => 'mrz_format', 'type' => 'select_one', 'label' => 'MRZ Format', 'hint' => '', 'required' => true, 'options' => "td3_two_line_44|TD3: 2 lines x 44 characters\ntd2_two_line_36|TD2: 2 lines x 36 characters\ntd1_three_line_30|TD1: 3 lines x 30 characters\nmrv_a_two_line_44|MRV-A: 2 lines x 44 characters\nmrv_b_two_line_36|MRV-B: 2 lines x 36 characters\nnot_available|Not available"],
            ['id' => 'mrz_line_1', 'type' => 'text', 'label' => 'MRZ Line 1', 'hint' => 'Optional until MRZ scanning is added.', 'required' => false, 'options' => ''],
            ['id' => 'mrz_line_2', 'type' => 'text', 'label' => 'MRZ Line 2', 'hint' => 'Optional until MRZ scanning is added.', 'required' => false, 'options' => ''],
            ['id' => 'mrz_line_3', 'type' => 'text', 'label' => 'MRZ Line 3', 'hint' => 'For TD1 documents.', 'required' => false, 'options' => ''],
            ['id' => 'mrz_document_code', 'type' => 'text', 'label' => 'MRZ Document Code', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'mrz_issuing_state', 'type' => 'text', 'label' => 'MRZ Issuing State', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'mrz_primary_identifier', 'type' => 'text', 'label' => 'MRZ Primary Identifier', 'hint' => 'Surname or primary identifier as encoded in the MRZ.', 'required' => false, 'options' => ''],
            ['id' => 'mrz_secondary_identifier', 'type' => 'text', 'label' => 'MRZ Secondary Identifier', 'hint' => 'Given names or secondary identifiers as encoded in the MRZ.', 'required' => false, 'options' => ''],
            ['id' => 'mrz_document_number', 'type' => 'text', 'label' => 'MRZ Document Number', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'mrz_document_number_check_digit', 'type' => 'text', 'label' => 'MRZ Document Number Check Digit', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'mrz_nationality', 'type' => 'text', 'label' => 'MRZ Nationality', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'mrz_date_of_birth', 'type' => 'text', 'label' => 'MRZ Date of Birth', 'hint' => 'YYMMDD or filler characters as encoded.', 'required' => false, 'options' => ''],
            ['id' => 'mrz_date_of_birth_check_digit', 'type' => 'text', 'label' => 'MRZ Date of Birth Check Digit', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'mrz_sex', 'type' => 'select_one', 'label' => 'MRZ Sex', 'hint' => '', 'required' => false, 'options' => "m|M\nf|F\nx|X\nunspecified|Unspecified"],
            ['id' => 'mrz_expiry_date', 'type' => 'text', 'label' => 'MRZ Expiry Date', 'hint' => 'YYMMDD or filler characters as encoded.', 'required' => false, 'options' => ''],
            ['id' => 'mrz_expiry_date_check_digit', 'type' => 'text', 'label' => 'MRZ Expiry Date Check Digit', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'mrz_optional_data', 'type' => 'text', 'label' => 'MRZ Optional Data', 'hint' => 'Optional/personal number area, as encoded.', 'required' => false, 'options' => ''],
            ['id' => 'mrz_optional_data_check_digit', 'type' => 'text', 'label' => 'MRZ Optional Data Check Digit', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'mrz_composite_check_digit', 'type' => 'text', 'label' => 'MRZ Composite Check Digit', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'mrz_check_result', 'type' => 'select_one', 'label' => 'MRZ Check Result', 'hint' => '', 'required' => true, 'options' => "not_checked|Not checked\npassed|Passed\nfailed|Failed\nmanual_review|Manual review"],

            ['id' => 'section_viz_mrz_comparison', 'type' => 'note', 'label' => 'VIZ and MRZ Comparison', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'name_match_result', 'type' => 'select_one', 'label' => 'Name Match Result', 'hint' => 'Compare VIZ, MRZ, traveler, and supporting systems.', 'required' => true, 'options' => "match|Match\nminor_difference|Minor difference\nmismatch|Mismatch\nnot_checked|Not checked"],
            ['id' => 'document_number_match_result', 'type' => 'select_one', 'label' => 'Document Number Match Result', 'hint' => '', 'required' => true, 'options' => "match|Match\nmismatch|Mismatch\nnot_checked|Not checked"],
            ['id' => 'nationality_match_result', 'type' => 'select_one', 'label' => 'Nationality Match Result', 'hint' => '', 'required' => false, 'options' => "match|Match\nmismatch|Mismatch\nnot_checked|Not checked"],
            ['id' => 'birth_date_match_result', 'type' => 'select_one', 'label' => 'Birth Date Match Result', 'hint' => '', 'required' => false, 'options' => "match|Match\nmismatch|Mismatch\nnot_checked|Not checked"],
            ['id' => 'expiry_date_match_result', 'type' => 'select_one', 'label' => 'Expiry Date Match Result', 'hint' => '', 'required' => true, 'options' => "match|Match\nmismatch|Mismatch\nexpired|Expired\nnot_checked|Not checked"],
            ['id' => 'transliteration_notes', 'type' => 'text', 'label' => 'Transliteration Notes', 'hint' => 'Record name transliteration, filler-character, or spelling observations.', 'required' => false, 'options' => ''],

            ['id' => 'section_physical_security', 'type' => 'note', 'label' => 'Physical and Security Inspection', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'document_condition', 'type' => 'select_one', 'label' => 'Document Condition', 'hint' => '', 'required' => true, 'options' => "good|Good\nworn|Worn\ndamaged|Damaged\naltered|Suspected altered"],
            ['id' => 'bio_data_page_condition', 'type' => 'select_one', 'label' => 'Bio Data Page Condition', 'hint' => '', 'required' => false, 'options' => "good|Good\ndamaged|Damaged\naltered|Suspected altered\nmissing|Missing"],
            ['id' => 'laminate_or_overlay_condition', 'type' => 'select_one', 'label' => 'Laminate or Overlay Condition', 'hint' => '', 'required' => false, 'options' => "intact|Intact\nlifted|Lifted\nbubbled|Bubbled\ncut|Cut\nnot_applicable|Not applicable"],
            ['id' => 'security_features_checked', 'type' => 'select_multiple', 'label' => 'Security Features Checked', 'hint' => '', 'required' => false, 'options' => "uv|UV features\nwatermark|Watermark\nmicroprint|Microprint\nhologram|Hologram\nlaser_perforation|Laser perforation\noptically_variable|Optically variable feature\nsubstrate|Substrate/security paper\nphoto_integration|Photo integration"],
            ['id' => 'tamper_indicators', 'type' => 'select_multiple', 'label' => 'Tamper Indicators', 'hint' => '', 'required' => false, 'options' => "none|None observed\nphoto_substitution|Possible photo substitution\ndata_page_alteration|Data page alteration\npage_substitution|Page substitution\nchemical_erasures|Chemical erasures\nmechanical_damage|Mechanical damage\ncounterfeit_suspected|Counterfeit suspected"],
            ['id' => 'physical_security_result', 'type' => 'select_one', 'label' => 'Physical Security Result', 'hint' => '', 'required' => true, 'options' => "accepted|Accepted\nrefer|Refer for secondary inspection\nreject|Reject document"],

            ['id' => 'section_emrtd_chip', 'type' => 'note', 'label' => 'eMRTD Chip Inspection', 'hint' => 'Use where a contactless IC/ePassport inspection is available.', 'required' => false, 'options' => ''],
            ['id' => 'contactless_ic_present', 'type' => 'select_one', 'label' => 'Contactless IC Present', 'hint' => '', 'required' => false, 'options' => "yes|Yes\nno|No\nunknown|Unknown\nnot_applicable|Not applicable"],
            ['id' => 'chip_read_result', 'type' => 'select_one', 'label' => 'Chip Read Result', 'hint' => '', 'required' => false, 'options' => "not_checked|Not checked\nread_success|Read success\nread_failed|Read failed\nnot_present|Not present"],
            ['id' => 'chip_access_protocol', 'type' => 'select_one', 'label' => 'Chip Access Protocol', 'hint' => '', 'required' => false, 'options' => "not_checked|Not checked\nbac|BAC\npace|PACE\nother|Other"],
            ['id' => 'passive_authentication_result', 'type' => 'select_one', 'label' => 'Passive Authentication Result', 'hint' => '', 'required' => false, 'options' => "not_checked|Not checked\npassed|Passed\nfailed|Failed\ninconclusive|Inconclusive"],
            ['id' => 'chip_authentication_result', 'type' => 'select_one', 'label' => 'Chip Authentication Result', 'hint' => '', 'required' => false, 'options' => "not_checked|Not checked\npassed|Passed\nfailed|Failed\ninconclusive|Inconclusive"],
            ['id' => 'chip_face_image_match', 'type' => 'select_one', 'label' => 'Chip Face Image Match', 'hint' => '', 'required' => false, 'options' => "not_checked|Not checked\nmatch|Match\nmismatch|Mismatch\ninconclusive|Inconclusive"],
            ['id' => 'lds_data_groups_read', 'type' => 'select_multiple', 'label' => 'LDS Data Groups Read', 'hint' => '', 'required' => false, 'options' => "dg1|DG1 MRZ data\ndg2|DG2 face image\ndg3|DG3 fingerprints\ndg4|DG4 iris\ndg5|DG5 displayed portrait\ndg7|DG7 signature\ndg11|DG11 additional personal details\ndg12|DG12 additional document details"],
            ['id' => 'chip_inspection_notes', 'type' => 'text', 'label' => 'Chip Inspection Notes', 'hint' => '', 'required' => false, 'options' => ''],

            ['id' => 'section_visa_or_authorization', 'type' => 'note', 'label' => 'Visa or Travel Authorization', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'visa_required', 'type' => 'select_one', 'label' => 'Visa Required', 'hint' => '', 'required' => false, 'options' => "yes|Yes\nno|No\nunknown|Unknown"],
            ['id' => 'visa_type', 'type' => 'text', 'label' => 'Visa Type', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'visa_number', 'type' => 'text', 'label' => 'Visa Number', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'visa_issuing_state', 'type' => 'text', 'label' => 'Visa Issuing State', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'visa_valid_from', 'type' => 'date', 'label' => 'Visa Valid From', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'visa_valid_until', 'type' => 'date', 'label' => 'Visa Valid Until', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'visa_entries', 'type' => 'select_one', 'label' => 'Visa Entries', 'hint' => '', 'required' => false, 'options' => "single|Single\nmultiple|Multiple\nunknown|Unknown"],
            ['id' => 'intended_stay_days', 'type' => 'integer', 'label' => 'Intended Stay Days', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'visa_mrz_line_1', 'type' => 'text', 'label' => 'Visa MRZ Line 1', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'visa_mrz_line_2', 'type' => 'text', 'label' => 'Visa MRZ Line 2', 'hint' => '', 'required' => false, 'options' => ''],

            ['id' => 'section_travel_context', 'type' => 'note', 'label' => 'Travel Context', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'transport_mode', 'type' => 'select_one', 'label' => 'Transport Mode', 'hint' => '', 'required' => false, 'options' => "land|Land\nair|Air\nsea|Sea\nriver|River\nother|Other"],
            ['id' => 'transport_reference', 'type' => 'text', 'label' => 'Transport Reference', 'hint' => 'Vehicle plate, flight number, vessel, or convoy reference.', 'required' => false, 'options' => ''],
            ['id' => 'origin_country', 'type' => 'select_one', 'label' => 'From Country', 'hint' => 'Type to search or choose the country the traveller is coming from.', 'required' => false, 'option_source' => 'countries:all', 'options' => ''],
            ['id' => 'origin_location', 'type' => 'select_one', 'label' => 'From Location', 'hint' => 'Choose a frequent place, or choose Other location and type it on the next question.', 'required' => false, 'option_source' => 'locations:all', 'options' => ''],
            ['id' => 'origin_location_other', 'type' => 'text', 'label' => 'From Location if Other', 'hint' => 'Complete only when From Location is Other location or the place is missing from the list.', 'required' => false, 'options' => ''],
            ['id' => 'destination_country', 'type' => 'select_one', 'label' => 'To Country', 'hint' => 'Type to search or choose the country the traveller is going to.', 'required' => false, 'option_source' => 'countries:all', 'options' => ''],
            ['id' => 'destination_location', 'type' => 'select_one', 'label' => 'To Location', 'hint' => 'Choose a frequent place, or choose Other location and type it on the next question.', 'required' => false, 'option_source' => 'locations:all', 'options' => ''],
            ['id' => 'destination_location_other', 'type' => 'text', 'label' => 'To Location if Other', 'hint' => 'Complete only when To Location is Other location or the place is missing from the list.', 'required' => false, 'options' => ''],
            ['id' => 'purpose_of_travel', 'type' => 'select_one', 'label' => 'Purpose of Travel', 'hint' => '', 'required' => false, 'options' => "tourism|Tourism\nbusiness|Business\nfamily|Family visit\nstudy|Study\nwork|Work\ntransit|Transit\nofficial|Official\nother|Other"],
            ['id' => 'intended_address', 'type' => 'text', 'label' => 'Intended Address', 'hint' => '', 'required' => false, 'options' => ''],

            ['id' => 'section_decision', 'type' => 'note', 'label' => 'Officer Decision and Follow-up', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'watchlist_check_result', 'type' => 'select_one', 'label' => 'Watchlist Check Result', 'hint' => '', 'required' => true, 'options' => "not_checked|Not checked\nno_hit|No hit\npossible_hit|Possible hit\nconfirmed_hit|Confirmed hit"],
            ['id' => 'risk_indicators', 'type' => 'select_multiple', 'label' => 'Risk Indicators', 'hint' => '', 'required' => false, 'options' => "none|None observed\ndocument_issue|Document concern\nidentity_issue|Identity concern\ntravel_pattern|Travel pattern concern\nvisa_issue|Visa or authorization concern\nwatchlist|Watchlist concern\nother|Other"],
            ['id' => 'officer_decision', 'type' => 'select_one', 'label' => 'Officer Decision', 'hint' => '', 'required' => true, 'options' => "admitted|Admitted\nsecondary_review|Secondary review\nrefused|Refused\nreferred|Referred to supervisor\nseized_document|Document seized"],
            ['id' => 'referral_reason', 'type' => 'text', 'label' => 'Referral Reason', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'observations', 'type' => 'text', 'label' => 'Observations', 'hint' => 'Record document condition, identity concerns, referral notes, or final remarks.', 'required' => false, 'options' => ''],
        ];
    }

    private function basicBorderMovementFields(): array
    {
        return [
            ['id' => 'movement_type', 'type' => 'select_one', 'label' => 'Movement Type', 'hint' => '', 'required' => true, 'options' => "entry|Entry\nexit|Exit"],
            ['id' => 'movement_datetime', 'type' => 'datetime', 'label' => 'Movement Date and Time', 'hint' => '', 'required' => true, 'options' => ''],
            ['id' => 'origin_location', 'type' => 'select_one', 'label' => 'From Location', 'hint' => 'Choose a frequent place, or choose Other location and type it on the next question.', 'required' => false, 'option_source' => 'locations:all', 'options' => ''],
            ['id' => 'origin_location_other', 'type' => 'text', 'label' => 'From Location if Other', 'hint' => 'Complete only when From Location is Other location or the place is missing from the list.', 'required' => false, 'options' => ''],
            ['id' => 'destination_location', 'type' => 'select_one', 'label' => 'To Location', 'hint' => 'Choose a frequent place, or choose Other location and type it on the next question.', 'required' => false, 'option_source' => 'locations:all', 'options' => ''],
            ['id' => 'destination_location_other', 'type' => 'text', 'label' => 'To Location if Other', 'hint' => 'Complete only when To Location is Other location or the place is missing from the list.', 'required' => false, 'options' => ''],
            ['id' => 'full_name', 'type' => 'text', 'label' => 'Full Name', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'id_number', 'type' => 'text', 'label' => 'Document Number', 'hint' => '', 'required' => true, 'options' => ''],
            ['id' => 'nationality', 'type' => 'select_one', 'label' => 'Nationality', 'hint' => 'Type to search or choose the traveller nationality.', 'required' => true, 'option_source' => 'countries:all', 'options' => ''],
        ];
    }

    private function customsGoodsDeclarationFields(): array
    {
        return [
            ['id' => 'inspection_datetime', 'type' => 'datetime', 'label' => 'Inspection Date and Time', 'hint' => '', 'required' => true, 'options' => ''],
            ['id' => 'declaration_reference', 'type' => 'text', 'label' => 'Declaration Reference', 'hint' => 'Local declaration, manifest, receipt, or case reference.', 'required' => false, 'options' => ''],
            ['id' => 'customs_procedure', 'type' => 'select_one', 'label' => 'Customs Procedure', 'hint' => 'WCO-aligned procedure category.', 'required' => true, 'options' => "import|Import\nexport|Export\ntransit|Transit\ntemporary_admission|Temporary admission\nre_export|Re-export\nwarehousing|Warehousing\nother|Other"],
            ['id' => 'control_channel', 'type' => 'select_one', 'label' => 'Control Channel', 'hint' => '', 'required' => false, 'options' => "green|Green\nblue|Blue\nyellow|Yellow\nred|Red\nmanual|Manual referral"],
            ['id' => 'declarant_name', 'type' => 'text', 'label' => 'Declarant Name', 'hint' => '', 'required' => true, 'options' => ''],
            ['id' => 'declarant_role', 'type' => 'select_one', 'label' => 'Declarant Role', 'hint' => '', 'required' => false, 'options' => "traveller|Traveller\ncarrier|Carrier\nbroker|Customs broker\ntrader|Trader\nagent|Agent\nother|Other"],
            ['id' => 'document_number', 'type' => 'text', 'label' => 'ID or Travel Document Number', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'consignor_name', 'type' => 'text', 'label' => 'Consignor Name', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'consignee_name', 'type' => 'text', 'label' => 'Consignee Name', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'transport_mode', 'type' => 'select_one', 'label' => 'Mode of Transport', 'hint' => '', 'required' => true, 'options' => "road|Road\nair|Air\nsea|Sea\nrail|Rail\nriver|River\npostal|Postal\nother|Other"],
            ['id' => 'vehicle_or_container', 'type' => 'text', 'label' => 'Vehicle or Container Reference', 'hint' => 'Plate number, container number, or convoy reference.', 'required' => false, 'options' => ''],
            ['id' => 'carrier_reference', 'type' => 'text', 'label' => 'Carrier or Manifest Reference', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'goods_category', 'type' => 'select_one', 'label' => 'Goods Category', 'hint' => '', 'required' => true, 'options' => "food|Food or agriculture\nfuel|Fuel or petroleum\nmedicine|Medicine or health products\nmachinery|Machinery or parts\nconsumer_goods|Consumer goods\ncash|Cash or monetary instruments\nrestricted|Restricted goods\nother|Other"],
            ['id' => 'goods_description', 'type' => 'text', 'label' => 'Goods Description', 'hint' => '', 'required' => true, 'options' => ''],
            ['id' => 'hs_code', 'type' => 'text', 'label' => 'HS Commodity Code', 'hint' => 'Use HS heading/subheading when available.', 'required' => false, 'options' => ''],
            ['id' => 'origin_country', 'type' => 'select_one', 'label' => 'Country of Origin', 'hint' => 'Type to search or choose the country of origin.', 'required' => false, 'option_source' => 'countries:all', 'options' => ''],
            ['id' => 'export_country', 'type' => 'select_one', 'label' => 'Country of Export', 'hint' => 'Type to search or choose the export country.', 'required' => false, 'option_source' => 'countries:all', 'options' => ''],
            ['id' => 'declared_quantity', 'type' => 'decimal', 'label' => 'Declared Quantity', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'quantity_unit', 'type' => 'text', 'label' => 'Quantity Unit', 'hint' => 'Examples: kg, litres, cartons, units.', 'required' => false, 'options' => ''],
            ['id' => 'declared_value', 'type' => 'decimal', 'label' => 'Declared Value', 'hint' => 'Use local currency or specify currency in remarks.', 'required' => false, 'options' => ''],
            ['id' => 'currency_code', 'type' => 'text', 'label' => 'Currency Code', 'hint' => 'ISO 4217 code where available.', 'required' => false, 'options' => ''],
            ['id' => 'duty_or_tax_assessed', 'type' => 'decimal', 'label' => 'Duty or Tax Assessed', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'inspection_result', 'type' => 'select_one', 'label' => 'Inspection Result', 'hint' => '', 'required' => true, 'options' => "cleared|Cleared\nassessed|Duty assessed\nseized|Seized\nreferred|Referred\npending|Pending review"],
            ['id' => 'seizure_reference', 'type' => 'text', 'label' => 'Seizure Reference', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'risk_indicators', 'type' => 'select_multiple', 'label' => 'Risk Indicators', 'hint' => '', 'required' => false, 'options' => "none|None observed\nundeclared_goods|Undeclared goods\nmisclassification|Possible misclassification\nrestricted_goods|Restricted goods\nvaluation_concern|Valuation concern\nfraud_document|Document concern\nother|Other"],
            ['id' => 'remarks', 'type' => 'text', 'label' => 'Remarks', 'hint' => '', 'required' => false, 'options' => ''],
        ];
    }

    private function securityIncidentFields(): array
    {
        return [
            ['id' => 'incident_datetime', 'type' => 'datetime', 'label' => 'Incident Date and Time', 'hint' => '', 'required' => true, 'options' => ''],
            ['id' => 'incident_reference', 'type' => 'text', 'label' => 'Incident Reference', 'hint' => 'Local incident, watchlist, patrol, or supervisor reference.', 'required' => false, 'options' => ''],
            ['id' => 'incident_type', 'type' => 'select_one', 'label' => 'Incident Type', 'hint' => '', 'required' => true, 'options' => "irregular_crossing|Irregular crossing\nsmuggling|Smuggling\ntrafficking|Human trafficking concern\nviolence|Violence or threat\nwanted_person|Wanted person or watchlist\ncontraband|Contraband\npublic_order|Public order\nother|Other"],
            ['id' => 'severity', 'type' => 'select_one', 'label' => 'Severity', 'hint' => '', 'required' => true, 'options' => "low|Low\nmedium|Medium\nhigh|High\ncritical|Critical"],
            ['id' => 'detection_source', 'type' => 'select_one', 'label' => 'Detection Source', 'hint' => '', 'required' => false, 'options' => "routine_control|Routine control\npatrol|Patrol\ncommunity_report|Community report\nwatchlist|Watchlist hit\ncustoms_inspection|Customs inspection\nhealth_screening|Health screening\nother_agency|Other agency\nother|Other"],
            ['id' => 'location_description', 'type' => 'text', 'label' => 'Location Description', 'hint' => 'Describe the exact crossing, road, trail, counter, or checkpoint.', 'required' => false, 'options' => ''],
            ['id' => 'persons_involved_count', 'type' => 'integer', 'label' => 'Persons Involved Count', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'nationalities_involved', 'type' => 'text', 'label' => 'Nationalities Involved', 'hint' => 'Comma-separated when multiple.', 'required' => false, 'options' => ''],
            ['id' => 'goods_or_items_involved', 'type' => 'text', 'label' => 'Goods or Items Involved', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'incident_summary', 'type' => 'text', 'label' => 'Incident Summary', 'hint' => '', 'required' => true, 'options' => ''],
            ['id' => 'action_taken', 'type' => 'select_multiple', 'label' => 'Action Taken', 'hint' => '', 'required' => true, 'options' => "reported|Reported to supervisor\nreferred_police|Referred to police\nreferred_customs|Referred to customs\nreferred_health|Referred to health\nperson_detained|Person detained\ngoods_seized|Goods seized\nresolved|Resolved at post\nother|Other"],
            ['id' => 'agency_notified', 'type' => 'select_multiple', 'label' => 'Agency Notified', 'hint' => '', 'required' => false, 'options' => "immigration|Immigration\ncustoms|Customs\npolice|Police\nmilitary|Military\nhealth|Health\nintelligence|Intelligence\nlocal_authority|Local authority\nnone|None"],
            ['id' => 'referral_reference', 'type' => 'text', 'label' => 'Referral Reference', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'evidence_recorded', 'type' => 'select_multiple', 'label' => 'Evidence Recorded', 'hint' => '', 'required' => false, 'options' => "none|None\nidentity_document|Identity document\nvehicle_reference|Vehicle reference\ngoods_photo|Goods photo\nstatement|Statement\nlocation_coordinates|Location coordinates\nother|Other"],
            ['id' => 'follow_up_required', 'type' => 'select_one', 'label' => 'Follow-up Required', 'hint' => '', 'required' => true, 'options' => "yes|Yes\nno|No"],
            ['id' => 'follow_up_owner', 'type' => 'text', 'label' => 'Follow-up Owner', 'hint' => 'Officer, unit, or agency responsible.', 'required' => false, 'options' => ''],
            ['id' => 'remarks', 'type' => 'text', 'label' => 'Remarks', 'hint' => '', 'required' => false, 'options' => ''],
        ];
    }

    private function healthQuarantineScreeningFields(): array
    {
        return [
            ['id' => 'screening_datetime', 'type' => 'datetime', 'label' => 'Screening Date and Time', 'hint' => '', 'required' => true, 'options' => ''],
            ['id' => 'traveller_name', 'type' => 'text', 'label' => 'Traveller Name', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'document_number', 'type' => 'text', 'label' => 'Travel Document Number', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'movement_type', 'type' => 'select_one', 'label' => 'Movement Type', 'hint' => '', 'required' => true, 'options' => "entry|Entry\nexit|Exit\ntransit|Transit"],
            ['id' => 'transport_mode', 'type' => 'select_one', 'label' => 'Mode of Transport', 'hint' => '', 'required' => false, 'options' => "road|Road\nair|Air\nsea|Sea\nrail|Rail\nriver|River\nother|Other"],
            ['id' => 'vehicle_or_conveyance', 'type' => 'text', 'label' => 'Vehicle or Conveyance Reference', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'origin_country', 'type' => 'select_one', 'label' => 'From Country', 'hint' => 'Type to search or choose the country connected to the incident.', 'required' => false, 'option_source' => 'countries:all', 'options' => ''],
            ['id' => 'visited_affected_area', 'type' => 'select_one', 'label' => 'Visited Affected Area', 'hint' => '', 'required' => true, 'options' => "no|No\nyes|Yes\nunknown|Unknown"],
            ['id' => 'contact_phone', 'type' => 'text', 'label' => 'Contact Phone', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'contact_address', 'type' => 'text', 'label' => 'Contact Address', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'symptoms_observed', 'type' => 'select_multiple', 'label' => 'Symptoms Observed', 'hint' => '', 'required' => false, 'options' => "none|None observed\nfever|Fever\ncough|Cough\nrash|Rash\nvomiting|Vomiting\ndiarrhea|Diarrhea\nbleeding|Unusual bleeding\nweakness|Severe weakness\nother|Other"],
            ['id' => 'temperature_celsius', 'type' => 'decimal', 'label' => 'Temperature Celsius', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'recent_exposure_risk', 'type' => 'select_one', 'label' => 'Recent Exposure Risk', 'hint' => '', 'required' => true, 'options' => "none_reported|None reported\npossible|Possible exposure\nconfirmed|Confirmed exposure\nunknown|Unknown"],
            ['id' => 'vaccination_or_prophylaxis_document', 'type' => 'select_one', 'label' => 'Vaccination or Prophylaxis Document', 'hint' => '', 'required' => false, 'options' => "not_requested|Not requested\nvalid|Valid\ninvalid|Invalid\nnot_available|Not available"],
            ['id' => 'public_health_measure', 'type' => 'select_multiple', 'label' => 'Public Health Measure', 'hint' => '', 'required' => false, 'options' => "none|None\nhealth_advice|Health advice given\nmonitoring|Monitoring advised\nmask|Mask or infection-control guidance\nsample_collected|Sample collected\nisolated|Isolated\nreferred|Referred"],
            ['id' => 'screening_result', 'type' => 'select_one', 'label' => 'Screening Result', 'hint' => '', 'required' => true, 'options' => "cleared|Cleared\nmonitor|Monitor / follow-up\nreferred|Referred to health authority\nisolated|Isolated at point of entry\nrefused|Movement refused"],
            ['id' => 'referral_facility', 'type' => 'text', 'label' => 'Referral Facility', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'health_authority_notified', 'type' => 'select_one', 'label' => 'Health Authority Notified', 'hint' => '', 'required' => true, 'options' => "no|No\nyes|Yes\nnot_required|Not required"],
            ['id' => 'notification_reference', 'type' => 'text', 'label' => 'Notification Reference', 'hint' => '', 'required' => false, 'options' => ''],
            ['id' => 'public_health_notes', 'type' => 'text', 'label' => 'Public Health Notes', 'hint' => '', 'required' => false, 'options' => ''],
        ];
    }
}

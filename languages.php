<?php
/**
 * Language Support for CYCLOAN Registration
 * Supports English and Tagalog
 */

// Get user's language preference from session or default to English
$current_language = isset($_SESSION['language']) ? $_SESSION['language'] : 'en';

// Translation arrays
$translations = [
    'en' => [
        // Page titles and headers
        'page_title' => 'CYCLOAN - CLDD Department Registration',
        'form_title' => 'CYCLOAN Registration',

        // Step titles
        'step_personal_info' => 'Personal Information',
        'step_residential' => 'Residential Address',
        'step_business' => 'Business Address',
        'step_spouse' => 'Spouse Information',
        'step_financial' => 'Financial Information',
        'step_security' => 'Account Security',

        // Form labels - Personal Information
        'label_first_name' => 'First Name',
        'label_middle_name' => 'Middle Name',
        'label_last_name' => 'Last Name',
        'label_name_extension' => 'Name Extension',
        'label_nickname' => 'Nick Name',
        'label_birthday' => 'Birthday',
        'label_age' => 'Age',
        'label_birth_place' => 'Birth Place',
        'label_civil_status' => 'Civil Status',
        'label_contact' => 'Contact',
        'label_email' => 'Email',
        'label_confirm_email' => 'Confirm Email',
        'label_facebook' => 'Facebook Link / Username',
        'label_occupation' => 'Occupation',
        'label_reg_voter' => 'Registered Voter?',
        'label_residency_length' => 'Length of Residency',
        'placeholder_confirm_email' => 'Confirm your email',
        'label_monthly_income' => 'Monthly Income',


        // Form labels - Address
        'label_house_no' => 'House/Unit No.',
        'label_street_block' => 'Street/Block/Lot',
        'label_subdivision' => 'Subdivision/Village',
        'label_barangay' => 'Barangay',
        'label_house_ownership' => 'House Ownership',
        'label_complete_address' => 'Complete Address',
        'label_building_unit' => 'Building/Unit No.',
        'label_business_address' => 'Complete Business Address',
        'label_house_unit' => 'House/Unit No.',

        // Common labels
        'label_required' => 'required',
        'label_select' => 'Select',
        'label_other' => 'Other (enter manually)',
        'label_yes' => 'Yes',
        'label_no' => 'No',

        // Buttons
        'btn_next' => 'Next',
        'btn_previous' => 'Previous',
        'btn_submit' => 'Submit',
        'btn_accept' => 'I Accept',
        'btn_decline' => 'Decline',
        'btn_cancel' => 'Cancel',

        // Messages
        'msg_processing' => 'Processing your registration...',
        'msg_please_wait' => 'Please wait while we process your information',
        'msg_success' => 'Registration successful!',
        'msg_error' => 'An error occurred. Please try again.',
        'msg_email_match' => 'Emails match!',
        'msg_email_not_match' => 'Emails do not match',
        'msg_age_required' => 'You must be at least 21 years old',
        'msg_placeholder_enter' => 'Enter',

        // Placeholders
        'placeholder_first_name' => 'Enter your first name',
        'placeholder_middle_name' => 'Enter your middle name',
        'placeholder_last_name' => 'Enter your last name',
        'placeholder_nickname' => 'Enter your nickname',
        'placeholder_extension' => 'Enter name extension',
        'placeholder_birth_place' => 'Enter your birth place',
        'placeholder_phone' => 'Enter 11-digit phone number',
        'placeholder_email' => 'example@email.com',
        'placeholder_facebook' => 'https://www.facebook.com/username',
        'placeholder_occupation' => 'Please specify your occupation',
        'placeholder_residency' => 'e.g., 6m, 1y, 1y6m, 2y, 03/2020',
        // New placeholders and helper texts for consistency
        'placeholder_specify_occupation' => 'Please specify your occupation',
        'help_enter_specific_occupation' => 'Please enter your specific occupation',
        'placeholder_residency_format' => 'e.g., 6m, 1y, 1y6m, 2y, 03/2020',
        'help_residency_format' => 'Format: 6m (months), 2y (years), or 1y6m (combined)',
        'placeholder_house_unit' => 'Enter house/unit number',
        'placeholder_street_block' => 'Enter street/block/lot',
        'placeholder_house_no' => 'Enter house/unit number',
        'placeholder_street' => 'Enter street/block/lot',
        'placeholder_subdivision' => 'Enter subdivision/village',
        'placeholder_building_unit' => 'Enter building/unit number',
        'placeholder_building_no' => 'Enter building/unit number',
        'placeholder_business_address' => 'Enter complete business address',
        'placeholder_enter' => 'Enter',
        'placeholder_contact' => 'Enter your contact number',
        'placeholder_occupation' => 'Type of occupation',
        'placeholder_civil_status' => 'Select civil status',
        'placeholder_residency_length' => 'Enter length of residency',
        // Spouse placeholders
        'placeholder_spouse_first_name' => "Enter spouse's first name",
        'placeholder_spouse_middle_name' => "Enter spouse's middle name",
        'placeholder_spouse_last_name' => "Enter spouse's last name",
        'placeholder_spouse_nickname' => "Enter spouse's nickname",

        // Option labels for dropdowns
        'option_none' => 'None',
        'option_select_option' => 'Select an option',
        'option_select_status' => 'Select Status',
        'option_select_ownership' => 'Select Ownership',
        'option_single' => 'Single',
        'option_married' => 'Married',
        'option_widowed' => 'Widowed',
        'option_separated' => 'Separated',
        'option_live_in' => 'Live In',
        'option_owned' => 'Owned',
        'option_rented' => 'Rented',
        'option_living_with_parents' => 'Living with Parents/Relatives',
        'option_mortgaged' => 'Mortgaged',
        'option_yes_calamba' => 'Yes, Voter of Calamba',
        'option_yes_not_calamba' => 'Yes, Voter but not in Calamba',
        'option_no' => 'No',
        'option_yes' => 'Yes',
        'option_other_manual' => 'Other (enter manually)',
        'option_select_duration' => 'Select duration',
        'option_6_months' => '6 months',
        'option_1_year' => '1 year',
        'option_1y6m' => '1 year 6 months',
        'option_2_years' => '2 years',
        'option_3_years' => '3 years',
        'option_4_years' => '4 years',
        'option_5_years' => '5 years',
        'option_10_years' => '10 years',
        'label_other' => 'Other (enter manually)',
        'label_house_ownership' => 'House Ownership',
        'label_complete_address' => 'Complete Address',
        'label_building_no' => 'Building/Unit No.',
        'label_business_address' => 'Complete Business Address',
        'label_business_address' => 'Kumpletong Adres ng Negosyo',
        'label_income_sources' => 'Pinagmumulan ng Kita',
        'label_monthly_expenses' => 'Monthly Expenses',

        // Data Privacy
        'data_privacy_title' => 'Data Privacy Consent',
        'data_privacy_content' => 'By checking this box, you consent to the collection and processing of your personal data in accordance with the Data Privacy Act of 2012.',

        // Language selector
        'label_language' => 'Language',
        'lang_english' => 'English',
        'lang_tagalog' => 'Tagalog',
    ],
    'tl' => [
        // Page titles and headers
        'page_title' => 'CYCLOAN - CLDD Department Registration',
        'form_title' => 'CYCLOAN Pagrehistro',

        // Step titles
        'step_personal_info' => 'Personal na Impormasyon',
        'step_residential' => 'Adres ng Tahanan',
        'step_business' => 'Adres ng Negosyo',
        'step_spouse' => 'Impormasyon ng Asawa',
        'step_financial' => 'Pinansyal na Impormasyon',
        'step_security' => 'Account Security',

        // Form labels - Personal Information
        'label_first_name' => 'Unang Pangalan',
        'label_middle_name' => 'Gitna ng Pangalan',
        'label_last_name' => 'Apelyido',
        'label_name_extension' => 'Pandagdag sa Pangalan',
        'label_nickname' => 'Palayaw',
        'label_birthday' => 'Kaarawan',
        'label_age' => 'Edad',
        'label_birth_place' => 'Lugar ng Pagsilang',
        'label_civil_status' => 'Kalagayan ng Pamilya',
        'label_contact' => 'Kontakt',
        'label_email' => 'Email',
        'label_confirm_email' => 'Kumpirmahin ang Email',
        'label_fb_account' => 'Facebook Link / Username',
        'label_occupation' => 'Trabaho',
        'label_reg_voter' => 'Rehistradong Botante?',
        'label_residency_length' => 'Tagal ng Paninirahan',
        'label_faceboook' => 'Facebook Link / Username',
        'label_house_unit' => 'Numero ng Bahay/Unit',
        'label_street_block' => 'Kalye/Block/Lote',
        'label_house_unit' => 'Numero ng Bahay/Unit',
        'placeholder_confirm_email' => 'Kumpirmahin ang iyong email',
        'label_building_unit' => 'Numero ng Gusali/Unit',
        'label_business_address' => 'Kumpletong Adres ng Negosyo',
        'label_income_sources' => 'Pinagmumulan ng Kita',
        'label_monthly_expenses' => 'Buwanang Gastos',



        // Form labels - Address
        'label_house_no' => 'Numero ng Bahay/Unit',
        'label_street' => 'Kalye/Block/Lote',
        'label_subdivision' => 'Subdibisyon/Nayboryo',
        'label_barangay' => 'Barangay',
        'label_house_ownership' => 'Pagmamay-ari ng Bahay',
        'label_complete_address' => 'Kumpletong Adres',
        'label_building_no' => 'Numero ng Gusali/Unit',
        'label_business_address' => 'Kumpletong Adres ng Negosyo',
        'placeholder_confirm_email' => 'Kumpirmahin ang iyong email',


        // Common labels
        'label_required' => 'kailangan',
        'label_select' => 'Piliin',
        'label_other' => 'Iba (ipasok ng manual)',
        'label_yes' => 'Oo',
        'label_no' => 'Hindi',

        // Buttons
        'btn_next' => 'Susunod',
        'btn_previous' => 'Nakaraang',
        'btn_submit' => 'Ipadala',
        'btn_accept' => 'Tinatanggap Ko',
        'btn_decline' => 'Tumanggi',
        'btn_cancel' => 'Kanselahin',

        // Messages
        'msg_processing' => 'Pinoproseso ang iyong pagrehistro...',
        'msg_please_wait' => 'Mangyaring maghintay habang sinusulong namin ang iyong impormasyon',
        'msg_success' => 'Matagumpay na pagrehistro!',
        'msg_error' => 'Nagkaroon ng error. Mangyaring subukan muli.',
        'msg_email_match' => 'Tumutugma ang mga email!',
        'msg_email_not_match' => 'Ang mga email ay hindi tumutugma',
        'msg_age_required' => 'Dapat kang maging hindi bababa sa 21 taong gulang',
        'msg_placeholder_enter' => 'Ipasok',

        // Placeholders
        'placeholder_first_name' => 'Iyong unang pangalan',
        'placeholder_middle_name' => 'Iyong gitna ng pangalan',
        'placeholder_last_name' => 'Iyong apelyido',
        'placeholder_nickname' => 'Iyong palayaw',
        'placeholder_extension' => 'Ipasok ang pandagdag sa pangalan',
        'placeholder_birth_place' => 'Iyong lugar ng pagsilang',
        'placeholder_phone' => '11-digit na numero ng telepono',
        'placeholder_email' => 'halimbawa@email.com',
        'placeholder_facebook' => 'https://www.facebook.com/username',
        'placeholder_occupation' => 'Ilagay ang iyong trabaho',
        'placeholder_residency' => 'hal. 6m, 1y, 1y6m, 2y, 03/2020',
        // New placeholders and helper texts for consistency
        'placeholder_specify_occupation' => 'Pakilagay ang iyong trabaho',
        'help_enter_specific_occupation' => 'Pakilagay ang iyong tiyak na trabaho',
        'placeholder_residency_format' => 'hal. 6m, 1y, 1y6m, 2y, 03/2020',
        'help_residency_format' => 'Format: 6m (buwan), 2y (taon), o 1y6m (pinagsama)',
        'placeholder_house_unit' => 'Ilagay ang numero ng bahay/unit',
        'placeholder_street_block' => 'Ilagay ang kalye/block/lote',
        'placeholder_house_no' => 'Numero ng bahay/unit',
        'placeholder_street' => 'kalye/block/lote',
        'placeholder_subdivision' => 'subdibisyon/nayboryo',
        'placeholder_building_unit' => 'Ilagay ang numero ng gusali/unit',
        'placeholder_building_no' => 'numero ng gusali/unit',
        'placeholder_business_address' => 'Kumpletong adres ng negosyo',
        'placeholder_enter' => 'Ipasok',
        'placeholder_contact' => 'Ilagay ang iyong contact number',
        'placeholder_occupation' => 'Uri ng trabaho',
        'placeholder_civil_status' => 'Piliin ang kalagayan ng pamilya',
        'placeholder_residency_length' => 'Ilagay ang tagal ng paninirahan',
        // Spouse placeholders
        'placeholder_spouse_first_name' => 'Ilagay ang unang pangalan ng asawa',
        'placeholder_spouse_middle_name' => 'Ilagay ang gitnang pangalan ng asawa',
        'placeholder_spouse_last_name' => 'Ilagay ang apelyido ng asawa',
        'placeholder_spouse_nickname' => 'Ilagay ang palayaw ng asawa',

        // Option labels for dropdowns
        'option_none' => 'Walang',
        'option_select_option' => 'Pumili ng opsyon',
        'option_select_status' => 'Pumili ng Kalagayan',
        'option_select_ownership' => 'Pumili ng Pagmamay-ari',
        'option_single' => 'Nag-iisa',
        'option_married' => 'Kasal',
        'option_widowed' => 'Biyuda/Biyudo',
        'option_separated' => 'Hiwalay',
        'option_live_in' => 'Nagsasama',
        'option_owned' => 'Sarili',
        'option_rented' => 'Inaarkila',
        'option_living_with_parents' => 'Nakatira kasama ang Magulang/Kamag-anak',
        'option_mortgaged' => 'Mortgaged',
        'option_yes_calamba' => 'Oo, Botante ng Calamba',
        'option_yes_not_calamba' => 'Oo, Botante ngunit hindi sa Calamba',
        'option_no' => 'Hindi',
        'option_yes' => 'Oo',
        'option_other_manual' => 'Iba (ipasok ng manual)',
        'option_select_duration' => 'Pumili ng tagal',
        'option_6_months' => '6 na buwan',
        'option_1_year' => '1 taon',
        'option_1y6m' => '1 taon 6 na buwan',
        'option_2_years' => '2 taon',
        'option_3_years' => '3 taon',
        'option_4_years' => '4 na taon',
        'option_5_years' => '5 taon',
        'option_10_years' => '10 taon',

        // Data Privacy
        'data_privacy_title' => 'Pahintulot ng Privacy ng Data',
        'data_privacy_content' => 'Sa pamamagitan ng pagsusuri sa kahong ito, sumasang-ayon ka sa pagkolekta at pagpoproseso ng iyong personal na datos alinsunod sa Data Privacy Act ng 2012.',

        // Language selector
        'label_language' => 'Wika',
        'lang_english' => 'English',
        'lang_tagalog' => 'Tagalog',
    ]
];

/**
 * Get translation text
 * @param string $key Translation key
 * @param string $lang Language code (optional, defaults to current language)
 * @return string Translated text or key if not found
 */
function t($key, $lang = null)
{
    global $translations, $current_language;

    $lang = $lang ?? $current_language;

    // Return translated text or the key if not found
    return $translations[$lang][$key] ?? $translations['en'][$key] ?? $key;
}

/**
 * Handle language change via GET parameter
 */
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'tl'])) {
    $_SESSION['language'] = $_GET['lang'];
    $current_language = $_GET['lang'];
    // Redirect to clean URL
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

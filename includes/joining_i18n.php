<?php
/**
 * VK Logistics Hub - Bilingual (English + Telugu) Dictionary for Employee Joining Form & Policies
 * Source Reference: VK_Logistics_Hub_Bilingual_Joining_Form_and_Associate_Policy.pdf (Pages 1 - 9)
 */

class JoiningI18n {
    public static $translations = [
        'en' => [
            // Header & Meta
            'company_name' => 'VK LOGISTICS HUB',
            'company_tagline' => 'YOUR SHIPPING PARTNER',
            'doc_type_employee_record' => 'EMPLOYEE RECORD',
            'doc_type_human_resources' => 'HUMAN RESOURCES',
            'form_title' => 'Employee Information & Joining Form',
            'form_subtitle' => 'For new and existing employees in all departments. Complete the details below and submit the form to HR.',
            'employee_type' => 'Employee type',
            'new_joiner' => 'New joiner',
            'existing_employee' => 'Existing employee',
            'submission_date' => 'Submission date',
            'passport_photo' => 'Passport-size photo',
            
            // Section 1: Employee details
            'sec1_title' => '1. Employee details',
            'employee_id' => 'Employee ID',
            'employee_id_auto_note' => '(Auto-generated for new joiners upon HR approval)',
            'full_name' => 'Full name as shown on ID',
            'father_name' => 'Father\'s name',
            'dob' => 'Date of birth',
            'gender' => 'Gender',
            'gender_male' => 'Male',
            'gender_female' => 'Female',
            'gender_other' => 'Other',
            'mobile_number' => 'Mobile number',
            'alternate_mobile' => 'Alternate mobile number',
            'email_address' => 'Email address',
            'designation' => 'Designation / job role',
            'department' => 'Department',
            'date_of_joining' => 'Date of joining',
            'blood_group' => 'Blood group (optional)',
            'previous_experience' => 'Previous work experience',
            
            // Section 2: Present address
            'sec2_title' => '2. Present address',
            'house_flat_no' => 'House / flat number',
            'street_area' => 'Street / area',
            'village_town' => 'Village / town',
            'mandal' => 'Mandal',
            'district' => 'District',
            'state' => 'State',
            'pincode' => 'PIN code',
            
            // Section 3: Permanent address
            'sec3_title' => '3. Permanent address',
            'same_as_present' => 'Same as present address?',
            'yes' => 'Yes',
            'no' => 'No',
            'permanent_if_different' => 'Permanent address if different',
            
            // Section 4: Identity documents
            'sec4_title' => '4. Identity documents',
            'aadhaar_masked' => 'Aadhaar (masked preferred)',
            'aadhaar_note' => 'Provide Aadhaar only where lawfully required. Prefer masked Aadhaar. Only authorised staff may access these details.',
            'other_id_proof' => 'Other ID proof (optional)',
            'documents_submitted' => 'Documents submitted',
            'verified_by' => 'Verified by',
            
            // Section 5: Emergency contact
            'sec5_title' => '5. Emergency contact',
            'relationship' => 'Relationship',
            'address' => 'Address',
            
            // Section 6 & 7: References
            'sec6_title' => '6. Reference 1',
            'sec7_title' => '7. Reference 2',
            'how_known' => 'Relationship / how known',
            
            // Section 8: Previous employment
            'sec8_title' => '8. Previous employment',
            'previous_employer' => 'Previous employer',
            'previous_designation' => 'Previous designation',
            'previous_employer_phone' => 'Previous employer phone',
            'reason_for_leaving' => 'Reason for leaving',
            
            // Page 4: Employment Terms
            'terms_title' => 'Employment Terms',
            'probation_title' => 'Probation and confirmation',
            'probation_text' => 'New employees serve a three-month probation from their joining date. Management reviews attendance, punctuality, performance, conduct and suitability before confirming employment.',
            'leave_attendance_title' => 'Leave and attendance',
            'leave_attendance_text' => 'Follow the leave policy for your role. Pickup associates should follow the attached Associate Leave & Pickup Incentive Policy. Report on time and tell your manager if you will be late or absent.',
            'resignation_title' => 'Resignation, notice and handover',
            'resignation_text1' => 'Resign in writing, even if you are leaving in the first few days or during probation. Serve the 30-day notice period where your appointment terms and law allow it. Management can approve a shorter notice period or waive it in writing.',
            'resignation_text2' => 'Hand over pending work and return company property, ID cards, uniforms, documents and equipment. Any notice-pay recovery must be allowed by your contract and the law. The company settles earned wages and final dues in line with applicable law.',
            'property_conduct_title' => 'Company property and conduct',
            'property_conduct_text' => 'Use company property and vehicles carefully. Report any loss or damage immediately. Treat customers and colleagues with respect, follow management instructions and keep company and customer information confidential.',
            
            // Page 5: Pickup Associates Summary
            'pickup_badge' => 'PICKUP ASSOCIATES',
            'pickup_summary_title' => 'Leave & Pickup Incentives',
            'pickup_summary_note' => 'This summary is for pickup associates. The full policy is attached at the end of this document.',
            'paid_leave_title' => 'Paid leave and holidays',
            'paid_leave_summary1' => '12 paid annual leave days and 12 listed paid public or festival holidays each calendar year. Company annual leave can be used after probation. Paid public holidays apply during probation. Statutory leave applies from the date you qualify.',
            'paid_leave_summary2' => 'Request planned leave at least seven days ahead. Get written approval from your manager and HR. For sickness or an emergency, notify them immediately. Submit a medical certificate for sick leave and any supporting documents HR requests.',
            'daily_target_title' => 'Daily target and extra pickup bonus',
            'daily_target_summary1' => 'Your written salary agreement sets a daily target of 3 or 4 completed pickups. For each verified pickup above your target, you earn the bonus rate HR confirms in writing. For example, 4 completed pickups against a target of 3 earn 1 bonus pickup.',
            'daily_target_summary2' => 'The extra pickup bonus is the only company incentive for this role. Tell your supervisor if a pickup may run past your shift. Log your actual hours and pickup details. Payroll pays any overtime required by law.',
            'training_title' => 'Training',
            'training_summary' => 'The first two weeks are for induction and training. If you leave early, HR checks what pay is due for work and any training that qualifies for pay.',
            
            // Page 6: Employee Declaration & HR Approval
            'declaration_header' => 'SIGNATURES',
            'declaration_title' => 'Employee Declaration & HR Approval',
            'declaration_p1' => 'I confirm that the information and documents I provided are accurate to the best of my knowledge. I have read and understood the employment terms and the policies that apply to my role. If I leave, I will notify management and complete the applicable notice and handover.',
            'declaration_p2' => 'I agree to follow company rules and authorise the company to use my data for employment administration, payroll, statutory compliance and emergency contact, with appropriate safeguards.',
            'pickup_policy_checkbox' => 'Pickup associates only. I acknowledge receipt of the attached Associate Leave & Pickup Incentive Policy.',
            'emp_confirmation_title' => 'Employee confirmation',
            'emp_signature' => 'Employee signature',
            'thumb_impression' => 'Left thumb impression',
            'office_use_title' => 'For office use only',
            'docs_verified_by' => 'Documents verified by',
            'emp_id_assigned' => 'Employee ID assigned',
            'joining_date_confirmed' => 'Joining date confirmed',
            'approved_by' => 'Approved by',
            'hr_signature' => 'HR / Admin signature',
            
            // Pages 7, 8, 9: Full Associate Leave & Pickup Incentive Policy
            'full_policy_title' => 'Associate Leave & Pickup Incentive Policy',
            'full_policy_scope' => 'Applies to VK Logistics Hub pickup associates employed by the company',
            'full_policy_owner' => 'Policy owner: Human Resources',
            
            'policy_sec1_title' => '1. Annual leave and paid holidays',
            'policy_sec1_text1' => 'The company provides 12 paid annual leave days and 12 designated paid public or festival holidays each year. The annual leave and holiday year runs from 1 January to 31 December.',
            'policy_sec1_text2' => 'You can use company annual leave once you pass probation. Paid public holidays apply during probation. You can take any leave the law gives you from the date you qualify, even during probation.',
            'policy_sec1_text3' => 'You earn one extra day of paid annual leave for each full year you work here, starting after your first year. You can carry unused leave into the next year if HR approves it in writing or the law requires it.',
            
            'policy_sec2_title' => '2. Designated paid public and festival holidays',
            'holiday_list' => [
                '1. New Year\'s Day - 1 January',
                '2. Sankranthi',
                '3. Republic Day - 26 January',
                '4. Ramzan (Eid al-Fitr)',
                '5. Ugadi',
                '6. Holi',
                '7. Independence Day - 15 August',
                '8. Vinayaka Chavithi',
                '9. Gandhi Jayanti - 2 October',
                '10. Dasara',
                '11. Diwali',
                '12. Christmas - 25 December'
            ],
            'holiday_note' => 'HR publishes festival dates each year and observes additional mandatory paid holidays where applicable, including May Day (1 May) and Telangana Formation Day (2 June).',
            
            'policy_sec3_title' => '3. How to request leave',
            'policy_sec3_text1' => 'Request planned leave at least 7 days ahead through the HR portal or by email. Get written approval from your manager and HR before taking leave or booking travel. Approval depends on team coverage and workload.',
            'policy_sec3_text2' => 'You can usually take up to 3 annual leave days in a row. Ask for leave either before or after a weekend or public holiday. Get written approval from HR and Management for leave on both sides or for more than 3 days in a row. During festivals and busy periods, approval depends on team coverage.',
            
            'policy_sec4_title' => '4. Returning after a Sunday, holiday or approved leave',
            'policy_sec4_text1' => 'Report on your first scheduled working day after a Sunday, other weekly rest day, public holiday or approved leave. If you need to extend leave, obtain written approval from your Reporting Manager and HR.',
            'policy_sec4_text2' => 'HR records unapproved time missed on a scheduled working day as Leave Without Pay (LWP), subject to applicable law. If Sunday is your normal rest day and you miss Monday without approval, Monday is treated as LWP. The same rule applies on the first scheduled working day after a public holiday or approved leave.',
            'policy_sec4_text3' => 'HR reviews related absences together. Sundays, public holidays and approved paid leave stay paid when the law or your employment terms protect that pay. If you were scheduled and lawfully required to work on a Sunday and missed the shift without approval, the missed work time may be unpaid.',
            'policy_sec4_text4' => 'Repeated unauthorised absence or failure to report or communicate may lead to disciplinary action under company procedure.',
            
            'policy_sec5_title' => '5. Sick leave and emergencies',
            'policy_sec5_text1' => 'Notify your Reporting Manager and HR immediately. For an emergency, explain the reason and expected duration as soon as reasonably possible. For sick leave, submit a medical certificate from a registered medical practitioner immediately, along with other relevant documents HR requests.',
            'policy_sec5_text2' => 'If you cannot get the documents straight away, tell HR and send them as soon as you get them. HR will apply any sick leave you qualify for by law.',
            
            'policy_sec6_title' => '6. Two-week induction and training',
            'policy_sec6_text1' => 'Your first two weeks are for induction and training. HR and Operations record your attendance and activities. If you leave early, HR checks what pay is due for work performed and for any training time payable under the law or your employment terms.',
            'policy_sec6_text2' => 'Example: leaving after 5 days. If none of the five days qualify for payment, no payment is due for those days. If you worked or attended training that qualifies for pay, payroll pays what you earned.',
            
            'policy_sec7_title' => '7. Agreed daily pickup target',
            'policy_sec7_text1' => 'Check your written salary agreement for your daily target. It will be 3 or 4 completed pickups per scheduled working day.',
            'policy_sec7_text2' => 'If you miss your target, record why. Operations will check how many pickups were assigned, distance, parcel weight, time spent, cancellations and delays outside your control. HR handles any confirmed performance or attendance issues under company procedure. Missing the target does not automatically reduce your base pay.',
            
            'policy_sec8_title' => '8. Bonus for extra pickups',
            'policy_sec8_text1' => 'You earn a bonus for each verified pickup above your daily target. HR gives you the rate in writing. Operations checks the booking records and sends the verified count to payroll.',
            'policy_sec8_example1' => '3-pickup target: 3 completed = daily target met. 4 completed = 1 bonus pickup.',
            'policy_sec8_example2' => '4-pickup target: 4 completed = daily target met. 6 completed = 2 bonus pickups.',
            
            'policy_sec9_title' => '9. Shift hours and daily records',
            'policy_sec9_text1' => 'Complete pickups within your scheduled shift. If a pickup is likely to run past your shift, tell your supervisor so they can reassign or reschedule it. Record the hours you actually worked, including any time beyond your shift.',
            'policy_sec9_text2' => 'The extra pickup bonus is the only company incentive for this role. Payroll pays any overtime the law requires.',
            'policy_sec9_text3' => 'Your daily log must show shift and pickup start and finish times, booking references, pickup status, distance, parcel weight in kg and reasons for missed pickups or time worked beyond your shift. Your supervisor checks it before sending it to HR and payroll.',
            
            'policy_admin_title' => 'Policy administration',
            'policy_admin_text' => 'HR will communicate any policy amendments through official company channels. Applicable legal entitlements take precedence.',
            'hr_contact_title' => 'HR contact',
            'hr_emails' => 'hr@vkinnect.com | hr.vkinnect@gmail.com'
        ],

        'te' => [
            // Header & Meta
            'company_name' => 'వికె లాజిస్టిక్స్ హబ్',
            'company_tagline' => 'యువర్ షిప్పింగ్ పార్ట్‌నర్',
            'doc_type_employee_record' => 'ఉద్యోగి రికార్డు',
            'doc_type_human_resources' => 'హ్యూమన్ రిసోర్సెస్',
            'form_title' => 'ఉద్యోగి సమాచారం మరియు చేరిక ఫారమ్',
            'form_subtitle' => 'అన్ని విభాగాల్లోని కొత్త మరియు ప్రస్తుత ఉద్యోగుల కోసం. కింది వివరాలను పూరించి హెచ్‌ఆర్‌కు ఇవ్వండి.',
            'employee_type' => 'ఉద్యోగి రకం',
            'new_joiner' => 'కొత్త ఉద్యోగి',
            'existing_employee' => 'ప్రస్తుత ఉద్యోగి',
            'submission_date' => 'సమర్పించిన తేదీ',
            'passport_photo' => 'పాస్‌పోర్ట్ సైజు ఫోటో',
            
            // Section 1: Employee details
            'sec1_title' => '1. Employee details ఉద్యోగి వివరాలు',
            'employee_id' => 'ఉద్యోగి ఐడి',
            'employee_id_auto_note' => '(కొత్త ఉద్యోగులకు హెచ్‌ఆర్ ఆమోదం తర్వాత ఆటో-జనరేట్ చేయబడుతుంది)',
            'full_name' => 'గుర్తింపు పత్రం ప్రకారం పూర్తి పేరు',
            'father_name' => 'తండ్రి పేరు',
            'dob' => 'పుట్టిన తేదీ',
            'gender' => 'లింగం',
            'gender_male' => 'పురుషుడు',
            'gender_female' => 'స్త్రీ',
            'gender_other' => 'ఇతర',
            'mobile_number' => 'మొబైల్ నంబర్',
            'alternate_mobile' => 'ప్రత్యామ్నాయ మొబైల్ నంబర్',
            'email_address' => 'ఇమెయిల్ చిరునామా',
            'designation' => 'ఉద్యోగ హోదా',
            'department' => 'విభాగం',
            'date_of_joining' => 'ఉద్యోగంలో చేరిన తేదీ',
            'blood_group' => 'రక్త వర్గం (ఐచ్ఛికం)',
            'previous_experience' => 'గత ఉద్యోగ అనుభవం',
            
            // Section 2: Present address
            'sec2_title' => '2. Present address ప్రస్తుత చిరునామా',
            'house_flat_no' => 'ఇంటి / ఫ్లాట్ నంబర్',
            'street_area' => 'వీధి / ప్రాంతం',
            'village_town' => 'గ్రామం / పట్టణం',
            'mandal' => 'మండలం',
            'district' => 'జిల్లా',
            'state' => 'రాష్ట్రం',
            'pincode' => 'పిన్ కోడ్',
            
            // Section 3: Permanent address
            'sec3_title' => '3. Permanent address శాశ్వత చిరునామా',
            'same_as_present' => 'ప్రస్తుత చిరునామానేనా?',
            'yes' => 'అవును',
            'no' => 'కాదు',
            'permanent_if_different' => 'వేరుగా ఉంటే శాశ్వత చిరునామా',
            
            // Section 4: Identity documents
            'sec4_title' => '4. Identity documents గుర్తింపు పత్రాలు',
            'aadhaar_masked' => 'ఆధార్ (మాస్క్ చేసిన ఆధార్‌కు ప్రాధాన్యం)',
            'aadhaar_note' => 'చట్టబద్ధంగా అవసరమైనప్పుడు మాత్రమే ఆధార్ వివరాలు ఇవ్వండి. మాస్క్ చేసిన ఆధార్‌కు ప్రాధాన్యం ఇవ్వండి. అధీకృత సిబ్బంది మాత్రమే ఈ వివరాలను చూడాలి.',
            'other_id_proof' => 'ఇతర గుర్తింపు పత్రం (ఐచ్ఛికం)',
            'documents_submitted' => 'సమర్పించిన పత్రాలు',
            'verified_by' => 'ధృవీకరించిన అధికారి',
            
            // Section 5: Emergency contact
            'sec5_title' => '5. Emergency contact అత్యవసర సంప్రదింపు',
            'relationship' => 'సంబంధం',
            'address' => 'చిరునామా',
            
            // Section 6 & 7: References
            'sec6_title' => '6. Reference 1 మొదటి పరిచయ వ్యక్తి',
            'sec7_title' => '7. Reference 2 రెండవ పరిచయ వ్యక్తి',
            'how_known' => 'సంబంధం / పరిచయం',
            
            // Section 8: Previous employment
            'sec8_title' => '8. Previous employment గత ఉద్యోగ వివరాలు',
            'previous_employer' => 'గత సంస్థ పేరు',
            'previous_designation' => 'గత ఉద్యోగ హోదా',
            'previous_employer_phone' => 'గత సంస్థ ఫోన్ నంబర్',
            'reason_for_leaving' => 'ఉద్యోగం వదిలిన కారణం',
            
            // Page 4: Employment Terms
            'terms_title' => 'ఉద్యోగ నిబంధనలు (Employment Terms)',
            'probation_title' => 'ప్రొబేషన్ మరియు ఉద్యోగ నిర్ధారణ (Probation and confirmation)',
            'probation_text' => 'కొత్త ఉద్యోగులకు చేరిన తేదీ నుంచి మూడు నెలల ప్రొబేషన్ ఉంటుంది. ఉద్యోగాన్ని నిర్ధారించే ముందు హాజరు, సమయపాలన, పనితీరు, ప్రవర్తన మరియు పనికి తగిన సామర్థ్యాన్ని యాజమాన్యం పరిశీలిస్తుంది.',
            'leave_attendance_title' => 'సెలవులు మరియు హాజరు (Leave and attendance)',
            'leave_attendance_text' => 'మీ ఉద్యోగానికి వర్తించే సెలవు విధానాన్ని పాటించండి. పికప్ అసోసియేట్లు జతచేసిన సెలవు మరియు పికప్ ప్రోత్సాహక విధానాన్ని పాటించాలి. సమయానికి విధులకు హాజరవ్వండి. ఆలస్యం లేదా గైర్హాజరు అయితే మేనేజర్‌కు తెలియజేయండి.',
            'resignation_title' => 'రాజీనామా, నోటీసు మరియు బాధ్యతల అప్పగింత (Resignation, notice and handover)',
            'resignation_text1' => 'చేరిన కొద్ది రోజుల్లో లేదా ప్రొబేషన్ సమయంలో మానివేయాలనుకున్నా లిఖితపూర్వకంగా రాజీనామా చేయండి. నియామక నిబంధనలు మరియు చట్టం ప్రకారం వర్తించే 30 రోజుల నోటీసు ఇవ్వండి. యాజమాన్యం తక్కువ నోటీసు కాలాన్ని లేదా మినహాయింపును లిఖితపూర్వకంగా ఆమోదించవచ్చు.',
            'resignation_text2' => 'పెండింగ్ పనులతో పాటు కంపెనీ వస్తువులు, ఐడి కార్డులు, యూనిఫారాలు, పత్రాలు మరియు పరికరాలను అప్పగించండి. ఒప్పందం మరియు చట్టం అనుమతించినప్పుడే నోటీసు జీతాన్ని రికవరీ చేయవచ్చు. సంపాదించిన జీతం మరియు తుది బకాయిలను వర్తించే చట్టం ప్రకారం కంపెనీ చెల్లిస్తుంది.',
            'property_conduct_title' => 'కంపెనీ ఆస్తులు మరియు ప్రవర్తన (Company property and conduct)',
            'property_conduct_text' => 'కంపెనీ ఆస్తులు, వాహనాలను జాగ్రత్తగా ఉపయోగించండి. ఏదైనా నష్టం లేదా వస్తువు పోయినప్పుడు వెంటనే తెలియజేయండి. కస్టమర్లు, సహోద్యోగులతో గౌరవంగా వ్యవహరించండి. యాజమాన్య సూచనలు పాటించి కంపెనీ, కస్టమర్ సమాచారాన్ని గోప్యంగా ఉంచండి.',
            
            // Page 5: Pickup Associates Summary
            'pickup_badge' => 'పికప్ అసోసియేట్లు (PICKUP ASSOCIATES)',
            'pickup_summary_title' => 'సెలవులు మరియు పికప్ ప్రోత్సాహకాలు (Leave & Pickup Incentives)',
            'pickup_summary_note' => 'ఈ సారాంశం పికప్ అసోసియేట్ల కోసం. పూర్తి విధానం ఈ పత్రం చివర జతచేయబడింది.',
            'paid_leave_title' => 'వేతనంతో కూడిన సెలవులు (Paid leave and holidays)',
            'paid_leave_summary1' => 'ప్రతి క్యాలెండర్ సంవత్సరంలో 12 వేతనంతో కూడిన వార్షిక సెలవులు, జాబితాలోని 12 వేతనంతో కూడిన ప్రభుత్వ లేదా పండుగ సెలవులు ఉంటాయి. కంపెనీ వార్షిక సెలవులను ప్రొబేషన్ పూర్తయ్యాక ఉపయోగించవచ్చు. ప్రొబేషన్ సమయంలో కూడా వేతనంతో కూడిన ప్రభుత్వ సెలవులు వర్తిస్తాయి. చట్టబద్ధమైన సెలవులు అర్హత వచ్చిన తేదీ నుంచి వర్తిస్తాయి.',
            'paid_leave_summary2' => 'ముందుగా నిర్ణయించిన సెలవుకు కనీసం ఏడు రోజుల ముందు దరఖాస్తు చేయండి. మేనేజర్ మరియు హెచ్‌ఆర్ నుంచి లిఖితపూర్వక అనుమతి తీసుకోండి. అనారోగ్యం లేదా అత్యవసర పరిస్థితి ఉంటే వెంటనే తెలియజేయండి. అనారోగ్య సెలవుకు వైద్య ధృవీకరణ పత్రం మరియు హెచ్‌ఆర్ కోరిన ఇతర పత్రాలు ఇవ్వండి.',
            'daily_target_title' => 'రోజువారీ లక్ష్యం మరియు అదనపు పికప్ బోనస్ (Daily target and extra pickup bonus)',
            'daily_target_summary1' => 'మీ జీత ఒప్పందంలో రోజుకు 3 లేదా 4 పికప్‌లు పూర్తి చేయాలనే లక్ష్యం ఉంటుంది. లక్ష్యానికి మించి పూర్తి చేసి ధృవీకరించిన ప్రతి పికప్‌కు హెచ్‌ఆర్ లిఖితపూర్వకంగా తెలిపిన రేటు ప్రకారం బోనస్ లభిస్తుంది. ఉదాహరణకు, లక్ష్యం 3 కాగా 4 పికప్‌లు పూర్తి చేస్తే 1 పికప్‌కు బోనస్ వస్తుంది.',
            'daily_target_summary2' => 'ఈ ఉద్యోగానికి కంపెనీ ఇచ్చే ఏకైక ప్రోత్సాహకం అదనపు పికప్ బోనస్. పికప్ షిఫ్ట్ సమయం దాటవచ్చని అనిపిస్తే సూపర్‌వైజర్‌కు చెప్పండి. పని చేసిన సమయం, పికప్ వివరాలు నమోదు చేయండి. చట్టం ప్రకారం చెల్లించాల్సిన అదనపు పని వేతనాన్ని కంపెనీ చెల్లిస్తుంది.',
            'training_title' => 'శిక్షణ (Training)',
            'training_summary' => 'మొదటి రెండు వారాలు పరిచయం మరియు శిక్షణ కోసం. ముందుగానే మానివేస్తే, చేసిన పనికి మరియు వేతనం వర్తించే శిక్షణకు ఎంత చెల్లించాలో హెచ్‌ఆర్ పరిశీలిస్తుంది.',
            
            // Page 6: Employee Declaration & HR Approval
            'declaration_header' => 'సంతకాలు (SIGNATURES)',
            'declaration_title' => 'ఉద్యోగి ప్రకటన మరియు హెచ్‌ఆర్ ఆమోదం (Employee Declaration & HR Approval)',
            'declaration_p1' => 'నేను ఇచ్చిన సమాచారం మరియు పత్రాలు నాకు తెలిసినంతవరకు నిజమైనవని ధృవీకరిస్తున్నాను. ఉద్యోగ నిబంధనలు మరియు నా ఉద్యోగానికి వర్తించే విధానాలను చదివి అర్థం చేసుకున్నాను. ఉద్యోగం మానివేస్తే యాజమాన్యానికి తెలియజేసి, వర్తించే నోటీసు మరియు బాధ్యతల అప్పగింత పూర్తి చేస్తాను.',
            'declaration_p2' => 'కంపెనీ నియమాలను పాటిస్తాను. ఉద్యోగ నిర్వహణ, జీతాల చెల్లింపు, చట్టపరమైన అవసరాలు, అత్యవసర సంప్రదింపుల కోసం తగిన భద్రతతో నా సమాచారాన్ని ఉపయోగించడానికి అంగీకరిస్తున్నాను.',
            'pickup_policy_checkbox' => 'పికప్ అసోసియేట్లకు మాత్రమే. జతచేసిన సెలవు మరియు పికప్ ప్రోత్సాహక విధానం అందుకున్నాను. (I acknowledge receipt of the attached Associate Leave & Pickup Incentive Policy.)',
            'emp_confirmation_title' => 'ఉద్యోగి ధృవీకరణ (Employee confirmation)',
            'emp_signature' => 'ఉద్యోగి సంతకం (Employee signature)',
            'thumb_impression' => 'ఎడమ బొటనవేలు ముద్ర (Left thumb impression)',
            'office_use_title' => 'కార్యాలయ ఉపయోగనికి మాత్రమే (For office use only)',
            'docs_verified_by' => 'పత్రాలను ధృవీకరించిన అధికారి',
            'emp_id_assigned' => 'కేటాయించిన ఉద్యోగి ఐడి',
            'joining_date_confirmed' => 'చేరిన తేదీ నిర్ధారణ',
            'approved_by' => 'ఆమోదించిన అధికారి',
            'hr_signature' => 'హెచ్‌ఆర్ / అడ్మిన్ సంతకం',
            
            // Pages 7, 8, 9: Full Associate Leave & Pickup Incentive Policy
            'full_policy_title' => 'అసోసియేట్ సెలవు మరియు పికప్ ప్రోత్సాహక విధానం (Associate Leave & Pickup Incentive Policy)',
            'full_policy_scope' => 'కంపెనీలో పనిచేసే వికె లాజిస్టిక్స్ హబ్ పికప్ అసోసియేట్లకు వర్తిస్తుంది',
            'full_policy_owner' => 'విధాన యజమాని: హ్యూమన్ రిసోర్సెస్',
            
            'policy_sec1_title' => '1. Annual leave and paid holidays వార్షిక సెలవులు మరియు వేతనంతో కూడిన సెలవులు',
            'policy_sec1_text1' => 'కంపెనీ ప్రతి సంవత్సరం 12 వేతనంతో కూడిన వార్షిక సెలవులను మరియు 12 నిర్దేశిత వేతనంతో కూడిన పబ్లిక్ లేదా పండుగ సెలవులను అందిస్తుంది. వార్షిక సెలవు మరియు సెలవు సంవత్సరం జనవరి 1 నుండి డిసెంబర్ 31 వరకు ఉంటుంది.',
            'policy_sec1_text2' => 'మీరు ప్రొబేషన్ పూర్తి చేసుకున్న తర్వాత కంపెనీ వార్షిక సెలవును ఉపయోగించుకోవచ్చు. ప్రొబేషన్ సమయంలో వేతనంతో కూడిన ప్రభుత్వ సెలవులు వర్తిస్తాయి.',
            'policy_sec1_text3' => 'మొదటి సంవత్సరం తర్వాత మీరు ఇక్కడ పని చేసే ప్రతి పూర్తి సంవత్సరానికి ఒక అదనపు రోజు వేతనంతో కూడిన వార్షిక సెలవును పొందుతారు. హెచ్‌ఆర్ లిఖితపూర్వకంగా ఆమోదిస్తే లేదా చట్టం ప్రకారం అవసరమైతే ఉపయోగించని సెలవులను తరువాతి సంవత్సరానికి తీసుకెళ్లవచ్చు.',
            
            'policy_sec2_title' => '2. Designated paid public and festival holidays నిర్దేశిత వేతనంతో కూడిన ప్రభుత్వ మరియు పండుగ సెలవులు',
            'holiday_list' => [
                '1. న్యూ ఇయర్ డే (New Year\'s Day) - 1 జనవరి',
                '2. సంక్రాంతి (Sankranthi)',
                '3. రిపబ్లిక్ డే (Republic Day) - 26 జనవరి',
                '4. రంజాన్ (Ramzan / Eid al-Fitr)',
                '5. ఉగాది (Ugadi)',
                '6. హోలీ (Holi)',
                '7. స్వాతంత్య్ర దినోత్సవం (Independence Day) - 15 ఆగస్టు',
                '8. వినాయక చవితి (Vinayaka Chavithi)',
                '9. గాంధీ జయంతి (Gandhi Jayanti) - 2 అక్టోబర్',
                '10. దసరా (Dasara)',
                '11. దీపావళి (Diwali)',
                '12. క్రిస్మస్ (Christmas) - 25 డిసెంబర్'
            ],
            'holiday_note' => 'హెచ్‌ఆర్ ప్రతి సంవత్సరం పండుగ తేదీలను ప్రచురిస్తుంది మరియు మే డే (మే 1) మరియు తెలంగాణ ఆవిర్భావ దినోత్సవం (జూన్ 2) సహా అదనపు తప్పనిసరి వేతనంతో కూడిన సెలవులను పాటిస్తుంది.',
            
            'policy_sec3_title' => '3. How to request leave సెలవును ఎలా అభ్యర్థించాలి',
            'policy_sec3_text1' => 'హెచ్‌ఆర్ పోర్టల్ ద్వారా లేదా ఇమెయిల్ ద్వారా కనీసం 7 రోజుల ముందుగానే నిర్ణీత సెలవును అభ్యర్థించండి. సెలవు తీసుకునే ముందు లేదా ప్రయాణ బుకింగ్ చేసుకునే ముందు మీ మేనేజర్ మరియు హెచ్‌ఆర్ నుంచి లిఖితపూర్వక అనుమతి పొందండి.',
            'policy_sec3_text2' => 'మీరు సాధారణంగా వరుసగా 3 వార్షిక సెలవు రోజుల వరకు తీసుకోవచ్చు. వారాంతం లేదా పబ్లిక్ హాలిడే ముందు లేదా తర్వాత సెలవు కోరవచ్చు. పండుగలు మరియు రద్దీ సమయాల్లో బృందం కవరేజ్ ఆధారంగా ఆమోదం ఉంటుంది.',
            
            'policy_sec4_title' => '4. Returning after a Sunday, holiday or approved leave ఆదివారం, సెలవు లేదా ఆమోదించబడిన సెలవు తర్వాత తిరిగి రావడం',
            'policy_sec4_text1' => 'ఆదివారం, ఇతర వారపు సెలవు, పబ్లిక్ హాలిడే లేదా ఆమోదించబడిన సెలవు తర్వాత మీ మొదటి షెడ్యూల్ చేసిన పని దినాన విధులకు హాజరు కావాలి. సెలవును పొడిగించాలనుకుంటే మీ రిపోర్టింగ్ మేనేజర్ మరియు హెచ్‌ఆర్ నుంచి లిఖితపూర్వక అనుమతి పొందాలి.',
            'policy_sec4_text2' => 'షెడ్యూల్ చేసిన పని దినాన అనుమతి లేకుండా గైర్హాజరైన సమయాన్ని హెచ్‌ఆర్ సెలవు లేకుండా వేతనం (Leave Without Pay - LWP) గా నమోదు చేస్తుంది.',
            'policy_sec4_text3' => 'అనుమతి లేని గైర్హాజరు పునరావృతమైతే లేదా సమాచారం అందించడంలో విఫలమైతే కంపెనీ విధానం ప్రకారం క్రమశిక్షణా చర్యలు ఉంటాయి.',
            
            'policy_sec5_title' => '5. Sick leave and emergencies అనారోగ్య సెలవు మరియు అత్యవసర పరిస్థితులు',
            'policy_sec5_text1' => 'మీ రిపోర్టింగ్ మేనేజర్ మరియు హెచ్‌ఆర్‌కు వెంటనే తెలియజేయండి. అత్యవసర పరిస్థితి కోసం వీలైనంత త్వరగా కారణం మరియు అంచనా వ్యవధిని వివరించండి. అనారోగ్య సెలవు కోసం రిజిస్టర్డ్ మెడికల్ ప్రాక్టీషనర్ నుంచి మెడికల్ సర్టిఫికేట్‌ను వెంటనే సమర్పించండి.',
            'policy_sec5_text2' => 'పత్రాలను వెంటనే పొందలేకపోతే హెచ్‌ఆర్‌కు తెలియజేసి లభించిన వెంటనే పంపండి. చట్టం ప్రకారం అర్హత ఉన్న అనారోగ్య సెలవును హెచ్‌ఆర్ వర్తింపజేస్తుంది.',
            
            'policy_sec6_title' => '6. Two-week induction and training రెండు వారాల పరిచయం మరియు శిక్షణ',
            'policy_sec6_text1' => 'మీ మొదటి రెండు వారాలు పరిచయం మరియు శిక్షణ కోసం. హెచ్‌ఆర్ మరియు ఆపరేషన్స్ మీ హాజరును రికార్డ్ చేస్తాయి. ముందుగానే మానివేస్తే చట్టం ప్రకారం చెల్లించాల్సిన వేతనాన్ని హెచ్‌ఆర్ లెక్కిస్తుంది.',
            'policy_sec6_text2' => 'ఉదాహరణ: 5 రోజుల తర్వాత మానివేయడం. ఐదు రోజుల్లో ఏదీ చెల్లింపుకు అర్హత లేకపోతే ఆ రోజులకు ఎటువంటి చెల్లింపు ఉండదు.',
            
            'policy_sec7_title' => '7. Agreed daily pickup target అంగీకరించిన రోజువారీ పికప్ లక్ష్యం',
            'policy_sec7_text1' => 'మీ రోజువారీ లక్ష్యం కోసం మీ లిఖితపూర్వక జీత ఒప్పందాన్ని చూడండి. ఇది షెడ్యూల్ చేయబడిన పని దినానికి 3 లేదా 4 పూర్తయిన పికప్‌లుగా ఉంటుంది.',
            'policy_sec7_text2' => 'లక్ష్యాన్ని చేరుకోలేకపోతే కారణాన్ని రికార్డ్ చేయండి. దూరం, బరువు, ట్రాఫిక్ ఆలస్యం మొదలైనవి ఆపరేషన్స్ పరిశీలిస్తుంది. లక్ష్యాన్ని చేరుకోలేకపోవడం వల్ల మీ మూల వేతనం స్వయంచాలకంగా తగ్గదు.',
            
            'policy_sec8_title' => '8. Bonus for extra pickups అదనపు పికప్‌లకు బోనస్',
            'policy_sec8_text1' => 'మీ రోజువారీ లక్ష్యానికి మించి ధృవీకరించబడిన ప్రతి పికప్‌కు మీరు బోనస్ పొందుతారు. హెచ్‌ఆర్ ఆ రేటును లిఖితపూర్వకంగా ఇస్తుంది.',
            'policy_sec8_example1' => '3-పికప్ లక్ష్యం: 3 పూర్తయ్యాయి = రోజువారీ లక్ష్యం నెరవేరింది. 4 పూర్తయ్యాయి = 1 బోనస్ పికప్.',
            'policy_sec8_example2' => '4-పికప్ లక్ష్యం: 4 పూర్తయ్యాయి = రోజువారీ లక్ష్యం నెరవేరింది. 6 పూర్తయ్యాయి = 2 బోనస్ పికప్‌లు.',
            
            'policy_sec9_title' => '9. Shift hours and daily records షిఫ్ట్ గంటలు మరియు రోజువారీ రికార్డులు',
            'policy_sec9_text1' => 'మీ షెడ్యూల్ చేసిన షిఫ్ట్‌లోనే పికప్‌లను పూర్తి చేయండి. మీ షిఫ్ట్ దాటిపోయే అవకాశం ఉంటే మీ సూపర్‌వైజర్‌కు చెప్పండి. అదనపు పని గంటలను నమోదు చేయండి. చట్టం ప్రకారం వర్తించే ఓవర్‌టైమ్‌ను కంపెనీ చెల్లిస్తుంది.',
            'policy_sec9_text2' => 'మీ రోజువారీ లాగ్‌లో షిఫ్ట్ మరియు పికప్ ప్రారంభ/ముగింపు సమయాలు, బుకింగ్ రిఫరెన్స్, పికప్ స్థితి, దూరం, పార్శిల్ బరువు (కిలోలలో) నమోదు కావాలి.',
            
            'policy_admin_title' => 'విధాన నిర్వహణ (Policy administration)',
            'policy_admin_text' => 'అధికారిక కంపెనీ మాధ్యమాల ద్వారా హెచ్‌ఆర్ ఏవైనా విధాన సవరణలను తెలియజేస్తుంది. వర్తించే చట్టబద్ధమైన హక్కులు ప్రాధాన్యత కలిగి ఉంటాయి.',
            'hr_contact_title' => 'హెచ్‌ఆర్ సంప్రదింపు (HR contact)',
            'hr_emails' => 'hr@vkinnect.com | hr.vkinnect@gmail.com'
        ]
    ];

    public static $aliases = [
        'terms_clause1_title' => 'probation_title',
        'terms_clause1_text1' => 'probation_text',
        'terms_clause2_title' => 'leave_attendance_title',
        'terms_clause2_text1' => 'leave_attendance_text',
        'terms_clause3_title' => 'resignation_title',
        'terms_clause3_text1' => 'resignation_text1',
        'terms_clause4_title' => 'property_conduct_title',
        'terms_clause4_text1' => 'property_conduct_text',
        'sec8_title' => 'terms_title',
        'sec9_title' => 'pickup_summary_title',
        'sec9_applicable_note' => 'pickup_summary_note',
        'sec10_title' => 'declaration_title',
        'decl_point1' => 'declaration_p1',
        'decl_point2' => 'declaration_p2',
        'decl_point3' => 'leave_attendance_text',
        'decl_point4' => 'resignation_text1',
        'decl_point5' => 'declaration_p2',
        'signature_of_employee' => 'emp_signature',
        'left_thumb_impression' => 'thumb_impression',
        'reference_1' => 'sec6_title',
        'reference_2' => 'sec7_title',
        'aadhaar_number' => 'aadhaar_masked',
        'relationship_how_known' => 'how_known',
        'daily_target_col' => 'daily_target_title',
        'completed_pickups_col' => 'pickup_summary_title',
        'bonus_pickups_col' => 'daily_target_title'
    ];

    public static function t($key, $lang = 'en') {
        if (!isset(self::$translations[$lang])) {
            $lang = 'en';
        }
        if (isset(self::$translations[$lang][$key])) {
            return self::$translations[$lang][$key];
        }
        $alias = self::$aliases[$key] ?? null;
        if ($alias && isset(self::$translations[$lang][$alias])) {
            return self::$translations[$lang][$alias];
        }
        return self::$translations['en'][$key] ?? (isset($alias) ? (self::$translations['en'][$alias] ?? $key) : $key);
    }
}

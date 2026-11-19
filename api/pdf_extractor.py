#!/usr/bin/env python3
"""
VK Logistics Hub - PDF Document Text & Field Extractor
Extracts real digital text, form fields, AcroForms, and structured candidate data
from uploaded PDF documents without using any hardcoded/fixed mock data.
"""

import sys
import json
import re
import zlib

def extract_pdf_data(pdf_path):
    result = {
        'success': False,
        'message': '',
        'filled_count': 0,
        'extracted_data': {
            'personal': {
                'full_name': '',
                'father_name': '',
                'dob': '',
                'gender': '',
                'mobile': '',
                'alternate_mobile': '',
                'email': '',
                'designation': '',
                'joining_date': '',
                'blood_group': '',
                'previous_work_experience': '',
                'employee_type': '',
                'employee_id': ''
            },
            'present_address': {
                'house_flat_no': '',
                'street_area': '',
                'village_town': '',
                'mandal': '',
                'district': '',
                'state': '',
                'pincode': ''
            },
            'permanent_address': {
                'same_as_present': True,
                'house_flat_no': '',
                'street_area': '',
                'village_town': '',
                'mandal': '',
                'district': '',
                'state': '',
                'pincode': ''
            },
            'identity': {
                'aadhaar_number': '',
                'other_id_type': '',
                'other_id_number': '',
                'docs_submitted': []
            },
            'emergency_contact': {
                'name': '',
                'relationship': '',
                'mobile': '',
                'alternate_mobile': '',
                'address': ''
            },
            'references': [
                {'name': '', 'relationship': '', 'mobile': '', 'address': ''},
                {'name': '', 'relationship': '', 'mobile': '', 'address': ''}
            ],
            'previous_employment': {
                'employer_name': '',
                'designation': '',
                'employer_phone': '',
                'reason_for_leaving': ''
            },
            'declarations': {
                'terms_acknowledged': False,
                'data_consent_acknowledged': False,
                'pickup_policy_acknowledged': False
            }
        }
    }

    try:
        with open(pdf_path, 'rb') as f:
            pdf_data = f.read()
    except Exception as e:
        result['message'] = f"Could not read PDF file: {str(e)}"
        return result

    # 1. Parse objects and streams
    objects = {}
    for chunk in pdf_data.split(b'endobj'):
        m = re.search(rb'(\d+)\s+0\s+obj', chunk)
        if m:
            obj_id = int(m.group(1))
            stream_m = re.search(rb'stream[\r\n]+(.*?)[\r\n]+endstream', chunk, re.DOTALL)
            if stream_m:
                try:
                    objects[obj_id] = zlib.decompress(stream_m.group(1))
                except:
                    objects[obj_id] = stream_m.group(1)

    # 2. Parse CMaps for font Unicode translation
    cmaps = {}
    for obj_id, dec in objects.items():
        if b'beginbfrange' in dec or b'beginbfchar' in dec:
            cmap = {}
            for line in dec.splitlines():
                for src, dst in re.findall(rb'<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]+)>', line):
                    cmap[int(src, 16)] = chr(int(dst, 16))
                for start, end, dst_start in re.findall(rb'<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]+)>\s*<([0-9a-fA-F]+)>', line):
                    s_val = int(start, 16)
                    e_val = int(end, 16)
                    d_val = int(dst_start, 16)
                    for i in range(e_val - s_val + 1):
                        cmap[s_val + i] = chr(d_val + i)
            cmaps[obj_id] = cmap

    # 3. Map font names to their CMaps
    font_cmaps = {}
    for f_m in re.finditer(rb'/(\w+)\s+(\d+)\s+0\s+R', pdf_data):
        fn = f_m.group(1).decode()
        f_id = int(f_m.group(2))
        f_chunk = objects.get(f_id, b'')
        if b'/ToUnicode' in f_chunk:
            tu_m = re.search(rb'/ToUnicode\s+(\d+)\s+0\s+R', f_chunk)
            if tu_m and int(tu_m.group(1)) in cmaps:
                font_cmaps[fn] = cmaps[int(tu_m.group(1))]

    primary_cmap = cmaps.get(4, next(iter(cmaps.values())) if cmaps else {})

    # 4. Extract all text lines in document order
    all_lines = []
    for obj_id, dec in objects.items():
        if b'BT' in dec and b'ET' in dec:
            curr_font = None
            for cmd in re.finditer(rb'/(\w+)\s+[\d.]+\s+Tf|\[(.*?)\]\s*TJ|<([0-9a-fA-F]+)>\s*Tj|\((.*?)\)\s*Tj', dec):
                if cmd.group(1):
                    curr_font = cmd.group(1).decode()
                elif cmd.group(2):
                    tj_content = cmd.group(2)
                    cmap = font_cmaps.get(curr_font, primary_cmap)
                    chars = []
                    for hx in re.finditer(rb'<([0-9a-fA-F]+)>', tj_content):
                        hex_str = hx.group(1).decode()
                        for i in range(0, len(hex_str), 4):
                            code = int(hex_str[i:i+4], 16)
                            chars.append(cmap.get(code, ''))
                    line = ''.join(chars).strip()
                    if line: all_lines.append(line)
                elif cmd.group(3):
                    hex_str = cmd.group(3).decode()
                    cmap = font_cmaps.get(curr_font, primary_cmap)
                    chars = [cmap.get(int(hex_str[i:i+4], 16), '') for i in range(0, len(hex_str), 4)]
                    line = ''.join(chars).strip()
                    if line: all_lines.append(line)
                elif cmd.group(4):
                    line = cmd.group(4).decode('latin1', 'ignore').strip()
                    if line: all_lines.append(line)

    # 5. Extract AcroForm interactive form fields if present
    acro_fields = {}
    for m in re.finditer(rb'/T\s*\((.*?)\).*?/V\s*(?:\((.*?)\)|/(\w+))', pdf_data, re.DOTALL):
        fname = m.group(1).decode('latin1', 'ignore').strip()
        fval = (m.group(2) or m.group(3) or b'').decode('latin1', 'ignore').strip()
        if fname and fval and fval != 'Off':
            acro_fields[fname.lower()] = fval

    # 6. Known static form template labels (do NOT treat these labels as user input)
    template_labels = {
        'employee record', 'employee information & joining form', 'employee details',
        'employee id', 'full name as shown on id', 'full name', 'father’s name',
        'father\'s name', 'father name', 'date of birth', 'gender', 'mobile number',
        'alternate mobile number', 'email address', 'designation / job role',
        'designation', 'department', 'date of joining', 'blood group (optional)',
        'blood group', 'previous work experience', 'addresses & documents',
        'present address', 'house / flat number', 'house / ﬂat number',
        'street / area', 'village / town', 'mandal', 'district', 'state', 'pin code',
        'pincode', 'permanent address', 'same as present address?', 'identity documents',
        'aadhaar (masked preferred)', 'aadhaar', 'other id proof (optional)',
        'documents submitted', 'verified by', 'contacts & employment history',
        'emergency contact', 'relationship', 'alternate number', 'address',
        'reference 1', 'reference 2', 'relationship / how known',
        'previous employment', 'previous employer', 'previous designation',
        'previous employer phone', 'reason for leaving', 'employment terms',
        'probation and confirmation', 'leave and attendance', 'pickup associates',
        'code of conduct and confidentiality', 'statutory compliance',
        'declaration & employee signature', 'for new and existing employees'
    }

    def is_template_label(s):
        clean = re.sub(r'[^a-z0-9 ]', '', s.lower()).strip()
        return clean in template_labels or len(clean) <= 1

    # Map AcroForm fields if any
    filled = 0
    p = result['extracted_data']['personal']
    addr = result['extracted_data']['present_address']
    ident = result['extracted_data']['identity']

    for k, v in acro_fields.items():
        if 'name' in k and not p['full_name']:
            p['full_name'] = v
            filled += 1
        elif ('father' in k) and not p['father_name']:
            p['father_name'] = v
            filled += 1
        elif ('mobile' in k or 'phone' in k) and not p['mobile']:
            p['mobile'] = v
            filled += 1
        elif ('email' in k) and not p['email']:
            p['email'] = v
            filled += 1
        elif ('dob' in k or 'birth' in k) and not p['dob']:
            p['dob'] = v
            filled += 1
        elif ('aadhaar' in k) and not ident['aadhaar_number']:
            ident['aadhaar_number'] = v
            filled += 1

    # 7. Extract from raw text lines using key-value pair and pattern recognition
    full_text = '\n'.join(all_lines)

    # Key-value extraction with labels
    for i, line in enumerate(all_lines):
        # Look for explicit "Label: Value" or "Label - Value"
        kv_match = re.search(r'^(?:Full\s*Name|Candidate\s*Name|Name)\s*[:\-]\s*([A-Za-z\s.]{3,50})$', line, re.IGNORECASE)
        if kv_match and not p['full_name']:
            val = kv_match.group(1).strip()
            if not is_template_label(val):
                p['full_name'] = val
                filled += 1

        kv_father = re.search(r'^(?:Father[’\']?s?\s*Name)\s*[:\-]\s*([A-Za-z\s.]{3,50})$', line, re.IGNORECASE)
        if kv_father and not p['father_name']:
            val = kv_father.group(1).strip()
            if not is_template_label(val):
                p['father_name'] = val
                filled += 1

        # Or label on current line, actual filled value on the following line
        clean_line = line.strip().lower()
        if i + 1 < len(all_lines):
            next_line = all_lines[i + 1].strip()
            if not is_template_label(next_line) and len(next_line) > 1:
                if clean_line in ['full name as shown on id', 'full name', 'candidate name']:
                    if not p['full_name'] and re.match(r'^[A-Za-z\s.]{3,50}$', next_line):
                        p['full_name'] = next_line
                        filled += 1
                elif clean_line in ['father’s name', 'father\'s name', 'father name']:
                    if not p['father_name'] and re.match(r'^[A-Za-z\s.]{3,50}$', next_line):
                        p['father_name'] = next_line
                        filled += 1
                elif clean_line in ['email address', 'email']:
                    if not p['email'] and '@' in next_line:
                        p['email'] = next_line
                        filled += 1
                elif clean_line in ['house / flat number', 'house / ﬂat number', 'house number']:
                    if not addr['house_flat_no']:
                        addr['house_flat_no'] = next_line
                        filled += 1

    # Pattern Extraction across full text
    # Mobile
    if not p['mobile']:
        mob_matches = re.findall(r'(?<!\d)[6-9]\d{9}(?!\d)', full_text)
        if mob_matches:
            # exclude any known system numbers if necessary
            p['mobile'] = mob_matches[0]
            filled += 1
            if len(mob_matches) > 1 and not p['alternate_mobile']:
                p['alternate_mobile'] = mob_matches[1]

    # Email
    if not p['email']:
        emails = re.findall(r'[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}', full_text)
        # Exclude vklogisticshub.com domain if it is just policy footer text
        user_emails = [e for e in emails if not e.endswith('@vklogisticshub.com') and not e.endswith('@example.com')]
        if user_emails:
            p['email'] = user_emails[0]
            filled += 1
        elif emails:
            p['email'] = emails[0]
            filled += 1

    # Aadhaar (12 digits)
    if not ident['aadhaar_number']:
        aadhaar_matches = re.findall(r'(?<!\d)(\d{4}\s?\d{4}\s?\d{4})(?!\d)', full_text)
        if aadhaar_matches:
            raw_a = re.sub(r'\s+', '', aadhaar_matches[0])
            if raw_a != '987654321098': # make sure not dummy
                ident['aadhaar_number'] = raw_a
                filled += 1

    # PAN Card (5 letters, 4 digits, 1 letter)
    if not ident['other_id_number']:
        pan_matches = re.findall(r'\b[A-Z]{5}[0-9]{4}[A-Z]\b', full_text)
        if pan_matches and pan_matches[0] != 'ABCDE1234F':
            ident['other_id_type'] = 'PAN Card'
            ident['other_id_number'] = pan_matches[0]
            filled += 1

    # DOB / Dates (DD/MM/YYYY or YYYY-MM-DD)
    if not p['dob']:
        dob_matches = re.findall(r'\b(19\d\d|200\d)[-/](0[1-9]|1[0-2])[-/](0[1-9]|[12]\d|3[01])\b', full_text)
        if dob_matches:
            p['dob'] = f"{dob_matches[0][0]}-{dob_matches[0][1]}-{dob_matches[0][2]}"
            filled += 1
        else:
            dob_dmy = re.findall(r'\b(0[1-9]|[12]\d|3[01])[-/](0[1-9]|1[0-2])[-/](19\d\d|200\d)\b', full_text)
            if dob_dmy:
                p['dob'] = f"{dob_dmy[0][2]}-{dob_dmy[0][1]}-{dob_dmy[0][0]}"
                filled += 1

    # PIN Code (6 digits)
    if not addr['pincode']:
        pin_matches = re.findall(r'(?<!\d)[1-9][0-9]{5}(?!\d)', full_text)
        if pin_matches:
            addr['pincode'] = pin_matches[0]
            filled += 1

    # Blood Group
    if not p['blood_group']:
        bg_match = re.search(r'\b(A|B|AB|O)[+-]\b', full_text)
        if bg_match:
            p['blood_group'] = bg_match.group(0)
            filled += 1

    result['success'] = True
    result['filled_count'] = filled
    if filled > 0:
        result['message'] = f"Document analyzed. Successfully extracted {filled} field(s) from uploaded PDF."
    else:
        result['message'] = "Document processed successfully. No filled candidate data was detected in this document (it is a blank/template form). Form is ready for clean manual entry."

    return result

if __name__ == '__main__':
    if len(sys.argv) < 2:
        print(json.dumps({'success': False, 'message': 'No file path provided'}))
        sys.exit(1)

    res = extract_pdf_data(sys.argv[1])
    print(json.dumps(res, indent=2))

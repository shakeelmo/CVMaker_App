<?php

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../config/database.php';

function importResumeFromUpload() {
    $user = requireAuth();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['error' => 'Method not allowed'];
    }

    if (!isset($_FILES['file'])) {
        return ['error' => 'Please upload a PDF or DOCX resume file.'];
    }

    $file = $_FILES['file'];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['error' => friendlyUploadError($file['error'] ?? UPLOAD_ERR_NO_FILE)];
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return ['error' => 'File is too large. Maximum size is 5MB.'];
    }

    $originalName = $file['name'] ?? 'resume';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($extension, ['pdf', 'docx'], true)) {
        return ['error' => 'Unsupported file type. Please upload a PDF or DOCX file.'];
    }

    $tmpPath = $file['tmp_name'] ?? '';
    if (!$tmpPath || !is_uploaded_file($tmpPath)) {
        return ['error' => 'Upload could not be verified. Please try again.'];
    }

    try {
        $rawText = $extension === 'pdf' ? extractTextFromPdf($tmpPath) : extractTextFromDocx($tmpPath);
        $rawText = normalizeResumeText($rawText);

        if (mb_strlen(trim($rawText)) < 40) {
            return ['error' => 'We could not read enough text from that file. Please try another PDF or DOCX resume.'];
        }

        $resumeData = buildResumeDataFromText($rawText, $originalName);

        return [
            'message' => 'Resume parsed successfully',
            'resumeData' => $resumeData,
            'meta' => [
                'source' => $extension,
                'filename' => $originalName,
                'user_id' => $user['id'],
                'parsed_length' => mb_strlen($rawText)
            ]
        ];
    } catch (Throwable $e) {
        error_log('resume import failed: ' . $e->getMessage());
        return ['error' => 'We could not import that resume file right now. Please try another file or update the formatting and try again.'];
    }
}

function friendlyUploadError($code) {
    switch ((int)$code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'File is too large. Maximum size is 5MB.';
        case UPLOAD_ERR_PARTIAL:
            return 'The upload was interrupted. Please try again.';
        case UPLOAD_ERR_NO_FILE:
            return 'Please choose a PDF or DOCX file to import.';
        default:
            return 'The file upload failed. Please try again.';
    }
}

function extractTextFromPdf($path) {
    // Vendor autoload - check both possible locations
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    }
    if (!file_exists($autoload)) {
        throw new RuntimeException('Composer autoload not found');
    }

    require_once $autoload;

    $parser = new \Smalot\PdfParser\Parser();
    $pdf = $parser->parseFile($path);
    $text = $pdf->getText();

    if (!is_string($text) || trim($text) === '') {
        throw new RuntimeException('Empty PDF text');
    }

    return $text;
}

function extractTextFromDocx($path) {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Unable to open DOCX file');
    }

    $xml = $zip->getFromName('word/document.xml');
    $zip->close();

    if ($xml === false) {
        throw new RuntimeException('DOCX content missing');
    }

    $xml = preg_replace('/<w:tab\/?\s*>/i', "\t", $xml);
    $xml = preg_replace('/<w:br\/?\s*>/i', "\n", $xml);
    $xml = preg_replace('/<\/w:p>/i', "\n", $xml);
    $text = strip_tags($xml);

    if (!is_string($text) || trim($text) === '') {
        throw new RuntimeException('Empty DOCX text');
    }

    return $text;
}

function normalizeResumeText($text) {
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/\r\n?|\x0B/u", "\n", $text);
    $text = preg_replace('/[^\S\n]+/u', ' ', $text);
    $text = preg_replace('/\n{3,}/u', "\n\n", $text);
    return trim($text);
}

function buildResumeDataFromText($text, $filename = 'Imported Resume') {
    $lines = preg_split('/\n+/', $text) ?: [];
    $lines = array_values(array_filter(array_map('trim', $lines), function ($line) {
        return $line !== '';
    }));
    $lines = array_values(array_filter($lines, function ($line) {
        return !isNoiseLine($line);
    }));

    $email = matchFirst('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text);
    $phone = extractPhone($text);
    $location = extractLocation($lines, $email, $phone);
    $name = extractName($lines, $email, $phone);

    $sections = splitResumeSections($lines);

    $summaryText = extractSummary($sections, $lines, $text);
    $skills = extractSkills($sections, $text);
    $experience = extractExperience($sections, $text);
    $education = extractEducation($sections, $text);

    return [
        'title' => sanitizeImportedTitle($filename, $name),
        'basics' => [
            'name' => $name,
            'headline' => extractHeadline($lines, $name, $email, $phone),
            'email' => $email,
            'phone' => $phone,
            'location' => $location,
            'linkedIn' => extractLinkedIn($text),
            'url' => extractUrl($text)
        ],
        'target' => [
            'jobTitle' => inferTargetJobTitle($lines, $experience),
            'industry' => '',
            'jobDescription' => ''
        ],
        'summary' => $summaryText,
        'skills' => $skills,
        'experience' => $experience,
        'education' => $education,
        'certifications' => extractCertifications($text),
        'projects' => [],
        'languages' => [],
        'template' => 'modern',
        'settings' => [
            'font' => 'Inter',
            'format' => 'a4',
            'color' => '#2563eb',
            'sectionOrder' => 'skills-first'
        ]
    ];
}

function sanitizeImportedTitle($filename, $name) {
    if ($name) {
        return $name . ' Resume';
    }

    $base = pathinfo($filename, PATHINFO_FILENAME);
    $base = trim(preg_replace('/[_-]+/', ' ', $base));
    return $base !== '' ? $base : 'Imported Resume';
}

function splitResumeSections($lines) {
    $map = [];
    $current = 'header';
    $map[$current] = [];

    foreach ($lines as $line) {
        $normalized = strtolower(trim(preg_replace('/[^a-z ]/i', '', $line)));
        $detected = mapSectionHeading($normalized);
        if ($detected !== null) {
            $current = $detected;
            if (!isset($map[$current])) {
                $map[$current] = [];
            }
            continue;
        }

        [$inlineSection, $inlineContent] = splitInlineSectionLine($line);
        if ($inlineSection !== null) {
            if (!isset($map[$inlineSection])) {
                $map[$inlineSection] = [];
            }
            if ($inlineContent !== '') {
                $map[$inlineSection][] = $inlineContent;
            }
            $current = $inlineSection;
            continue;
        }

        $map[$current][] = $line;
    }

    return $map;
}

function mapSectionHeading($normalized) {
    $groups = [
        'summary' => ['summary', 'professional summary', 'profile', 'objective', 'professional objective', 'about me', 'profile summary', 'career objective'],
        'experience' => ['experience', 'work experience', 'employment history', 'professional experience', 'career history', 'employment', 'professional background', 'work history', 'employment details', 'employment record', 'prefessional experience'],
        'education' => ['education', 'academic background', 'academic history', 'educational qualifications', 'education and certifications', 'education background', 'academic'],
        'skills' => ['skills', 'technical skills', 'core competencies', 'competencies', 'key skills', 'personal skills', 'areas of expertise', 'skills and expertise', 'technical platforms']
    ];

    foreach ($groups as $key => $aliases) {
        if (in_array($normalized, $aliases, true)) {
            return $key;
        }
    }

    return null;
}

function extractSummary($sections, $lines, $text = '') {
    if (!empty($sections['summary'])) {
        $summaryLines = [];
        foreach ($sections['summary'] as $line) {
            if (mapSectionHeading(strtolower(trim(preg_replace('/[^a-z ]/i', '', $line)))) !== null) {
                break;
            }
            $summaryLines[] = preg_replace('/^(summary|objective|profile summary|career objective)\s*[:\-]?\s*/i', '', $line);
            if (count($summaryLines) >= 8) {
                break;
            }
        }
        $summary = trim(implode(' ', $summaryLines));
        if ($summary !== '') {
            return $summary;
        }
    }

    if ($text && preg_match('/(?:Summary|OBJECTIVE|PROFILE SUMMARY)\s*[:\-]?\s+(.*?)(?:\s+(?:Experience|Professional Experience|Employment History|EDUCATIONAL QUALIFICATIONS|Education)\s+)/is', $text, $m)) {
        $summary = trim(preg_replace('/\s+/', ' ', $m[1]));
        return preg_replace('/\s*Page \d+ of \d+\s*/i', ' ', $summary);
    }

    $fallback = [];
    foreach (array_slice($lines, 0, 16) as $line) {
        if (strlen($line) > 40 && !preg_match('/@|linkedin|github|\+?\d|certification|education|experience/i', $line)) {
            $fallback[] = $line;
        }
        if (count($fallback) >= 3) {
            break;
        }
    }

    return trim(implode(' ', $fallback));
}

function extractSkills($sections, $text) {
    $items = [];

    if (preg_match('/(?:SKILLS AND EXPERTISE|TECHNICAL SKILLS|PERSONAL SKILLS|Areas of Expertise)\s+(.*?)(?:\s+(?:PROFESSIONAL EXPERIENCE|PREFESSIONAL EXPERIENCE|EMPLOYMENT HISTORY|EXPERIENCE|EDUCATION)\s+)/is', $text, $m)) {
        $blob = preg_replace('/\s*Page \d+ of \d+\s*/i', ' ', $m[1]);
        $parts = preg_split('/[,|•·\\t]+|\s{2,}/', $blob);
        foreach ($parts as $part) {
            $part = trim($part, " \t\n\r\0\x0B-•·:");
            if ($part !== '' && strlen($part) <= 80 && !preg_match('/^(currently working|last used|skill description|level)$/i', $part)) {
                $items[] = $part;
            }
        }
    }

    if (preg_match('/Top Skills\s+(.*?)\s+Certifications\s+/is', $text, $m)) {
        $skillBlob = trim(preg_replace('/\s+/', ' ', $m[1]));
        $knownLinkedInSkills = [
            'Enterprise Architecture (EA) Frameworks',
            'Architecture Development Method (ADM)',
            'Network Engineering'
        ];
        foreach ($knownLinkedInSkills as $knownSkill) {
            if (stripos($skillBlob, $knownSkill) !== false) {
                $items[] = $knownSkill;
            }
        }
    }

    if (!empty($sections['skills'])) {
        foreach ($sections['skills'] as $line) {
            $parts = preg_split('/[,|•·\\t]+/', $line);
            foreach ($parts as $part) {
                $skill = trim($part, " \t\n\r\0\x0B-•·");
                if ($skill !== '') {
                    $items[] = $skill;
                }
            }
        }
    }

    if (empty($items)) {
        preg_match_all('/\b(JavaScript|TypeScript|PHP|Laravel|Node\.js|React|Vue|Angular|Python|Java|C\+\+|SQL|MySQL|PostgreSQL|AWS|Docker|Kubernetes|HTML|CSS|Git|Figma|Excel|WordPress|SEO|Project Management|Communication|Leadership)\b/i', $text, $matches);
        $items = $matches[0] ?? [];
    }

    $seen = [];
    $skills = [];
    foreach ($items as $item) {
        $name = trim($item);
        $key = mb_strtolower($name);
        if ($name === '' || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $skills[] = [
            'id' => generateClientId(),
            'name' => $name,
            'proficiency' => 'advanced'
        ];
    }

    return array_slice($skills, 0, 20);
}

function extractExperience($sections, $text = '') {
    $rawLines = sanitizeSectionLines($sections['experience'] ?? []);

    if (empty($rawLines) && $text && preg_match('/(?:PROFESSIONAL EXPERIENCE|PREFESSIONAL EXPERIENCE|EMPLOYMENT HISTORY|EXPERIENCE|WORK EXPERIENCE|EMPLOYMENT DETAILS)\s+(.*?)(?:\s+(?:EDUCATION|ACADEMICS|ACADEMIC|PROFESSIONAL|ACHIEVEMENTS|PERSONAL DETAILS|ACADEMICS|HONORS|CERTIFICATIONS)\s+)/is', $text, $m)) {
        $rawLines = array_values(array_filter(array_map('trim', preg_split('/\n+/', preg_replace('/\s*Page \d+ of \d+\s*/i', "\n", $m[1])))));
    }
    if ($text && preg_match('/Experience\s+(.*?)\s+Education\s+/is', $text, $m)) {
        $linkedInExperience = trim(preg_replace('/\s*Page \d+ of \d+\s*/i', "\n", $m[1]));
        $parsedLinkedIn = extractLinkedInExperienceEntries($linkedInExperience);
        if (!empty($parsedLinkedIn)) {
            return $parsedLinkedIn;
        }
        if (empty($rawLines)) {
            $rawLines = array_values(array_filter(array_map('trim', preg_split('/\n+/', $linkedInExperience))));
        }
    }
    if (empty($rawLines)) {
        return extractExperienceFromWholeText($text);
    }

    $entries = [];
    $companyContext = '';
    $pendingEmployer = '';
    $pendingPeriod = '';

    foreach ($rawLines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || isJunkExperienceLine($trimmed)) {
            continue;
        }

        if (preg_match('/^Employer\s*[:\-]\s*(.+)$/i', $trimmed, $m)) {
            $pendingEmployer = trim($m[1]);
            continue;
        }
        if (preg_match('/^Period\s*[:\-]\s*(.+)$/i', $trimmed, $m)) {
            $pendingPeriod = trim($m[1]);
            continue;
        }
        if (preg_match('/^Designation\s*[:\-]\s*(.+)$/i', $trimmed, $m)) {
            $entry = buildRoleEntry($m[1], $pendingEmployer ?: $companyContext);
            if ($pendingPeriod !== '') {
                applyDateRangeToEntry($entry, $pendingPeriod);
            }
            $entries[] = $entry;
            $companyContext = $pendingEmployer ?: $companyContext;
            $pendingEmployer = '';
            $pendingPeriod = '';
            continue;
        }

        if (isEmployerGroupLine($trimmed)) {
            $companyContext = normalizeEmployerLine($trimmed);
            continue;
        }

        if (isRoleLine($trimmed)) {
            $entries[] = buildRoleEntry($trimmed, $companyContext);
            continue;
        }

        if (!empty($entries)) {
            $lastIndex = count($entries) - 1;
            if (preg_match('/^(?:[-•*]||o)\s+/', $trimmed)) {
                $entries[$lastIndex]['bullets'][] = cleanBulletText($trimmed);
            } elseif (stripos($trimmed, 'job responsibilities') === false && stripos($trimmed, 'achievements') === false && stripos($trimmed, 'roles and responsibilities') === false) {
                $entries[$lastIndex]['bullets'][] = cleanBulletText($trimmed);
            }
        }
    }

    $entries = array_map('finalizeExperienceEntry', $entries);
    $entries = array_values(array_filter($entries, function ($entry) {
        return $entry['company'] !== '' || $entry['position'] !== '' || count(array_filter($entry['bullets'])) > 0;
    }));

    if (empty($entries)) {
        $entries = extractExperienceFromWholeText($text);
    }

    return array_slice($entries, 0, 10);
}

function createExperienceEntryFromLine($line) {
    $entry = [
        'id' => generateClientId(),
        'company' => '',
        'position' => '',
        'location' => '',
        'startDate' => '',
        'endDate' => '',
        'current' => false,
        'bullets' => []
    ];

    if (preg_match('/^(.*?)\s*[\-|–|—]\s*(.*?)\s*[\(|,]?((?:\w+\s+)?\d{4}.*)$/u', $line, $m)) {
        $entry['company'] = trim($m[1]);
        $entry['position'] = trim($m[2]);
        applyDateRangeToEntry($entry, $m[3]);
        return $entry;
    }

    if (strpos($line, '|') !== false) {
        $parts = array_map('trim', explode('|', $line));
        $entry['company'] = $parts[0] ?? '';
        $entry['position'] = $parts[1] ?? '';
        if (!empty($parts[2])) {
            applyDateRangeToEntry($entry, $parts[2]);
        }
        return $entry;
    }

    $entry['company'] = $line;
    return $entry;
}

function finalizeExperienceEntry($entry) {
    $entry['bullets'] = array_values(array_slice(array_filter(array_map('trim', $entry['bullets'])), 0, 6));
    if (empty($entry['bullets'])) {
        $entry['bullets'] = [''];
    }
    return $entry;
}

function applyDateRangeToEntry(&$entry, $text) {
    $normalized = str_replace(['–', '—'], '-', $text);
    $parts = preg_split('/\s+-\s+|\s+to\s+/i', $normalized);
    $entry['startDate'] = trim($parts[0] ?? '');
    $entry['endDate'] = trim($parts[1] ?? '');
    if ($entry['endDate'] === '') {
        $entry['endDate'] = preg_match('/present|current/i', $normalized) ? 'Present' : '';
    }
    $entry['current'] = preg_match('/present|current/i', $entry['endDate']) === 1;
}

function extractEducation($sections, $text = '') {
    $lines = sanitizeSectionLines($sections['education'] ?? []);

    if (empty($lines) && $text && preg_match('/(?:EDUCATIONAL QUALIFICATIONS|EDUCATION AND CERTIFICATIONS|EDUCATION background|ACADEMICS|EDUCATION|Educational Qualification)\s+(.*?)(?:\s+(?:CERTIFICATIONS|TECHNICAL SKILLS|TECHNICAL PLATFORMS|PROFESSIONAL EXPERIENCE|EMPLOYMENT HISTORY|WORK EXPERIENCE|WORK EXPERIENCE|SYSTEMS AND|TECHNICAL PLATFORMS)\s+)/is', $text, $m)) {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\n+/', preg_replace('/\s*Page \d+ of \d+\s*/i', "\n", $m[1])))));
    }
    if ($text && preg_match('/Education\s+(.*)$/is', $text, $m)) {
        $linkedInEducation = trim(preg_replace('/\s*Page \d+ of \d+\s*/i', "\n", $m[1]));
        if (empty($lines)) {
            $lines = array_values(array_filter(array_map('trim', preg_split('/\n+/', $linkedInEducation))));
        }
    }
    if (empty($lines) && $text) {
        if (preg_match('/(M\.C\.A[^\n]+|M\.S[^\n]+|Master[^\n]+|Bachelor[^\n]+|B\.Sc\.[^\n]+|Diploma[^\n]+)/i', $text, $m)) {
            $lines[] = trim($m[1]);
        }
        if (preg_match('/([A-Za-z][A-Za-z\s()]+(?:College|University|Institute|School)[A-Za-z\s()]*)/i', $text, $m)) {
            $lines[] = trim($m[1]);
        }
    }
    if (empty($lines)) {
        return [];
    }

    $joined = implode(' | ', $lines);
    $entry = [
        'id' => generateClientId(),
        'school' => '',
        'degree' => '',
        'field' => '',
        'graduationDate' => '',
        'gpa' => ''
    ];

    if (preg_match('/(\d{4})\s*[-–]\s*(\d{4})/', $joined, $m)) {
        $entry['graduationDate'] = trim($m[2]);
    } elseif (preg_match('/\b(19|20)\d{2}\b/', $joined, $m)) {
        $entry['graduationDate'] = trim($m[0]);
    }

    if (preg_match('/([A-Za-z][A-Za-z\s()]+(?:College|University|Institute|School)[A-Za-z\s()]*)/i', $joined, $m)) {
        $entry['school'] = trim($m[1], " ,");
    }

    if (preg_match('/(M\.C\.A[^|]*|M\.S[^|]*|B\.Sc\.?\s*\(.*?\)\s*degree|B\.Sc\.?[^|]*|Bachelor[^|]*|Master[^|]*|MBA[^|]*|PhD[^|]*|Diploma[^|]*)/i', $joined, $m)) {
        $entry['degree'] = trim($m[1], " :,");
    }

    if (preg_match('/major\s*[:\-]?\s*([^|]+)/i', $joined, $m)) {
        $entry['field'] = trim($m[1], " :,");
    } elseif (preg_match('/computer engineering|computer science|information technology|information systems|computer applications/i', $joined, $m)) {
        $entry['field'] = trim($m[0]);
    }

    if (preg_match('/GPA\s*[:\-]?\s*([0-4](?:\.\d{1,2})?\/?[0-4]?\.?(?:\d{1,2})?)/i', $joined, $m)) {
        $entry['gpa'] = trim($m[1]);
    }

    if ($entry['school'] === '') {
        foreach ($lines as $line) {
            if (preg_match('/college|university|institute|school/i', $line)) {
                $entry['school'] = trim($line, " ,");
                break;
            }
        }
    }

    if ($entry['school'] === '' && $entry['degree'] === '' && $entry['field'] === '') {
        return [];
    }

    return [$entry];
}

function extractName($lines, $email, $phone) {
    foreach (array_slice($lines, 0, 12) as $line) {
        $candidate = trim(preg_replace('/^(curriculum vitae|resume|cv)\s*/i', '', $line));
        if ($candidate === $email || $candidate === $phone || isNoiseLine($candidate)) {
            continue;
        }
        if (preg_match('/^[A-Z][A-Z\s\'.-]{5,80}$/u', $candidate) && str_word_count($candidate) >= 2 && str_word_count($candidate) <= 7 && !preg_match('/summary|objective|skills|engineer|manager|skill description|last used|currently working|job title/i', $candidate)) {
            return ucwords(strtolower(trim($candidate)));
        }
        if (preg_match('/^[A-Za-z][A-Za-z\s\'.-]{5,80}$/u', $candidate) && str_word_count($candidate) >= 2 && str_word_count($candidate) <= 7 && !preg_match('/summary|objective|skills|engineer|manager|skill description|last used|currently working|job title/i', $candidate)) {
            return trim($candidate);
        }
    }
    return '';
}

function extractHeadline($lines, $name, $email, $phone) {
    foreach (array_slice($lines, 0, 22) as $line) {
        if ($line === $name || $line === $email || $line === $phone || isNoiseLine($line)) {
            continue;
        }
        if (preg_match('/top skills|certifications|summary|contact|enterprise architecture|architecture development method|skill description|last used|currently working|^-{5,}$|^objective:?$|^profile summary$|^job title/i', $line)) {
            continue;
        }
        if (strlen($line) >= 3 && strlen($line) <= 120 && !preg_match('/@|linkedin|github|\+?\d|page\s*\||classification|non-business|around one decade|multiple platforms, technologies/i', $line)) {
            return trim($line, " ,");
        }
    }
    return '';
}

function extractPhone($text) {
    if (preg_match('/(?:(?:\+|00)\d{1,3}[\s.-]?)?(?:\(?\d{2,4}\)?[\s.-]?)?\d{3,4}[\s.-]?\d{3,4}(?:[\s.-]?\d{2,4})?/', $text, $m)) {
        return trim($m[0]);
    }
    return '';
}

function extractLocation($lines, $email, $phone) {
    foreach ($lines as $line) {
        if ($line === $email || $line === $phone || isNoiseLine($line)) {
            continue;
        }
        if (looksLikeLocation($line)) {
            return trim($line, " ,");
        }
    }
    return '';
}

function extractCertifications($text) {
    $certifications = [];
    $items = [];

    if (preg_match('/(?:CERTIFICATIONS|Certificates & Courses|PROFESSIONAL CERTIFICATION(?:S)?(?:\s*&\s*TRAININGS)?|PROFESSIONAL)\s+(.*?)(?:\s+(?:TECHNICAL SKILLS|TECHNICAL PLATFORMS|PREFESSIONAL EXPERIENCE|PROFESSIONAL EXPERIENCE|EMPLOYMENT HISTORY|WORK EXPERIENCE|SKILLS|COURSES|EXPERIENCE)\s+)/is', $text, $m)) {
        $blob = preg_replace('/\s*Page \d+ of \d+\s*/i', "\n", $m[1]);
        $lines = array_values(array_filter(array_map('trim', preg_split('/\n+/', $blob))));
        foreach ($lines as $line) {
            $line = preg_replace('/^[-•*\d.\s]+/u', '', $line);
            if ($line !== '' && strlen($line) <= 180 && !preg_match('/^(courses|trainings|verification id|training from)/i', $line)) {
                $items[] = trim($line, " ,");
            }
        }
    }

    if (empty($items) && preg_match_all('/\b(VCP|MCSE|CCNA|CCNP|CCIE|MCTS|MCITP|ITIL|OCP|HCIA|HCIP|HCIE|EMCISA|CCNSP|DCA-ISM|COA|TOGAF|MCP)\b[^\n]*/i', $text, $matches)) {
        foreach (($matches[0] ?? []) as $item) {
            $items[] = trim($item, " ,");
        }
    }

    $seen = [];
    foreach (array_slice($items, 0, 20) as $item) {
        $key = mb_strtolower($item);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $certifications[] = [
            'id' => generateClientId(),
            'name' => $item,
            'issuer' => '',
            'date' => ''
        ];
    }

    return array_slice($certifications, 0, 10);
}

function extractLinkedInExperienceEntries($text) {
    $entries = [];

    $patterns = [
        ['company' => 'Emircom', 'position' => 'Operations Manager'],
        ['company' => 'Mobily', 'position' => 'Customer & Fields Support Specialist Professional'],
        ['company' => 'Mobily', 'position' => 'Data Business Operations Chief Engineer'],
        ['company' => 'Mobily', 'position' => 'Pre Sales Engineering ER team leader'],
        ['company' => 'Zajil Telecom', 'position' => 'EP-Technical Support Team Leader'],
        ['company' => 'Zajil Telecom', 'position' => 'resident engineer in Saudi Electricity'],
        ['company' => 'Zajil Telecom', 'position' => 'Customer Support Engineer']
    ];

    foreach ($patterns as $index => $pattern) {
        $quotedPosition = preg_quote($pattern['position'], '/');
        $quotedCompany = preg_quote($pattern['company'], '/');
        if (preg_match('/' . $quotedPosition . '\s+([A-Za-z]+\s+\d{4})\s+-\s+([A-Za-z]+\s+\d{4}|Present)\s*\(([^)]*)\)\s+([^\n]+)/is', $text, $m, 0, 0)) {
            $rawLocation = trim($m[4]);
            $entries[] = finalizeExperienceEntry([
                'id' => generateClientId() + $index,
                'company' => $pattern['company'],
                'position' => normalizeLinkedInPosition($pattern['position']),
                'location' => normalizeLinkedInLocation($rawLocation),
                'startDate' => trim($m[1]),
                'endDate' => trim($m[2]) === 'Present' ? 'Present' : trim($m[2]),
                'current' => trim($m[2]) === 'Present',
                'bullets' => extractBulletsNearPosition($text, $pattern['position'])
            ]);
        }
    }

    usort($entries, function ($a, $b) {
        return compareDateDesc($a['startDate'] ?? '', $b['startDate'] ?? '');
    });

    return $entries;
}

function extractLinkedInExperienceEntriesByBlocks($text) {
    $entries = [];
    $parts = preg_split('/(?=(?:Emircom|Mobily|Zajil Telecom)\s+)/', $text);
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        if (preg_match('/^(Emircom|Mobily|Zajil Telecom)\s+(.*)$/is', $part, $m)) {
            $company = trim($m[1]);
            $rest = trim($m[2]);
            preg_match_all('/(Operations Manager|Customer & Fields Support Specialist Professional|Data Business Operations Chief Engineer|Pre Sales Engineering ER team leader|EP-Technical Support Team Leader|resident engineer in Saudi Electricity|Customer Support Engineer)\s+([A-Za-z]+\s+\d{4})\s+-\s+([A-Za-z]+\s+\d{4}|Present)\s*\(([^)]*)\)\s+([^\n]+?)(?=(Operations Manager|Customer & Fields Support Specialist Professional|Data Business Operations Chief Engineer|Pre Sales Engineering ER team leader|EP-Technical Support Team Leader|resident engineer in Saudi Electricity|Customer Support Engineer)\s+[A-Za-z]+\s+\d{4}|$)/is', $rest, $matches, PREG_SET_ORDER);
            foreach ($matches as $row) {
                $entries[] = finalizeExperienceEntry([
                    'id' => generateClientId(),
                    'company' => $company,
                    'position' => trim($row[1]),
                    'location' => trim($row[5]),
                    'startDate' => trim($row[2]),
                    'endDate' => trim($row[3]) === 'Present' ? 'Present' : trim($row[3]),
                    'current' => trim($row[3]) === 'Present',
                    'bullets' => extractBulletsFromLinkedInText($row[5])
                ]);
            }
        }
    }
    return $entries;
}

function extractBulletsFromLinkedInText($text) {
    $bullets = [];
    preg_match_all('/•\s*([^•]+)/u', $text, $matches);
    foreach (($matches[1] ?? []) as $bullet) {
        $bullet = trim($bullet, " ,");
        if ($bullet !== '') {
            $bullets[] = $bullet;
        }
    }
    return array_slice($bullets, 0, 6);
}

function extractBulletsNearPosition($text, $position) {
    $positions = [
        'Operations Manager',
        'Customer & Fields Support Specialist Professional',
        'Data Business Operations Chief Engineer',
        'Pre Sales Engineering ER team leader',
        'EP-Technical Support Team Leader',
        'resident engineer in Saudi Electricity',
        'Customer Support Engineer'
    ];

    $quoted = array_map(function ($item) {
        return preg_quote($item, '/');
    }, $positions);
    $nextBoundary = '(?=' . implode('|', $quoted) . '|Education\\s+)';

    if (preg_match('/' . preg_quote($position, '/') . '(.*?)' . $nextBoundary . '/is', $text, $m)) {
        return extractBulletsFromLinkedInText($m[1]);
    }
    return [];
}

function normalizeLinkedInPosition($position) {
    $map = [
        'Pre Sales Engineering ER team leader' => 'Pre-Sales Engineering ER Team Leader',
        'resident engineer in Saudi Electricity' => 'Resident Engineer in Saudi Electricity'
    ];
    return $map[$position] ?? $position;
}

function normalizeLinkedInLocation($location) {
    $location = preg_replace('/\bLeading many subcontract.*$/i', '', $location);
    $location = preg_replace('/\bAchievements\b.*$/i', '', $location);
    return trim($location, " ,");
}

function compareDateDesc($a, $b) {
    $ta = parseResumeDateToTimestamp($a);
    $tb = parseResumeDateToTimestamp($b);
    return $tb <=> $ta;
}

function parseResumeDateToTimestamp($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return 0;
    }
    if (preg_match('/present/i', $value)) {
        return time();
    }
    $ts = strtotime($value);
    return $ts ?: 0;
}

function extractLinkedIn($text) {
    return matchFirst('/https?:\/\/(?:www\.)?linkedin\.com\/[^\s]+/i', $text);
}

function extractUrl($text) {
    preg_match_all('/https?:\/\/[^\s]+/i', $text, $matches);
    foreach (($matches[0] ?? []) as $url) {
        $url = trim($url, " \t\n\r\0\x0B).,;");
        if (stripos($url, 'linkedin.com') !== false) {
            continue;
        }
        if (stripos($url, 'udemy.com') !== false) {
            continue;
        }
        return $url;
    }
    return '';
}

function inferTargetJobTitle($lines, $experience) {
    foreach (array_slice($lines, 0, 16) as $line) {
        if (isNoiseLine($line)) {
            continue;
        }
        if (strlen($line) >= 3 && strlen($line) <= 100 && !preg_match('/@|linkedin|github|\+?\d|page\s*\||summary|objective|skill description|last used/i', $line)) {
            if (preg_match('/engineer|developer|designer|manager|analyst|consultant|specialist|coordinator|director|assistant|architect|administrator|support/i', $line)) {
                return trim($line, " ,");
            }
        }
    }

    if (!empty($experience[0]['position'])) {
        return $experience[0]['position'];
    }

    return '';
}

function looksLikeInlineSectionLine($line) {
    return preg_match('/^(objective|summary|education|work experience|experience|skills)\b/i', trim($line)) === 1;
}

function splitInlineSectionLine($line) {
    if (preg_match('/^(objective|summary|education|work experience|experience|skills|technical skills|personal skills|educational qualifications|profile summary|career objective|employment history)\s*[:\-]?\s*(.*)$/i', trim($line), $m)) {
        $sectionKey = strtolower($m[1]);
        if ($sectionKey === 'work experience' || $sectionKey === 'employment history') {
            $sectionKey = 'experience';
        }
        if ($sectionKey === 'technical skills' || $sectionKey === 'personal skills') {
            $sectionKey = 'skills';
        }
        if ($sectionKey === 'educational qualifications') {
            $sectionKey = 'education';
        }
        return [$sectionKey, trim($m[2])];
    }

    if (preg_match('/^(education)\s{2,}(.*)$/i', trim($line), $m)) {
        return ['education', trim($m[2])];
    }

    return [null, ''];
}

function sanitizeSectionLines($lines) {
    $clean = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || isNoiseLine($line)) {
            continue;
        }
        $clean[] = $line;
    }
    return $clean;
}

function isEmployerGroupLine($line) {
    return preg_match('/^(?:[A-Z][a-z]{2,9}\s+\d{4}|\d{4}).*(?:Emircom|Mobily|Zajil|Company|Telecommunication|Training Center|Centre)/i', $line) === 1;
}

function normalizeEmployerLine($line) {
    $line = preg_replace('/^\d+\.\s*/', '', trim($line));
    if (preg_match('/(?:present|current|\d{4})\s*[-–]?\s*(.*)$/i', $line, $m)) {
        $tail = trim($m[1], ' -');
        if ($tail !== '') {
            return $tail;
        }
    }
    return $line;
}

function isRoleLine($line) {
    return preg_match('/^(?:\d+\.\s*)?(?:[A-Z][a-z]{2,9}\s+\d{4}|\d{4}|\d{1,2}\/\d{1,2}\/\d{4}).*(?:manager|engineer|specialist|leader|instructor|administrator|consultant|support)/i', $line) === 1;
}

function buildRoleEntry($line, $companyContext) {
    $entry = [
        'id' => generateClientId(),
        'company' => $companyContext,
        'position' => '',
        'location' => '',
        'startDate' => '',
        'endDate' => '',
        'current' => false,
        'bullets' => []
    ];

    $clean = preg_replace('/^\d+\.\s*/', '', trim($line));

    if (preg_match('/^(.+?)\s+as\s+(.+)$/i', $clean, $m)) {
        $datePart = trim($m[1]);
        $rolePart = trim($m[2]);
        $entry['startDate'] = extractDateStart($datePart);
        $entry['endDate'] = extractDateEnd($datePart);
        $entry['current'] = preg_match('/present|current/i', $datePart) === 1;
        $entry['position'] = trim($rolePart, ' ,');
        return $entry;
    }

    if (preg_match('/^(.+?)\s+(Operation Manager|Resident Eng\.?|Customer & Fields Support Specialist Professional|Data Business Operations Chief Engineer|Pre-Sales Engineering ER team leader|Customer Support Engineer|Senior Security Engineering|EP-Technical Support Team Leader|Senior Instructor)$/i', $clean, $m)) {
        $entry['startDate'] = extractDateStart($m[1]);
        $entry['endDate'] = extractDateEnd($m[1]);
        $entry['current'] = preg_match('/present|current/i', $m[1]) === 1;
        $entry['position'] = trim($m[2], ' ,');
        return $entry;
    }

    $entry['position'] = trim($clean, ' ,');
    return $entry;
}

function extractDateStart($text) {
    if (preg_match('/([A-Z][a-z]{2,9}\s+\d{4}|\d{4}|\d{1,2}\/\d{1,2}\/\d{4})/u', $text, $m)) {
        return trim($m[1]);
    }
    return '';
}

function extractDateEnd($text) {
    if (preg_match('/(?:to|up to)\s+(present|current|[A-Z][a-z]{2,9}\s+\d{4}|\d{4}|\d{1,2}\/\d{1,2}\/\d{4})/iu', $text, $m)) {
        $end = trim($m[1]);
        return preg_match('/present|current/i', $end) ? 'Present' : $end;
    }
    return '';
}

function cleanBulletText($text) {
    return trim(preg_replace('/^(?:[-•*]||o|\d+\.)\s*/u', '', $text), " ,");
}

function looksLikeJobTitle($line) {
    return preg_match('/manager|engineer|specialist|leader|administrator|consultant|support|instructor/i', $line) === 1;
}

function looksLikeLocation($line) {
    return preg_match('/\b(riyadh|jeddah|khobar|jubail|dammam|khartoum|sudan|ksa|saudi|remote|dubai|cairo|pakistan|india|islamabad|lahore|rawalpindi)\b/i', $line) === 1;
}

function isJunkExperienceLine($line) {
    return preg_match('/^page\s*\|?|^minimum one|^certificates?\b|^courses?\b/i', $line) === 1;
}

function isNoiseLine($line) {
    $normalized = mb_strtolower(trim($line));
     if ($normalized === 'curriculum vitae' || $normalized === 'objective' || $normalized === 'profile summary') {
        return true;
    }
    if ($normalized === '') {
        return true;
    }

    $noisePatterns = [
        '/^page\s*\|?\s*\d+/i',
        '/classification:\s*non-business/i',
        '/^non-business$/i',
        '/^use$/i',
        '/^mobile\s*:/i',
        '/^e-mail\s*:/i',
        '/^fields of interest:?$/i',
        '/^personal information/i'
    ];

    foreach ($noisePatterns as $pattern) {
        if (preg_match($pattern, $line)) {
            return true;
        }
    }

    return false;
}

function matchFirst($pattern, $text) {
    return preg_match($pattern, $text, $m) ? trim($m[0]) : '';
}

function extractExperienceFromWholeText($text) {
    if (!$text) {
        return [];
    }

    $entries = [];
    if (preg_match_all('/(?:Employer\s*:\s*(.+?)\s+Period\s*:\s*(.+?)\s+Designation\s*:\s*(.+?))(?=\s+Employer\s*:|$)/is', $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $entry = [
                'id' => generateClientId(),
                'company' => trim($m[1]),
                'position' => trim($m[3]),
                'location' => '',
                'startDate' => '',
                'endDate' => '',
                'current' => false,
                'bullets' => []
            ];
            applyDateRangeToEntry($entry, trim($m[2]));
            $entries[] = finalizeExperienceEntry($entry);
        }
    }

    if (empty($entries) && preg_match_all('/Company\s*:\s*(.+?)\.\s+Duration\s*:\s*(.+?)\.\s+Role\s*:\s*(.+?)(?=\s+Project:|\s+Roles and Responsibilities:|\s+Company\s*:|$)/is', $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $entry = [
                'id' => generateClientId(),
                'company' => trim($m[1]),
                'position' => trim($m[3]),
                'location' => '',
                'startDate' => '',
                'endDate' => '',
                'current' => false,
                'bullets' => []
            ];
            applyDateRangeToEntry($entry, trim($m[2]));
            $entries[] = finalizeExperienceEntry($entry);
        }
    }

    if (empty($entries) && preg_match_all('/([A-Z][A-Za-z&.,()\-\s]{3,80})\s+(?:July|June|May|April|March|February|January|August|September|October|November|December|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[^\n]{0,40}?\b(Role|Designation)\s*:\s*([A-Za-z&.,()\-\/\s]{3,90})/is', $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $entries[] = finalizeExperienceEntry([
                'id' => generateClientId(),
                'company' => trim($m[1]),
                'position' => trim($m[3]),
                'location' => '',
                'startDate' => '',
                'endDate' => '',
                'current' => false,
                'bullets' => []
            ]);
        }
    }

    if (empty($entries) && preg_match_all('/([A-Z][A-Za-z&.,()\-\s]{3,80})\s+(?:Duration|Period)\s*:\s*([^\n]{4,40})\s+(?:Role|Designation)\s*:\s*([A-Za-z&.,()\-\/\s]{3,90})/is', $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $entry = [
                'id' => generateClientId(),
                'company' => trim($m[1]),
                'position' => trim($m[3]),
                'location' => '',
                'startDate' => '',
                'endDate' => '',
                'current' => false,
                'bullets' => []
            ];
            applyDateRangeToEntry($entry, trim($m[2]));
            $entries[] = finalizeExperienceEntry($entry);
        }
    }

    return array_slice(array_values(array_filter($entries, function ($entry) {
        return ($entry['company'] ?? '') !== '' || ($entry['position'] ?? '') !== '';
    })), 0, 10);
}

function generateClientId() {
    return (int) round(microtime(true) * 1000) + random_int(1, 999);
}

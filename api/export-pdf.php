<?php
// API Endpoint for PDF Export
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';

class PDFExport {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function export($resumeId, $userId, $format = 'pdf') {
        // Fetch resume data
        $stmt = $this->db->prepare("SELECT * FROM resumes WHERE id = ? AND user_id = ?");
        $stmt->execute([$resumeId, $userId]);
        $resume = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$resume) {
            return ['error' => 'Resume not found'];
        }
        
        $content = json_decode($resume['content'], true);
        
        // Generate HTML for PDF
        $html = $this->generateResumeHTML($content, $resume['title']);
        
        // Save HTML for preview/download
        $filename = 'resume_' . $resumeId . '_' . time();
        $htmlPath = __DIR__ . '/../../exports/' . $filename . '.html';
        $pdfPath = __DIR__ . '/../../exports/' . $filename . '.pdf';
        
        // Ensure exports directory exists
        if (!is_dir(__DIR__ . '/../../exports')) {
            mkdir(__DIR__ . '/../../exports', 0755, true);
        }
        
        file_put_contents($htmlPath, $html);
        
        // Return HTML URL for client-side PDF generation
        return [
            'success' => true,
            'html_url' => '/exports/' . $filename . '.html',
            'preview_url' => '/preview.html?resume=' . $resumeId,
            'filename' => $filename
        ];
    }
    
    private function generateResumeHTML($content, $title) {
        $template = $content['template'] ?? 'modern';
        $color = $content['settings']['color'] ?? '#2563eb';
        $font = $content['settings']['font'] ?? 'Inter';
        
        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($title); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: '<?php echo $font; ?>', Arial, sans-serif; 
            line-height: 1.4; 
            color: #333;
            background: white;
        }
        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        @media print {
            body { background: white; }
            .page { 
                width: 100%; 
                box-shadow: none; 
                padding: 10mm;
            }
            .no-print { display: none; }
        }
        
        /* Modern Template */
        .template-modern .header {
            text-align: center;
            border-bottom: 2px solid <?php echo $color; ?>;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .template-modern .name {
            font-size: 28px;
            font-weight: bold;
            color: <?php echo $color; ?>;
            margin-bottom: 5px;
        }
        .template-modern .title {
            font-size: 16px;
            color: #666;
        }
        .template-modern .contact {
            margin-top: 10px;
            font-size: 12px;
            color: #666;
        }
        .template-modern .section {
            margin-bottom: 20px;
        }
        .template-modern .section-title {
            font-size: 14px;
            font-weight: bold;
            color: <?php echo $color; ?>;
            border-bottom: 1px solid <?php echo $color; ?>;
            padding-bottom: 5px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .template-modern .skills-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .template-modern .skill-tag {
            background: #f0f4ff;
            color: <?php echo $color; ?>;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 11px;
        }
        .template-modern .experience-item {
            margin-bottom: 15px;
        }
        .template-modern .exp-header {
            display: flex;
            justify-content: space-between;
            font-weight: 600;
        }
        .template-modern .exp-company {
            color: <?php echo $color; ?>;
        }
        .template-modern .exp-date {
            color: #666;
            font-size: 12px;
        }
        .template-modern .exp-location {
            font-size: 12px;
            color: #888;
        }
        .template-modern .bullet {
            margin: 5px 0 5px 15px;
            font-size: 12px;
        }
        
        /* Classic Template */
        .template-classic .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .template-classic .name {
            font-size: 24px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        
        /* Minimal Template */
        .template-minimal .header {
            margin-bottom: 25px;
        }
        .template-minimal .name {
            font-size: 26px;
            font-weight: 300;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }
        .template-minimal .section-title {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 10px;
            color: #333;
        }
    </style>
</head>
<body class="template-<?php echo $template; ?>">
    <div class="page">
        <?php $this->renderHeader($content['basics'] ?? []); ?>
        
        <?php if (!empty($content['summary'])): ?>
        <div class="section">
            <div class="section-title">Professional Summary</div>
            <p><?php echo nl2br(htmlspecialchars($content['summary'])); ?></p>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($content['skills'])): ?>
        <div class="section">
            <div class="section-title">Skills</div>
            <div class="skills-list">
                <?php foreach ($content['skills'] as $skill): ?>
                <span class="skill-tag"><?php echo htmlspecialchars($skill['name']); ?> 
                    (<?php echo ucfirst($skill['proficiency']); ?>)</span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($content['experience'])): ?>
        <div class="section">
            <div class="section-title">Work Experience</div>
            <?php foreach ($content['experience'] as $exp): ?>
            <div class="experience-item">
                <div class="exp-header">
                    <span><?php echo htmlspecialchars($exp['position']); ?></span>
                    <span class="exp-date"><?php echo htmlspecialchars($exp['startDate']); ?> - <?php echo htmlspecialchars($exp['endDate'] ?: 'Present'); ?></span>
                </div>
                <div class="exp-company"><?php echo htmlspecialchars($exp['company']); ?></div>
                <?php if (!empty($exp['bullets'])): ?>
                    <?php foreach ($exp['bullets'] as $bullet): ?>
                        <?php if ($bullet): ?>
                        <div class="bullet">• <?php echo htmlspecialchars($bullet); ?></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($content['education'])): ?>
        <div class="section">
            <div class="section-title">Education</div>
            <?php foreach ($content['education'] as $edu): ?>
            <div class="experience-item">
                <div class="exp-header">
                    <span><?php echo htmlspecialchars($edu['school']); ?></span>
                    <span class="exp-date"><?php echo htmlspecialchars($edu['graduationDate']); ?></span>
                </div>
                <div><?php echo htmlspecialchars($edu['degree']); ?><?php echo $edu['field'] ? ', ' . htmlspecialchars($edu['field']) : ''; ?></div>
                <?php if ($edu['gpa']): ?>
                <div style="font-size: 11px; color: #666;">GPA: <?php echo htmlspecialchars($edu['gpa']); ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($content['certifications'])): ?>
        <div class="section">
            <div class="section-title">Certifications</div>
            <?php foreach ($content['certifications'] as $cert): ?>
            <div class="bullet">
                <strong><?php echo htmlspecialchars($cert['name']); ?></strong> - 
                <?php echo htmlspecialchars($cert['issuer']); ?>
                (<?php echo htmlspecialchars($cert['date']); ?>)
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($content['languages'])): ?>
        <div class="section">
            <div class="section-title">Languages</div>
            <div class="skills-list">
                <?php foreach ($content['languages'] as $lang): ?>
                <span class="skill-tag"><?php echo htmlspecialchars($lang['name']); ?> - 
                    <?php echo ucfirst($lang['proficiency']); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="no-print" style="position: fixed; top: 20px; right: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #2563eb; color: white; border: none; border-radius: 5px; cursor: pointer; font-size: 14px;">
            <span style="margin-right: 5px;">🖨️</span> Print / Save as PDF
        </button>
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }
    
    private function renderHeader($basics) {
        ?>
        <div class="header">
            <?php if (!empty($basics['name'])): ?>
            <div class="name"><?php echo htmlspecialchars($basics['name']); ?></div>
            <?php endif; ?>
            <?php if (!empty($basics['headline'])): ?>
            <div class="title"><?php echo htmlspecialchars($basics['headline']); ?></div>
            <?php endif; ?>
            <div class="contact">
                <?php 
                $contacts = [];
                if ($basics['email']) $contacts[] = $basics['email'];
                if ($basics['phone']) $contacts[] = $basics['phone'];
                if ($basics['location']) $contacts[] = $basics['location'];
                if ($basics['linkedIn']) $contacts[] = 'LinkedIn: ' . $basics['linkedIn'];
                echo implode(' | ', array_map('htmlspecialchars', $contacts));
                ?>
            </div>
        </div>
        <?php
    }
}

// Handle request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $resumeId = $data['resume_id'] ?? null;
    $format = $data['format'] ?? 'pdf';
    
    if (!$resumeId) {
        echo json_encode(['error' => 'Resume ID required']);
        exit;
    }
    
    $auth = new Auth($pdo);
    $user = $auth->verifyToken();
    
    if (!$user) {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $pdf = new PDFExport($pdo);
    $result = $pdf->export($resumeId, $user['id'], $format);
    
    echo json_encode($result);
}


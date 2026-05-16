<?php
require_once __DIR__ . '/config/database.php';

$pageType = $_GET['slug'] ?? '';
$allowed = ['privacy', 'terms', 'about', 'help-center', 'faqs'];
if (!in_array($pageType, $allowed, true)) {
    http_response_code(404);
    echo 'Page not found';
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM pages WHERE page_type = ? AND status = 'active' LIMIT 1");
$stmt->execute([$pageType]);
$page = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$page) {
    http_response_code(404);
    echo 'Page not found';
    exit;
}

$title = $page['meta_title'] ?: $page['title'];
$description = $page['meta_description'] ?: $page['title'];
$seoOverrides = [
    'about' => [
        'title' => 'About CV Maker | Free Online Resume Builder',
        'description' => 'Learn about CV Maker, a free online resume builder that helps job seekers create professional, ATS-friendly resumes quickly.'
    ],
    'privacy' => [
        'title' => 'Privacy Policy | CV Maker',
        'description' => 'Read the CV Maker privacy policy to understand how personal information and resume data are collected, used, and protected.'
    ],
    'terms' => [
        'title' => 'Terms of Service | CV Maker',
        'description' => 'Review the CV Maker terms of service for the rules, responsibilities, and conditions that apply when using the resume builder.'
    ],
    'help-center' => [
        'title' => 'CV Maker Help Center | Resume Builder Support',
        'description' => 'Get help with CV Maker accounts, resume templates, editing, downloads, and common online resume builder questions.'
    ],
    'faqs' => [
        'title' => 'CV Maker FAQs | Resume Builder Help',
        'description' => 'Find answers to common CV Maker questions about resume templates, accounts, editing, privacy, support, and building professional resumes online.'
    ]
];
$displayTitleMap = [
    'help-center' => 'CV Maker Help Center',
    'faqs' => 'CV Maker FAQs'
];
$seoTitle = $seoOverrides[$pageType]['title'] ?? $title;
$seoDescription = $seoOverrides[$pageType]['description'] ?? $description;
$displayTitle = $displayTitleMap[$pageType] ?? $page['title'];
$canonical = 'https://cvmaker.ink/' . $pageType . '.html';
$pageLabelMap = [
    'about' => 'Company',
    'privacy' => 'Legal',
    'terms' => 'Legal',
    'help-center' => 'Support',
    'faqs' => 'Support'
];
$pageLeadMap = [
    'about' => 'Learn more about cvmaker.ink, what we offer, and how we help job seekers build stronger resumes faster.',
    'privacy' => 'Understand how cvmaker.ink collects, uses, stores, and protects information when you use the platform.',
    'terms' => 'Review the main rules, responsibilities, and conditions that apply when using cvmaker.ink.',
    'help-center' => 'Find practical support guidance, account help, and answers to common resume builder questions.',
    'faqs' => 'Browse quick answers about CV Maker accounts, templates, resume editing, privacy, and support.'
];
$pageIconMap = [
    'about' => '🏢',
    'privacy' => '🔒',
    'terms' => '📘',
    'help-center' => '🛟',
    'faqs' => '❓'
];
$eyebrow = $pageLabelMap[$pageType] ?? 'Information';
$lead = $pageLeadMap[$pageType] ?? $description;
$pageIcon = $pageIconMap[$pageType] ?? '📄';
$navItems = [
    ['slug' => 'about', 'label' => 'About'],
    ['slug' => 'privacy', 'label' => 'Privacy'],
    ['slug' => 'terms', 'label' => 'Terms'],
    ['slug' => 'help-center', 'label' => 'Help Center'],
    ['slug' => 'faqs', 'label' => 'FAQs']
];
$structuredData = [
    '@context' => 'https://schema.org',
    '@type' => $pageType === 'faqs' ? 'FAQPage' : 'WebPage',
    'name' => $displayTitle,
    'description' => $seoDescription,
    'url' => $canonical,
    'isPartOf' => [
        '@type' => 'WebSite',
        'name' => 'CV Maker',
        'url' => 'https://cvmaker.ink/'
    ],
    'publisher' => [
        '@type' => 'Organization',
        'name' => 'CV Maker',
        'url' => 'https://cvmaker.ink/'
    ]
];

if ($pageType === 'faqs') {
    $structuredData['mainEntity'] = [
        [
            '@type' => 'Question',
            'name' => 'What is cvmaker.ink?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'cvmaker.ink is an online resume builder that helps users create professional resumes using structured templates and simple editing tools.'
            ]
        ],
        [
            '@type' => 'Question',
            'name' => 'Do I need an account to use cvmaker.ink?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'Some features may require an account so you can save and manage your resume content over time.'
            ]
        ],
        [
            '@type' => 'Question',
            'name' => 'Can I edit my resume later?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'Yes. If your resume is saved under your account, you can sign in later and continue editing it.'
            ]
        ],
        [
            '@type' => 'Question',
            'name' => 'Are the templates ATS-friendly?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'The platform is designed to provide clean, readable templates that support practical resume formatting for online job applications.'
            ]
        ],
        [
            '@type' => 'Question',
            'name' => 'Can I contact support?',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => 'Yes. If you need help, use the Contact Us page and send us your question.'
            ]
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($seoTitle); ?> | cvmaker.ink</title>
    <meta name="description" content="<?php echo htmlspecialchars($seoDescription); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($canonical); ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo htmlspecialchars($seoTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($seoDescription); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonical); ?>">
    <meta property="og:image" content="https://cvmaker.ink/assets/og-default.jpg">
    <script type="application/ld+json">
    <?php echo json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .page-shell { background: radial-gradient(circle at top, rgba(59,130,246,0.10), transparent 32%), linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%); }
        .content-card { box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08); }
        .page-prose { color: #334155; line-height: 1.8; }
        .page-prose > div { display: flex; flex-direction: column; gap: 2rem; }
        .page-prose section { border-top: 1px solid #e2e8f0; padding-top: 1.5rem; }
        .page-prose section:first-of-type { border-top: 0; padding-top: 0; }
        .page-prose h2 { font-size: 1.25rem; line-height: 1.75rem; font-weight: 700; color: #0f172a; margin-bottom: 0.75rem; }
        .page-prose p { margin-bottom: 1rem; }
        .page-prose ul, .page-prose ol { margin: 1rem 0 1rem 1.25rem; }
        .page-prose li { margin: 0.5rem 0; padding-left: 0.25rem; }
        .page-prose a { color: #2563eb; font-weight: 500; text-decoration: none; }
        .page-prose a:hover { text-decoration: underline; }
        .page-prose section h2 + p,
        .page-prose section h2 + ul,
        .page-prose section h2 + ol { margin-top: 0.5rem; }
        .mini-nav a.active { background: #dbeafe; color: #1d4ed8; border-color: #bfdbfe; }
        .faq-accordion details { border: 1px solid #e2e8f0; border-radius: 1rem; background: #f8fafc; padding: 1rem 1.25rem; }
        .faq-accordion details + details { margin-top: 1rem; }
        .faq-accordion summary { cursor: pointer; list-style: none; font-weight: 700; color: #0f172a; }
        .faq-accordion summary::-webkit-details-marker { display: none; }
        .faq-accordion .faq-answer { margin-top: 0.85rem; color: #475569; }
    </style>
  <script src="/assets/js/tracking-loader.js"></script>
</head>
<body class="page-shell text-gray-900 min-h-screen">
    <div class="max-w-5xl mx-auto px-6 py-10 md:py-14">
        <div class="mb-8 flex items-center justify-between gap-4">
            <a href="/" class="inline-flex items-center text-blue-700 hover:text-blue-800 font-medium">&larr; Back to Home</a>
            <a href="/contact.html" class="hidden sm:inline-flex items-center px-4 py-2 rounded-lg bg-white border border-slate-200 text-slate-700 hover:bg-slate-50">Contact Support</a>
        </div>

        <nav class="mini-nav mb-8 flex flex-wrap gap-3">
            <?php foreach ($navItems as $item): ?>
                <?php $isActive = $item['slug'] === $pageType; ?>
                <a href="/<?php echo htmlspecialchars($item['slug']); ?>.html" class="<?php echo $isActive ? 'active' : ''; ?> inline-flex items-center px-4 py-2 rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 text-sm font-medium">
                    <?php echo htmlspecialchars($item['label']); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <section class="mb-8 md:mb-10">
            <span class="inline-flex items-center px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-sm font-semibold mb-4"><?php echo htmlspecialchars($eyebrow); ?></span>
            <div class="flex items-start gap-4 mb-4">
                <div class="text-4xl md:text-5xl leading-none"><?php echo htmlspecialchars($pageIcon); ?></div>
                <div>
                    <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight text-slate-900 mb-4"><?php echo htmlspecialchars($displayTitle); ?></h1>
                    <p class="max-w-3xl text-lg text-slate-600"><?php echo htmlspecialchars($lead); ?></p>
                </div>
            </div>
        </section>

        <article class="content-card bg-white rounded-3xl border border-slate-200 p-8 md:p-12 page-prose <?php echo $pageType === 'faqs' ? 'faq-accordion' : ''; ?>">
            <div><?php echo $page['content']; ?></div>
        </article>
    </div>

    <script>
      if (document.body.classList.contains('page-shell') && window.location.pathname === '/faqs.html') {
        document.querySelectorAll('.faq-accordion section').forEach((section) => {
          const heading = section.querySelector('h2');
          const paragraphs = Array.from(section.querySelectorAll('p, ul, ol'));
          if (!heading || paragraphs.length === 0) return;
          const details = document.createElement('details');
          const summary = document.createElement('summary');
          summary.textContent = heading.textContent;
          details.appendChild(summary);
          const answer = document.createElement('div');
          answer.className = 'faq-answer';
          paragraphs.forEach((node) => answer.appendChild(node.cloneNode(true)));
          details.appendChild(answer);
          section.replaceWith(details);
        });
      }
    </script>
</body>
</html>

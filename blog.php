<?php
require_once __DIR__ . '/config/database.php';

$slug = $_GET['slug'] ?? null;

if ($slug) {
    $stmt = $pdo->prepare("SELECT * FROM blogs WHERE slug = ? AND status = 'published' LIMIT 1");
    $stmt->execute([$slug]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$post) {
        http_response_code(404);
        echo 'Blog post not found';
        exit;
    }

    $update = $pdo->prepare("UPDATE blogs SET views = views + 1 WHERE id = ?");
    $update->execute([$post['id']]);

    $title = $post['meta_title'] ?: $post['title'];
    $description = $post['meta_description'] ?: ($post['excerpt'] ?: $post['title']);
    $canonical = 'https://cvmaker.ink/blog/' . rawurlencode($post['slug']) . '.html';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> | cvmaker.ink</title>
        <meta name="description" content="<?php echo htmlspecialchars($description); ?>">
        <link rel="canonical" href="<?php echo htmlspecialchars($canonical); ?>">
        <meta property="og:type" content="article">
        <meta property="og:title" content="<?php echo htmlspecialchars($title); ?>">
        <meta property="og:description" content="<?php echo htmlspecialchars($description); ?>">
        <meta property="og:url" content="<?php echo htmlspecialchars($canonical); ?>">
        <?php if (!empty($post['featured_image'])): ?>
        <meta property="og:image" content="https://cvmaker.ink<?php echo htmlspecialchars($post['featured_image']); ?>">
        <?php endif; ?>
        <script type="application/ld+json">
        <?php
        echo json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $post['title'],
            'description' => $description,
            'url' => $canonical,
            'datePublished' => date('c', strtotime($post['created_at'])),
            'dateModified' => date('c', strtotime($post['updated_at'] ?? $post['created_at'])),
            'image' => !empty($post['featured_image']) ? 'https://cvmaker.ink' . $post['featured_image'] : null,
            'author' => [
                '@type' => 'Organization',
                'name' => 'CV Maker'
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'CV Maker',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => 'https://cvmaker.ink/uploads/branding/cvmaker-logo-20260515.svg'
                ]
            ]
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        ?>
        </script>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            .prose h1 { font-size: 2.25rem; font-weight: 800; margin-top: 2rem; margin-bottom: 1rem; color: #111827; line-height: 1.2; }
            .prose h2 { font-size: 1.75rem; font-weight: 700; margin-top: 2rem; margin-bottom: 1rem; color: #1f2937; line-height: 1.3; }
            .prose h3 { font-size: 1.375rem; font-weight: 600; margin-top: 1.5rem; margin-bottom: 0.75rem; color: #374151; line-height: 1.4; }
            .prose p { margin-bottom: 1.25rem; line-height: 1.75; color: #4b5563; }
            .prose ul { list-style-type: disc; padding-left: 1.5rem; margin-bottom: 1.25rem; }
            .prose li { margin-bottom: 0.5rem; color: #4b5563; }
            .prose strong { font-weight: 700; color: #111827; }
            .prose a { color: #2563eb; text-decoration: underline; }
            .prose a:hover { color: #1d4ed8; }
        </style>
      <script src="/branding.js"></script>
      <script src="/assets/js/tracking-loader.js"></script>
    </head>
    <body class="bg-gray-50 text-gray-900">
        <div class="max-w-4xl mx-auto px-6 py-12">
            <div class="mb-8 flex gap-4 text-sm">
                <a href="/blog.html" class="text-blue-600 hover:underline">&larr; Back to Blog</a>
                <a href="/" class="text-blue-600 hover:underline">Home</a>
            </div>
            <article class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <?php if (!empty($post['featured_image'])): ?>
                    <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="w-full h-72 object-cover">
                <?php endif; ?>
                <div class="p-8 md:p-12">
                    <p class="text-sm text-gray-500 mb-3"><?php echo htmlspecialchars(date('F j, Y', strtotime($post['created_at']))); ?></p>
                    <h1 class="text-4xl font-bold text-gray-900 mb-4"><?php echo htmlspecialchars($post['title']); ?></h1>
                    <?php if (!empty($post['excerpt'])): ?>
                        <p class="text-lg text-gray-600 mb-8"><?php echo htmlspecialchars($post['excerpt']); ?></p>
                    <?php endif; ?>
                    <div class="prose prose-slate max-w-none"><?php echo $post['content']; ?></div>
                </div>
            </article>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$stmt = $pdo->query("SELECT id, title, slug, excerpt, featured_image, created_at FROM blogs WHERE status = 'published' ORDER BY created_at DESC");
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume Writing Tips, CV Examples & Career Advice | CV Maker</title>
    <meta name="description" content="Read CV Maker resume writing tips, ATS resume advice, CV examples, and career guidance to help you build a better job-ready resume.">
    <link rel="canonical" href="https://cvmaker.ink/blog.html">
    <meta property="og:title" content="Resume Writing Tips, CV Examples & Career Advice | CV Maker">
    <meta property="og:description" content="Read resume writing tips, ATS resume advice, CV examples, and career guidance from CV Maker.">
    <meta property="og:url" content="https://cvmaker.ink/blog.html">
    <script type="application/ld+json">
    <?php
    echo json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Blog',
        'name' => 'CV Maker Blog',
        'url' => 'https://cvmaker.ink/blog.html',
        'description' => 'Resume writing tips, CV examples, ATS resume advice, and career guidance from CV Maker.',
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'CV Maker',
            'url' => 'https://cvmaker.ink/'
        ]
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    ?>
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    </head>
<body class="bg-gray-50 text-gray-900">
    <div class="max-w-6xl mx-auto px-6 py-12">
        <div class="mb-10">
            <a href="/" class="text-blue-600 hover:underline">&larr; Back to Home</a>
            <h1 class="text-4xl font-bold text-gray-900 mt-4">Resume Writing Tips & Career Advice</h1>
            <p class="text-gray-600 mt-2">Practical CV examples, ATS resume guidance, and job-search tips from CV Maker.</p>
        </div>
        <?php if (!$posts): ?>
            <div class="bg-white rounded-2xl border border-gray-200 p-10 text-center text-gray-500">No published posts yet.</div>
        <?php else: ?>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($posts as $post): ?>
                    <article class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                        <?php if (!empty($post['featured_image'])): ?>
                            <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="w-full h-48 object-cover">
                        <?php endif; ?>
                        <div class="p-6">
                            <p class="text-sm text-gray-500 mb-2"><?php echo htmlspecialchars(date('F j, Y', strtotime($post['created_at']))); ?></p>
                            <h2 class="text-xl font-semibold text-gray-900 mb-3"><?php echo htmlspecialchars($post['title']); ?></h2>
                            <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($post['excerpt'] ?: 'Read the full article.'); ?></p>
                            <a href="/blog/<?php echo rawurlencode($post['slug']); ?>.html" class="text-blue-600 font-medium hover:underline">Read more</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

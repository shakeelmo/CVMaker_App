-- CV Maker v2.0 Database Schema and Seed Data
-- Created: 2026-05-03
-- Character Set: utf8mb4_unicode_ci
-- Foreign Keys: NONE (intentionally omitted for installer compatibility)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- =============================================
-- TABLE: users
-- =============================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'user',
  `phone` varchar(20) DEFAULT NULL,
  `avatar_url` varchar(500) DEFAULT NULL,
  `subscription_tier` enum('free','basic','pro') DEFAULT 'free',
  `subscription_expires` date DEFAULT NULL,
  `ai_enabled` tinyint(1) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `email_verified` tinyint(1) NOT NULL DEFAULT 0,
  `force_password_change` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_subscription` (`subscription_tier`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: resumes
-- =============================================
DROP TABLE IF EXISTS `resumes`;
CREATE TABLE `resumes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `template_id` int(11) DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `view_count` int(11) DEFAULT 0,
  `download_count` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `profile_photo` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_user` (`user_id`),
  KEY `idx_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- TABLE: templates
-- SEED DATA: 12 default templates
-- =============================================
DROP TABLE IF EXISTS `templates`;
CREATE TABLE `templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `css_file` varchar(255) DEFAULT NULL,
  `is_premium` tinyint(1) DEFAULT 0,
  `category` enum('modern','classic','creative','minimal','professional') DEFAULT 'modern',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_category` (`category`),
  KEY `idx_premium` (`is_premium`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `templates` (`id`, `name`, `slug`, `description`, `thumbnail_url`, `css_file`, `is_premium`, `category`, `is_active`, `created_at`) VALUES
(1, 'Modern Professional', 'modern-professional', 'Clean and professional template suitable for most industries', NULL, NULL, 0, 'modern', 1, NOW()),
(2, 'Classic Elegant', 'classic-elegant', 'Traditional elegant design for conservative industries', NULL, NULL, 1, 'classic', 1, NOW()),
(3, 'Minimal Clean', 'minimal-clean', 'Simple minimal design that focuses on content', NULL, NULL, 0, 'minimal', 1, NOW()),
(4, 'Creative Bold', 'creative-bold', 'Standout design for creative professionals', NULL, NULL, 0, 'creative', 1, NOW()),
(5, 'Executive Premium', 'executive-premium', 'Sophisticated design for senior positions', NULL, NULL, 0, 'professional', 1, NOW()),
(6, 'Glalie', 'glalie', 'Creative Pokemon-themed template with ice blue accents', '/uploads/templates/glalie.jpg', NULL, 0, 'creative', 1, NOW()),
(7, 'Azurill', 'azurill', 'Playful Pokemon-themed template with soft blue tones', '/uploads/templates/azurill.jpg', NULL, 0, 'creative', 1, NOW()),
(8, 'Bronzor', 'bronzor', 'Professional Pokemon-themed template with metallic bronze', '/uploads/templates/bronzor.jpg', NULL, 0, 'professional', 1, NOW()),
(9, 'Chikorita', 'chikorita', 'Nature-inspired Pokemon-themed template with fresh green', '/uploads/templates/chikorita.jpg', NULL, 0, 'creative', 1, NOW()),
(10, 'Ditto', 'ditto', 'Transformative Pokemon-themed template adaptable for any role', '/uploads/templates/ditto.jpg', NULL, 0, 'modern', 1, NOW()),
(11, 'Pikachu', 'pikachu', 'Energetic Pokemon-themed template with electric yellow accents', '/uploads/templates/pikachu.jpg', NULL, 0, 'creative', 1, NOW()),
(12, 'Gengar', 'gengar', 'Bold Pokemon-themed template with deep purple styling', '/uploads/templates/gengar.jpg', NULL, 0, 'creative', 1, NOW());

-- =============================================
-- TABLE: pages
-- SEED DATA: 6 CMS pages
-- =============================================
DROP TABLE IF EXISTS `pages`;
CREATE TABLE `pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page_type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `meta_keywords` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_type` (`page_type`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `pages` (`id`, `page_type`, `title`, `content`, `meta_title`, `meta_description`, `meta_keywords`, `status`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'privacy', 'Privacy Policy', '<div class="space-y-8">
  <p>This Privacy Policy explains how cvmaker.ink collects, uses, stores, and protects information when you use our website and services.</p>

  <section>
    <h2>Information we collect</h2>
    <p>We may collect personal information you provide directly to us, including your name, email address, account details, resume content, support messages, and any other information you choose to submit while using cvmaker.ink.</p>
  </section>

  <section>
    <h2>How we use information</h2>
    <ul>
      <li>To create and manage your account</li>
      <li>To provide access to resume-building tools and related features</li>
      <li>To save and display the resume content you create</li>
      <li>To respond to support requests and service-related communication</li>
      <li>To improve the website, content, usability, and platform performance</li>
      <li>To protect the platform from abuse, fraud, or unauthorized access</li>
    </ul>
  </section>

  <section>
    <h2>Resume content</h2>
    <p>Resume content stored on cvmaker.ink may include work history, education, skills, summaries, and contact information. You are responsible for the accuracy of the content you provide.</p>
  </section>

  <section>
    <h2>Cookies and analytics</h2>
    <p>We may use essential cookies, session data, and basic analytics tools to maintain functionality, improve performance, and understand how users interact with the platform.</p>
  </section>

  <section>
    <h2>How we share information</h2>
    <p>We do not sell your personal information. We may share limited information with trusted service providers or infrastructure partners when required to operate the platform, process payments, deliver technical services, or maintain security.</p>
  </section>

  <section>
    <h2>Data retention</h2>
    <p>We retain personal information and resume content for as long as needed to provide the service, comply with legal obligations, resolve disputes, or enforce our agreements.</p>
  </section>

  <section>
    <h2>Data security</h2>
    <p>We apply reasonable technical and organizational measures to protect personal information. However, no internet-based service can guarantee absolute security.</p>
  </section>

  <section>
    <h2>Your choices</h2>
    <p>You may contact us for account-related support, corrections, or other assistance regarding your information and platform use.</p>
  </section>

  <section>
    <h2>Children\'s privacy</h2>
    <p>cvmaker.ink is not intended for children under 13, and we do not knowingly collect personal information from children under 13.</p>
  </section>

  <section>
    <h2>Changes to this policy</h2>
    <p>We may update this Privacy Policy from time to time. Updated versions will be reflected on this page.</p>
  </section>

  <section>
    <h2>Contact us</h2>
    <p>If you have questions about this Privacy Policy, please contact us through our <a href="/contact.html">Contact Us</a> page.</p>
  </section>
</div>', NULL, 'Read the cvmaker.ink Privacy Policy to understand how we collect, use, store, and protect your personal information and account data.', NULL, 'active', NULL, NOW(), NOW()),
(2, 'terms', 'Terms of Service', '<div class="space-y-8">
  <p>These Terms of Service govern your access to and use of cvmaker.ink. By using the platform or creating an account, you agree to these terms.</p>

  <section>
    <h2>Use of the service</h2>
    <p>cvmaker.ink provides tools to help users create, manage, and improve resumes and related career documents. You agree to use the platform only for lawful purposes and in a way that does not harm the service, other users, or platform security.</p>
  </section>

  <section>
    <h2>Account responsibility</h2>
    <p>You are responsible for maintaining the confidentiality of your account credentials and for activities that occur under your account.</p>
  </section>

  <section>
    <h2>User content</h2>
    <p>You remain responsible for the content you create, upload, save, or submit through cvmaker.ink, including resumes, profile details, and messages. You confirm that you have the right to use that content.</p>
  </section>

  <section>
    <h2>Prohibited conduct</h2>
    <ul>
      <li>Using the platform for unlawful, deceptive, or abusive purposes</li>
      <li>Attempting to disrupt, damage, reverse engineer, scrape, or compromise the service</li>
      <li>Uploading malicious code or harmful material</li>
      <li>Using the platform in a way that infringes privacy or intellectual property rights</li>
    </ul>
  </section>

  <section>
    <h2>Subscriptions and payments</h2>
    <p>Some features may require a paid subscription or upgraded plan. Pricing, billing terms, and available features may change over time.</p>
  </section>

  <section>
    <h2>Availability</h2>
    <p>We may update, modify, suspend, or discontinue parts of the service at any time. We do not guarantee uninterrupted availability.</p>
  </section>

  <section>
    <h2>Disclaimer</h2>
    <p>cvmaker.ink is provided on an "as is" and "as available" basis. We do not guarantee the platform will always be error-free, uninterrupted, or suitable for every specific hiring outcome.</p>
  </section>

  <section>
    <h2>Limitation of liability</h2>
    <p>To the maximum extent permitted by law, cvmaker.ink and its operators shall not be liable for indirect, incidental, special, consequential, or punitive damages arising from use of the service.</p>
  </section>

  <section>
    <h2>Termination</h2>
    <p>We may suspend or terminate access if these Terms are violated, misuse is detected, or continued access creates risk for the platform or other users.</p>
  </section>

  <section>
    <h2>Changes to these terms</h2>
    <p>We may update these Terms of Service from time to time. Continued use of the platform after updates means you accept the revised terms.</p>
  </section>

  <section>
    <h2>Contact</h2>
    <p>If you have questions about these Terms, please contact us through our <a href="/contact.html">Contact Us</a> page.</p>
  </section>
</div>', NULL, 'Review the cvmaker.ink Terms of Service for the rules, responsibilities, and conditions that apply when using our resume builder platform.', NULL, 'active', NULL, NOW(), NOW()),
(3, 'contact', 'Contact Us', '<h1>Contact Us</h1><p>Get in touch with us...</p>', 'Contact Us', '', '', 'active', NULL, NOW(), NOW()),
(4, 'about', 'About', '<div class="space-y-8">
  <p>cvmaker.ink is an online resume builder created to help job seekers build clear, professional, and ATS-friendly resumes faster. We focus on practical tools that reduce formatting friction and help users present their experience with confidence.</p>
  <p>Whether you are a student, graduate, professional, freelancer, or career changer, cvmaker.ink is designed to give you a simpler way to create polished resumes without starting from a blank page.</p>

  <section>
    <h2>What we offer</h2>
    <ul>
      <li>Professional resume templates built for clarity and readability</li>
      <li>Simple editing tools for personal details, experience, education, and skills</li>
      <li>Structured resume-building workflows that save time</li>
      <li>Account-based access to save, manage, and improve resumes over time</li>
    </ul>
  </section>

  <section>
    <h2>Our mission</h2>
    <p>Our mission is to make resume creation faster, easier, and more effective. We want users to spend less time struggling with layout and more time communicating their value to employers.</p>
  </section>

  <section>
    <h2>Who we serve</h2>
    <p>cvmaker.ink is designed for people creating resumes for job applications, internships, freelance opportunities, career transitions, and professional networking.</p>
  </section>

  <section>
    <h2>Need help?</h2>
    <p>If you have questions, feedback, or support requests, please visit our <a href="/contact.html">Contact Us</a> page.</p>
  </section>
</div>', 'About', 'Learn about cvmaker.ink, our mission, and how our platform helps job seekers create better resumes faster.', '', 'active', NULL, NOW(), NOW()),
(5, 'help-center', 'Help Center', '<div class="space-y-8">
  <p>Welcome to the cvmaker.ink Help Center. Here you can find support guidance for using the platform more effectively.</p>

  <section>
    <h2>Getting started</h2>
    <ol>
      <li>Create an account or sign in to your existing account</li>
      <li>Select a resume template that fits your needs</li>
      <li>Add your personal details, experience, education, and skills</li>
      <li>Review your resume carefully before saving or downloading</li>
    </ol>
  </section>

  <section>
    <h2>Account help</h2>
    <p>If you cannot sign in, verify that your email address and password are correct. If the issue continues, use the <a href="/contact.html">Contact Us</a> page to request support.</p>
  </section>

  <section>
    <h2>Resume editing help</h2>
    <p>To update a saved resume, sign in to your account, open the resume from your dashboard, make the needed changes, and save again.</p>
  </section>

  <section>
    <h2>Templates and formatting</h2>
    <p>cvmaker.ink templates are structured for readability and professional presentation. Choose the design that best matches your background and goals.</p>
  </section>

  <section>
    <h2>Payments and subscriptions</h2>
    <p>If premium features are available on your account, review the current plan details before purchase. For billing or access issues, contact support using your account email address.</p>
  </section>

  <section>
    <h2>Technical issues</h2>
    <p>If a page is not loading correctly, try refreshing the page, signing out and back in, or using a current browser version. If the problem continues, contact support with the page name and a short description of the issue.</p>
  </section>

  <section>
    <h2>Contact support</h2>
    <p>If you need direct help, please use the <a href="/contact.html">Contact Us</a> page and include enough detail for faster support.</p>
  </section>
</div>', 'Help Center', 'Visit the cvmaker.ink Help Center for support articles, guidance, and answers to common account and resume builder questions.', '', 'active', NULL, NOW(), NOW()),
(6, 'faqs', 'FAQs', '<div class="space-y-6">
  <p>Below are answers to common questions about cvmaker.ink.</p>

  <section>
    <h2>What is cvmaker.ink?</h2>
    <p>cvmaker.ink is an online resume builder that helps users create professional resumes using structured templates and simple editing tools.</p>
  </section>

  <section>
    <h2>Do I need an account to use cvmaker.ink?</h2>
    <p>Some features may require an account so you can save and manage your resume content over time.</p>
  </section>

  <section>
    <h2>Can I edit my resume later?</h2>
    <p>Yes. If your resume is saved under your account, you can sign in later and continue editing it.</p>
  </section>

  <section>
    <h2>Are the templates ATS-friendly?</h2>
    <p>The platform is designed to provide clean, readable templates that support practical resume formatting for online job applications.</p>
  </section>

  <section>
    <h2>Can I contact support?</h2>
    <p>Yes. If you need help, use the <a href="/contact.html">Contact Us</a> page and send us your question.</p>
  </section>

  <section>
    <h2>Do you offer paid features?</h2>
    <p>cvmaker.ink may offer premium or upgraded features depending on the current platform setup. Please review the plan details shown on the website for the latest information.</p>
  </section>

  <section>
    <h2>Is my resume data private?</h2>
    <p>Please review our <a href="/privacy.html">Privacy Policy</a> for details on how personal information and resume content are handled.</p>
  </section>

  <section>
    <h2>Where can I learn the rules for using the service?</h2>
    <p>Please review our <a href="/terms.html">Terms of Service</a> for the conditions that apply when using cvmaker.ink.</p>
  </section>
</div>', 'FAQs', 'Find answers to the most common questions about cvmaker.ink, including templates, accounts, subscriptions, and resume creation.', '', 'active', NULL, NOW(), NOW());

-- =============================================
-- TABLE: blogs
-- SEED DATA: 5 blog posts with LOCAL image paths
-- =============================================
DROP TABLE IF EXISTS `blogs`;
CREATE TABLE `blogs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `excerpt` mediumtext DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `featured_image` varchar(500) DEFAULT NULL,
  `status` enum('draft','published') DEFAULT 'draft',
  `author_id` int(11) DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` mediumtext DEFAULT NULL,
  `meta_keywords` mediumtext DEFAULT NULL,
  `views` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_status` (`status`),
  KEY `idx_author` (`author_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `blogs` (`id`, `title`, `slug`, `excerpt`, `content`, `featured_image`, `status`, `author_id`, `meta_title`, `meta_description`, `meta_keywords`, `views`, `created_at`, `updated_at`) VALUES
(3, 'How to Write a Resume That Gets Past ATS in 2026', 'ats-resume-2026', 'Learn how to beat applicant tracking systems in 2026. Expert tips on ATS resume formatting, keyword optimization, and common mistakes to avoid.', '<p>You spent hours perfecting your resume. You chose the right words, formatted it beautifully, and felt proud hitting submit. Then silence. No callback, no interview, nothing. The frustrating truth? Your resume probably never reached human eyes.</p><h2>What Exactly Is an ATS?</h2><p>An Applicant Tracking System is software that automates the hiring process. When you submit your resume online, the ATS scans, parses, and ranks your application before any human reviews it.</p><p>Research suggests that up to 75% of resumes never make it past ATS screening. Three out of four qualified candidates get eliminated by software before a human ever sees their application.</p><h2>Why Most Resumes Fail ATS Screening</h2><ul><li>Complex formatting confuses parsing algorithms</li><li>Missing keywords from the job description</li><li>Wrong file format</li><li>Unstandardized section headings</li><li>Contact information in headers</li></ul><h2>Quick Wins for Immediate Improvement</h2><ul><li>Customize your resume for each application using the exact job title</li><li>Mirror the language from the job description</li><li>Include a dedicated Skills section with relevant keywords</li><li>Quantify achievements with numbers</li><li>Test your resume with free ATS scanners</li></ul>', '/uploads/blog/ats-resume-2026.jpg', 'published', 1, 'How to Write a Resume That Gets Past ATS in 2026 | CV Maker', 'Learn how to beat applicant tracking systems in 2026. Expert tips on ATS resume formatting, keyword optimization, and common mistakes to avoid.', NULL, 0, NOW(), NOW()),
(4, '10 Resume Mistakes That Are Costing You Interviews', 'resume-mistakes', 'Learn the 10 most common resume mistakes that prevent you from getting interviews. Fix these errors today and start landing more job offers.', '<p>You have sent out dozens of resumes. You have applied to jobs you are perfectly qualified for. Yet your inbox remains silent. Before you blame the job market, consider this: your resume might be sabotaging you.</p><h2>Mistake #1: Using a Generic Resume for Every Application</h2><p>Sending the same resume to every job is like wearing a suit to the beach. Companies use applicant tracking systems that scan for specific keywords. When your resume lacks those terms, it gets filtered out.</p><h2>Mistake #2: Writing Long Paragraphs Instead of Bullet Points</h2><p>Recruiters spend an average of six seconds scanning your resume initially. Dense paragraphs hide your achievements in walls of text.</p><h2>Mistake #3: Focusing on Responsibilities Instead of Results</h2><p>Responsible for managing a team tells me what you were supposed to do. Managed a team of 12, reducing turnover by 40% while increasing productivity by 25% tells me what you actually achieved.</p><h2>Mistake #4: Including Outdated or Irrelevant Information</h2><p>Your resume from 2015 will not get you hired in 2026. Remove outdated skills, old positions, and anything that does not support your current career goals.</p>', '/uploads/blog/resume-mistakes.jpg', 'published', 1, '10 Resume Mistakes That Are Costing You Interviews | CV Maker', 'Learn the 10 most common resume mistakes that prevent you from getting interviews. Fix these errors today and start landing more job offers.', NULL, 0, NOW(), NOW()),
(5, 'How to Write a Professional Summary for Your Resume (With Examples)', 'resume-professional-summary', 'Learn how to write a compelling professional summary that grabs recruiters attention. Includes proven formula and 5 real examples you can copy.', '<p>The professional summary sits at the top of your resume, yet most job seekers treat it as an afterthought. This small paragraph is actually your most important real estate.</p><h2>What Is a Professional Summary?</h2><p>A professional summary is a 3-5 sentence overview of your qualifications, experience, and unique value proposition. It answers one critical question: Why should we hire you?</p><h2>The Formula That Works</h2><p>[Job Title] with [X years] of experience in [Industry/Skill]. Proven track record of [Specific Achievement 1] and [Specific Achievement 2]. Skilled in [Key Skill 1], [Key Skill 2], and [Key Skill 3].</p><h2>Example for a Marketing Manager</h2><p>Marketing Manager with 8 years of experience driving growth for SaaS companies. Increased lead generation by 150% through data-driven campaigns and improved conversion rates by 35%. Expert in SEO, content marketing, and marketing automation.</p>', '/uploads/blog/resume-professional-summary.jpg', 'published', 1, 'How to Write a Professional Summary for Your Resume (With Examples) | CV Maker', 'Learn how to write a compelling professional summary that grabs recruiters attention. Includes proven formula and 5 real examples you can copy.', NULL, 0, NOW(), NOW()),
(7, 'Free vs Paid Resume Builders: Which One Should You Use in 2026?', 'free-vs-paid-resume-builders', 'Should you use a free or paid resume builder? Discover the pros and cons of each option and find out which one is right for your job search in 2026.', '<p>You need a resume that stands out. You have two options: use a free resume builder or pay for a premium service. Which should you choose?</p><h2>The Case for Free Resume Builders</h2><p>Free builders work well for simple, straightforward resumes. If you have a clean work history and need a basic layout, free tools can get the job done.</p><h2>Limitations of Free Tools</h2><ul><li>Limited template selection</li><li>Basic formatting options</li><li>No ATS optimization features</li><li>Watermarked exports</li><li>No customer support</li></ul><h2>When Paid Tools Make Sense</h2><p>Premium resume builders offer advanced templates, ATS optimization, unlimited exports, and professional support. The investment pays off when you land your target job faster.</p>', '/uploads/blog/free-vs-paid-resume-builders.jpg', 'published', 1, 'Free vs Paid Resume Builders: Which One Should You Use in 2026? | CV Maker', 'Should you use a free or paid resume builder? Discover the pros and cons of each option and find out which one is right for your job search in 2026.', NULL, 0, NOW(), NOW()),
(8, 'How to Tailor Your Resume for Every Job Application', 'tailor-resume-for-job', 'Stop sending generic resumes. Learn the exact steps to customize your resume for each job application and dramatically increase your interview rate.', '<p>You found the perfect job posting. You have the skills. You have the experience. You send your standard resume and hear nothing back. What went wrong?</p><h2>Why Generic Resumes Fail</h2><p>Companies receive hundreds of applications. Generic resumes blend into the noise. Customized resumes stand out because they directly address the specific needs of the employer.</p><h2>The 5-Minute Tailoring Process</h2><ol><li>Read the job description carefully and highlight key requirements</li><li>Compare your resume to those requirements</li><li>Add missing keywords naturally into your skills and experience sections</li><li>Reorder bullet points to prioritize relevant achievements</li><li>Adjust your professional summary to match the role</li></ol><h2>Keyword Strategy</h2><p>Mirror the exact language from the job posting. If they ask for project management experience, do not write led initiatives. Use their exact words.</p>', '/uploads/blog/tailor-resume-for-job.jpg', 'published', 1, 'How to Tailor Your Resume for Every Job Application | CV Maker', 'Stop sending generic resumes. Learn the exact steps to customize your resume for each job application and dramatically increase your interview rate.', NULL, 0, NOW(), NOW());

-- =============================================
-- TABLE: settings
-- SEED DATA: 36 settings with SENSITIVE VALUES CLEARED
-- =============================================
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `setting_key` varchar(191) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `is_encrypted` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`, `is_encrypted`, `updated_at`) VALUES
('ai_enabled', 'enabled', 0, NOW()),
('ai_free_uses_per_day', '', 0, NOW()),
('ai_pro_uses_per_day', '', 0, NOW()),
('allow_registration', '1', 0, NOW()),
('bing_verification_code', '', 0, NOW()),
('contact_email', '', 0, NOW()),
('ga_enabled', '', 0, NOW()),
('ga_measurement_id', '', 0, NOW()),
('gemini_api_key', '', 1, NOW()),
('gemini_model', 'gemini-flash-latest', 0, NOW()),
('gsc_verification_code', '', 0, NOW()),
('gsc_verification_id', '', 0, NOW()),
('maintenance_mode', '0', 0, NOW()),
('max_resumes_per_free_user', '3', 0, NOW()),
('max_resumes_per_user', '10', 0, NOW()),
('paypal_client_id', '', 1, NOW()),
('paypal_client_secret', '', 1, NOW()),
('paypal_env', 'sandbox', 0, NOW()),
('paypal_webhook_id', '', 0, NOW()),
('pro_plan_currency', 'USD', 0, NOW()),
('pro_plan_name', 'CV Maker Pro', 0, NOW()),
('pro_plan_price', '9', 0, NOW()),
('registration_mode', 'open', 0, NOW()),
('site_description', 'Create professional resumes', 0, NOW()),
('site_email', '', 0, NOW()),
('site_favicon', '/uploads/branding/cvmaker-favicon-20260515.svg', 0, NOW()),
('site_logo', '/uploads/branding/cvmaker-logo-20260515.svg', 0, NOW()),
('site_name', 'cvmaker.ink', 0, NOW()),
('smtp_encryption', 'tls', 0, NOW()),
('smtp_from_email', '', 0, NOW()),
('smtp_from_name', 'CV Maker', 0, NOW()),
('smtp_host', '', 0, NOW()),
('smtp_password', '', 1, NOW()),
('smtp_port', '587', 0, NOW()),
('smtp_username', '', 0, NOW()),
('yandex_verification_code', '', 0, NOW());

-- =============================================
-- TABLE: email_templates
-- SEED DATA: 3 default templates (placeholders)
-- =============================================
DROP TABLE IF EXISTS `email_templates`;
CREATE TABLE `email_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` longtext DEFAULT NULL,
  `is_system` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `email_templates` (`id`, `name`, `subject`, `body`, `is_system`, `created_at`) VALUES
(1, 'Welcome Email', 'Welcome to cvmaker.ink', '<h1>Welcome!</h1><p>Thank you for joining cvmaker.ink. Start building your professional resume today.</p>', 1, NOW()),
(2, 'Password Reset', 'Password Reset Request', '<h1>Reset Your Password</h1><p>Click the link below to reset your password. This link expires in 1 hour.</p><p><a href="{{reset_url}}">Reset Password</a></p>', 1, NOW()),
(3, 'Contact Confirmation', 'We received your message', '<h1>Thank You</h1><p>We have received your message and will respond shortly.</p>', 1, NOW());

-- =============================================
-- ADDITIONAL TABLES (structure only, no seed data)
-- =============================================

-- AI Usage tracking
DROP TABLE IF EXISTS `ai_usage`;
CREATE TABLE `ai_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `feature` varchar(50) NOT NULL,
  `date` date NOT NULL,
  `count` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_ai_usage_user_feature_date` (`user_id`,`feature`,`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email verifications
DROP TABLE IF EXISTS `email_verifications`;
CREATE TABLE `email_verifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password resets
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Newsletter subscribers
DROP TABLE IF EXISTS `newsletter_subscribers`;
CREATE TABLE `newsletter_subscribers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `status` enum('active','unsubscribed') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contact form submissions
DROP TABLE IF EXISTS `contact_submissions`;
CREATE TABLE `contact_submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` mediumtext DEFAULT NULL,
  `status` enum('new','read','replied') DEFAULT 'new',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` mediumtext DEFAULT NULL,
  `notes` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contact message replies (threaded email support)
DROP TABLE IF EXISTS `contact_message_replies`;
CREATE TABLE `contact_message_replies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `contact_submission_id` int(11) NOT NULL,
  `direction` enum('outbound','inbound') NOT NULL DEFAULT 'outbound',
  `sender_email` varchar(255) NOT NULL,
  `sender_name` varchar(255) DEFAULT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body_html` longtext NOT NULL,
  `body_text` longtext DEFAULT NULL,
  `status` enum('draft','sent','failed') NOT NULL DEFAULT 'sent',
  `provider_message_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contact_submission_id` (`contact_submission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email log
DROP TABLE IF EXISTS `email_log`;
CREATE TABLE `email_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) DEFAULT NULL,
  `recipient_email` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `status` enum('sent','failed','opened') DEFAULT 'sent',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity log
DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

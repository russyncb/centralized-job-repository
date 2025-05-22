-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 22, 2025 at 05:29 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `shasha_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_profiles`
--

CREATE TABLE `admin_profiles` (
  `admin_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `department` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_profiles`
--

INSERT INTO `admin_profiles` (`admin_id`, `user_id`, `department`) VALUES
(1, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `admin_queries`
--

CREATE TABLE `admin_queries` (
  `query_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `query_type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `status` enum('pending','in_progress','resolved','rejected') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `responded_at` datetime DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_queries`
--

INSERT INTO `admin_queries` (`query_id`, `user_id`, `subject`, `query_type`, `message`, `status`, `admin_notes`, `created_at`, `responded_at`, `updated_at`) VALUES
(1, 3, '', 'account_support', 'wassam twin', 'resolved', 'on it', '2025-05-20 21:35:10', NULL, '2025-05-21 02:21:17');

-- --------------------------------------------------------

--
-- Table structure for table `applicant_documents`
--

CREATE TABLE `applicant_documents` (
  `document_id` int(11) NOT NULL,
  `jobseeker_id` int(11) NOT NULL,
  `application_id` int(11) DEFAULT NULL,
  `document_type` enum('cv','cover_letter','certificate','other') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `document_title` varchar(255) DEFAULT NULL,
  `document_description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applicant_documents`
--

INSERT INTO `applicant_documents` (`document_id`, `jobseeker_id`, `application_id`, `document_type`, `file_path`, `original_filename`, `file_size`, `mime_type`, `upload_date`, `document_title`, `document_description`) VALUES
(1, 1, 3, 'cv', 'uploads/documents/1/cv_1747179043_cover_letter.docx', 'cover letter.docx', 0, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '2025-05-13 23:30:43', 'resume', NULL),
(2, 1, 4, 'cv', 'uploads/documents/1/cv_1747796239_structure_chapter_3.docx', 'structure chapter 3.docx', 14469, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '2025-05-21 02:57:19', 'resume', NULL),
(3, 1, 4, 'cover_letter', 'uploads/documents/1/cover_letter_1747796239_skrtt.docx', 'skrtt.docx', 41422, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '2025-05-21 02:57:19', 'cover', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `application_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `jobseeker_id` int(11) NOT NULL,
  `cover_letter` text DEFAULT NULL,
  `resume` varchar(255) DEFAULT NULL,
  `status` enum('pending','reviewed','shortlisted','rejected','hired') DEFAULT 'pending',
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `exported` tinyint(1) DEFAULT 0,
  `export_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`application_id`, `job_id`, `jobseeker_id`, `cover_letter`, `resume`, `status`, `applied_at`, `updated_at`, `exported`, `export_date`) VALUES
(1, 3, 1, 'im very good', 'uploads/resumes/2_1747143662_cv nyama.docx', '', '2025-05-13 14:13:53', '2025-05-13 19:21:34', 0, NULL),
(2, 15, 1, 'im so good you know', 'uploads/resumes/2_1747143662_cv nyama.docx', 'shortlisted', '2025-05-13 17:39:32', '2025-05-13 20:48:19', 0, NULL),
(3, 14, 1, NULL, NULL, 'pending', '2025-05-13 23:30:43', '2025-05-13 23:30:43', 0, NULL),
(4, 17, 1, NULL, NULL, 'pending', '2025-05-21 02:57:19', '2025-05-21 02:57:19', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `chatbot_logs`
--

CREATE TABLE `chatbot_logs` (
  `id` int(11) NOT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `user_type` enum('seeker','employer') DEFAULT NULL,
  `message` text DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_assistant` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`message_id`, `user_id`, `message`, `is_assistant`, `created_at`) VALUES
(1, 3, 'hello', 0, '2025-05-14 00:05:35'),
(2, 3, 'I understand your message. For specific assistance, you can submit a query to our admin team using the form on the right. They\'ll be happy to help!', 1, '2025-05-14 00:05:35'),
(3, 3, 'i want to post a job', 0, '2025-05-20 21:34:47'),
(4, 3, 'I understand your message. For specific assistance, you can submit a query to our admin team using the form on the right. They\'ll be happy to help!', 1, '2025-05-20 21:34:47'),
(5, 3, 'Dayvon', 0, '2025-05-20 22:06:38'),
(6, 3, 'I understand your message. For specific assistance, you can submit a query to our admin team using the form on the right. They\'ll be happy to help!', 1, '2025-05-20 22:06:38'),
(7, 3, 'hi', 0, '2025-05-20 22:06:44'),
(8, 3, 'I understand your message. For specific assistance, you can submit a query to our admin team using the form on the right. They\'ll be happy to help!', 1, '2025-05-20 22:06:44'),
(9, 3, 'tell me about this system', 0, '2025-05-22 16:08:40'),
(10, 3, 'I understand your message. For specific assistance, you can submit a query to our admin team using the form on the right. They\'ll be happy to help!', 1, '2025-05-22 16:08:40');

-- --------------------------------------------------------

--
-- Table structure for table `employer_profiles`
--

CREATE TABLE `employer_profiles` (
  `employer_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `company_name` varchar(100) NOT NULL,
  `company_logo` varchar(255) DEFAULT NULL,
  `industry` varchar(100) DEFAULT NULL,
  `company_size` varchar(50) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `business_file` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employer_profiles`
--

INSERT INTO `employer_profiles` (`employer_id`, `user_id`, `company_name`, `company_logo`, `industry`, `company_size`, `website`, `description`, `location`, `verified`, `verified_at`, `verified_by`, `business_file`) VALUES
(1, 3, 'RUSO Banking Services', 'uploads/company_logos/1_1747145780_6387783.jpg', 'Finance & Banking', '11-50 employees', 'https://www.rusobanks.co.zw', 'We are a company which only do banking seervices.', 'Harare', 1, '2025-05-13 13:47:23', 1, NULL),
(2, 4, 'Apex Technologies', 'uploads/company_logos/2_1747145543_monkey_wearing_sunglasses_and_red_cap.jpg', 'Information Technology', '', 'https://apextechnologies.co.zw', 'Apex Technologies is a leading technology company in Zimbabwe providing innovative solutions across multiple industries.', 'Harare, Zimbabwe', 1, NULL, NULL, NULL),
(3, 5, 'Citadel Surgery Clinic', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(4, 6, 'Olivine Industries', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(5, 7, 'FREEBANDZ', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(6, 8, 'OPIUM', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(7, 9, 'FREEBANDZ', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(8, 10, 'GANG', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-05-21 02:37:44', 1, NULL),
(9, 12, 'UTOPIA', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL),
(10, 13, 'MAMBO INV', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '/uploads/business_files/skrtt.docx'),
(11, 14, 'OPIUM', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-05-21 05:37:26', 1, '/uploads/business_files/4.1 Topics 2024.docx'),
(13, 16, 'MORALES', NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, '/uploads/business_files/1747927670_682f4276610e6_unoproject.docx.pdf');

-- --------------------------------------------------------

--
-- Table structure for table `export_logs`
--

CREATE TABLE `export_logs` (
  `export_id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `export_type` varchar(50) NOT NULL,
  `export_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `file_path` varchar(255) NOT NULL,
  `status` enum('processing','completed','failed') NOT NULL DEFAULT 'processing',
  `error_message` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `job_id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `requirements` text DEFAULT NULL,
  `responsibilities` text DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `job_type` enum('full-time','part-time','contract','internship','remote') NOT NULL,
  `category` varchar(100) NOT NULL,
  `salary_min` decimal(10,2) DEFAULT NULL,
  `salary_max` decimal(10,2) DEFAULT NULL,
  `salary_currency` varchar(10) DEFAULT 'USD',
  `application_deadline` date DEFAULT NULL,
  `posted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','closed','draft','archived') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`job_id`, `employer_id`, `title`, `description`, `requirements`, `responsibilities`, `location`, `job_type`, `category`, `salary_min`, `salary_max`, `salary_currency`, `application_deadline`, `posted_at`, `updated_at`, `status`) VALUES
(1, 2, 'Senior Software Developer', 'Join our team of skilled developers building enterprise-level applications for various sectors.', '- Minimum 5 years experience in software development\n- Strong knowledge of Java, Python, or C#\n- Experience with web frameworks (Spring, Django, or ASP.NET)\n- Bachelor\'s degree in Computer Science or related field', '- Design and develop high-quality software solutions\n- Review and debug code\n- Collaborate with cross-functional teams\n- Mentor junior developers', 'Harare, Zimbabwe', 'full-time', 'Information Technology', 1500.00, 2200.00, 'USD', '2025-05-20', '2025-05-13 14:02:24', '2025-05-13 14:02:24', 'active'),
(2, 2, 'DevOps Engineer', 'Help us improve our CI/CD pipelines and infrastructure management.', '- 3+ years of experience with CI/CD tools\n- Experience with Docker, Kubernetes\n- Knowledge of cloud platforms (AWS, Azure)\n- Strong scripting skills (Bash, Python)', '- Manage cloud infrastructure\n- Improve deployment processes\n- Monitor system performance\n- Implement security best practices', 'Bulawayo, Zimbabwe', 'remote', 'Information Technology', 1300.00, 1800.00, 'USD', '2025-05-28', '2025-05-13 14:02:24', '2025-05-13 14:02:24', 'active'),
(3, 2, 'IT Support Specialist', 'Provide technical support to our employees and customers.', '- 2+ years of IT support experience\n- Knowledge of Windows and Linux operating systems\n- Network troubleshooting skills\n- Excellent communication skills', '- Respond to support tickets\n- Troubleshoot hardware and software issues\n- Maintain IT documentation\n- Assist with system updates and maintenance', 'Harare, Zimbabwe', 'part-time', 'Information Technology', NULL, NULL, 'USD', '2025-05-15', '2025-05-13 14:02:25', '2025-05-13 14:02:25', 'active'),
(4, 2, 'Electrical Engineer', 'Design and implement electrical systems for our industrial clients.', '- Bachelor\'s degree in Electrical Engineering\n- 3+ years of experience in electrical design\n- Knowledge of AutoCAD and electrical simulation software\n- Familiarity with local electrical codes and standards', '- Design electrical systems and circuits\n- Perform electrical calculations and analysis\n- Create technical documentation\n- Collaborate with cross-functional teams', 'Gweru, Zimbabwe', 'full-time', 'Engineering', 1200.00, 1700.00, 'USD', '2025-06-02', '2025-05-13 14:02:25', '2025-05-13 14:02:25', 'active'),
(5, 2, 'Mechanical Engineer', 'Join our product development team designing new mechanical solutions.', '- Bachelor\'s degree in Mechanical Engineering\n- Experience with CAD software\n- Knowledge of manufacturing processes\n- Problem-solving abilities', '- Design mechanical components and systems\n- Create prototypes and test products\n- Develop technical specifications\n- Collaborate with cross-functional teams', 'Harare, Zimbabwe', 'full-time', 'Engineering', NULL, NULL, 'USD', '2025-05-22', '2025-05-13 14:02:25', '2025-05-13 14:02:25', 'active'),
(6, 2, 'Civil Engineer Intern', 'Learn and contribute to our infrastructure projects across Zimbabwe.', '- Currently pursuing a degree in Civil Engineering\n- Knowledge of AutoCAD\n- Strong analytical skills\n- Eager to learn and grow', '- Assist senior engineers with project work\n- Help with site inspections\n- Prepare technical drawings\n- Learn about construction management', 'Mutare, Zimbabwe', 'internship', 'Engineering', 300.00, 450.00, 'USD', '2025-06-05', '2025-05-13 14:02:25', '2025-05-13 14:02:25', 'active'),
(7, 2, 'Health Informatics Specialist', 'Bridge the gap between healthcare and technology in our innovative health tech division.', '- Degree in Health Informatics, Computer Science, or related field\n- Understanding of healthcare workflows\n- Knowledge of health information systems\n- Data analysis skills', '- Implement and maintain health information systems\n- Train healthcare staff on technology\n- Analyze healthcare data\n- Ensure data security and compliance', 'Harare, Zimbabwe', 'full-time', 'Healthcare', 1000.00, 1500.00, 'USD', '2025-05-25', '2025-05-13 14:02:25', '2025-05-13 14:02:25', 'active'),
(8, 2, 'Telemedicine Coordinator', 'Manage our growing telemedicine services connecting patients with healthcare providers.', '- Background in healthcare or health administration\n- Experience with telemedicine platforms\n- Excellent organizational skills\n- Customer service orientation', '- Schedule and coordinate telemedicine appointments\n- Provide technical support to patients and providers\n- Maintain patient records\n- Generate service reports', 'Bulawayo, Zimbabwe', 'part-time', 'Healthcare', NULL, NULL, 'USD', '2025-05-18', '2025-05-13 14:02:25', '2025-05-13 14:02:25', 'active'),
(9, 2, 'Medical Software Trainer', 'Train healthcare professionals on our medical software solutions.', '- Background in healthcare or health informatics\n- Strong presentation and communication skills\n- Patient and detail-oriented\n- Willing to travel within Zimbabwe', '- Develop training materials\n- Conduct training sessions\n- Provide ongoing support\n- Gather feedback for software improvements', 'Harare, Zimbabwe', 'contract', 'Healthcare', 800.00, 1200.00, 'USD', '2025-06-01', '2025-05-13 14:02:25', '2025-05-13 14:02:25', 'active'),
(10, 2, 'E-Learning Content Developer', 'Create engaging educational content for our digital learning platforms.', '- Degree in Education, Instructional Design, or related field\n- Experience creating digital learning materials\n- Knowledge of learning management systems\n- Creative and innovative thinking', '- Develop interactive learning materials\n- Design assessment tools\n- Collaborate with subject matter experts\n- Update content based on feedback', 'Harare, Zimbabwe', 'remote', 'Education', NULL, NULL, 'USD', '2025-05-30', '2025-05-13 14:02:25', '2025-05-13 14:02:25', 'active'),
(11, 2, 'Corporate Trainer', 'Deliver technology training programs to our corporate clients.', '- Training or teaching experience\n- Strong knowledge of technology concepts\n- Excellent communication skills\n- Adaptable to different learning environments', '- Deliver training sessions on various technologies\n- Assess learning outcomes\n- Customize training materials for different audiences\n- Provide feedback to participants', 'Harare, Zimbabwe', 'full-time', 'Education', 900.00, 1300.00, 'USD', '2025-05-24', '2025-05-13 14:02:25', '2025-05-13 14:02:25', 'active'),
(12, 2, 'STEM Program Coordinator', 'Manage our initiative to promote STEM education in Zimbabwean schools.', '- Background in STEM education\n- Project management experience\n- Passion for educational development\n- Knowledge of the Zimbabwean education system', '- Coordinate STEM workshops and events\n- Liaise with schools and educational institutions\n- Train teachers on STEM teaching methods\n- Monitor and evaluate program outcomes', 'Mutare, Zimbabwe', 'contract', 'Education', 750.00, 1100.00, 'USD', '2025-06-04', '2025-05-13 14:02:25', '2025-05-13 14:02:25', 'active'),
(13, 2, 'Financial Analyst', 'Analyze financial data and provide insights to support business decisions.', '- Degree in Finance, Accounting, or related field\n- Strong analytical skills\n- Proficiency in Excel and financial software\n- Attention to detail', '- Prepare financial reports and forecasts\n- Analyze financial performance\n- Support budgeting processes\n- Identify cost-saving opportunities', 'Harare, Zimbabwe', 'full-time', 'Finance', 1100.00, 1600.00, 'USD', '2025-05-23', '2025-05-13 14:02:25', '2025-05-13 14:02:25', 'active'),
(14, 1, 'Software Developer', 'Develop and maintain software applications.', 'Degree in Computer Science, 3+ years experience.', 'Code, test, debug software.', 'Harare, Zimbabwe', 'full-time', 'Information Technology', 1500.00, 3500.00, 'USD', '2025-06-30', '2025-05-13 14:25:14', '2025-05-13 14:25:14', 'active'),
(15, 1, 'Network Engineer', 'Manage IT network infrastructure.', 'CCNA certification, 2+ years experience.', 'Maintain and troubleshoot network systems.', 'Bulawayo, Zimbabwe', 'remote', 'Information Technology', 1200.00, 2800.00, 'USD', '2025-06-25', '2025-05-13 14:25:14', '2025-05-13 14:25:14', 'active'),
(16, 1, 'Civil Engineer', 'Design and oversee construction projects.', 'Degree in Civil Engineering, 5+ years experience.', 'Manage project execution and quality control.', 'Chinhoyi, Zimbabwe', 'contract', 'Engineering', 2000.00, 4500.00, 'USD', '2025-07-10', '2025-05-13 14:25:14', '2025-05-13 14:25:14', 'active'),
(17, 1, 'Electrical Engineer', 'Design and implement electrical systems.', 'Degree in Electrical Engineering, 3+ years experience.', 'Ensure safety standards and maintenance.', 'Mutare, Zimbabwe', 'full-time', 'Engineering', 1800.00, 4000.00, 'USD', '2025-05-22', '2025-05-13 14:25:14', '2025-05-13 14:35:30', 'active'),
(18, 1, 'Nurse Practitioner', 'Provide healthcare services to patients.', 'Nursing degree, 3+ years experience.', 'Assist in patient recovery and medication administration.', 'Harare, Zimbabwe', 'internship', 'Healthcare', 800.00, 1500.00, 'USD', '2025-05-16', '2025-05-13 14:25:14', '2025-05-13 14:34:59', 'active'),
(19, 1, 'Teacher', 'Teach students at primary and secondary levels.', 'Teaching diploma, 2+ years experience.', 'Prepare lesson plans and assess students.', 'Masvingo, Zimbabwe', 'full-time', 'Education', 1000.00, 2500.00, 'USD', '2025-07-01', '2025-05-13 14:25:14', '2025-05-13 14:25:14', 'active'),
(20, 1, 'Accountant', 'Manage financial records and transactions.', 'Degree in Accounting, 4+ years experience.', 'Prepare financial reports and audits.', 'Harare, Zimbabwe', 'part-time', 'Finance', 1200.00, 3000.00, 'USD', '2025-07-05', '2025-05-13 14:25:14', '2025-05-13 14:25:14', 'active'),
(21, 1, 'Marketing Executive', 'Develop marketing strategies and campaigns.', 'Degree in Marketing, 3+ years experience.', 'Manage advertising and brand visibility.', 'Bulawayo, Zimbabwe', 'contract', 'Sales & Marketing', 1300.00, 2700.00, 'USD', '2025-06-30', '2025-05-13 14:25:14', '2025-05-13 14:25:14', 'active'),
(22, 1, 'Office Administrator', 'Oversee office operations and administration.', 'Diploma in Business Administration, 2+ years experience.', 'Manage staff coordination and office supplies.', 'Gweru, Zimbabwe', 'full-time', 'Administration', 900.00, 2000.00, 'USD', '2025-07-15', '2025-05-13 14:25:14', '2025-05-13 14:25:14', 'active'),
(23, 1, 'Customer Service Representative', 'Handle customer inquiries and support.', 'High school diploma, excellent communication skills.', 'Resolve customer issues and provide information.', 'Harare, Zimbabwe', 'remote', 'Customer Service', 700.00, 1500.00, 'USD', '2025-07-10', '2025-05-13 14:25:14', '2025-05-13 14:25:14', 'active'),
(24, 1, 'Construction Site Supervisor', 'Supervise construction projects and workers.', 'Diploma in Construction Management, 5+ years experience.', 'Ensure safety compliance and workflow management.', 'Chitungwiza, Zimbabwe', 'full-time', 'Construction', 1500.00, 3500.00, 'USD', '2025-06-20', '2025-05-13 14:25:14', '2025-05-13 14:25:14', 'active'),
(25, 1, 'Farm Manager', 'Oversee agricultural operations.', 'Degree in Agriculture, 4+ years experience.', 'Manage crops and livestock.', 'Bindura, Zimbabwe', 'contract', 'Agriculture', 1400.00, 2800.00, 'USD', '2025-07-01', '2025-05-13 14:25:14', '2025-05-13 14:25:14', 'active'),
(26, 1, 'IT Support Technician', 'Provide technical assistance and troubleshooting.', 'Diploma in IT, 2+ years experience.', 'Resolve hardware and software issues.', 'Harare, Zimbabwe', 'internship', 'Information Technology', 800.00, 1600.00, 'USD', '2025-06-25', '2025-05-13 14:25:14', '2025-05-13 14:25:14', 'active'),
(27, 1, 'Mechanical Engineer', 'Design and maintain mechanical systems.', 'Degree in Mechanical Engineering, 3+ years experience.', 'Ensure machine efficiency and safety.', 'Bulawayo, Zimbabwe', 'part-time', 'Engineering', 1800.00, 4200.00, 'USD', '2025-07-05', '2025-05-13 14:25:14', '2025-05-13 14:25:14', 'active'),
(28, 1, 'Pharmacist', 'Dispense medications and advise patients.', 'Degree in Pharmacy, 3+ years experience.', 'Ensure prescription accuracy and patient guidance.', 'Harare, Zimbabwe', 'full-time', 'Healthcare', 1500.00, 3200.00, 'USD', '2025-06-30', '2025-05-13 14:25:14', '2025-05-13 14:25:14', 'active'),
(29, 1, 'Lecturer', 'Teach university courses.', 'PhD or Masters in relevant field, 3+ years experience.', 'Deliver lectures and grade assessments.', 'Masvingo, Zimbabwe', 'contract', 'Education', 2000.00, 4500.00, 'USD', '2025-07-20', '2025-05-13 14:25:14', '2025-05-13 14:25:14', 'active'),
(30, 1, 'Financial Analyst', 'Analyze financial data and trends.', 'Degree in Finance, 4+ years experience.', 'Prepare economic reports and forecasts.', 'Harare, Zimbabwe', 'remote', 'Finance', 2200.00, 4800.00, 'USD', '2025-06-25', '2025-05-13 14:25:14', '2025-05-13 14:25:14', 'active'),
(31, 1, 'Digital Marketing Specialist', 'Manage online marketing campaigns.', 'Degree in Marketing, 2+ years experience.', 'SEO, PPC, and social media strategy.', 'Bulawayo, Zimbabwe', 'part-time', 'Sales & Marketing', 1300.00, 2700.00, 'USD', '2025-07-10', '2025-05-13 14:25:14', '2025-05-13 14:25:14', 'active'),
(32, 1, 'Call Center Agent', 'Assist customers through phone support.', 'High school diploma, excellent communication skills.', 'Respond to customer inquiries and complaints.', 'Harare, Zimbabwe', 'full-time', 'Customer Service', 800.00, 1800.00, 'USD', '2025-07-05', '2025-05-13 14:25:14', '2025-05-13 14:25:14', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `jobseeker_profiles`
--

CREATE TABLE `jobseeker_profiles` (
  `jobseeker_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `resume` varchar(255) DEFAULT NULL,
  `headline` varchar(255) DEFAULT NULL,
  `education_level` varchar(100) DEFAULT NULL,
  `experience_years` int(11) DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `address` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jobseeker_profiles`
--

INSERT INTO `jobseeker_profiles` (`jobseeker_id`, `user_id`, `resume`, `headline`, `education_level`, `experience_years`, `skills`, `date_of_birth`, `address`) VALUES
(1, 2, 'uploads/resumes/2_1747143662_cv nyama.docx', 'Senior IT Technician', 'Bachelor\'s Degree', 5, 'PHP\r\nJAVASCRIPT\r\nHTML\r\nCSS', NULL, '27690'),
(2, 11, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `job_categories`
--

CREATE TABLE `job_categories` (
  `category_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_categories`
--

INSERT INTO `job_categories` (`category_id`, `name`, `description`) VALUES
(1, 'Information Technology', 'IT, Software development, System administration, etc.'),
(2, 'Engineering', 'Civil, Mechanical, Electrical engineering positions'),
(3, 'Healthcare', 'Medical, Nursing, and other healthcare positions'),
(4, 'Education', 'Teaching, Training, and educational positions'),
(5, 'Finance', 'Accounting, Banking, and financial services'),
(6, 'Sales & Marketing', 'Sales, Marketing, and advertising positions'),
(7, 'Administration', 'Office administration and management'),
(8, 'Customer Service', 'Customer support and service roles'),
(9, 'Construction', 'Building, architecture, and construction jobs'),
(10, 'Agriculture', 'Farming, agriculture, and related positions'),
(11, 'Trappin', 'Traplanta');

-- --------------------------------------------------------

--
-- Table structure for table `job_types`
--

CREATE TABLE `job_types` (
  `job_type_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_types`
--

INSERT INTO `job_types` (`job_type_id`, `name`, `description`, `created_at`) VALUES
(1, 'full-time', 'Standard full-time employment position', '2025-05-13 22:52:52'),
(2, 'part-time', 'Part-time employment position with reduced hours', '2025-05-13 22:52:52'),
(3, 'contract', 'Fixed-term contract position', '2025-05-13 22:52:52'),
(4, 'internship', 'Training position for students or recent graduates', '2025-05-13 22:52:52'),
(5, 'remote', 'Position that can be performed remotely', '2025-05-13 22:52:52');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 3, 'New Job Application', 'New application for Software Developer from Russel Ncube', 0, '2025-05-13 23:30:43'),
(2, 3, 'New Job Application', 'New application for Electrical Engineer from Russel Ncube', 0, '2025-05-21 02:57:19');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `saved_jobs`
--

CREATE TABLE `saved_jobs` (
  `saved_id` int(11) NOT NULL,
  `jobseeker_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `saved_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `saved_jobs`
--

INSERT INTO `saved_jobs` (`saved_id`, `jobseeker_id`, `job_id`, `saved_at`) VALUES
(1, 1, 3, '2025-05-13 14:13:28');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_id` int(11) NOT NULL,
  `setting_name` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_id`, `setting_name`, `setting_value`, `updated_at`) VALUES
(1, 'site_name', 'ShaSha CJRS', '2025-05-13 22:11:44'),
(2, 'site_email', 'info@shasha.co.zw', '2025-05-13 22:11:44'),
(3, 'site_phone', '+263 242 123 456', '2025-05-13 22:11:44'),
(4, 'site_address', 'Harare, Zimbabwe', '2025-05-13 22:11:44'),
(5, 'jobs_per_page', '5', '2025-05-13 22:11:44'),
(6, 'enable_job_alerts', '1', '2025-05-13 22:11:44'),
(7, 'enable_employer_verification', '1', '2025-05-13 22:11:44'),
(8, 'enable_auto_search', '1', '2025-05-13 22:53:14');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','employer','jobseeker') NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `status` enum('active','inactive','suspended','pending') DEFAULT 'pending',
  `verification_token` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password`, `role`, `first_name`, `last_name`, `phone`, `profile_image`, `created_at`, `updated_at`, `last_login`, `status`, `verification_token`, `is_verified`) VALUES
(1, 'admin@shasha.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Admin', 'User', NULL, NULL, '2025-05-13 13:32:38', '2025-05-22 15:27:54', '2025-05-22 15:27:54', 'active', NULL, 0),
(2, 'russyncb1505@gmail.com', '$2y$10$WeOYquTKN45WcaD6r7RR5uYmjvz1sbbHGc.t8u7duclZinTjb9ZB.', 'jobseeker', 'Russel', 'Ncube', '0787116840', NULL, '2025-05-13 13:36:37', '2025-05-22 07:36:09', '2025-05-22 07:36:09', 'active', NULL, 0),
(3, 'jackripper1505@gmail.com', '$2y$10$GgKtf8WTYoVIRxiZsCcUr.32a3UJLlvLN/1pwTzn2ovcKeYCRjDh.', 'employer', 'Jack', 'Ripper', '0774055161', NULL, '2025-05-13 13:42:59', '2025-05-22 14:08:23', '2025-05-22 14:08:23', 'active', NULL, 0),
(4, 'hr@apextechnologies.co.zw', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employer', 'HR', 'Department', '+263772123456', NULL, '2025-05-13 14:02:24', '2025-05-13 14:05:46', '2025-05-13 14:05:46', 'active', NULL, 0),
(5, 'wagivaw811@daupload.com', '$2y$10$Ail9s4HUUrdtjwUHiZRzU.QQvbpuBJGHaMgJKfozBhInxrhGJoy3K', 'employer', 'Jason', 'Love', '0775548634', NULL, '2025-05-13 23:54:47', '2025-05-21 05:37:54', NULL, '', NULL, 0),
(6, 'hrdepartment@olivine.com', '$2y$10$tdA.VkFzVVM3I0D99qbPR.Wk3x1Q27ij.5imVc.Nqn9f5lQ1Zb5L6', 'employer', 'HR', 'Department', '0775548634', NULL, '2025-05-14 00:23:46', '2025-05-21 05:37:50', NULL, '', NULL, 0),
(7, 'homixide@gang.com', '$2y$10$VVMa4OLnuur5u5N.LyzHieqlMazph0SOSkU1/JL72y7LSptmLLi4C', 'employer', 'Playboi', 'Carti', '0772866034', NULL, '2025-05-16 13:17:54', '2025-05-21 02:52:29', NULL, '', NULL, 0),
(8, 'dayvon@gmail.com', '$2y$10$kEEBtRl0bXqVqJrjMLfCve/fbfSDXqkQaT6FHwz1Ozc66tYkhN8H2', 'employer', 'Dayvon', 'Jordan', '0788227950', NULL, '2025-05-16 13:30:18', '2025-05-21 02:52:26', NULL, '', NULL, 0),
(9, 'homixide2024@gang.com', '$2y$10$HNgp/bwLEjAdKtImrWbs7ebcLNqS70lL3dCYxmBM9xvxW6eHZ/HTW', 'employer', 'gang', 'homixide', '0773066034', NULL, '2025-05-18 10:12:49', '2025-05-21 02:52:22', NULL, '', NULL, 0),
(10, 'opium@gang.co.zw', '$2y$10$rIrhaNkTwjbO1pfmkb5WEuYjJeYPHlRi3JcEjAlA.pL84Ea.eBp32', 'employer', 'Starr', 'Face', '0773066035', NULL, '2025-05-20 19:23:57', '2025-05-21 02:37:44', NULL, 'active', NULL, 0),
(11, 'tadiwazhou@gmail.com', '$2y$10$mEgvEdAUybUD2.gi5gBYj.LTa.FDTnBdTGDU1ZRwif8A0UxFhSCE2', 'jobseeker', 'Tadiwa', 'Zhou', '0719955949', NULL, '2025-05-21 02:41:25', '2025-05-21 02:49:02', '2025-05-21 02:49:02', 'active', NULL, 0),
(12, 'utopia@gmail.com', '$2y$10$7tpNH18kLtQlLwOZPqgsH.GNBlCNRAUDWtQkkFdkM24YoXYgDWDRG', 'employer', 'Cactus', 'Jack', '0773066035', NULL, '2025-05-21 02:53:46', '2025-05-21 05:37:47', NULL, '', NULL, 0),
(13, 'kudzai@gmail.com', '$2y$10$flm3reS0vIKv6W833dZ.8exOKsrIANyVxSxsc.LYse7F3qLL.Dbeu', 'employer', 'kudzai', 'mambo', '0773066035', NULL, '2025-05-20 15:02:25', '2025-05-21 04:47:08', NULL, '', NULL, 0),
(14, 'starr@gmail.com', '$2y$10$I98O5B5ikwoZd7xs4I6XqOjFcNt.Sw9A0glwv0YxFVQJtRoDTTP6e', 'employer', 'Starr', 'Face', '0719955949', NULL, '2025-05-21 04:49:02', '2025-05-21 05:37:26', NULL, 'active', NULL, 0),
(16, 'morales@gmail.com', '$2y$10$4fRTwG7ChHXpaM2SqAivoeRQ3tpxzvGFkKXwpm4gxPxVNqasVQUNe', 'employer', 'Miles', 'Morales', '0773066035', NULL, '2025-05-22 15:27:50', '2025-05-22 15:27:50', NULL, 'pending', NULL, 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_profiles`
--
ALTER TABLE `admin_profiles`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `admin_queries`
--
ALTER TABLE `admin_queries`
  ADD PRIMARY KEY (`query_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `applicant_documents`
--
ALTER TABLE `applicant_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `jobseeker_id` (`jobseeker_id`),
  ADD KEY `application_id` (`application_id`);

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`application_id`),
  ADD UNIQUE KEY `unique_application` (`job_id`,`jobseeker_id`),
  ADD KEY `jobseeker_id` (`jobseeker_id`),
  ADD KEY `idx_application_export` (`exported`,`export_date`);

--
-- Indexes for table `chatbot_logs`
--
ALTER TABLE `chatbot_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `employer_profiles`
--
ALTER TABLE `employer_profiles`
  ADD PRIMARY KEY (`employer_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `verified_by` (`verified_by`);

--
-- Indexes for table `export_logs`
--
ALTER TABLE `export_logs`
  ADD PRIMARY KEY (`export_id`),
  ADD KEY `employer_id` (`employer_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`job_id`),
  ADD KEY `employer_id` (`employer_id`);

--
-- Indexes for table `jobseeker_profiles`
--
ALTER TABLE `jobseeker_profiles`
  ADD PRIMARY KEY (`jobseeker_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `job_categories`
--
ALTER TABLE `job_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `job_types`
--
ALTER TABLE `job_types`
  ADD PRIMARY KEY (`job_type_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `saved_jobs`
--
ALTER TABLE `saved_jobs`
  ADD PRIMARY KEY (`saved_id`),
  ADD UNIQUE KEY `unique_saved_job` (`jobseeker_id`,`job_id`),
  ADD KEY `job_id` (`job_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_profiles`
--
ALTER TABLE `admin_profiles`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admin_queries`
--
ALTER TABLE `admin_queries`
  MODIFY `query_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `applicant_documents`
--
ALTER TABLE `applicant_documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `chatbot_logs`
--
ALTER TABLE `chatbot_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `employer_profiles`
--
ALTER TABLE `employer_profiles`
  MODIFY `employer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `export_logs`
--
ALTER TABLE `export_logs`
  MODIFY `export_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `job_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `jobseeker_profiles`
--
ALTER TABLE `jobseeker_profiles`
  MODIFY `jobseeker_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `job_categories`
--
ALTER TABLE `job_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `job_types`
--
ALTER TABLE `job_types`
  MODIFY `job_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `saved_jobs`
--
ALTER TABLE `saved_jobs`
  MODIFY `saved_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `setting_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_profiles`
--
ALTER TABLE `admin_profiles`
  ADD CONSTRAINT `admin_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `admin_queries`
--
ALTER TABLE `admin_queries`
  ADD CONSTRAINT `admin_queries_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `applicant_documents`
--
ALTER TABLE `applicant_documents`
  ADD CONSTRAINT `applicant_documents_ibfk_1` FOREIGN KEY (`jobseeker_id`) REFERENCES `jobseeker_profiles` (`jobseeker_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `applicant_documents_ibfk_2` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE SET NULL;

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`job_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`jobseeker_id`) REFERENCES `jobseeker_profiles` (`jobseeker_id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `employer_profiles`
--
ALTER TABLE `employer_profiles`
  ADD CONSTRAINT `employer_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employer_profiles_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `export_logs`
--
ALTER TABLE `export_logs`
  ADD CONSTRAINT `export_logs_ibfk_1` FOREIGN KEY (`employer_id`) REFERENCES `employer_profiles` (`employer_id`) ON DELETE CASCADE;

--
-- Constraints for table `jobs`
--
ALTER TABLE `jobs`
  ADD CONSTRAINT `jobs_ibfk_1` FOREIGN KEY (`employer_id`) REFERENCES `employer_profiles` (`employer_id`) ON DELETE CASCADE;

--
-- Constraints for table `jobseeker_profiles`
--
ALTER TABLE `jobseeker_profiles`
  ADD CONSTRAINT `jobseeker_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `saved_jobs`
--
ALTER TABLE `saved_jobs`
  ADD CONSTRAINT `saved_jobs_ibfk_1` FOREIGN KEY (`jobseeker_id`) REFERENCES `jobseeker_profiles` (`jobseeker_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `saved_jobs_ibfk_2` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`job_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

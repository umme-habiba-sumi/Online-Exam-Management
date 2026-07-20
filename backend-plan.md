# ExamPortal — PHP/MySQL Backend Plan

ফ্রন্টএন্ডের প্রতিটা পেজ (login, forgot-password, student dashboard/exam/result/profile, admin dashboard এর ৬টা ট্যাব, admin profile) অনুযায়ী এই প্ল্যান সাজানো হয়েছে, যাতে সরাসরি ইমপ্লিমেন্ট করা যায়।

---

## ১. টেক স্ট্যাক ও স্ট্রাকচার

- **PHP** (procedural বা lightweight — কোনো heavy framework দরকার নেই এই স্কেলে), **PDO + MySQL** (prepared statements বাধ্যতামূলক, raw query কখনোই না)
- Session-based auth (`$_SESSION`), JSON API endpoints — ফ্রন্টএন্ডের `main.js`-এর `DEMO` অবজেক্টের জায়গায় `fetch()` কল বসবে
- Folder structure:

```
/backend
  /config
    db.php              -> PDO connection (env ভ্যারিয়েবল থেকে creds)
  /includes
    auth.php            -> requireLogin(), requireRole('admin')
    helpers.php         -> sanitize(), jsonResponse(), csrf helpers
  /api
    /auth
      login.php
      logout.php
      request-otp.php
      verify-otp.php
      reset-password.php
    /admin
      /users   (create.php, list.php, update.php, delete.php)
      /exams   (create.php, list.php, update.php, delete.php, publish.php)
      /questions (create.php, list.php, update.php, delete.php)
      /results (list.php)
    /student
      /exams   (list.php, start.php, save-answer.php, submit.php)
      /results (list.php, detail.php)
    /profile
      get.php
      update-password.php
/frontend   <- আগের zip, অপরিবর্তিত থাকবে, শুধু main.js-এর DEMO কল ধীরে ধীরে fetch()-এ বদলাবে
```

---

## ২. ডাটাবেজ স্কিমা

```sql
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('student','admin') NOT NULL,
  roll_or_id VARCHAR(50),              -- Roll/Registration no. অথবা Employee ID
  department VARCHAR(50) DEFAULT 'CSE',
  designation VARCHAR(100) NULL,       -- শুধু admin/teacher-এর জন্য
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  otp_code VARCHAR(6) NOT NULL,
  is_verified TINYINT(1) DEFAULT 0,
  expires_at DATETIME NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE question_bank (
  id INT AUTO_INCREMENT PRIMARY KEY,
  subject_code VARCHAR(20) NOT NULL,
  topic VARCHAR(100),
  question_text TEXT NOT NULL,
  option_a VARCHAR(255) NOT NULL,
  option_b VARCHAR(255) NOT NULL,
  option_c VARCHAR(255) NOT NULL,
  option_d VARCHAR(255) NOT NULL,
  correct_option ENUM('A','B','C','D') NOT NULL,
  created_by INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE exams (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  subject_code VARCHAR(20) NOT NULL,
  duration_minutes INT NOT NULL,
  total_marks INT NOT NULL,
  start_time DATETIME NOT NULL,
  status ENUM('draft','published','closed') DEFAULT 'draft',
  created_by INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE exam_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  exam_id INT NOT NULL,
  question_bank_id INT NULL,           -- বank থেকে টানা হলে রেফারেন্স রাখে
  question_text TEXT NOT NULL,
  option_a VARCHAR(255) NOT NULL,
  option_b VARCHAR(255) NOT NULL,
  option_c VARCHAR(255) NOT NULL,
  option_d VARCHAR(255) NOT NULL,
  correct_option ENUM('A','B','C','D') NOT NULL,
  marks INT DEFAULT 1,
  order_no INT DEFAULT 0,
  FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
  FOREIGN KEY (question_bank_id) REFERENCES question_bank(id)
);

CREATE TABLE exam_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  exam_id INT NOT NULL,
  student_id INT NOT NULL,
  started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  submitted_at DATETIME NULL,
  score INT NULL,
  status ENUM('in_progress','submitted') DEFAULT 'in_progress',
  UNIQUE KEY one_attempt_per_student (exam_id, student_id),   -- একজন ছাত্র একবারই দিতে পারবে
  FOREIGN KEY (exam_id) REFERENCES exams(id),
  FOREIGN KEY (student_id) REFERENCES users(id)
);

CREATE TABLE exam_answers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  attempt_id INT NOT NULL,
  exam_question_id INT NOT NULL,
  selected_option ENUM('A','B','C','D') NULL,
  is_correct TINYINT(1) NULL,
  UNIQUE KEY one_answer_per_question (attempt_id, exam_question_id),
  FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
  FOREIGN KEY (exam_question_id) REFERENCES exam_questions(id)
);
```

**নোট:** `correct_option` কখনোই স্টুডেন্টের কাছে পাঠানো JSON-এ থাকবে না, শুধু গ্রেডিং-এর সময় সার্ভার-সাইডে ব্যবহার হবে।

---

## ৩. পেজ-ভিত্তিক API ম্যাপিং

### 🔐 `login.html`
| Frontend action | Endpoint | কাজ |
|---|---|---|
| Sign in ফর্ম সাবমিট | `POST /api/auth/login.php` | email, password, role নেয় → `password_verify()` → সঠিক হলে `session_regenerate_id()` + `$_SESSION['user_id']`, `role` সেট → role অনুযায়ী `student/dashboard.html` বা `admin/dashboard.html`-এর URL রিটার্ন করে |

### 🔑 `forgot-password.html` (2-step OTP)
| Step | Endpoint | কাজ |
|---|---|---|
| Step 1: email+role সাবমিট | `POST /api/auth/request-otp.php` | ইউজার খুঁজে বের করে, 6-digit OTP জেনারেট করে `password_resets`-এ সেভ করে (10 মিনিট expiry), PHPMailer দিয়ে ইমেইলে পাঠায় |
| Step 2: কোড ভেরিফাই | `POST /api/auth/verify-otp.php` | OTP মিলিয়ে দেখে, expiry চেক করে, `is_verified=1` করে |
| Step 3: নতুন পাসওয়ার্ড | `POST /api/auth/reset-password.php` | verified থাকলে `password_hash()` করে `users.password_hash` আপডেট করে |

### 🎓 `student/dashboard.html`
| Endpoint | কাজ |
|---|---|
| `GET /api/student/exams/list.php` | লগইন করা স্টুডেন্টের department/subject অনুযায়ী exams লিস্ট করে, প্রতিটার status (`start_time` আর `duration` দিয়ে গণনা করা live/upcoming/closed) সহ রিটার্ন করে |
| `GET /api/student/results/list.php` | `exam_attempts` জয়েন করে স্টুডেন্টের নিজের সাবমিট করা রেজাল্ট লিস্ট করে |

### 📝 `student/exam.html`
| Endpoint | কাজ |
|---|---|
| `GET /api/student/exams/start.php?exam_id=` | সার্ভার-সাইডে যাচাই করে exam এখন live কিনা; আগে attempt আছে কিনা চেক করে (থাকলে ব্লক); না থাকলে `exam_attempts` রো তৈরি করে; `correct_option` **বাদ দিয়ে** প্রশ্ন+অপশন পাঠায় |
| `POST /api/student/exams/save-answer.php` | প্রতিটা উত্তর সিলেক্ট করার সাথে সাথে autosave করে (নেট চলে গেলেও উত্তর হারাবে না) |
| `POST /api/student/exams/submit.php` | সার্ভারে থাকা `correct_option` এর সাথে মিলিয়ে স্কোর গণনা করে, `exam_attempts.status='submitted'` করে — **ক্লায়েন্ট থেকে পাঠানো স্কোর কখনো বিশ্বাস করা হবে না** |

এখানে timer-ও সার্ভার-সাইডে ভ্যালিডেট করা দরকার — `start_time + duration_minutes` পার হয়ে গেলে submit রিকোয়েস্ট রিজেক্ট বা auto-grade করে দিতে হবে, শুধু ফ্রন্টএন্ডের জাভাস্ক্রিপ্ট টাইমারের উপর নির্ভর করা যাবে না।

### 📊 `student/result.html`
| Endpoint | কাজ |
|---|---|
| `GET /api/student/results/detail.php?attempt_id=` | ঐ attempt-এর প্রতিটা প্রশ্ন, স্টুডেন্টের উত্তর, সঠিক উত্তর, correct/wrong ফ্ল্যাগ রিটার্ন করে (রিভিউ লিস্টের জন্য) |

### 👤 `student/profile.html` / `admin/profile.html`
| Endpoint | কাজ |
|---|---|
| `GET /api/profile/get.php` | সেশনের ইউজারের নাম, ইমেইল, roll/empId, department/designation, created_at রিটার্ন করে |
| `POST /api/profile/update-password.php` | current password `password_verify()`, তারপর নতুনটা `password_hash()` করে সেভ |

### 🛠️ `admin/dashboard.html` — Manage Exams / Create Exam
| Endpoint | কাজ |
|---|---|
| `GET /api/admin/exams/list.php` | admin-এর তৈরি সব exam + attempt count |
| `POST /api/admin/exams/create.php` | exam + প্রশ্ন-অপশন সব একসাথে একটা DB **transaction**-এ ইনসার্ট (Publish বাটনে) |
| `POST /api/admin/exams/update.php` | title/subject আপডেট |
| `POST /api/admin/exams/delete.php` | ক্যাসকেড ডিলিট (attempts/questions সহ) — এখানে extra caution: attempt আছে এমন exam ডিলিটের আগে warning |

### 📚 Question Bank ট্যাব
| Endpoint | কাজ |
|---|---|
| `GET /api/admin/questions/list.php?subject=` | subject filter সহ bank লিস্ট |
| `POST /api/admin/questions/create.php` / `update.php` / `delete.php` | CRUD |

### 📈 Results ট্যাব
| Endpoint | কাজ |
|---|---|
| `GET /api/admin/results/list.php?exam_id=` | নির্দিষ্ট exam বা সব exam-এর সব স্টুডেন্টের score লিস্ট |

### 👥 Manage Users ট্যাব
| Endpoint | কাজ |
|---|---|
| `GET /api/admin/users/list.php?role=` | সব ইউজার লিস্ট, role filter |
| `POST /api/admin/users/create.php` | নতুন স্টুডেন্ট/টিচার অ্যাকাউন্ট — এখানেই "Create a new account" ফর্মটা যায়, `password_hash()` করে সেভ করে, শুধু **admin role হলেই** এই এন্ডপয়েন্ট কল করা যাবে |
| `POST /api/admin/users/update.php` / `delete.php` | Edit/Remove modal |

---

## ৪. সিকিউরিটি চেকলিস্ট (মাস্ট-হ্যাভ)

- সব query-তে **PDO prepared statements** — কখনো string concatenation দিয়ে SQL না
- পাসওয়ার্ড সবসময় `password_hash()` / `password_verify()` — plain text কখনোই না
- প্রতিটা `/api/admin/*` এন্ডপয়েন্টের শুরুতে `requireRole('admin')` — সরাসরি URL হিট করেও যেন student admin API ছুঁতে না পারে
- Exam চলাকালীন `correct_option` কখনো ক্লায়েন্টে পাঠানো JSON-এ যাবে না
- CSRF টোকেন প্রতিটা POST ফর্মে (session-এ টোকেন রেখে হিডেন ফিল্ডে/হেডারে পাঠানো)
- OTP রিকোয়েস্টে rate-limit (যেমন একই ইমেইলে ৫ মিনিটে ১ বার) — brute-force আটকাতে
- আউটপুটে `htmlspecialchars()` (XSS ঠেকাতে), যদিও ফ্রন্টএন্ড মূলত JSON রেন্ডার করছে
- `.env` ফাইলে DB creds, gitignore-এ রাখা, ভার্সন কন্ট্রোলে কখনো না
- HTTPS বাধ্যতামূলক প্রোডাকশনে, session cookie-তে `HttpOnly` + `Secure` ফ্ল্যাগ

---

## ৫. তৈরির ক্রম (সাজেস্টেড অর্ডার)

1. DB স্কিমা রান + `config/db.php`
2. Auth: login, logout, session guard (`includes/auth.php`)
3. Admin → Manage Users (যেহেতু বাকি সব রোল এখান থেকেই শুরু)
4. Admin → Create Exam + Question Bank
5. Student → Exam list + Start exam + Submit (গ্রেডিং লজিক সবচেয়ে জটিল অংশ, সময় নিয়ে করা ভালো)
6. Student/Admin → Result review
7. Forgot Password OTP ফ্লো
8. Profile পেজ

---

**পরবর্তী ধাপ:** চাইলে আমি এখনই কোড শুরু করে দিতে পারি — যেমন `config/db.php` + auth সিস্টেম দিয়ে শুরু করব, নাকি নির্দিষ্ট কোনো অংশ (যেমন exam submit-এর গ্রেডিং লজিক) আগে চান, জানালে সেভাবে এগোই।
